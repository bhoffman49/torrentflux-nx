<?php

/*************************************************************
*  TorrentFlux - PHP Torrent Manager
*  www.torrentflux.com
**************************************************************/
/*
    This file is part of TorrentFlux.

    TorrentFlux is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    TorrentFlux is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with TorrentFlux; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Security helpers every page uses: the visitor's real address behind a reverse proxy, session cookie
// settings, and refusing actions that another website tries to start (cross-site request forgery).

// The visitor's IP address. Behind a reverse proxy (the request comes from a private or local address)
// it is the last X-Forwarded-For entry, the one our proxy added; earlier entries can be made up by the client.
function tfClientIp()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff != '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
    {
        $parts = array_map('trim', explode(',', $xff));
        $last = end($parts);
        if (filter_var($last, FILTER_VALIDATE_IP)) $ip = $last;
    }
    return $ip;
}

function tfIsHttps()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') == 'https';
}

// Session cookie that page scripts can't read, that other sites' form posts don't carry, and that is
// HTTPS-only when the site is served over HTTPS
function tfStartSession()
{
    session_name("TorrentFlux");
    session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => tfIsHttps()));
    session_start();
}

// Refuse a request that can change something (any POST, or a GET with parameters such as ?kill= or ?del=)
// when the browser says another website started it. Current browsers send Sec-Fetch-Site; for older
// ones the Origin or Referer host must be this site.
function tfBlockCrossSite()
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method != 'POST' && ($_SERVER['QUERY_STRING'] ?? '') == '') return;

    $site = strtolower($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($site == 'cross-site') tfDenyCrossSite();
    if ($site == '')
    {
        $from = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        if ($from != '' && $from != 'null' && strtolower((string)parse_url($from, PHP_URL_HOST)) != $host) tfDenyCrossSite();
    }
}

function tfDenyCrossSite()
{
    http_response_code(403);
    echo "This request was started from another website, so TorrentFlux blocked it. Open TorrentFlux and try again from there.";
    exit();
}

?>
