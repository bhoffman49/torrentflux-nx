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

// File upload for the Directory list (dir.php, upload.js). The browser sends a file in pieces of a few MB
// as raw request bodies, so files of any size get past PHP's upload limits and the proxy's timeouts:
//   POST upload.php?op=start&dir=..&name=..&size=..   -> {id, name}   checks permission and free space,
//                                                                       picks a name no file uses yet
//   POST upload.php?op=chunk&id=..&offset=..  (piece)  -> {received}   appends the piece
//   POST upload.php?op=finish&id=..                    -> {name}       moves the file into place, logs it
//   POST upload.php?op=cancel&id=..
// A user uploads into their own folder, an admin into any folder. A file being uploaded is a hidden
// ".<name>.<id>.tfpart" file in the target folder (dir.php doesn't list dot files); abandoned ones are
// removed after a day. Errors come back as {error} with an HTTP error status.

include_once("config.php");
include_once("functions.php");

header("Content-Type: application/json");

function upFail($msg, $code = 400, $extra = array())
{
    http_response_code($code);
    echo json_encode(array_merge(array("error" => $msg), $extra));
    exit();
}

function upDone($data)
{
    echo json_encode($data);
    exit();
}

// the folder to upload into (relative to the download path), kept inside it; users only their own
function upFolder($dir)
{
    global $cfg;
    $dir = trim(str_replace("\\", "/", $dir), "/");
    if (strpos($dir, "..") !== false) upFail("That folder isn't allowed.");
    $user = strtolower($cfg["user"]);
    if (!IsAdmin() && strtolower($dir) != $user && strpos(strtolower($dir), $user."/") !== 0)
    {
        AuditAction($cfg["constants"]["error"], "ILLEGAL UPLOAD: ".$cfg['user']." tried to upload into ".$dir);
        upFail("You can only upload into your own folder.", 403);
    }
    $base = realpath($cfg["path"]);
    $real = realpath($cfg["path"].$dir);
    if ($base === false || $real === false || !is_dir($real) || ($real != $base && strpos($real, $base."/") !== 0))
    {
        upFail("That folder doesn't exist.", 404);
    }
    return $real;
}

// a plain file name: no folders, control characters or characters other systems can't store
function upName($name)
{
    $name = basename(str_replace("\\", "/", $name));
    $name = preg_replace('/[\x00-\x1f\x7f<>:"|?*]/', '', $name);
    $name = trim($name, " .");
    if ($name == "") upFail("That file name can't be used.");
    return mb_substr($name, 0, 200);
}

// name.iso, or name (2).iso, name (3).iso ... if taken: an upload never replaces a file
function upFreeName($folder, $name)
{
    $info = pathinfo($name);
    $ext = isset($info['extension']) ? ".".$info['extension'] : "";
    $try = $name;
    for ($n = 2; file_exists($folder."/".$try); $n++)
    {
        $try = $info['filename']." (".$n.")".$ext;
    }
    return $try;
}

function upState($id)
{
    if (!is_string($id) || !isset($_SESSION['tf_up'][$id])) upFail("That upload isn't known (it may have been cancelled).", 404);
    return $_SESSION['tf_up'][$id];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') != 'POST') upFail("Uploads must be sent with POST.", 405);

$op = $_GET['op'] ?? '';
$id = $_GET['id'] ?? '';

switch ($op)
{
    case "start":
        $folder = upFolder($_GET['dir'] ?? '');
        $size = filter_var($_GET['size'] ?? '', FILTER_VALIDATE_INT, array("options" => array("min_range" => 0)));
        if ($size === false) upFail("The file size is missing.");

        // keep 512 MB spare so an upload can't fill the disk the downloads need
        $free = @disk_free_space($folder);
        if ($free !== false && $size > $free - 512 * 1024 * 1024)
        {
            upFail("Not enough free space: the file needs ".formatBytesToKBMGGB($size).", ".formatBytesToKBMGGB(max(0, $free))." is free.", 507);
        }

        // partial uploads abandoned more than a day ago
        foreach (glob($folder."/.*.tfpart") as $old)
        {
            if (@filemtime($old) < time() - 86400) @unlink($old);
        }

        $name = upFreeName($folder, upName($_GET['name'] ?? ''));
        $id = bin2hex(random_bytes(8));
        $part = $folder."/.".$name.".".$id.".tfpart";
        if (@file_put_contents($part, "") === false) upFail("The server can't write to that folder.", 500);

        $_SESSION['tf_up'][$id] = array('folder' => $folder, 'name' => $name, 'size' => $size, 'part' => $part);
        upDone(array("id" => $id, "name" => $name));

    case "chunk":
        $u = upState($id);
        session_write_close();      // don't hold the session (and the user's other pages) while the piece is written

        clearstatcache();
        $have = @filesize($u['part']);
        if ($have === false) upFail("The partial file is gone.", 410);
        $offset = filter_var($_GET['offset'] ?? '', FILTER_VALIDATE_INT);
        if ($offset !== $have) upFail("Piece out of order.", 409, array("expected" => $have));

        $in = fopen("php://input", "rb");
        $out = fopen($u['part'], "ab");
        $len = stream_copy_to_stream($in, $out, $u['size'] - $have + 1);
        fclose($in);
        fclose($out);
        if ($have + $len > $u['size'])
        {
            @unlink($u['part']);
            upFail("More data arrived than the file's size.", 400);
        }
        upDone(array("received" => $have + $len));

    case "finish":
        $u = upState($id);
        clearstatcache();
        if (@filesize($u['part']) !== $u['size']) upFail("The upload isn't complete.", 409, array("expected" => @filesize($u['part'])));

        $name = upFreeName($u['folder'], $u['name']);   // in case a file of that name appeared meanwhile
        if (!@rename($u['part'], $u['folder']."/".$name)) upFail("The server couldn't save the file.", 500);
        unset($_SESSION['tf_up'][$id]);

        $rel = ltrim(substr($u['folder'], strlen(realpath($cfg["path"]))), "/");
        AuditAction($cfg["constants"]["fm_upload"], ($rel != "" ? $rel."/" : "").$name." (".formatBytesToKBMGGB($u['size']).")");
        upDone(array("name" => $name));

    case "cancel":
        if (isset($_SESSION['tf_up'][$id]))
        {
            @unlink($_SESSION['tf_up'][$id]['part']);
            unset($_SESSION['tf_up'][$id]);
        }
        upDone(array("cancelled" => true));

    default:
        upFail("Unknown request.");
}

?>
