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

// Server Stats (admins only): server load and memory from /proc, Transmission's totals over RPC,
// the torrent processes and the queue manager. Refreshes itself every 5 seconds (#tf-who-live).

include_once("config.php");
include_once("functions.php");
include_once("RunningTorrent.php");
require_once("torrentview.php");

if (! IsAdmin($cfg["user"])) {
    header("Location:index.php");
    exit();
}

// "2 days, 22 h" from seconds
function whoDuration($s)
{
    $d = floor($s / 86400); $h = floor(($s % 86400) / 3600); $m = floor(($s % 3600) / 60);
    if ($d > 0) return $d." day".($d == 1 ? "" : "s").", ".$h." h";
    if ($h > 0) return $h." h ".$m." min";
    return $m." min";
}

function whoSpeed($bytesPerSec)
{
    return number_format($bytesPerSec / 1024, 1)." kB/s";
}

// server (inside the container these show the host's kernel, CPUs and memory)
$uptime = (float)@file_get_contents("/proc/uptime");
$load = explode(" ", trim(@file_get_contents("/proc/loadavg")));
$cpus = preg_match_all('/^processor\s*:/m', @file_get_contents("/proc/cpuinfo"));
$mem = array();
foreach (file("/proc/meminfo") as $line)
{
    if (preg_match('/^(\w+):\s+(\d+) kB/', $line, $m)) $mem[$m[1]] = $m[2] * 1024;
}
$memUsed = $mem['MemTotal'] - $mem['MemAvailable'];
$swapUsed = $mem['SwapTotal'] - $mem['SwapFree'];
$diskFree = @disk_free_space($cfg["path"]);
$diskTotal = @disk_total_space($cfg["path"]);

// Transmission
$session = tfRpc('session-stats');
$st = $session['arguments'] ?? null;

// torrent processes (the bridge, one per running torrent) and the queue manager
$procs = array();
foreach (getRunningTorrents() as $line)
{
    $rt = new RunningTorrent($line);
    $procs[] = array($rt->processId, $rt->torrentOwner, str_replace(".stat", "", $rt->statFile));
}
$qmPid = getQManagerPID();

// people logged in to the server's shell (w -h: no header)
$logins = array_filter(explode("\n", trim((string)shell_exec("w -h 2>/dev/null"))));

function whoPercent($part, $whole)
{
    return $whole > 0 ? round($part * 100 / $whole) : 0;
}

DisplayHead(_SERVERSTATS);
echo "<table width=\"740\" border=0 cellpadding=0 cellspacing=0><tr><td>";
echo displayDriveSpaceBar(getDriveSpace($cfg["path"]));
echo "</td></tr></table>";
echo "<br>";
?>
<div class="tf-run tf-run-page" id="tf-who-live">

    <div class="tf-run-pagehead">
        <div class="tf-run-kicker">Admin</div>
        <h1><?php echo _SERVERSTATS ?></h1>
        <div class="tf-run-status">Up <?php echo whoDuration($uptime) ?> &middot; load <?php echo htmlentities($load[0]) ?> &middot; updates every 5 seconds</div>
    </div>

    <div class="tf-run-section">
        <h2>Server</h2>
        <div class="tf-run-card tf-run-stats tf-run-stats-3">
            <div><span>Uptime</span><b><?php echo whoDuration($uptime) ?></b></div>
            <div><span>Load (1 / 5 / 15 min)</span><b><?php echo htmlentities($load[0]." / ".$load[1]." / ".$load[2]) ?></b></div>
            <div><span>CPU Cores</span><b><?php echo $cpus ?></b></div>
            <div><span>Memory</span><b><?php echo formatBytesToKBMGGB($memUsed) ?> <small>of <?php echo formatBytesToKBMGGB($mem['MemTotal']) ?></small></b>
                <div class="tf-run-bar tf-run-bar-small"><span style="width:<?php echo whoPercent($memUsed, $mem['MemTotal']) ?>%"></span></div></div>
            <div><span>Swap</span><b><?php echo $mem['SwapTotal'] > 0 ? formatBytesToKBMGGB($swapUsed)." <small>of ".formatBytesToKBMGGB($mem['SwapTotal'])."</small>" : "none" ?></b>
                <div class="tf-run-bar tf-run-bar-small"><span style="width:<?php echo whoPercent($swapUsed, $mem['SwapTotal']) ?>%"></span></div></div>
            <div><span>Download Folder</span><b><?php echo formatBytesToKBMGGB($diskFree) ?> <small>free</small></b>
                <div class="tf-run-bar tf-run-bar-small"><span style="width:<?php echo whoPercent($diskTotal - $diskFree, $diskTotal) ?>%"></span></div></div>
        </div>
    </div>

    <div class="tf-run-section">
        <h2>Transmission</h2>
<?php
    if ($st)
    {
?>
        <div class="tf-run-card tf-run-stats tf-run-stats-3">
            <div><span>Download Speed</span><b><?php echo whoSpeed($st['downloadSpeed']) ?></b></div>
            <div><span>Upload Speed</span><b><?php echo whoSpeed($st['uploadSpeed']) ?></b></div>
            <div><span>Torrents</span><b><?php echo $st['activeTorrentCount'] ?> <small>active of <?php echo $st['torrentCount'] ?></small></b></div>
            <div><span>Downloaded</span><b><?php echo formatBytesToKBMGGB($st['cumulative-stats']['downloadedBytes']) ?> <small>all time</small></b></div>
            <div><span>Uploaded</span><b><?php echo formatBytesToKBMGGB($st['cumulative-stats']['uploadedBytes']) ?> <small>all time</small></b></div>
            <div><span>Running For</span><b><?php echo whoDuration($st['current-stats']['secondsActive']) ?></b></div>
        </div>
<?php
    }
    else
    {
        echo "        <div class=\"tf-run-notice\">Transmission isn't running. It starts with the first torrent.</div>\n";
    }
?>
    </div>

    <div class="tf-run-section">
        <h2>Torrent Processes</h2>
        <div class="tf-run-card">
            <table class="tf-run-table">
            <tr><th>PID</th><th>Owner</th><th>Torrent</th></tr>
<?php
    foreach ($procs as $p)
    {
        echo "            <tr><td>".htmlentities($p[0])."</td><td>".htmlentities($p[1])."</td><td>".htmlentities($p[2])."</td></tr>\n";
    }
    if (count($procs) == 0)
    {
        echo "            <tr><td colspan=\"3\" class=\"tf-run-dim\">No torrents running</td></tr>\n";
    }
?>
            </table>
        </div>
        <div class="tf-run-hint tf-run-owner">Queue Manager: <?php echo $qmPid != "" ? "running (PID ".htmlentities($qmPid).")" : "off" ?></div>
    </div>

    <div class="tf-run-section">
        <h2>Logged In to the Server</h2>
<?php
    if (count($logins) > 0)
    {
        echo "        <div class=\"tf-run-card\"><table class=\"tf-run-table\">\n";
        echo "            <tr><th>User</th><th>Terminal</th><th>From</th><th>Since</th><th>Doing</th></tr>\n";
        foreach ($logins as $l)
        {
            $f = preg_split('/\s+/', trim($l), 8);
            echo "            <tr><td>".htmlentities($f[0])."</td><td>".htmlentities($f[1] ?? "")."</td><td>".htmlentities($f[2] ?? "")."</td><td>".htmlentities($f[3] ?? "")."</td><td>".htmlentities($f[7] ?? "")."</td></tr>\n";
        }
        echo "        </table></div>\n";
    }
    else
    {
        echo "        <div class=\"tf-run-hint\">No one is logged in to the server's shell.</div>\n";
    }
?>
    </div>

</div>
<script type="text/javascript">
// refresh in place (live=1: not counted as a page hit)
setInterval(function () {
    if (document.hidden) return;
    fetch("who.php?live=1", { credentials: "same-origin", cache: "no-store" })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            var fresh = new DOMParser().parseFromString(html, "text/html").getElementById("tf-who-live");
            if (fresh) document.getElementById("tf-who-live").innerHTML = fresh.innerHTML;
        })
        .catch(function () { });
}, 5000);
</script>
<?php

DisplayFoot();

?>
