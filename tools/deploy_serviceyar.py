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

ROOT = os.environ.get("FTP_REMOTE_ROOT", "/public_html").rstrip("/") or "/"
PAYLOAD = Path("deploy-payload")
# Use an isolated FTP staging directory per Actions run. Concurrent or
# cancelled releases must never delete/move one another's staged files.
RUN_ID = "".join(c for c in os.environ.get("GITHUB_RUN_ID", "local") if c.isascii() and c.isalnum())
STAGING = ROOT + "/.serviceyar-staging-" + (RUN_ID or "local")
MANIFEST = ROOT + "/.serviceyar-manifest.json"
SCHEMA = 1


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
    """Verify the public domain serves the exact release, not just the FTP copy."""
    base = base_url.rstrip("/")
    checks = [
        ("/", None),
        ("/assets/operations.js", "assets/operations.js"),
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
            with urlopen(request, timeout=35) as response:
                body = response.read()
                status = response.status
        except (URLError, HTTPError, TimeoutError) as error:
            host = base.split("://", 1)[-1].split("/", 1)[0].split(":", 1)[0]
            try:
                addresses = sorted({item[4][0] for item in socket.getaddrinfo(host, 443, type=socket.SOCK_STREAM)})
                print(f"DNS {host}: {', '.join(addresses)}", flush=True)
            except OSError as dns_error:
                print(f"DNS lookup failed for {host}: {dns_error}", flush=True)
            # Diagnostic only: inspect whether the HTTP virtual host serves this release
            # when TLS certificate validation is failing. Never accept this as a pass.
            for candidate in ("https://www.challi.ir/", "http://challi.ir/", "http://www.challi.ir/"):
                try:
                    diagnostic = Request(candidate + "?deploy_diagnostic=" + build_id, headers={"Cache-Control": "no-cache", "User-Agent": "ServiceYar-Deploy-Diagnostic/1.0"})
                    with urlopen(diagnostic, timeout=12, context=ssl._create_unverified_context()) as response:
                        body = response.read(400000).decode("utf-8", "replace")
                        title = body.split("<title>", 1)[1].split("</title>", 1)[0][:140] if "<title>" in body and "</title>" in body else "no-title"
                        print(f"DIAGNOSTIC ONLY {candidate}: HTTP {response.status}; build={build_id in body}; title={title!r}; final={response.url}; server={response.headers.get('Server')}; x-cache={response.headers.get('X-LiteSpeed-Cache')}", flush=True)
                        if candidate.startswith("https://www."):
                            for asset in ("/assets/operations.js", "/assets/brand-theme.css"):
                                asset_url = candidate.rstrip("/") + asset + "?v=" + build_id
                                asset_request = Request(asset_url, headers={"Cache-Control":"no-cache","User-Agent":"ServiceYar-Deploy-Diagnostic/1.0"})
                                with urlopen(asset_request, timeout=12, context=ssl._create_unverified_context()) as asset_response:
                                    asset_body = asset_response.read()
                                    asset_expected = "assets/operations.js" if asset.endswith(".js") else "assets/brand-theme.css"
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
                raise RuntimeError(
                    f"{base}/ is not serving uploaded index.html build {build_id[:12]}; "
                    "check FTP_REMOTE_ROOT and the domain document root."
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
        print(f"FTP server peer IP: {ftp.sock.getpeername()[0]}", flush=True)
        ftp.login(os.environ["FTP_USER"], os.environ["FTP_PASS"])
        print(f"FTP login directory: {ftp.pwd()}", flush=True)
        try:
            print("FTP login directory entries: " + ", ".join(sorted(ftp.nlst())[:40]), flush=True)
        except error_perm as error:
            print(f"FTP directory listing unavailable: {error}", flush=True)
        print(f"Deployment target directory: {ROOT}", flush=True)
        try:
            print("Deployment target entries: " + ", ".join(sorted(ftp.nlst(ROOT))[:100]), flush=True)
        except error_perm as error:
            print(f"Cannot list deployment target: {error}", flush=True)
        old = previous_files(ftp)
        changed = [name for name in files if old.get(name) != digests[name]]
        print(f"Release: {len(changed)} changed, {len(files) - len(changed)} unchanged, {len(files)} total", flush=True)
        if not changed:
            print("FTP manifest matches this release; public HTTP verification is still required", flush=True)
            verify_live(os.environ.get("PUBLIC_BASE_URL", "https://challi.ir"), os.environ.get("GITHUB_SHA", "local"), digests)
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
            live = ROOT + "/" + name
            ensure_dir(ftp, posixpath.dirname(live), dirs)
            ftp.rename(remote, live)
            verify(ftp, digests[name], live)

        # Verify the actual domain/document-root before declaring the release successful.
        verify_live(os.environ.get("PUBLIC_BASE_URL", "https://challi.ir"), os.environ.get("GITHUB_SHA", "local"), digests)

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
