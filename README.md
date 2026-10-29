# TorrentFlux NX

**A self-hosted, web-based BitTorrent client for your own Linux server**, a modern rebuild of the classic
TorrentFlux 2.4 for PHP 8, SQLite and Transmission.

> TorrentFlux NX is a modern rebuild of the original **TorrentFlux 2.4** (2008).
> It is not related to the TorrentFlux-b4rt or TorrentFlux-NG forks.

> [!IMPORTANT]
> **Disclaimer.** TorrentFlux NX is provided as is, without warranty, and **without technical support**.
> You alone are responsible for what you download and share, and for securing your server.
> Please read [DISCLAIMER.md](DISCLAIMER.md) before installing.

## What it does

- Add torrents by upload, URL or **magnet link**; start, stop, queue and delete them from the browser.
- **Live torrent list** with progress, speeds and ratios, updating every few seconds.
- Pick which files to download, set speed limits, seed ratio and what happens when finished.
- **Make Torrent** from any file or folder (public or private, with backup trackers), and seed it straight away.
- **Directory browser** with file-type icons: download files, download folders as zip/tar, view .nfo files,
  delete, and **upload files** in chunks with a progress bar.
- **Multi-user**: each user has their own folder; admins manage users, settings, links, RSS feeds and the activity log.
- **Server Stats** page (uptime, load, memory, disk, Transmission totals) and Admin > Backup of the database.
- **Six themes** (sage, mint, workshop, walnut, clay, network), picked per user, and easy to add your own ([docs/THEMES.md](docs/THEMES.md)).
- Works on phones and tablets.

See [CHANGELOG.md](CHANGELOG.md) for everything that is new compared with TorrentFlux 2.4.

## Requirements

- Linux with **PHP 8.0+** (pdo_sqlite) and a web server (Apache is set up for you; nginx works too).
- **transmission-daemon**, **transmission-cli** (`transmission-create`, `transmission-show`), `zip`, `tar`, `ps`.
- PHP must be allowed to run `exec()`, `shell_exec()`, `passthru()` and `popen()`.
  That rules out most cheap shared hosting: use a VPS, a home server or Docker.
- Or just **Docker** with Compose, which brings everything else.

## Install

Two downloads are on the [Releases](../../releases) page. Both end in the same web installer, which checks the
server, creates the database and your admin account, then locks itself.

### Option 1 - Docker (easiest)

Download **torrentflux-nx-1.0.0-server.tar.gz**, then:

```bash
tar xzf torrentflux-nx-1.0.0-server.tar.gz
cd torrentflux-nx-1.0.0
docker compose up -d --build
```

Open `http://<your-server>:8080/` and the installer starts. Keep the suggested folders and the peer port 51413
(or change it in `docker-compose.yml` as well). Downloads land in `./data`, and settings and the database in `./state`.

### Option 2 - Native install on Debian/Ubuntu or RHEL/Alma/Rocky

From the same **server** package:

```bash
sudo ./install.sh
```

It installs only what is missing (Apache, PHP's SQLite module, Transmission from your distribution or EPEL, zip),
copies the files to `/opt/torrentflux-nx`, and serves it at `http://<your-server>/torrentflux-nx/`.
Then open `installer.php` there. Options: `--dir`, `--url-path`, `--listen 127.0.0.1:8090` (its own port),
`--web-user`, `--no-packages`, `--yes` - see `./install.sh --help`.

### Option 3 - Just the PHP files

Download **torrentflux-nx-1.0.0.zip** if you already run a web server with PHP and Transmission:

1. Unzip it somewhere, for example `/srv/torrentflux-nx`.
2. Point your web server (or an alias) at the **`html/`** folder only. `state/` and `data/` must not be served.
3. Make `state/` and `data/` writable by the user PHP runs as, and `html/TF_BitTornado/tfpython` executable.
4. Open `installer.php` in your browser.

nginx example (PHP-FPM):

```nginx
location /torrentflux-nx/ {
    alias /srv/torrentflux-nx/html/;
    index index.php;
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

## After installing

- **Use HTTPS.** Put it behind a reverse proxy with a certificate (Caddy, nginx, Apache) before exposing it
  to the internet. It detects the visitor's real IP behind a proxy, and marks cookies Secure when the proxy
  sends `X-Forwarded-Proto: https`.
- **Open the peer port** (default 51413, TCP and UDP) in your firewall and router for good speeds.
  Transmission's own control port (9091) only listens on 127.0.0.1.
- **Create users** under Admin > New User. Stick to `a-z 0-9 . _ -` in user names (the queue manager skips other names).
- **Back up** `state/` (settings and database) and whatever you need from `data/`. Admin > Backup downloads a
  copy of the database.
- Delete `installer.php` if the installer could not delete it itself (it is locked either way).

## Upgrading

- **Docker:** replace the files with the new release, then `docker compose up -d --build`. `state/` and `data/` are kept.
- **install.sh:** run the new release's `install.sh` again; it keeps `state/` and `data/` and leaves the old code in `html.previous/`.
- **Manual:** replace `html/` and `sql/`, keep `state/` and `data/`, and remove the new `installer.php`.

## Folder layout

```
html/    the web application - the only folder the web server serves
sql/     database schema used by the installer
state/   this server's settings (config.local.php), install.lock and the SQLite database
data/    downloads: one folder per user, plus .torrents/ and Transmission's .tfdata/
```

## Support

There is no technical support (see [DISCLAIMER.md](DISCLAIMER.md)). You are welcome to open an issue, but there is
no promise it will be answered or fixed. Pull requests are welcome.

## License and credits

TorrentFlux NX is free software under the **GNU General Public License v2** (or later); see [COPYING](COPYING).

- **TorrentFlux 2.4** by Qrome and the TorrentFlux contributors (2004-2008); history in [CHANGELOG-2.4.txt](CHANGELOG-2.4.txt).
- PHP 8 / SQLite port by ClaudeClaw.
- Icons: Material Icons by Google (Apache 2.0). Fonts: Google Fonts (SIL OFL 1.1).
- Bundled libraries: ADOdb, dTree, overLIB, wz_tooltip. See [NOTICE](NOTICE) for all third-party notices.
