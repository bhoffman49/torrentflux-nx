# TorrentFlux NX - PHP 8.4 + Apache + Transmission in one container.
# Build and run with docker compose (see docker-compose.yml and README.md).
FROM php:8.4-apache

ENV TZ=UTC

# Transmission does the downloading; zip/tar/procps are used by the web pages.
# (pdo_sqlite is already built into the official PHP image.)
RUN apt-get update \
 && apt-get install -y --no-install-recommends transmission-daemon transmission-cli zip procps \
 && rm -rf /var/lib/apt/lists/*

COPY html /var/www/torrentflux/html
COPY sql  /var/www/torrentflux/sql
COPY docker/apache.conf    /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini        /usr/local/etc/php/conf.d/torrentflux.ini
COPY docker/entrypoint.sh  /usr/local/bin/torrentflux-entrypoint

RUN mkdir -p /var/www/torrentflux/state /var/www/torrentflux/data \
 && chmod 755 /var/www/torrentflux/html/TF_BitTornado/tfpython /usr/local/bin/torrentflux-entrypoint \
 && chown www-data:www-data /var/www/torrentflux/state /var/www/torrentflux/data

# state: settings, install lock and database. data: the downloads.
VOLUME ["/var/www/torrentflux/state", "/var/www/torrentflux/data"]
EXPOSE 80 51413/tcp 51413/udp

ENTRYPOINT ["torrentflux-entrypoint"]
CMD ["apache2-foreground"]
