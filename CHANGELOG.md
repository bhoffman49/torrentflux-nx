# Changelog

All notable changes to TorrentFlux NX are listed here.
The format follows [Keep a Changelog](https://keepachangelog.com/), and versions follow [Semantic Versioning](https://semver.org/).

TorrentFlux NX is a modern rebuild of **TorrentFlux 2.4** (2008, by Qrome and contributors).
It is not related to the TorrentFlux-b4rt or TorrentFlux-NG forks.
The original 2.x history is kept in [CHANGELOG-2.4.txt](CHANGELOG-2.4.txt).

## [1.0.0] — 2026-10-28

Everything below is new or changed compared with TorrentFlux 2.4.

### Platform
- Runs on **PHP 8.x** (tested on 8.4). The 2008 code was ported and swept for PHP 8 type errors, argument order, quoting and undefined keys.
- **SQLite** is the database. No MySQL or PostgreSQL server needed.
- Two install routes: **Docker Compose** or a native `install.sh`, both finished by a web **installer.php**. It checks the requirements, creates the database and admin account, writes `config.php`, then locks itself.
- No hard-coded paths: the database, data folder, Transmission port and PHP binary are all settings.

### Torrent engine
- **Transmission** replaces BitTornado and Python. A small PHP bridge drives transmission-daemon over RPC, so TorrentFlux's pages, queue and process checks work unchanged.
- Python-free replacements for Make Torrent, torrent details and the queue manager (`transmission-create`, `transmission-show`, and a PHP queue manager).
- **Magnet links** work. Each magnet is turned into a .torrent through Transmission before it is added.
- **Seeding what you already have:** a torrent made from a file in a sub-folder finds the data, verifies it and seeds it instead of downloading again.
- Choosing files works: unticked files are skipped, and you can change them later from Torrent Details.

### New features
- **Live torrent list:** the list and drive space update every 5 seconds without reloading the page.
- **Run Torrent, Make Torrent, Torrent Details and Download Details** open as in-page dialogs with a details card, progress bar, stat tiles and file list. Popup windows remain as a fallback.
- **Make Torrent:** private torrents, backup trackers one per line, remembered tracker list, inline validation, and it never overwrites an existing .torrent.
- **File upload** on the Directory page: chunked uploads with progress (no PHP size limit), into your own folder (admins: anywhere), with a free-space check, and existing files are never overwritten.
- **Server Stats** page: uptime, load, CPU, memory, swap, disk, Transmission totals, processes and logins, refreshing in place.
- **Clear History** in Admin > Activity: clear the activity log and/or upload history by age, always keeping the records TorrentFlux still needs.
- **Admin > Backup** downloads a consistent SQLite copy.

### Themes and interface
- **Six new themes**, all self-contained (each folder holds its own CSS, colours and icons): sage (default), mint, workshop, walnut, clay and network.
- **Theme picker** in Profile and a default theme in Admin > Settings. New theme folders show up automatically.
- New icon set based on Material Icons, in each theme's colours, plus file-type icons for 120 extensions, all sharp on high-DPI screens.
- **Touch-friendly:** bigger action icons and checkboxes, tested on iPad.
- Pages use a centered column and long magnet links wrap, so there's no sideways scrolling.
- Stylesheets and scripts are cache-busted, so updates show without a hard refresh.
- Settings that only applied to BitTornado are hidden, and the dead torrent search is removed.
- A theme guide for making your own: [THEMES.md](docs/THEMES.md).

### Security
- Passwords are stored with **bcrypt** (`password_hash`). Old MD5 hashes are upgraded at the next login.
- Login lockout after 5 failed attempts from one IP within 15 minutes, and a new session ID at login.
- Session cookies are HttpOnly and SameSite=Lax, and Secure over HTTPS.
- Cross-site request blocking (Sec-Fetch-Site / Origin / Referer checks).
- The real client IP is logged when TorrentFlux runs behind a reverse proxy.
- Every redirect now stops the page. Before, a non-admin could run admin actions.
- File delete, download and folder download are confined to the data folder, and to your own folder for non-admins. In 2.4 a crafted path could delete or download outside it.
- SQL injection fixes in profile cookies, messages, links and settings. All database IDs are cast and all text is quoted.
- Shell commands quote file names and paths, so a file name can no longer run commands.
- The installer replaces the 2.4 "first person to log in becomes admin" behaviour.

### Fixed
- Torrents wrongly shown as "Torrent Died" / Incomplete, because process IDs were read by fixed columns.
- Inverted `../` checks in download and Make Torrent.
- RSS feeds (regex delimiters, PHP 8 fatal error in lastRSS).
- File priority selection crashing on PHP 8 (`implode` argument order).
- Directory listing crashing on 0-byte files and showing dot files.
- Database errors not clearing (sticky error code in the SQLite driver).
- Single-file torrents' name and size run together in the details popup.
- The torrent list failing for one refresh while a torrent starts (PHP 8 fatal error on a half-written status file).
- Many PHP 8 warnings (undefined keys, missing properties).

### Removed
- BitTornado, Python and the bundled BitTornado sources.
- Torrent search and its search engines (every site it used is gone).
- MySQL/PostgreSQL schemas and the 2.x upgrade scripts.
- The old built-in themes, replaced by the six new ones.

### Credits
- TorrentFlux 2.4 by Qrome and the TorrentFlux contributors (GPL-2.0).
- PHP 8 / SQLite port by ClaudeClaw.
- Icons: Material Icons by Google (Apache-2.0). Fonts: see NOTICE (SIL OFL 1.1).
