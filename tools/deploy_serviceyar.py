"""Publish the staged ServiceYar payload using the runner's standard library."""
from ftplib import FTP, error_perm
from hashlib import sha256
from pathlib import Path
import io
import os
import posixpath

ROOT = "/public_html"
PAYLOAD = Path("deploy-payload")
STAGING = ROOT + "/.serviceyar-staging-" + os.environ["GITHUB_SHA"]


def ensure_dir(ftp, path):
    current = ""
    for part in path.strip("/").split("/"):
        current += "/" + part
        try:
            ftp.mkd(current)
        except error_perm:
            previous = ftp.pwd()
            ftp.cwd(current)
            ftp.cwd(previous)


def upload(ftp, source, destination):
    with source.open("rb") as stream:
        ftp.storbinary("STOR " + destination, stream)


def verify(ftp, source, destination):
    digest = sha256()
    ftp.retrbinary("RETR " + destination, digest.update)
    if digest.digest() != sha256(source.read_bytes()).digest():
        raise RuntimeError("Transferred file differs: " + destination)


def main():
    files = {
        path.relative_to(PAYLOAD).as_posix(): path
        for path in sorted(PAYLOAD.rglob("*"))
        if path.is_file()
    }
    required = {"index.html", ".htaccess", "assets/serviceyar-guide.pdf", "backend/public/index.php"}
    if not required <= files.keys():
        raise RuntimeError("Deployment payload is incomplete")

    with FTP(timeout=45) as ftp:
        ftp.connect(os.environ["FTP_HOST"], 21)
        ftp.login(os.environ["FTP_USER"], os.environ["FTP_PASS"])
        ensure_dir(ftp, STAGING)
        ftp.storbinary("STOR " + STAGING + "/.htaccess", io.BytesIO(b"Require all denied\n"))
        for name, source in files.items():
            remote = STAGING + "/" + (".htaccess.new" if name == ".htaccess" else name)
            ensure_dir(ftp, posixpath.dirname(remote))
            upload(ftp, source, remote)
            verify(ftp, source, remote)
        print(f"Verified {len(files)} files in staging", flush=True)

        # Preserve the previous ordering: server code first, entry point last.
        ordered = sorted(files, key=lambda name: (2 if name == "index.html" else
                         1 if name.startswith("assets/") or name == ".htaccess" else 0, name))
        for name in ordered:
            dest = ROOT + "/" + name
            ensure_dir(ftp, posixpath.dirname(dest))
            remote = STAGING + "/" + (".htaccess.new" if name == ".htaccess" else name)
            ftp.rename(remote, dest)
        for name, source in files.items():
            verify(ftp, source, ROOT + "/" + name)
        print(f"Verified {len(files)} live files, including the operator PDF", flush=True)


if __name__ == "__main__":
    main()
