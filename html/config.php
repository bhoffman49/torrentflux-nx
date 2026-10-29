<?php

/*************************************************************
*  TorrentFlux PHP Torrent Manager
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

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
// errors go to the web server's error log, not to the page
ini_set("display_errors", "0");
ini_set("log_errors", "1");
// TorrentFlux SQLite Configuration
$cfg["db_type"] = "sqlite";
$cfg["db_host"] = "";
$cfg["db_user"] = "";
$cfg["db_pass"] = "";

// This server's settings, written by installer.php into state/ (next to html/, outside the web root):
// db_name (the SQLite file), rpc_port (Transmission RPC) and peer_port. Upgrades never touch state/.
$cfg["rpc_port"] = 9091;
$cfg["peer_port"] = 51413;
if (is_file(dirname(__DIR__)."/state/config.local.php"))
{
    include(dirname(__DIR__)."/state/config.local.php");
}
elseif (PHP_SAPI != "cli" && basename($_SERVER["SCRIPT_NAME"]) != "installer.php")
{
    // not installed yet
    header("location: installer.php");
    exit();
}

$cfg["pagetitle"] = "TorrentFlux NX";
$cfg["version"] = "NX 1.0.0";
$cfg["project_url"] = "https://github.com/bhoffman49/torrentflux-nx";
$cfg["file_types_array"] = array("torrent");
$cfg["user"] = "";
include_once("tfsecurity.php");
$cfg["ip"] = tfClientIp();   // the visitor, also behind a reverse proxy

$cfg["constants"] = array();
$cfg["constants"]["url_upload"] = "URL Upload";
$cfg["constants"]["reset_owner"] = "Reset Owner";
$cfg["constants"]["start_torrent"] = "Started Torrent";
$cfg["constants"]["queued_torrent"] = "Queued Torrent";
$cfg["constants"]["unqueued_torrent"] = "Removed from Queue";
$cfg["constants"]["QManager"] = "QManager";
$cfg["constants"]["access_denied"] = "ACCESS DENIED";
$cfg["constants"]["delete_torrent"] = "Delete Torrent";
$cfg["constants"]["fm_delete"] = "File Manager Delete";
$cfg["constants"]["fm_download"] = "File Download";
$cfg["constants"]["fm_upload"] = "File Manager Upload";
$cfg["constants"]["kill_torrent"] = "Kill Torrent";
$cfg["constants"]["file_upload"] = "File Upload";
$cfg["constants"]["error"] = "ERROR";
$cfg["constants"]["hit"] = "HIT";
$cfg["constants"]["update"] = "UPDATE";
$cfg["constants"]["admin"] = "ADMIN";

asort($cfg["constants"]);

// Theme defaults (hardcoded fallback - DB settings load on top when available)
$cfg["default_theme"] = "sage";
$cfg["default_language"] = "lang-english.php";
$cfg["main_bgcolor"] = "#606060";
$cfg["table_border_dk"] = "#808080";
$cfg["table_header_bg"] = "#c0c0c0";
$cfg["body_data_bg"] = "#DDDDDD";
$cfg["debug_sql"] = "0";