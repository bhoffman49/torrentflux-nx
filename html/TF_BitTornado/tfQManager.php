<?php
// TorrentFlux queue manager - PHP port of BitTornado's tfQManager.py (which needs Python 2).
// tfpython runs this as:
//   php tfQManager.php <tfQManager.py path> <queue dir> <max server threads> <max user threads> <sleep seconds> "<launch prefix>"
// Same behaviour as the original: every <sleep> seconds, start the oldest queued torrent
// (queue/<alias>.stat.Qinfo holds its arguments) while the server-wide and per-user limits allow.
// The .py path stays on the command line so TorrentFlux's own process checks recognise this process.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

if (count($argv) < 7) { fwrite(STDERR, "usage: tfQManager <queue dir> <max server> <max user> <sleep> <launch prefix>\n"); exit(2); }
list(, , $qDir, $maxSvr, $maxUsr, $sleep, $execPath) = $argv;
$qDir = rtrim($qDir, '/') . '/';
$maxSvr = (int)$maxSvr; $maxUsr = (int)$maxUsr; $sleep = max(1, (float)$sleep);
$pidFile = $qDir . 'tfQManager.pid';
$log = @fopen($qDir . 'tfQManager.log', 'w');

function qlog($msg) { global $log; if ($log) { fwrite($log, date('Y-m-d H:i:s') . ' ' . $msg . "\n"); fflush($log); } }

// the downloader script name, e.g. btphptornado.php (last word of the launch prefix)
$parts = preg_split('/\s+/', trim($execPath));
$downloader = basename(end($parts));

// downloaders running in the background (parent pid 1), optionally only those for one user
function runningCount($downloader, $user = null)
{
    $count = 0;
    foreach (explode("\n", (string)shell_exec('ps x -o pid=,ppid=,command= -ww')) as $line) {
        $f = preg_split('/\s+/', trim($line), 3);
        if (count($f) < 3 || $f[1] !== '1') continue;
        if (strpos($f[2], $downloader) === false || strpos($f[2], 'tfQManager') !== false) continue;
        if ($user !== null && strpos($f[2], ' ' . $user . ' ') === false) continue;
        $count++;
    }
    return $count;
}

// only one queue manager at a time
if (is_file($pidFile)) {
    $old = (int)trim(@file_get_contents($pidFile));
    if ($old > 0 && $old != getmypid() && file_exists("/proc/$old")) { qlog("already running on pid $old"); exit(0); }
}
file_put_contents($pidFile, getmypid() . "\n");
qlog("started pid " . getmypid() . " maxServer=$maxSvr maxUser=$maxUsr sleep=$sleep downloader=$downloader");

while (true) {
    // oldest queued torrent first
    $queued = glob($qDir . '*.Qinfo') ?: array();
    usort($queued, function ($a, $b) { return filemtime($a) - filemtime($b); });

    foreach ($queued as $qFile) {
        if (runningCount($downloader) >= $maxSvr) break;              // server full - wait for the next round

        $statFile = dirname($qDir) . '/' . basename($qFile, '.Qinfo');  // queue/<alias>.stat.Qinfo -> <alias>.stat
        $stat = @file($statFile, FILE_IGNORE_NEW_LINES);
        $user = ($stat && isset($stat[5])) ? trim($stat[5]) : '';
        if ($user === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $user)) {
            qlog("no valid owner in $statFile - dropping " . basename($qFile));
            if ($stat) { $stat[0] = '0'; file_put_contents($statFile, implode("\n", $stat)); }
            @unlink($qFile);
            continue;
        }
        if (runningCount($downloader, $user) >= $maxUsr) { qlog("user $user at limit - skipping " . basename($qFile)); continue; }

        $args = trim((string)@file_get_contents($qFile));
        if ($args === '' || strpos($args, '|') !== false || strpos($args, ' ' . $user . ' ') === false) {
            qlog("bad command in " . basename($qFile) . " - dropping");
            @unlink($qFile);
            continue;
        }

        $stat[0] = '1';                                             // mark it running, then launch
        file_put_contents($statFile, implode("\n", $stat));
        $cmd = str_replace('TFQUSERNAME', $user, $execPath) . $args;
        qlog("starting " . basename($statFile) . " for $user");
        shell_exec($cmd);
        for ($i = 0; $i < 10 && !file_exists($statFile . '.pid'); $i++) sleep(1);   // let it write its pid file
        @unlink($qFile);
    }
    usleep((int)($sleep * 1000000));
}
