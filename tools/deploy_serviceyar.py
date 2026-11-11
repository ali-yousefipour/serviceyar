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

ROOT = "/public_html"
PAYLOAD = Path("deploy-payload")
STAGING = ROOT + "/.serviceyar-staging"
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

    with FTP(timeout=45) as ftp:
        ftp.connect(os.environ["FTP_HOST"], 21)
        ftp.login(os.environ["FTP_USER"], os.environ["FTP_PASS"])
        old = previous_files(ftp)
        changed = [name for name in files if old.get(name) != digests[name]]
        print(f"Release: {len(changed)} changed, {len(files) - len(changed)} unchanged, {len(files)} total", flush=True)
        if not changed:
            print("All release files already match the published manifest", flush=True)
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

        # Commit metadata only after all live files have been checksum-verified.
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
