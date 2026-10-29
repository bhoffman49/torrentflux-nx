#!/usr/bin/env python3
"""Build the two TorrentFlux NX release downloads into dist/:
  torrentflux-nx-<version>-server.tar.gz  everything: Docker files, install.sh, docs
  torrentflux-nx-<version>.zip            just the PHP application and the installer
Run from the repository root:  python tools/make-release.py
File modes are set here, so it gives the same result on Windows and Linux."""
import os, re, sys, tarfile, time, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
cfg = open(os.path.join(ROOT, "html", "config.php"), encoding="latin-1").read()
VERSION = re.search(r'\$cfg\["version"\] = "NX ([0-9][^"]*)"', cfg).group(1)
TOP = "torrentflux-nx-" + VERSION
EXEC = {"install.sh", "docker/entrypoint.sh", "html/TF_BitTornado/tfpython", "tools/make-release.py"}
SKIP_DIRS = {".git", "dist", "__pycache__"}
SKIP_FILES = {"config.local.php", "install.lock"}
ZIP_TOP = ["html", "sql", "state", "data", "COPYING", "NOTICE", "README.md", "CHANGELOG.md",
           "CHANGELOG-2.4.txt", "DISCLAIMER.md"]

def files():
    for d, dirs, names in os.walk(ROOT):
        dirs[:] = sorted(x for x in dirs if x not in SKIP_DIRS)
        rel_d = os.path.relpath(d, ROOT).replace(os.sep, "/")
        for n in sorted(names):
            rel = n if rel_d == "." else rel_d + "/" + n
            if n in SKIP_FILES or n.endswith((".db", ".bak", ".tfpart")):
                continue
            if rel.startswith(("state/", "data/")) and n != ".htaccess":
                continue
            yield rel

def mode(rel):
    return 0o755 if rel in EXEC else 0o644

os.makedirs(os.path.join(ROOT, "dist"), exist_ok=True)
now = time.time()

tgz = os.path.join(ROOT, "dist", TOP + "-server.tar.gz")
with tarfile.open(tgz, "w:gz") as t:
    for rel in files():
        ti = t.gettarinfo(os.path.join(ROOT, rel), TOP + "/" + rel)
        ti.mode, ti.uid, ti.gid, ti.uname, ti.gname, ti.mtime = mode(rel), 0, 0, "root", "root", now
        with open(os.path.join(ROOT, rel), "rb") as fh:
            t.addfile(ti, fh)

zp = os.path.join(ROOT, "dist", TOP + ".zip")
with zipfile.ZipFile(zp, "w", zipfile.ZIP_DEFLATED) as z:
    for rel in files():
        if rel.split("/")[0] not in ZIP_TOP:
            continue
        zi = zipfile.ZipInfo(TOP + "/" + rel, time.localtime(now)[:6])
        zi.create_system = 3   # Unix, so unzip keeps the permissions (it ignores them in Windows-made zips)
        zi.external_attr = (0o100000 | mode(rel)) << 16
        zi.compress_type = zipfile.ZIP_DEFLATED
        with open(os.path.join(ROOT, rel), "rb") as fh:
            z.writestr(zi, fh.read())

for p in (tgz, zp):
    print("%-50s %8.1f KB" % (os.path.relpath(p, ROOT), os.path.getsize(p) / 1024))
