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

// The torrent details card and file list shared by Run Torrent (startpop.php) and Torrent Details
// (details.php), plus the Transmission calls that apply a file selection to a running torrent.
// Styles: the "Run Torrent dialog" block (tf-run-*) at the end of each theme's style.css.

require_once("BDecode.php");

// Transmission RPC on the local daemon (as in TF_BitTornado/btphptornado.php); null when it is not running
function tfRpc($method, $args = array())
{
    static $sid = "";
    for ($try = 0; $try < 2; $try++)
    {
        $ctx = stream_context_create(array('http' => array(
            'method' => 'POST', 'ignore_errors' => true, 'timeout' => 10,
            'header' => "Content-Type: application/json\r\nX-Transmission-Session-Id: $sid\r\n",
            'content' => json_encode(array('method' => $method, 'arguments' => (object)$args)))));
        $r = @file_get_contents("http://127.0.0.1:".(int)$GLOBALS["cfg"]["rpc_port"]."/transmission/rpc", false, $ctx);
        $code = 0;
        foreach ((isset($http_response_header) ? $http_response_header : array()) as $h)
        {
            if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $code = (int)$m[1];
            if (stripos($h, 'X-Transmission-Session-Id:') === 0) $sid = trim(substr($h, 26));
        }
        if ($code == 409) continue;
        return ($r === false) ? null : json_decode($r, true);
    }
    return null;
}

// info hash of a .torrent file, which is how Transmission identifies it
function tfInfoHash($torrentFile)
{
    $out = array();
    exec("transmission-show ".escapeshellarg($torrentFile)." 2>/dev/null", $out);
    foreach ($out as $line)
    {
        if (preg_match('/^\s*Hash(?: v1)?:\s*([0-9a-f]{40})\s*$/i', $line, $m)) return strtolower($m[1]);
    }
    return "";
}

// everything the views need from a .torrent in the torrent folder
function tfLoadTorrent($torrent)
{
    global $cfg;
    $t = array('torrent' => $torrent, 'path' => $cfg["torrent_file_path"].$torrent);
    $t['meta'] = BDecode(file_get_contents($t['path']));
    $info = $t['meta']['info'];
    $t['name'] = $info['name'];

    // file index => array(path, size); index order is what the .prio file and Transmission use
    $t['files'] = array();
    if (array_key_exists('files', $info))
    {
        foreach ($info['files'] as $i => $f)
        {
            $t['files'][$i] = array(implode('/', $f['path']), $f['length']);
        }
    }
    else
    {
        $t['files'][0] = array($info['name'], $info['length']);
    }
    $t['multi'] = count($t['files']) > 1;
    $t['size'] = 0;
    foreach ($t['files'] as $f)
    {
        $t['size'] += $f[1];
    }

    // files skipped last time (-1 in the .prio file)
    $t['skip'] = array();
    $prioFile = $cfg["torrent_file_path"].getAliasName($torrent).".prio";
    if (is_readable($prioFile))
    {
        foreach (array_slice(explode(',', file_get_contents($prioFile)), 1) as $i => $p)
        {
            if (trim($p) == "-1") $t['skip'][$i] = true;
        }
    }

    $t['trackers'] = 0;
    if (array_key_exists('announce-list', $t['meta']))
    {
        foreach ($t['meta']['announce-list'] as $tier)
        {
            $t['trackers'] += count($tier);
        }
    }
    return $t;
}

// the torrent as Transmission has it right now, or null when it isn't loaded (not running)
function tfLiveTorrent($t)
{
    $hash = tfInfoHash($t['path']);
    if ($hash == "") return null;
    $r = tfRpc('torrent-get', array('ids' => array($hash), 'fields' => array('percentDone', 'status', 'fileStats')));
    return $r['arguments']['torrents'][0] ?? null;
}

// make Transmission download only the files with these indexes; false when the torrent isn't running
function tfApplyFileChoice($t, $wanted)
{
    $hash = tfInfoHash($t['path']);
    if ($hash == "") return false;
    $r = tfRpc('torrent-get', array('ids' => array($hash), 'fields' => array('id')));
    if (empty($r['arguments']['torrents'])) return false;
    $want = array(); $skip = array();
    foreach (array_keys($t['files']) as $i)
    {
        if (in_array($i, $wanted)) $want[] = $i; else $skip[] = $i;
    }
    $args = array('ids' => array($hash));
    if ($want) $args['files-wanted'] = $want;
    if ($skip) $args['files-unwanted'] = $skip;
    $r = tfRpc('torrent-set', $args);
    return ($r['result'] ?? "") == "success";
}

function tfFileIcon($name)
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $icon = ThemeImages()."files/".($ext != "" ? $ext : "unknown").".png";
    return file_exists("./".$icon) ? $icon : ThemeImages()."files/unknown.png";
}

function tfShowDetailsCard($t)
{
    $meta = $t['meta'];
    echo "<dl class=\"tf-run-meta tf-run-card\">\n";
    echo "<dt>Size</dt><dd>".formatBytesToKBMGGB($t['size'])." in ".count($t['files'])." file".($t['multi'] ? "s" : "")."</dd>\n";
    echo "<dt>Tracker</dt><dd>".htmlentities($meta['announce'] ?? "none (DHT only)", ENT_QUOTES).(($t['trackers'] > 1) ? " <span class=\"tf-run-dim\">+ ".($t['trackers'] - 1)." more</span>" : "")."</dd>\n";
    if (!empty($meta['creation date']))
    {
        echo "<dt>Created</dt><dd>".date("M j, Y, g:i a", $meta['creation date'])."</dd>\n";
    }
    if (!empty($meta['comment']))
    {
        $comment = htmlentities($meta['comment'], ENT_QUOTES);
        if (preg_match('#^https?://\S+$#', $meta['comment']))
        {
            $comment = "<a href=\"".$comment."\" target=\"_blank\" rel=\"noopener noreferrer\">".$comment."</a>";
        }
        echo "<dt>Comment</dt><dd>".$comment."</dd>\n";
    }
    echo "<dt>Piece Size</dt><dd>".formatBytesToKBMGGB($t['meta']['info']['piece length'])."</dd>\n";
    echo "<dt>File</dt><dd class=\"tf-run-dim\">".htmlentities($t['torrent'], ENT_QUOTES)."</dd>\n";
    echo "</dl>\n";
}

// $editable: tick boxes (files[] + count/filecount for setpriority.php); $live: tfLiveTorrent() for per-file progress
function tfShowFileList($t, $editable, $live = null)
{
    $editable = $editable && $t['multi'];
    echo "<div class=\"tf-run-card tf-run-files\">\n";
    if ($editable)
    {
        echo "<label class=\"tf-run-files-head\"><input type=\"checkbox\" id=\"allfiles\" onclick=\"SelectAll(this.checked)\"> Select all</label>\n";
        echo "<input type=\"hidden\" name=\"count\" value=\"".count($t['files'])."\">\n";
        echo "<input type=\"hidden\" name=\"filecount\" value=\"".count($t['files'])."\">\n";
    }
    foreach ($t['files'] as $i => $f)
    {
        $slash = strrpos($f[0], '/');
        $dir = ($slash === false) ? "" : substr($f[0], 0, $slash + 1);
        $base = ($slash === false) ? $f[0] : substr($f[0], $slash + 1);
        $stat = $live['fileStats'][$i] ?? null;
        $wanted = $stat ? $stat['wanted'] : !isset($t['skip'][$i]);

        echo "<label class=\"tf-run-file".($wanted ? "" : " off")."\">";
        if ($editable)
        {
            echo "<input type=\"checkbox\" name=\"files[]\" value=\"".$i."\" data-size=\"".$f[1]."\"".($wanted ? " checked" : "")." onclick=\"UpdateFiles()\">";
        }
        echo "<img src=\"".tfFileIcon($base)."\" width=\"16\" height=\"16\" alt=\"\">";
        echo "<span class=\"tf-run-name\">".($dir != "" ? "<span class=\"tf-run-dim\">".htmlentities($dir, ENT_QUOTES)."</span>" : "").htmlentities($base, ENT_QUOTES)."</span>";
        if ($stat)
        {
            $pct = $f[1] > 0 ? floor($stat['bytesCompleted'] * 100 / $f[1]) : 100;
            echo "<span class=\"tf-run-progress\" title=\"".$pct."% downloaded\"><span style=\"width:".$pct."%\"></span></span>";
        }
        echo "<span class=\"tf-run-size\">".formatBytesToKBMGGB($f[1])."</span></label>\n";
    }
    echo "</div>\n";
}

// JavaScript every page shown in the dialog needs (startpop.php, downloaddetails.php): close it,
// report its natural height so the dialog can fit it, close on Escape
function tfDialogPageScript()
{
?>
<script type="text/javascript">
function inDialog()
{
    return window.parent !== window && typeof window.parent.tfRunClose == "function";
}

function CloseRun()
{
    if (inDialog()) window.parent.tfRunClose();
    else window.close();
}

function tfRunHeight()
{
    var b = document.querySelector(".tf-run-body");
    return document.querySelector(".tf-run-head").offsetHeight + b.scrollHeight + document.querySelector(".tf-run-foot").offsetHeight;
}

document.addEventListener("keydown", function (e) { if (e.key == "Escape") CloseRun(); });
</script>
<?php
}

// JavaScript for an editable file list: select all, dimmed unticked rows, "3 of 5 files" summary in #summary
function tfFileListScript()
{
?>
<script type="text/javascript">
function selectedFiles()
{
    return Array.prototype.filter.call(document.querySelectorAll("input[name='files[]']"), function (c) { return c.checked; });
}

function formatSize(b)
{
    var u = ["B", "KB", "MB", "GB", "TB"], i = 0;
    while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
    return (i ? b.toFixed(b < 10 ? 2 : 1) : b) + " " + u[i];
}

function UpdateFiles()
{
    var all = document.querySelectorAll("input[name='files[]']"), sel = selectedFiles(), size = 0;
    Array.prototype.forEach.call(all, function (c) {
        c.closest(".tf-run-file").classList.toggle("off", !c.checked);
    });
    sel.forEach(function (c) { size += Number(c.getAttribute("data-size")); });
    var box = document.getElementById("allfiles");
    if (box)
    {
        box.checked = sel.length == all.length;
        box.indeterminate = sel.length > 0 && sel.length < all.length;
        document.getElementById("summary").textContent = sel.length + " of " + all.length + " files · " + formatSize(size);
    }
}

function SelectAll(on)
{
    Array.prototype.forEach.call(document.querySelectorAll("input[name='files[]']"), function (c) { c.checked = on; });
    UpdateFiles();
}
</script>
<?php
}

?>
