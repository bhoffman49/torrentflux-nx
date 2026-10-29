#!/bin/sh
# Bind-mounted state/ and data/ folders belong to the host user; the web server
# (and the Transmission it starts) runs as www-data, so hand the top folders over.
set -e
for d in /var/www/torrentflux/state /var/www/torrentflux/data; do
    mkdir -p "$d"
    chown www-data:www-data "$d"
done
chown -R www-data:www-data /var/www/torrentflux/state
exec "$@"
