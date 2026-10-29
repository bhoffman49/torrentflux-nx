<?php
/*************************************************************
*  TorrentFlux NX -- web installer
*  Checks the server, creates the data folder and the SQLite database, makes the
*  admin account, writes config.local.php, then locks itself (install.lock) so it
*  can never run again.
**************************************************************/
/*
    This file is part of TorrentFlux NX.
    TorrentFlux NX is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
*/

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set("display_errors", "0");
header("X-Frame-Options: DENY");
header("Cache-Control: no-store");

$here = __DIR__;
$base = dirname($here);
$state = $base."/state";   // this server's files, outside the web root: config, lock, database
$lock = $state."/install.lock";
$local = $state."/config.local.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

// once installed, never again
if (is_file($lock) || is_file($local))
{
    http_response_code(403);
    page("Already installed", "<p>TorrentFlux NX is already installed. The installer is locked.</p>"
        ."<p>Please delete <code>installer.php</code> from the server. To install again from scratch, remove "
        ."<code>install.lock</code> and <code>config.local.php</code> first (this does not delete your data or database).</p>"
        ."<p><a class=\"btn\" href=\"login.php\">Go to the login page</a></p>");
    exit();
}

// ---------------------------------------------------------------- requirements
function canRun($fn)
{
    $off = array_map("trim", explode(",", (string)ini_get("disable_functions")));
    return function_exists($fn) && !in_array($fn, $off);
}
function findBin($name)
{
    if (!canRun("exec")) return "";
    $out = array();
    @exec("command -v ".escapeshellarg($name)." 2>/dev/null", $out);
    return isset($out[0]) ? trim($out[0]) : "";
}

$tfpython = $here."/TF_BitTornado/tfpython";
if (is_file($tfpython) && !is_executable($tfpython)) @chmod($tfpython, 0755);

$checks = array();   // label => array(ok, detail, required)
$checks["PHP 8.0 or newer"] = array(version_compare(PHP_VERSION, "8.0.0", ">="), "PHP ".PHP_VERSION, true);
$checks["PDO SQLite extension"] = array(extension_loaded("pdo_sqlite"), extension_loaded("pdo_sqlite") ? "loaded" : "install php-pdo / php-sqlite3", true);
foreach (array("exec", "shell_exec", "passthru", "popen") as $fn)
{
    $checks["PHP function ".$fn."()"] = array(canRun($fn), canRun($fn) ? "allowed" : "disabled by disable_functions (common on shared hosting)", true);
}
foreach (array("transmission-daemon" => true, "transmission-create" => true, "transmission-show" => true, "ps" => true, "tar" => true, "zip" => false) as $bin => $req)
{
    $p = findBin($bin);
    $checks["Program: ".$bin] = array($p != "", $p != "" ? $p : ($req ? "not found - install it" : "not found - folder downloads as .zip will not work (tar still does)"), $req);
}
$checks["tfpython is executable"] = array(is_executable($tfpython), is_executable($tfpython) ? "ok" : "run: chmod 755 ".$tfpython, true);
$checks["state/ folder writable"] = array(is_dir($state) && is_writable($state), is_dir($state) && is_writable($state) ? $state : "create ".$state." and make it writable by the web server user (config, lock and database go there)", true);

$allOk = true;
foreach ($checks as $c) { if ($c[2] && !$c[0]) $allOk = false; }

$runAs = function_exists("posix_getpwuid") ? (posix_getpwuid(posix_geteuid())["name"] ?? "?") : get_current_user();

// ---------------------------------------------------------------- defaults / form
$themes = array();
foreach (glob($here."/themes/*", GLOB_ONLYDIR) as $d) $themes[] = basename($d);
sort($themes);

$f = array(
    "data" => $base."/data",
    "db" => $state."/torrentflux.db",
    "rpc_port" => "9091",
    "peer_port" => "51413",
    "theme" => in_array("sage", $themes) ? "sage" : ($themes[0] ?? ""),
    "user" => "",
);
foreach ($f as $k => $v) { if (isset($_POST[$k])) $f[$k] = trim((string)$_POST[$k]); }

$errors = array();
$manualConfig = "";

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    if (empty($_POST["accept"])) $errors[] = "Please read and accept the disclaimer.";
    if (!$allOk) $errors[] = "Fix the failed requirements first.";

    $data = rtrim($f["data"], "/");
    $dbf = $f["db"];
    $pw = (string)($_POST["pass1"] ?? "");
    if ($data == "" || $data[0] != "/") $errors[] = "The data folder must be an absolute path.";
    if ($dbf == "" || $dbf[0] != "/") $errors[] = "The database file must be an absolute path.";
    foreach (array("rpc_port" => "Transmission RPC port", "peer_port" => "Peer port") as $k => $label)
    {
        if (!ctype_digit($f[$k]) || (int)$f[$k] < 1024 || (int)$f[$k] > 65535) $errors[] = $label." must be a number from 1024 to 65535.";
    }
    if ($f["rpc_port"] == $f["peer_port"]) $errors[] = "The two ports must be different.";
    // another Transmission (a distribution's own service, or a second TorrentFlux) already on the RPC port
    $sock = ctype_digit($f["rpc_port"]) ? @fsockopen("127.0.0.1", (int)$f["rpc_port"], $en, $es, 1) : false;
    if ($sock)
    {
        fclose($sock);
        $errors[] = "Something is already listening on 127.0.0.1:".$f["rpc_port"]." - choose another Transmission RPC port (or stop the other service).";
    }
    if (!in_array($f["theme"], $themes)) $errors[] = "Pick a theme.";
    // the queue manager only accepts these characters in a user name
    if (!preg_match('/^[a-z0-9._-]{2,32}$/', $f["user"])) $errors[] = "Admin user name: 2-32 characters, lower-case letters, digits, dot, dash or underscore.";
    if (strlen($pw) < 8) $errors[] = "Admin password: at least 8 characters.";
    if ($pw !== (string)($_POST["pass2"] ?? "")) $errors[] = "The two passwords are different.";
    if (is_file($dbf)) $errors[] = "A database already exists at ".$dbf.". Move it away or choose another file name.";

    if (!$errors)
    {
        // folders
        foreach (array($data, $data."/.torrents", $data."/.tfdata/transmission") as $d)
        {
            if (!is_dir($d) && !@mkdir($d, 0775, true)) $errors[] = "Could not create ".$d." (the web server runs as ".$runAs.").";
        }
        if (!$errors && !is_writable($data)) $errors[] = $data." is not writable by ".$runAs.".";
        if (!is_dir(dirname($dbf)) || !is_writable(dirname($dbf))) $errors[] = "The folder ".dirname($dbf)." must exist and be writable by ".$runAs." (SQLite also writes its journal there).";
    }

    if (!$errors)
    {
        try
        {
            $sql = file_get_contents($base."/sql/sqlite_torrentflux.sql");
            if ($sql === false) $sql = file_get_contents($here."/sqlite_torrentflux.sql");
            if ($sql === false) throw new Exception("sql/sqlite_torrentflux.sql is missing from the package.");

            $pdo = new PDO("sqlite:".$dbf);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec($sql);

            $tf = $here."/TF_BitTornado/";
            $set = $pdo->prepare("UPDATE tf_settings SET tf_value=? WHERE tf_key=?");
            foreach (array(
                "path" => $data."/",
                "btphpbin" => $tf."btphptornado.php",
                "btshowmetainfo" => $tf."btshowmetainfo.py",
                "btmakemetafile" => $tf."btmakemetafile.py",
                "tfQManager" => $tf."tfQManager.py",
                "pythonCmd" => $tf."tfpython",
                "default_theme" => $f["theme"],
                "package_type" => findBin("zip") != "" ? "zip" : "tar",
            ) as $k => $v)
            {
                $set->execute(array($v, $k));
            }

            // the admin, hashed the way login.php reads the password field (trim, htmlentities, addslashes)
            $now = (string)time();
            $pdo->prepare("INSERT INTO tf_users (user_id, password, hits, last_visit, time_created, user_level, hide_offline, theme, language_file) VALUES (?,?,0,?,?,2,0,?,'lang-english.php')")
                ->execute(array($f["user"], password_hash(addslashes(htmlentities(trim($pw), ENT_QUOTES)), PASSWORD_DEFAULT), $now, $now, $f["theme"]));
            $pdo->prepare("INSERT INTO tf_log (user_id, file, action, ip, ip_resolved, user_agent, time) VALUES (?,?,?,?,?,?,?)")
                ->execute(array($f["user"], "TorrentFlux NX installed", "ADMIN", $_SERVER["REMOTE_ADDR"] ?? "", "", substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 200), $now));
            $pdo = null;
            @chmod($dbf, 0660);

            // Transmission: localhost-only RPC on this install's port, downloads into the data folder
            $ts = $data."/.tfdata/transmission/settings.json";
            if (!is_file($ts))
            {
                file_put_contents($ts, json_encode(array(
                    "rpc-bind-address" => "127.0.0.1", "rpc-port" => (int)$f["rpc_port"],
                    "rpc-authentication-required" => false, "rpc-whitelist" => "127.0.0.1",
                    "rpc-whitelist-enabled" => true, "download-dir" => $data,
                    "umask" => 2, "peer-port" => (int)$f["peer_port"], "dht-enabled" => true, "pex-enabled" => true,
                    "lpd-enabled" => false, "utp-enabled" => true,
                ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
        catch (Throwable $e)
        {
            $errors[] = "Database: ".$e->getMessage();
            @unlink($dbf);
        }
    }

    if (!$errors)
    {
        $conf = "<?php\n// Written by installer.php on ".date("Y-m-d H:i")." - this server's own settings (keep it when upgrading)\n"
              ."\$cfg[\"db_name\"] = ".var_export($dbf, true).";\n"
              ."\$cfg[\"rpc_port\"] = ".(int)$f["rpc_port"].";\n"
              ."\$cfg[\"peer_port\"] = ".(int)$f["peer_port"].";\n";
        if (@file_put_contents($local, $conf) === false)
        {
            $manualConfig = $conf;
        }
        @chmod($local, 0640);
        @file_put_contents($lock, "Installed ".date("c")."\n");
        $selfDeleted = @unlink(__FILE__);

        $msg = "<p class=\"ok\">TorrentFlux NX is installed.</p>";
        if ($manualConfig != "")
        {
            $msg .= "<p class=\"bad\">Could not write the settings file, so create <code>".h($local)."</code> yourself with exactly this content:</p>"
                  ."<pre>".h($manualConfig)."</pre>";
        }
        if (!is_file($lock))
        {
            $msg .= "<p class=\"bad\">Could not write install.lock. <b>Delete installer.php now</b> - until you do, anyone could run it again.</p>";
        }
        elseif (!$selfDeleted)
        {
            $msg .= "<p>The installer is locked. Please delete <code>installer.php</code> from the server as well.</p>";
        }
        $msg .= "<ul><li>Downloads: <code>".h($data)."</code></li><li>Database: <code>".h($dbf)."</code></li>"
              ."<li>Transmission RPC: 127.0.0.1:".(int)$f["rpc_port"].", peers on port ".(int)$f["peer_port"]." (open this port in your firewall/router for better speeds)</li></ul>"
              ."<p>Back up the database and your data yourself - see the README.</p>"
              ."<p><a class=\"btn\" href=\"login.php\">Log in as ".h($f["user"])."</a></p>";
        page("Installed", $msg);
        exit();
    }
}

// ---------------------------------------------------------------- page
$rows = "";
foreach ($checks as $label => $c)
{
    $cls = $c[0] ? "ok" : ($c[2] ? "bad" : "warn");
    $rows .= "<tr><td>".h($label)."</td><td class=\"".$cls."\">".($c[0] ? "&#10003;" : ($c[2] ? "&#10007;" : "!"))." ".h($c[1])."</td></tr>";
}
$themeOpts = "";
foreach ($themes as $t) $themeOpts .= "<option".($t == $f["theme"] ? " selected" : "").">".h($t)."</option>";
$err = $errors ? "<div class=\"errors\"><b>Please fix:</b><ul><li>".implode("</li><li>", array_map("h", $errors))."</li></ul></div>" : "";
$docroot = rtrim((string)($_SERVER["DOCUMENT_ROOT"] ?? ""), "/");
$inWeb = function ($p) use ($docroot) { return $docroot != "" && strpos(rtrim($p, "/")."/", $docroot."/") === 0; };
$webWarn = ($inWeb($f["data"]) || $inWeb($f["db"]))
    ? "<p class=\"warn\">The data folder or database is inside the web root (".h($docroot)."). Make sure your web server refuses to serve it, or choose a folder outside the web root.</p>" : "";

page("Install", $err."
<h2>1. Disclaimer</h2>
<div class=\"box\">
<p><b>TorrentFlux NX is provided as is, without warranty of any kind.</b> The authors are not liable for any damages, data loss or other problems from using it.</p>
<p><b>You alone are responsible for what you download and share</b>, and for following the laws of your country, including copyright law.</p>
<p><b>There is no technical support.</b> By installing it you confirm you know how to run and secure a Linux server, a web server and PHP, keep your own backups, and can fix problems yourself.</p>
<p>TorrentFlux NX is free software under the GNU General Public License v2 (see COPYING and DISCLAIMER.md).</p>
</div>

<h2>2. Requirements</h2>
<table class=\"req\">".$rows."</table>
<p class=\"dim\">The web server runs PHP as <b>".h($runAs)."</b>; the data folder and the database folder must be writable by that user.</p>
".($allOk ? "" : "<p class=\"bad\">Some required items are missing. Fix them and reload this page.</p>")."

<form method=\"post\" autocomplete=\"off\">
<h2>3. Settings</h2>
<label>Data folder (downloads; the user folders go inside)<input name=\"data\" value=\"".h($f["data"])."\" required></label>
<label>Database file (SQLite)<input name=\"db\" value=\"".h($f["db"])."\" required></label>
".$webWarn."
<div class=\"two\">
<label>Transmission RPC port (localhost only)<input name=\"rpc_port\" value=\"".h($f["rpc_port"])."\" required></label>
<label>Peer port (open it in your firewall)<input name=\"peer_port\" value=\"".h($f["peer_port"])."\" required></label>
</div>
<label>Default theme<select name=\"theme\">".$themeOpts."</select></label>

<h2>4. Admin account</h2>
<label>User name<input name=\"user\" value=\"".h($f["user"])."\" required pattern=\"[a-z0-9._-]{2,32}\"></label>
<div class=\"two\">
<label>Password (8+ characters)<input type=\"password\" name=\"pass1\" required minlength=\"8\"></label>
<label>Password again<input type=\"password\" name=\"pass2\" required minlength=\"8\"></label>
</div>

<label class=\"check\"><input type=\"checkbox\" name=\"accept\" value=\"1\" required> I have read and accept the disclaimer above</label>
<p><input class=\"btn\" type=\"submit\" value=\"Install TorrentFlux NX\"".($allOk ? "" : " disabled")."></p>
</form>");

function page($title, $body)
{
    echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
        ."<title>TorrentFlux NX - ".h($title)."</title><style>
:root{--bg:#e9ece9;--panel:#fff;--ink:#263029;--dim:#66706a;--line:#c9d0ca;--accent:#4f6b57;--ok:#2f7d46;--bad:#b3261e;--warn:#9a6700}
@media (prefers-color-scheme:dark){:root{--bg:#1d211e;--panel:#262b27;--ink:#e3e8e4;--dim:#9aa59d;--line:#3a423c;--accent:#8fb39a;--ok:#6cc58a;--bad:#ff8a80;--warn:#e0b44c}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
main{max-width:760px;margin:24px auto;padding:0 16px}header{font-size:22px;font-weight:600;margin-bottom:12px}
section{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:8px 20px 16px}
h2{font-size:16px;margin:20px 0 8px;color:var(--accent)}.box{border-left:3px solid var(--accent);padding:2px 12px;margin:8px 0}
table.req{width:100%;border-collapse:collapse}table.req td{padding:5px 6px;border-bottom:1px solid var(--line);vertical-align:top}
label{display:block;margin:10px 0;font-weight:500}input,select{display:block;width:100%;margin-top:4px;padding:8px;font:inherit;color:inherit;background:var(--bg);border:1px solid var(--line);border-radius:6px}
.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media (max-width:560px){.two{grid-template-columns:1fr}}
label.check{display:flex;gap:8px;align-items:center}label.check input{width:auto;margin:0}
.btn{display:inline-block;width:auto;padding:9px 18px;background:var(--accent);color:#fff;border:0;border-radius:6px;text-decoration:none;font-weight:600;cursor:pointer}
.btn[disabled]{opacity:.5;cursor:not-allowed}.ok{color:var(--ok)}.bad{color:var(--bad)}.warn{color:var(--warn)}.dim{color:var(--dim)}
.errors{border:1px solid var(--bad);border-radius:8px;padding:4px 14px;margin:12px 0;color:var(--bad)}
code,pre{font-family:ui-monospace,Consolas,monospace;font-size:13px}pre{background:var(--bg);padding:10px;border-radius:6px;overflow:auto}
</style></head><body><main><header>TorrentFlux NX &middot; ".h($title)."</header><section>".$body."</section></main></body></html>";
}
