<?php
// TorrentFlux -> transmission-show shim (replaces BitTornado's btshowmetainfo.py, which needs Python 2).
// tfpython runs this as: php btshowmetainfo.php <btshowmetainfo.py path> <torrent file>
// metaInfo.php shows the output as plain text when file priorities are turned off.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

if (!isset($argv[2])) { fwrite(STDERR, "usage: btshowmetainfo <torrent file>\n"); exit(2); }
passthru('transmission-show ' . escapeshellarg($argv[2]) . ' 2>&1', $rc);
exit($rc);
