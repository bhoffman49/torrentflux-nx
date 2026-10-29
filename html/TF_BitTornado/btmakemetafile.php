<?php
// TorrentFlux -> transmission-create shim (replaces BitTornado's btmakemetafile.py, which needs Python 2).
// tfpython runs this as: php btmakemetafile.php <btmakemetafile.py path> <trackerurl> <file|dir> [--option value ...]
// Options used by maketorrent.php: --comment, --piece_size_pow2, --announce_list, --private 1, --target.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$args = array_slice($argv, 2);              // drop the script name and the .py path tfpython passes along
if (count($args) < 2) { fwrite(STDERR, "usage: btmakemetafile <trackerurl> <file> [--option value ...]\n"); exit(2); }
$announce = array_shift($args);
$source   = array_shift($args);
$opt = array();
for ($i = 0; $i + 1 < count($args); $i += 2) { $opt[ltrim($args[$i], '-')] = $args[$i + 1]; }

$target = isset($opt['target']) ? $opt['target'] : rtrim($source, '/') . '.torrent';

// trackers: the main one first, then the announce list ("url,url|url": tiers split by |, urls by ,)
$trackers = array($announce);
if (!empty($opt['announce_list'])) {
    foreach (preg_split('/[|,]/', $opt['announce_list']) as $t) {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $trackers)) $trackers[] = $t;
    }
}

$cmd = 'transmission-create -o ' . escapeshellarg($target);
foreach ($trackers as $t) $cmd .= ' -t ' . escapeshellarg($t);
if (!empty($opt['comment'])) $cmd .= ' -c ' . escapeshellarg($opt['comment']);
if (!empty($opt['private'])) $cmd .= ' -p';  // private torrent: peers only from its trackers
// BitTornado takes the piece size as a power of two in bytes; transmission-create wants KiB
if (!empty($opt['piece_size_pow2']) && (int)$opt['piece_size_pow2'] >= 14 && (int)$opt['piece_size_pow2'] <= 26) {
    $cmd .= ' -s ' . (1 << ((int)$opt['piece_size_pow2'] - 10));
}
$cmd .= ' ' . escapeshellarg($source) . ' 2>&1';

@unlink($target);                           // transmission-create will not overwrite an existing file
exec($cmd, $out, $rc);
echo implode("\n", $out), "\n";
exit($rc);
