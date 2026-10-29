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

// Make Torrent: opened from the Directory list in the dialog over the page (rundialog.js), or as a popup
// window. Creates a .torrent from a file or folder with transmission-create, through the
// TF_BitTornado/btmakemetafile.php bridge ($cfg["btmakemetafile"], run by tfpython):
//   btmakemetafile <tracker> <file|dir> [--comment c] [--piece_size_pow2 n] [--announce_list a,b] [--private 1] --target t
// The new torrent is added to the list (an upload record makes the user its owner); a torrent file
// that already exists is never replaced (it may belong to a running torrent).

    include_once("config.php");
    include_once("functions.php");
    require_once("torrentview.php");

    // Variable information
    $tpath    = $cfg["torrent_file_path"];
    $tfile    = SecurityClean(getRequestVar('torrent'));    // a file name only, never a path
    $file     = getRequestVar('path');
    $torrent  = cleanFileName(StripFolders( trim($file) )) . ".torrent";
    // suggest a name no other torrent uses (name-2.torrent, name-3.torrent, ...)
    $tbase    = substr( $torrent, 0, -8 );
    for( $n = 2; file_exists( $tpath . $torrent ); $n++ )
    {
        $torrent = $tbase . "-" . $n . ".torrent";
    }
    $announce = trim( getRequestVar('announce') );
    $ancelist = trim( getRequestVar('announcelist') );
    $comment  = getRequestVar('comments');
    $peice    = getRequestVar('piecesize');
    $alert    = ( getRequestVar('alert') ) ? true : false;
    $private  = ( getRequestVar('Private') == "Private" ) ? true : false;

    // the outcome shown above the form: array(ok|error, html)
    $result = null;

    // Let's create the torrent -- but never over an existing torrent's file: it may be running
    if( $announce != "" && @file_exists( $tpath . $tfile ) )
    {
        $result = array( "error", "A torrent named <b>" . htmlentities( $tfile, ENT_QUOTES ) . "</b> already exists, so it was left alone. "
                                . "The name below has been changed to a free one: press Create again to use it." );
        $tfile = "";
    }
    elseif( $announce != "" && $tfile != "" )
    {
        // Create maketorrent directory if it doesn't exist
        if( !is_dir( $tpath ) )
        {
            @mkdir( $tpath );
        }

        // This is the command to execute
        $app = "nohup " . $cfg["pythonCmd"] . " -OO " . $cfg["btmakemetafile"] . " " . escapeshellarg( html_entity_decode( $announce, ENT_QUOTES ) ) . " " . escapeshellarg( $cfg['path'] . $file ) . " ";

        // Is there comments to add?
        if( !empty( $comment ) )
        {
            $app .= "--comment " . escapeshellarg( html_entity_decode( $comment, ENT_QUOTES ) ) . " ";
        }

        // Set the piece size
        if( !empty( $peice ) )
        {
            $app .= "--piece_size_pow2 " . escapeshellarg( $peice ) . " ";
        }

        // backup trackers: one per line (or the old url,url|url form), the main tracker is added by the bridge
        if( $ancelist != "" )
        {
            $app .= "--announce_list " . escapeshellarg( preg_replace( '/\s*[\r\n]+\s*/', ',', html_entity_decode( $ancelist, ENT_QUOTES ) ) ) . " ";
        }

        // private torrent: only its trackers find peers (no DHT or peer exchange)
        if( $private )
        {
            $app .= "--private 1 ";
        }

        // Set the target torrent fiel
        $app .= "--target " . escapeshellarg( $tpath . $tfile );

        // Set to never timeout for large torrents
        set_time_limit( 0 );

        // Let's see how long this takes...
        $time_start = microtime( true );

        // Execute the command -- w00t!
        exec( $app );

        // We want to check to make sure the file was successful
        $raw = @file_get_contents( $tpath . $tfile );
        if( preg_match( "/6:pieces([^:]+):/i", $raw ) )
        {
            // Make an entry for the owner
            AuditAction($cfg["constants"]["file_upload"], $tfile);

            $diff = duration( microtime( true ) - $time_start );
            $result = array( "ok", "<b>Creation completed!</b> Time taken: " . ( $diff != "" ? $diff : "under a second" ) . ".<br>"
                                 . "The new torrent has been added to your list. "
                                 . "<a href=\"maketorrent.php?download=" . urlencode( $tfile ) . "\">Download the .torrent file</a>" );
        }
        else
        {
            // Something went wrong, clean up
            if( @file_exists( $tpath . $tfile ) )
            {
                @unlink( $tpath . $tfile );
            }
            $result = array( "error", "<b>Creation failed!</b> An error occurred while trying to create the torrent." );
        }
    }

    // This is the torrent download prompt
    if( isset( $_REQUEST["download"] ) )
    {
        $tfile = SecurityClean(getRequestVar("download"));

        // Does the file exist?
        if ($tfile != "" && file_exists($tpath . $tfile))
        {
            // Prompt the user to download the new torrent file.
            header( "Content-type: application/octet-stream" );
            header( "Content-disposition: attachment; filename=\"" . $tfile . "\"" );
            header( "Content-transfer-encoding: binary");
            header( "Content-length: " . @filesize( $tpath . $tfile ) );

            // Send the torrent file
            $fp = @fopen( $tpath . $tfile, "r" );
            @fpassthru( $fp );
            @fclose( $fp );

            AuditAction($cfg["constants"]["fm_download"], $tfile);
        }
        else
        {
            AuditAction($cfg["constants"]["error"], "File Not found for download: ".$cfg['user']." tried to download ".$tfile);
        }
        exit();
    }

    // Strip the folders from the path
    function StripFolders( $path )
    {
        $pos = strrpos( $path, "/" ) + 1;
        $path = substr( $path, $pos );
        return $path;
    }

    // Convert a timestamp to a duration string
    function duration( $timestamp )
    {

        $years = floor( $timestamp / ( 60 * 60 * 24 * 365 ) );
        $timestamp %= 60 * 60 * 24 * 365;

        $weeks = floor( $timestamp / ( 60 * 60 * 24 * 7 ) );
        $timestamp %= 60 * 60 * 24 * 7;

        $days = floor( $timestamp / ( 60 * 60 * 24 ) );
        $timestamp %= 60 * 60 * 24;

        $hrs = floor( $timestamp / ( 60 * 60 ) );
        $timestamp %= 60 * 60;

        $mins = floor( $timestamp / 60 );
        $secs = $timestamp % 60;

        $str = "";

        if( $years >= 1 )
            $str .= "{$years} years ";
        if( $weeks >= 1 )
            $str .= "{$weeks} weeks ";
        if( $days >= 1 )
            $str .= "{$days} days ";
        if( $hrs >= 1 )
            $str .= "{$hrs} hours ";
        if( $mins >= 1 )
            $str .= "{$mins} minutes ";
        if( $secs >= 1 )
            $str.="{$secs} seconds ";

        return trim( $str );
    }

    $pieces = array( "0" => "Automatic", "18" => "256 KB", "19" => "512 KB", "20" => "1 MB", "21" => "2 MB", "22" => "4 MB", "23" => "8 MB", "24" => "16 MB" );
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="<?php echo _CHARSET ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $cfg["pagetitle"]; ?> - Make Torrent</title>
    <link rel="icon" href="<?php echo ThemeImages() ?>favicon.ico" type="image/x-icon">
    <link rel="StyleSheet" href="themes/<?php echo $cfg["theme"] ?>/style.css?v=<?php echo @filemtime("themes/".$cfg["theme"]."/style.css") ?>" type="text/css">
<?php tfDialogPageScript(); ?>
<script type="text/javascript">
// the trackers last used for Make Torrent, kept in this browser only
var TRACKERS_KEY = "tf-make-trackers";

function showStatus(cls, html)
{
    var n = document.getElementById("status");
    n.className = cls;
    n.innerHTML = html;
    if (inDialog() && window.parent.tfRunFit) window.parent.tfRunFit();
}

function doSubmit()
{
    var f = document.maketorrent, problems = [];
    [f.torrent, f.announce].forEach(function (el) { el.classList.remove("tf-run-invalid"); });
    if (!/\.torrent$/i.test(f.torrent.value.trim()))
    {
        problems.push("The torrent name must end in <b>.torrent</b>.");
        f.torrent.classList.add("tf-run-invalid");
    }
    if (!/^(udp|https?):\/\/\S+$/i.test(f.announce.value.trim()))
    {
        problems.push(f.announce.value.trim() == "" ? "Enter a <b>Tracker URL</b> (the grey text is only an example)."
                                                    : "The <b>Tracker URL</b> must start with udp://, http:// or https://");
        f.announce.classList.add("tf-run-invalid");
    }
    if (problems.length)
    {
        showStatus("tf-run-notice tf-run-notice-error", "<b>Please check:</b> " + problems.join(" "));
        (f.torrent.classList.contains("tf-run-invalid") ? f.torrent : f.announce).focus();
        return false;
    }
    if (f.tsubmit.disabled) return false;
    try { localStorage.setItem(TRACKERS_KEY, JSON.stringify({ announce: f.announce.value.trim(), list: f.announcelist.value.trim() })); } catch (e) { }
    f.tsubmit.disabled = true;
    f.tsubmit.value = "Creating...";
    showStatus("tf-run-notice", "<b>Creating the torrent...</b> Large files and folders can take a while; keep this open until it finishes.");
    return true;
}

// fill in the trackers used last time when the form starts empty
document.addEventListener("DOMContentLoaded", function () {
    var f = document.maketorrent, saved = null;
    try { saved = JSON.parse(localStorage.getItem(TRACKERS_KEY) || "null"); } catch (e) { }
    if (!saved) return;
    if (f.announce.value == "" && saved.announce) f.announce.value = saved.announce;
    if (f.announcelist.value == "" && saved.list) f.announcelist.value = saved.list;
    if (saved.announce && inDialog() && window.parent.tfRunFit) window.parent.tfRunFit();
});
</script>
</head>

<body class="tf-run">
<form class="tf-run-form" action="<?php echo htmlentities($_SERVER['REQUEST_URI'], ENT_QUOTES); ?>" method="post" name="maketorrent" onsubmit="return doSubmit()">

<div class="tf-run-head">
    <div>
        <div class="tf-run-kicker">Make Torrent</div>
        <h1><?php echo htmlentities( StripFolders( html_entity_decode( $file, ENT_QUOTES ) ), ENT_QUOTES ); ?></h1>
    </div>
    <button type="button" class="tf-run-x" title="Close" onclick="CloseRun()">&#x2715;</button>
</div>

<div class="tf-run-body">
<?php
    if( $result )
    {
        echo "    <div id=\"status\" class=\"tf-run-notice".( $result[0] == "error" ? " tf-run-notice-error" : " tf-run-notice-ok" )."\">".$result[1]."</div>\n";
    }
    else
    {
        echo "    <div id=\"status\"></div>\n";
    }
?>

    <div class="tf-run-grid tf-run-grid-form">
        <div class="tf-run-field tf-run-wide">
            <label for="torrent">Torrent Name</label>
            <input type="text" id="torrent" name="torrent" value="<?php echo $torrent; ?>">
        </div>
        <div class="tf-run-field tf-run-wide">
            <label for="announce">Tracker URL</label>
            <input type="text" id="announce" name="announce" value="<?php echo $announce; ?>" placeholder="e.g. udp://tracker.opentrackr.org:1337/announce">
            <div class="tf-run-hint">The main tracker for this torrent. The trackers you use are remembered in this browser for next time.</div>
        </div>
        <div class="tf-run-field tf-run-wide">
            <label for="announcelist">Backup Trackers</label>
            <textarea id="announcelist" name="announcelist" rows="3" placeholder="e.g. one tracker URL per line (optional)"><?php echo $ancelist; ?></textarea>
        </div>
        <div class="tf-run-field">
            <label for="piecesize">Piece Size</label>
            <select id="piecesize" name="piecesize">
<?php
    foreach( $pieces as $value => $label )
    {
        echo "                <option value=\"".$value."\"".( (string)$peice === (string)$value ? " selected" : "" ).">".$label."</option>\n";
    }
?>
            </select>
            <div class="tf-run-hint">Automatic suits almost everything</div>
        </div>
        <div class="tf-run-field">
            <label for="Private">Private Torrent</label>
            <select id="Private" name="Private">
                <option value="NotPrivate">No</option>
                <option value="Private"<?php echo $private ? " selected" : ""; ?>>Yes</option>
            </select>
            <div class="tf-run-hint">Yes: peers only from these trackers (no DHT)</div>
        </div>
        <div class="tf-run-field tf-run-wide">
            <label for="comments">Comment</label>
            <textarea id="comments" name="comments" rows="3" placeholder="Optional"><?php echo $comment; ?></textarea>
        </div>
    </div>

    <div class="tf-run-hint tf-run-owner">From: <?php echo htmlentities( html_entity_decode( $file, ENT_QUOTES ), ENT_QUOTES ); ?></div>
</div>

<div class="tf-run-foot">
    <label class="tf-run-summary tf-run-check-inline"><input type="checkbox" name="alert" value="AlertMe"<?php echo $alert ? " checked" : ""; ?>> Notify me when it's done</label>
    <input type="button" class="tf-run-secondary" value="Close" onclick="CloseRun()">
    <input type="submit" id="tsubmit" name="tsubmit" value="Create">
</div>

</form>
<?php
    if( $result && $alert )
    {
        echo "<script type=\"text/javascript\">alert(".json_encode( $result[0] == "ok" ? "Creation of torrent completed!" : "Creation of torrent failed!" ).");</script>\n";
    }
    if( $result && $result[0] == "ok" )
    {
        // refresh the torrent list behind the dialog (main page live updates)
        echo "<script type=\"text/javascript\">if (inDialog() && window.parent.tfLiveUpdate) window.parent.tfLiveUpdate();</script>\n";
    }
?>
</body>
</html>
