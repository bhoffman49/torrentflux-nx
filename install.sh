#!/bin/bash
# TorrentFlux NX - native installer for a Linux server with Apache (Debian/Ubuntu or RHEL/Alma/Rocky).
#
#   sudo ./install.sh [options]
#
#   --dir DIR          install folder (default /opt/torrentflux-nx); html/ is served, state/ and data/ are not
#   --url-path PATH    serve it at http://<server>PATH on the existing web server (default /torrentflux-nx)
#   --listen ADDR:PORT serve it on its own address/port instead, e.g. 127.0.0.1:8090
#   --web-user USER    the user PHP runs as (default: detected - www-data, apache, or the php-fpm pool user)
#   --no-packages      do not install missing packages
#   --yes              accept the disclaimer without asking
#
# Only packages that are missing are installed; nothing already on the server is upgraded.
# Running it again on an existing install upgrades the code and keeps state/ and data/.
set -euo pipefail

DIR=/opt/torrentflux-nx
URLPATH=/torrentflux-nx
LISTEN=""
WEBUSER=""
PACKAGES=1
YES=0
SRC="$(cd "$(dirname "$0")" && pwd)"

die() { echo "ERROR: $*" >&2; exit 1; }
say() { echo "==> $*"; }

while [ $# -gt 0 ]; do
    case "$1" in
        --dir) DIR="${2%/}"; shift 2 ;;
        --url-path) URLPATH="/${2#/}"; URLPATH="${URLPATH%/}"; shift 2 ;;
        --listen) LISTEN="$2"; shift 2 ;;
        --web-user) WEBUSER="$2"; shift 2 ;;
        --no-packages) PACKAGES=0; shift ;;
        --yes) YES=1; shift ;;
        -h|--help) sed -n '2,15p' "$0"; exit 0 ;;
        *) die "unknown option $1 (see --help)" ;;
    esac
done

[ "$(id -u)" -eq 0 ] || die "run as root (sudo ./install.sh)"
[ -f "$SRC/html/config.php" ] || die "run install.sh from the unpacked TorrentFlux NX folder"
case "$DIR" in /*) ;; *) die "--dir must be an absolute path" ;; esac
LISTEN_RE='^([0-9.]+|\[[0-9a-fA-F:]+\]):[0-9]+$'   # 127.0.0.1:8090 or [::1]:8090
[ -z "$LISTEN" ] || [[ "$LISTEN" =~ $LISTEN_RE ]] || die "--listen must look like 127.0.0.1:8090"

# ---------------------------------------------------------------- disclaimer
cat <<'TXT'

TorrentFlux NX - DISCLAIMER (full text: DISCLAIMER.md)
  * Provided AS IS, without warranty of any kind. The authors are not liable for any
    damages, data loss or other problems from using it.
  * You alone are responsible for what you download and share, and for following the
    laws of your country, including copyright law.
  * There is NO technical support. You are expected to know how to run and secure a
    Linux server, a web server and PHP, keep your own backups, and fix problems yourself.

TXT
if [ "$YES" -ne 1 ]; then
    read -r -p "Type 'yes' to accept and continue: " answer
    [ "$answer" = "yes" ] || die "not accepted - nothing was changed"
fi

# ---------------------------------------------------------------- system
if command -v apt-get >/dev/null; then
    FAMILY=debian; APACHE=apache2; CONFDIR=/etc/apache2/conf-available; SITEDIR=/etc/apache2/sites-available
elif command -v dnf >/dev/null || command -v yum >/dev/null; then
    FAMILY=redhat; APACHE=httpd; CONFDIR=/etc/httpd/conf.d; SITEDIR=/etc/httpd/conf.d
    PKG=$(command -v dnf || command -v yum)
else
    die "only Debian/Ubuntu (apt) and RHEL/Alma/Rocky (dnf) are supported - see README for a manual install"
fi
say "System: $FAMILY"

have_pdo_sqlite() { command -v php >/dev/null && php -m 2>/dev/null | grep -qi '^pdo_sqlite$'; }

if [ "$PACKAGES" -eq 1 ]; then
    missing=()
    if [ "$FAMILY" = debian ]; then
        command -v apache2 >/dev/null || missing+=(apache2)
        command -v php >/dev/null || missing+=(php libapache2-mod-php)
        have_pdo_sqlite || missing+=(php-sqlite3)
        tr_was_installed=0; command -v transmission-daemon >/dev/null && tr_was_installed=1
        command -v transmission-daemon >/dev/null || missing+=(transmission-daemon)
        command -v transmission-create >/dev/null || missing+=(transmission-cli)
        command -v zip >/dev/null || missing+=(zip)
        command -v ps >/dev/null || missing+=(procps)
        if [ ${#missing[@]} -gt 0 ]; then
            say "Installing: ${missing[*]}"
            apt-get update -q
            DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "${missing[@]}"
        fi
        # the Debian package starts its own transmission-daemon on port 9091; TorrentFlux runs its own
        if [ "$tr_was_installed" -eq 0 ] && systemctl list-unit-files transmission-daemon.service >/dev/null 2>&1; then
            say "Disabling the system transmission-daemon service (TorrentFlux starts its own)"
            systemctl disable --now transmission-daemon.service || true
        fi
    else
        command -v httpd >/dev/null || missing+=(httpd)
        command -v php >/dev/null || missing+=(php php-cli)
        have_pdo_sqlite || missing+=(php-pdo)
        if ! command -v transmission-daemon >/dev/null || ! command -v transmission-create >/dev/null; then
            rpm -q epel-release >/dev/null 2>&1 || { say "Enabling EPEL (Transmission comes from there)"; "$PKG" install -y epel-release; }
            command -v transmission-daemon >/dev/null || missing+=(transmission-daemon)
            command -v transmission-create >/dev/null || missing+=(transmission-cli)
        fi
        command -v zip >/dev/null || missing+=(zip)
        command -v ps >/dev/null || missing+=(procps-ng)
        if [ ${#missing[@]} -gt 0 ]; then
            say "Installing: ${missing[*]}"
            "$PKG" install -y "${missing[@]}"
        fi
    fi
fi

for c in php transmission-daemon transmission-create transmission-show ps tar; do
    command -v "$c" >/dev/null || die "$c is not installed"
done
have_pdo_sqlite || die "PHP's pdo_sqlite extension is not installed"

# who runs PHP: the php-fpm pool user if php-fpm is in use, else Apache's user
if [ -z "$WEBUSER" ]; then
    WEBUSER=$(ps -o user= -C php-fpm,php-fpm8.4,php-fpm8.3,php-fpm8.2 2>/dev/null | grep -v '^root$' | head -1 || true)
    if [ -z "$WEBUSER" ]; then
        if [ "$FAMILY" = debian ]; then WEBUSER=www-data; else WEBUSER=apache; fi
    fi
fi
id "$WEBUSER" >/dev/null 2>&1 || die "web user '$WEBUSER' does not exist (use --web-user)"
say "PHP runs as: $WEBUSER"

# ---------------------------------------------------------------- files
mkdir -p "$DIR"
if [ -f "$DIR/state/config.local.php" ]; then
    say "Existing install found in $DIR - upgrading the code (state/ and data/ are kept)"
    rm -rf "$DIR/html.previous"
    if [ -d "$DIR/html" ]; then mv "$DIR/html" "$DIR/html.previous"; fi
    cp -a "$SRC/html" "$DIR/html"
    rm -f "$DIR/html/installer.php"
    UPGRADE=1
else
    if [ -d "$DIR/html" ]; then die "$DIR/html exists but there is no state/config.local.php - pick an empty --dir"; fi
    cp -a "$SRC/html" "$DIR/html"
    UPGRADE=0
fi
rm -rf "$DIR/sql"; cp -a "$SRC/sql" "$DIR/sql"
for f in COPYING DISCLAIMER.md README.md CHANGELOG.md CHANGELOG-2.4.txt NOTICE; do
    if [ -f "$SRC/$f" ]; then cp "$SRC/$f" "$DIR/"; fi
done
mkdir -p "$DIR/state" "$DIR/data"
cp "$SRC/state/.htaccess" "$DIR/state/" 2>/dev/null || true
cp "$SRC/data/.htaccess" "$DIR/data/" 2>/dev/null || true

# code: root-owned and read-only for the web server; state and data: the web server's
chown -R root:root "$DIR/html" "$DIR/sql"
chmod -R u=rwX,go=rX "$DIR/html" "$DIR/sql"
chmod 755 "$DIR/html/TF_BitTornado/tfpython"
chmod 755 "$DIR"
chown "$WEBUSER": "$DIR/state" "$DIR/data"
chmod 750 "$DIR/state"
chmod 775 "$DIR/data"

# ---------------------------------------------------------------- Apache
DIRBLOCK="    <Directory $DIR/html>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        DirectoryIndex index.php
    </Directory>"
if [ -n "$LISTEN" ]; then
    CONF="$SITEDIR/torrentflux-nx.conf"
    BODY="# TorrentFlux NX (install.sh)
Listen $LISTEN
<VirtualHost $LISTEN>
    DocumentRoot $DIR/html
$DIRBLOCK
</VirtualHost>"
    URL="http://$LISTEN/"
else
    CONF="$CONFDIR/torrentflux-nx.conf"
    BODY="# TorrentFlux NX (install.sh)
Alias $URLPATH $DIR/html
$DIRBLOCK"
    URL="http://<this server>$URLPATH/"
fi
if [ -f "$CONF" ] && ! grep -q "^# TorrentFlux NX (install.sh)" "$CONF"; then
    die "$CONF exists and was not written by install.sh - move it away first"
fi
if [ -f "$CONF" ]; then cp "$CONF" "$CONF.bak"; fi
echo "$BODY" > "$CONF"
if [ "$FAMILY" = debian ]; then
    if [ -n "$LISTEN" ]; then a2ensite -q torrentflux-nx; else a2enconf -q torrentflux-nx; fi
fi
if apachectl configtest; then
    systemctl reload "$APACHE" 2>/dev/null || systemctl restart "$APACHE"
else
    if [ -f "$CONF.bak" ]; then mv "$CONF.bak" "$CONF"; else rm -f "$CONF"; fi
    die "Apache rejected the configuration - it was removed again, nothing else changed in Apache"
fi
rm -f "$CONF.bak"

# ---------------------------------------------------------------- done
echo
if [ "$UPGRADE" -eq 1 ]; then
    say "Upgraded. The previous code is in $DIR/html.previous (delete it once all is well)."
    say "Open $URL"
else
    say "Files are in $DIR. Now open ${URL}installer.php to finish."
    say "Use $DIR/data for downloads and $DIR/state for the database in the installer (the defaults)."
fi
say "Open the peer port you choose in the installer (default 51413, tcp+udp) in your firewall."
