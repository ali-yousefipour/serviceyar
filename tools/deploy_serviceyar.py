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
            raise RuntimeError(
                f"Public-host verification failed for {url}: {error}. "
                "FTP upload alone does not prove that this is the domain document root."
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
        ftp.login(os.environ["FTP_USER"], os.environ["FTP_PASS"])
        print(f"FTP login directory: {ftp.pwd()}", flush=True)
        try:
            print("FTP login directory entries: " + ", ".join(sorted(ftp.nlst())[:40]), flush=True)
        except error_perm as error:
            print(f"FTP directory listing unavailable: {error}", flush=True)
        print(f"Deployment target directory: {ROOT}", flush=True)
        old = previous_files(ftp)
        changed = [name for name in files if old.get(name) != digests[name]]
        print(f"Release: {len(changed)} changed, {len(files) - len(changed)} unchanged, {len(files)} total", flush=True)
        if not changed:
            print("FTP manifest matches this release; public HTTP verification is still required", flush=True)
            verify_live(os.environ.get("PUBLIC_BASE_URL", "https://service-yar.ir"), os.environ.get("GITHUB_SHA", "local"), digests)
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
        verify_live(os.environ.get("PUBLIC_BASE_URL", "https://service-yar.ir"), os.environ.get("GITHUB_SHA", "local"), digests)

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
