<?php
// TorrentFlux -> Transmission shim (replaces btphptornado.py).
// Args: <autoDie True|False> <shareKill> <statFile> <user> --responsefile <torrent> --max_download_rate N --max_upload_rate N ...
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
if ($argc < 5) { fwrite(STDERR, "usage: btphptornado.php autodie sharekill statfile user [--opts]\n"); exit(1); }
$autoDie = $argv[1]; $shareKill = $argv[2]; $statFile = $argv[3]; $user = $argv[4];
$opt = array();
for ($i = 5; $i < $argc - 1; $i += 2) { $opt[ltrim($argv[$i], '-')] = $argv[$i + 1]; }
$torrent = $opt['responsefile'];
// The data folder is the parent of the .torrents folder the stat file is in (<path>/.torrents/<alias>.stat);
// the ports come from installer.php's state/config.local.php
$dataPath = dirname(dirname($statFile));
$cfg = array('rpc_port' => 9091, 'peer_port' => 51413);
$local = dirname(__DIR__, 2) . '/state/config.local.php';
if (is_file($local)) include $local;
$cfgDir  = $dataPath . '/.tfdata/transmission';
$port    = (int)$cfg['rpc_port'];
$rpcUrl  = "http://127.0.0.1:$port/transmission/rpc";
$sid     = '';

function rpc($method, $args = array()) {
    global $rpcUrl, $sid;
    for ($try = 0; $try < 2; $try++) {
        $ctx = stream_context_create(array('http' => array(
            'method' => 'POST', 'ignore_errors' => true, 'timeout' => 10,
            'header' => "Content-Type: application/json\r\nX-Transmission-Session-Id: $sid\r\n",
            'content' => json_encode(array('method' => $method, 'arguments' => (object)$args)))));
        $r = @file_get_contents($rpcUrl, false, $ctx);
        $code = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $code = (int)$m[1];
                if (stripos($h, 'X-Transmission-Session-Id:') === 0) $sid = trim(substr($h, 26));
            }
        }
        if ($code == 409) continue;
        if ($r === false) return null;
        return json_decode($r, true);
    }
    return null;
}

function writeStat($lines) {
    global $statFile;
    $tmp = $statFile . '.tmp' . getmypid();
    file_put_contents($tmp, implode("\n", $lines));
    rename($tmp, $statFile);
}
function fmtRate($b) { return $b > 0 ? sprintf('%.1f kB/s', $b / 1024) : '0.0 kB/s'; }
function fmtEta($s) {
    if ($s < 0) return 'unknown';
    $m = intdiv($s, 60); $s %= 60; $h = intdiv($m, 60); $m %= 60; $d = intdiv($h, 24); $h %= 24;
    if ($d > 0) return "{$d}d {$h}h";
    if ($h > 0) return "{$h}h {$m}m";
    if ($m > 0) return "{$m}m {$s}s";
    return "{$s}s";
}
function runningFlag() {
    global $statFile;
    $c = @file_get_contents($statFile);
    return ($c === false || $c === '') ? '1' : $c[0];
}

// Search $root (up to 6 levels) for $name: a file of exactly $size bytes, or (when $size < 0) a folder.
function findExisting($root, $name, $size) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
    $it->setMaxDepth(5);
    foreach ($it as $path => $f) {
        if ($f->getFilename() !== $name) continue;
        if ($size >= 0 && $f->isFile() && $f->getSize() == $size) return $path;
        if ($size < 0 && $f->isDir()) return $path;
    }
    return null;
}

// Make sure the daemon is up (localhost only, no auth).
if (rpc('session-get') === null) {
    @mkdir($cfgDir, 0775, true);
    if (!file_exists("$cfgDir/settings.json")) {
        file_put_contents("$cfgDir/settings.json", json_encode(array(
            'rpc-bind-address' => '127.0.0.1', 'rpc-port' => $port,
            'rpc-authentication-required' => false, 'rpc-whitelist' => '127.0.0.1',
            'rpc-whitelist-enabled' => true, 'download-dir' => $dataPath,
            'umask' => 2, 'peer-port' => (int)$cfg['peer_port'], 'dht-enabled' => true, 'pex-enabled' => true,
            'lpd-enabled' => false, 'utp-enabled' => true,
        ), JSON_PRETTY_PRINT));
    }
    exec('transmission-daemon -g ' . escapeshellarg($cfgDir) . ' >/dev/null 2>&1');
    for ($i = 0; $i < 20 && rpc('session-get') === null; $i++) sleep(1);
    if (rpc('session-get') === null) {
        writeStat(array('0', '-100', 'Failed', '', '', $user, '0+0', '0', '0', $shareKill, '0', '0', '0', 'Could not start transmission-daemon'));
        exit(1);
    }
}

// Per-user download dir, like the original (cfg path . owner).
$dl = $dataPath . '/' . $user;
@mkdir($dl, 0775, true);

// Added paused: first make sure Transmission looks where the data already is (see findExisting)
$add = rpc('torrent-add', array('filename' => $torrent, 'download-dir' => $dl, 'paused' => true));
$t = $add['arguments']['torrent-added'] ?? ($add['arguments']['torrent-duplicate'] ?? null);
if (!$t) {
    writeStat(array('0', '-100', 'Failed', '', '', $user, '0+0', '0', '0', $shareKill, '0', '0', '0', 'Transmission rejected torrent: ' . ($add['result'] ?? 'no response')));
    exit(1);
}
$id = $t['id'];

// A torrent made with Make Torrent from a file or folder inside a sub-folder (e.g. admin/some-folder/file.iso)
// has its data there, not at <user folder>/<name>. Find it so the torrent seeds instead of downloading again.
$info = rpc('torrent-get', array('ids' => array($id), 'fields' => array('name', 'totalSize', 'files', 'downloadDir')));
$tt = $info['arguments']['torrents'][0] ?? null;
if ($tt && !file_exists($tt['downloadDir'] . '/' . $tt['name'])) {
    $found = findExisting($dl, $tt['name'], count($tt['files']) == 1 ? (int)$tt['totalSize'] : -1);
    if ($found !== null) {
        rpc('torrent-set-location', array('ids' => array($id), 'location' => dirname($found), 'move' => false));
        rpc('torrent-verify', array('ids' => array($id)));
    }
}
// Files left unticked in Run Torrent arrive as --priority 1,-1,... (-1 = skip that file)
if (!empty($opt['priority'])) {
    $skip = array_keys(array_filter(explode(',', $opt['priority']), function ($p) { return trim($p) === '-1'; }));
    if ($skip) rpc('torrent-set', array('ids' => array($id), 'files-unwanted' => $skip));
}
rpc('torrent-start', array('ids' => array($id)));
$limits = array();
if (!empty($opt['max_download_rate']) && $opt['max_download_rate'] > 0) { $limits['downloadLimit'] = (int)$opt['max_download_rate']; $limits['downloadLimited'] = true; }
if (!empty($opt['max_upload_rate'])   && $opt['max_upload_rate']   > 0) { $limits['uploadLimit']   = (int)$opt['max_upload_rate'];   $limits['uploadLimited']   = true; }
if ($limits) rpc('torrent-set', array_merge(array('ids' => array($id)), $limits));

file_put_contents($statFile . '.pid', getmypid() . "\n");
$fields = array('id','percentDone','eta','rateDownload','rateUpload','peersSendingToUs','peersConnected','uploadedEver','downloadedEver','sizeWhenDone','totalSize','leftUntilDone','error','errorString');
$lastDown = 0;

while (true) {
    sleep(5);
    $flag = runningFlag();
    $r = rpc('torrent-get', array('ids' => array($id), 'fields' => $fields));
    $tt = $r['arguments']['torrents'][0] ?? null;

    if ($flag === '0' || !$tt) {
        // Stopped from the UI (it already wrote the stop state) or torrent vanished.
        rpc('torrent-remove', array('ids' => array($id), 'delete-local-data' => false));
        @unlink($statFile . '.pid');
        exit(0);
    }

    $done  = ($tt['leftUntilDone'] == 0);
    $pct   = $done ? '100' : sprintf('%.1f', $tt['percentDone'] * 100);
    $down  = $tt['downloadedEver']; $up = $tt['uploadedEver']; $size = $tt['totalSize'];
    $base  = $down > 0 ? $down : $size;
    $share = ($up > 0 && $base > 0) ? sprintf('%.1f', $up / $base * 100) : '0.0';
    $eta   = $done ? 'complete!' : fmtEta($tt['eta']);
    $errs  = array();
    if (!empty($tt['errorString'])) $errs[] = $tt['errorString'];

    $stop = false;
    if ($done && $autoDie === 'True') $stop = true;
    if ($done && $shareKill !== '0' && $shareKill !== '' && (float)$share >= (float)$shareKill) $stop = true;

    if ($stop) {
        writeStat(array('0', '100', 'Download Succeeded!', '', '', $user, '0+0', '0', $share, $shareKill, (string)$up, (string)$down, (string)$size));
        rpc('torrent-remove', array('ids' => array($id), 'delete-local-data' => false));
        @unlink($statFile . '.pid');
        exit(0);
    }

    writeStat(array_merge(array(
        '1', $pct, $eta, fmtRate($tt['rateDownload']), fmtRate($tt['rateUpload']), $user,
        $tt['peersSendingToUs'] . '+0.000', (string)$tt['peersConnected'], $share, $shareKill,
        (string)$up, (string)$down, (string)$size), $errs));
}
