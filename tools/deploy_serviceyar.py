"""Incremental, verified FTP deploy for a single-tenant shared Linux host.

Changed files are staged and checksum-verified before publication. A last-written
manifest records the complete release, so repeated deployments avoid the huge FTP
round-trip cost of resending and rechecking every unchanged file.
"""
from ftplib import FTP, error_perm
from hashlib import sha256
from pathlib import Path
import io
import json
import os
import posixpath
import socket
import ssl
from urllib.request import Request, urlopen
from urllib.error import URLError, HTTPError
from urllib.parse import urlsplit

ROOT = os.environ.get("FTP_REMOTE_ROOT", "/").rstrip("/") or "/"
PAYLOAD = Path("deploy-payload")
# Use an isolated FTP staging directory per Actions run. Concurrent or
# cancelled releases must never delete/move one another's staged files.
RUN_ID = "".join(c for c in os.environ.get("GITHUB_RUN_ID", "local") if c.isascii() and c.isalnum())
STAGING = posixpath.join(ROOT, ".serviceyar-staging-" + (RUN_ID or "local"))
MANIFEST = posixpath.join(ROOT, ".serviceyar-manifest.json")
SCHEMA = 1
CANONICAL_HOST = "service-yar.ir"


def production_base_url():
    """Return the one canonical HTTPS production origin; reject accidental host drift."""
    base = os.environ.get("PUBLIC_BASE_URL", f"https://{CANONICAL_HOST}").rstrip("/")
    parsed = urlsplit(base)
    if (
        parsed.scheme != "https"
        or parsed.hostname != CANONICAL_HOST
        or parsed.path not in ("", "/")
        or parsed.query
        or parsed.fragment
    ):
        raise RuntimeError(
            f"PUBLIC_BASE_URL must be https://{CANONICAL_HOST}; received {base!r}"
        )
    return base


def ensure_dir(ftp, path, known):
    """Avoid repeated MKD/CWD requests for every file in the same directory."""
    current = ""
    for part in path.strip("/").split("/"):
        current += "/" + part
        if current in known:
            continue
        try:
            ftp.mkd(current)
        except error_perm:
            previous = ftp.pwd()
            ftp.cwd(current)
            ftp.cwd(previous)
        known.add(current)


def upload(ftp, source, destination):
    with source.open("rb") as stream:
        ftp.storbinary("STOR " + destination, stream, blocksize=65536)


def verify(ftp, expected_hex, destination):
    digest = sha256()
    ftp.retrbinary("RETR " + destination, digest.update, blocksize=65536)
    if digest.hexdigest() != expected_hex:
        raise RuntimeError("Transferred file differs: " + destination)


def previous_files(ftp):
    buffer = io.BytesIO()
    try:
        ftp.retrbinary("RETR " + MANIFEST, buffer.write)
    except error_perm as error:
        if not str(error).startswith("550"):
            raise
        return {}
    try:
        record = json.loads(buffer.getvalue().decode("utf-8"))
    except (UnicodeDecodeError, ValueError):
        return {}
    if not isinstance(record, dict) or record.get("schema") != SCHEMA:
        return {}
    data = record.get("files")
    return data if isinstance(data, dict) else {}



def verify_live(base_url, build_id, expected_digests):
    """Verify the canonical HTTPS host serves the exact release, not just the FTP copy."""
    base = base_url.rstrip("/")
    parsed_base = urlsplit(base)
    if parsed_base.scheme != "https" or parsed_base.hostname != CANONICAL_HOST:
        raise RuntimeError(f"Live verification must use https://{CANONICAL_HOST}; received {base!r}")
    # Deployment-only exception requested by the operator: do not validate the
    # certificate. Still verify HTTP host, exact build marker and asset SHA-256.
    # This never changes browser TLS behavior or application HTTPS settings.
    print('WARNING: deploy verification skips TLS certificate validation; check SSL separately.', flush=True)
    checks = [
        ("/", None),
        ("/assets/app.js", "assets/app.js"),
        ("/assets/operations.js", "assets/operations.js"),
        ("/assets/app.css", "assets/app.css"),
        ("/assets/operations.css", "assets/operations.css"),
        ("/assets/brand-theme.css", "assets/brand-theme.css"),
    ]
    for route, relative in checks:
        url = base + route + "?deploy_verify=" + build_id
        request = Request(url, headers={
            "Cache-Control": "no-cache, no-store, max-age=0",
            "Pragma": "no-cache",
            "User-Agent": "ServiceYar-Deploy-Verification/1.0",
        })
        try:
            with urlopen(request, timeout=35, context=ssl._create_unverified_context()) as response:
                body = response.read()
                status = response.status
                final_url = response.geturl()
                live_headers = {key.lower(): val for key, val in response.headers.items()}
            final = urlsplit(final_url)
            if final.scheme != "https" or final.hostname != CANONICAL_HOST:
                raise RuntimeError(
                    f"Production request left the canonical origin: {final_url!r}; "
                    f"expected https://{CANONICAL_HOST}"
                )
        except (URLError, HTTPError, TimeoutError) as error:
            host = base.split("://", 1)[-1].split("/", 1)[0].split(":", 1)[0]
            try:
                addresses = sorted({item[4][0] for item in socket.getaddrinfo(host, 443, type=socket.SOCK_STREAM)})
                print(f"DNS {host}: {', '.join(addresses)}", flush=True)
            except OSError as dns_error:
                print(f"DNS lookup failed for {host}: {dns_error}", flush=True)
            # Diagnostic only: inspect whether the HTTP virtual host serves this release
            # when TLS certificate validation is failing. Never accept this as a pass.
            for candidate in ("https://www.service-yar.ir/", "http://service-yar.ir/", "http://www.service-yar.ir/"):
                try:
                    diagnostic = Request(candidate + "?deploy_diagnostic=" + build_id, headers={"Cache-Control": "no-cache", "User-Agent": "ServiceYar-Deploy-Diagnostic/1.0"})
                    with urlopen(diagnostic, timeout=12, context=ssl._create_unverified_context()) as response:
                        body = response.read(400000).decode("utf-8", "replace")
                        title = body.split("<title>", 1)[1].split("</title>", 1)[0][:140] if "<title>" in body and "</title>" in body else "no-title"
                        print(f"DIAGNOSTIC ONLY {candidate}: HTTP {response.status}; build={build_id in body}; title={title!r}; final={response.url}; server={response.headers.get('Server')}; x-cache={response.headers.get('X-LiteSpeed-Cache')}", flush=True)
                        if candidate.startswith("https://www."):
                            for asset in ("/assets/app.js", "/assets/operations.js", "/assets/brand-theme.css"):
                                asset_url = candidate.rstrip("/") + asset + "?v=" + build_id
                                asset_request = Request(asset_url, headers={"Cache-Control":"no-cache","User-Agent":"ServiceYar-Deploy-Diagnostic/1.0"})
                                with urlopen(asset_request, timeout=12, context=ssl._create_unverified_context()) as asset_response:
                                    asset_body = asset_response.read()
                                    asset_expected = "assets/" + asset.rsplit("/", 1)[-1]
                                    print(f"DIAGNOSTIC ASSET {asset_url}: HTTP {asset_response.status}; sha_match={sha256(asset_body).hexdigest() == expected_digests[asset_expected]}; bytes={len(asset_body)}", flush=True)
                except Exception as diagnostic_error:
                    print(f"DIAGNOSTIC ONLY {candidate}: {diagnostic_error}", flush=True)
            raise RuntimeError(
                f"Public-host verification failed for {url}: {error}. "
                "The FTP upload may have completed, but the public domain cannot be verified securely. "
                "Check the domain SSL certificate and its document-root mapping."
            ) from error
        if status != 200:
            raise RuntimeError(f"Public-host verification returned HTTP {status} for {route}")
        if route == "/":
            html = body.decode("utf-8", "replace")
            if build_id not in html:
                import re
                from subprocess import run
                apparent_builds = re.findall(r'/assets/(?:app|operations)\.js\?v=([a-f0-9]{8,64})', html)
                print(f"LIVE DOMAIN MISMATCH: expected={build_id[:12]}, HTML asset builds={apparent_builds[:5]}", flush=True)
                print("LIVE HTTP: status={}, final={}, server={}, cache={}, age={}, via={}".format(
                    status, final_url, live_headers.get("server",""),
                    live_headers.get("x-litespeed-cache",live_headers.get("x-cache","")),
                    live_headers.get("age",""), live_headers.get("via","")), flush=True)
                try:
                    host_ips = sorted({item[4][0] for item in socket.getaddrinfo(CANONICAL_HOST,443,type=socket.SOCK_STREAM)})
                    print(f"LIVE DNS {CANONICAL_HOST}: {host_ips}", flush=True)
                except OSError as error:
                    print(f"LIVE DNS lookup failed: {error}", flush=True)
                for check_path, expected_file in (
                    ("/index.html", None),
                    ("/assets/app.js", "assets/app.js"),
                    ("/assets/operations.js", "assets/operations.js"),
                ):
                    try:
                        check_url = base + check_path + "?deploy_verify=" + build_id
                        check_request = Request(check_url, headers={"Cache-Control": "no-store, max-age=0"})
                        with urlopen(check_request, timeout=18, context=ssl._create_unverified_context()) as check_response:
                            check_body = check_response.read()
                            check_headers = check_response.headers
                            check_status = check_response.status
                        if expected_file is None:
                            detail = "new_build_in_html=" + str(build_id in check_body.decode("utf-8", "replace"))
                        else:
                            detail = "sha256_matches_uploaded=" + str(sha256(check_body).hexdigest() == expected_digests[expected_file])
                        print(f"PUBLIC PATH {check_path}: HTTP {check_status}; {detail}; cache={check_headers.get('X-LiteSpeed-Cache','')}; bytes={len(check_body)}", flush=True)
                    except Exception as check_error:
                        print(f"PUBLIC PATH {check_path}: {check_error}", flush=True)
                origin_ip = os.environ.get("SERVICEYAR_FTP_PEER_IP","").strip()
                if origin_ip and ":" not in origin_ip:
                    # Use the FTP server's actual peer IP as an origin diagnostic;
                    # curl keeps HTTPS SNI/Host unchanged while bypassing public DNS.
                    result = run([
                        "curl", "--noproxy", "*", "--silent", "--show-error", "--insecure",
                        "--location", "--max-time", "18", "--resolve",
                        f"{CANONICAL_HOST}:443:{origin_ip}", "-o", "/dev/null",
                        "-w", "%{http_code} %{url_effective}", url
                    ], capture_output=True, text=True, timeout=22)
                    print(f"ORIGIN DIRECT {origin_ip}: {result.stdout.strip()}; error={result.stderr.strip()[:180]}", flush=True)
                raise RuntimeError(
                    f"{base}/ is not serving uploaded index.html build {build_id[:12]}; "
                    "FTP checksums are correct. Inspect DNS/vhost or server-side HTML caching."
                )
            print(f"Public homepage verified: {base}/ serves build {build_id[:12]}", flush=True)
        else:
            actual = sha256(body).hexdigest()
            expected = expected_digests[relative]
            if actual != expected:
                raise RuntimeError(
                    f"Public asset mismatch at {url}: expected {expected}, received {actual}. "
                    "The domain may point to another document root or a stale cache."
                )
            print(f"Public asset verified: {route}", flush=True)

def main():
    files = {
        path.relative_to(PAYLOAD).as_posix(): path
        for path in sorted(PAYLOAD.rglob("*"))
        if path.is_file()
    }
    required = {"index.html", ".htaccess", "assets/serviceyar-guide.pdf", "backend/public/index.php"}
    if not required <= files.keys():
        raise RuntimeError("Deployment payload is incomplete")
    digests = {name: sha256(source.read_bytes()).hexdigest() for name, source in files.items()}

    # Shared-hosting FTP can take longer than 45 seconds to return its release
    # manifest when the server is under load. Give control/data transfers a
    # realistic limit without bypassing checksum verification.
    with FTP(timeout=120) as ftp:
        ftp.connect(os.environ["FTP_HOST"], 21)
        ftp_peer_ip = ftp.sock.getpeername()[0]
        os.environ["SERVICEYAR_FTP_PEER_IP"] = ftp_peer_ip
        print(f"FTP server peer IP: {ftp_peer_ip}", flush=True)
        ftp.login(os.environ["FTP_USER"], os.environ["FTP_PASS"])
        print(f"FTP login directory: {ftp.pwd()}", flush=True)
        try:
            print("FTP login directory entries: " + ", ".join(sorted(ftp.nlst())[:40]), flush=True)
        except error_perm as error:
            print(f"FTP directory listing unavailable: {error}", flush=True)
        print(f"Deployment target directory: {ROOT}", flush=True)
        # The FTP login is now chrooted to the actual public_html directory.
        # Guard against accidentally publishing into a parent account directory.
        if ROOT == "/":
            entries = set(name.rsplit("/",1)[-1] for name in ftp.nlst("/"))
            if not {"index.html", "assets", "backend"} <= entries:
                raise RuntimeError(
                    "FTP root does not look like the ServiceYar document root; "
                    "expected index.html, assets and backend. Refusing to publish."
                )
        try:
            print("Deployment target entries: " + ", ".join(sorted(ftp.nlst(ROOT))[:100]), flush=True)
        except error_perm as error:
            print(f"Cannot list deployment target: {error}", flush=True)
        old = previous_files(ftp)
        changed = [name for name in files if old.get(name) != digests[name]]
        print(f"Release: {len(changed)} changed, {len(files) - len(changed)} unchanged, {len(files)} total", flush=True)
        if not changed:
            print("FTP manifest matches this release; public HTTP verification is still required", flush=True)
            verify_live(production_base_url(), os.environ.get("GITHUB_SHA", "local"), digests)
            return

        dirs = set()
        ensure_dir(ftp, STAGING, dirs)
        # Fail closed: no partially uploaded PHP or config file is web-accessible.
        ftp.storbinary("STOR " + STAGING + "/.htaccess", io.BytesIO(b"Require all denied\n"))

        for name in changed:
            remote = STAGING + "/" + (".htaccess.new" if name == ".htaccess" else name)
            ensure_dir(ftp, posixpath.dirname(remote), dirs)
            upload(ftp, files[name], remote)
            verify(ftp, digests[name], remote)
        print(f"Verified {len(changed)} changed files in staging", flush=True)

        ordered = sorted(changed, key=lambda name: (
            3 if name == "index.html" else
            2 if name == ".htaccess" else
            1 if name.startswith("assets/") else 0, name))
        for name in ordered:
            remote = STAGING + "/" + (".htaccess.new" if name == ".htaccess" else name)
            live = posixpath.join(ROOT, name)
            ensure_dir(ftp, posixpath.dirname(live), dirs)
            ftp.rename(remote, live)
            verify(ftp, digests[name], live)

        # Verify the actual domain/document-root before declaring the release successful.
        verify_live(production_base_url(), os.environ.get("GITHUB_SHA", "local"), digests)

        # Commit metadata only after FTP and public HTTP checks have passed.
        manifest = json.dumps({
            "schema": SCHEMA,
            "commit": os.environ.get("GITHUB_SHA", ""),
            "files": digests
        }, ensure_ascii=True, separators=(",", ":"), sort_keys=True).encode("utf-8")
        temporary = STAGING + "/manifest.json.tmp"
        ftp.storbinary("STOR " + temporary, io.BytesIO(manifest))
        ftp.rename(temporary, MANIFEST)
        verify(ftp, sha256(manifest).hexdigest(), MANIFEST)
        print(f"Published and verified {len(changed)} changes; release manifest updated", flush=True)


if __name__ == "__main__":
    main()
