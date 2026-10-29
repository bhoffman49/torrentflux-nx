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

// Download Details: a torrent's live status, opened in the dialog over the main page (rundialog.js)
// or as a popup window. It refreshes itself every 5 seconds by swapping #tf-dd-live in place.

include_once("config.php");
include_once("functions.php");
include_once("AliasFile.php");
require_once("torrentview.php");

$torrent = getRequestVar('torrent');
$error = "";
$torrentowner = getOwner($torrent);
$alias = SecurityClean(getRequestVar('alias'));
if (!empty($alias))
{
    // read the alias file
    // create AliasFile object
    $af = new AliasFile($cfg["torrent_file_path"].$alias, $torrentowner);

    for ($inx = 0; $inx < sizeof($af->errors); $inx++)
    {
        $error .= "<li>".$af->errors[$inx]."</li>";
    }

    if ($af->seedlimit <= 0)
    {
        $af->seedlimit = "none";
    }
    else
    {
        $af->seedlimit .= "%";
    }
}
else
{
    die("fatal error torrent file not specified");
}

if ($af->percent_done < 0)
{
    $af->percent_done = round(($af->percent_done*-1)-100,1);
    $af->time_left = _INCOMPLETE;
}

if($af->percent_done >= 100)
{
    $af->percent_done = 100;
}

$hd = getStatusImage($af);

if ($af->running == 1)
{
    $state = ($af->percent_done >= 100) ? "Seeding" : "Downloading";
}
elseif ($af->running == 3)
{
    $state = "Queued";
}
else
{
    $state = ($af->percent_done >= 100) ? "Finished" : "Stopped";
}

// torrent name from the .torrent file name, without the extension
$name = preg_replace('/\.torrent$/i', '', html_entity_decode($torrent, ENT_QUOTES));

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="<?php echo _CHARSET ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $af->percent_done ?>% - <?php echo htmlentities($name, ENT_QUOTES) ?></title>
    <link rel="StyleSheet" href="themes/<?php echo $cfg["theme"] ?>/style.css?v=<?php echo @filemtime("themes/".$cfg["theme"]."/style.css") ?>" type="text/css">
<?php tfDialogPageScript(); ?>
</head>

<body class="tf-run">
<div class="tf-run-form">

<div class="tf-run-head">
    <div>
        <div class="tf-run-kicker"><?php echo _DOWNLOADDETAILS ?></div>
        <h1><?php echo htmlentities($name, ENT_QUOTES) ?></h1>
    </div>
    <button type="button" class="tf-run-x" title="Close" onclick="CloseRun()">&#x2715;</button>
</div>

<div class="tf-run-body" id="tf-dd-live">

    <div class="tf-run-statusline">
        <img src="<?php echo ThemeImages().$hd->image ?>" width="10" height="10" alt="">
        <b><?php echo $state ?></b>
        <span class="tf-run-dim">&middot; <?php echo $af->percent_done ?>% of <?php echo formatBytesToKBMGGB($af->size) ?></span>
        <span class="tf-run-dim tf-run-eta"><?php echo $af->time_left ?></span>
    </div>
    <div class="tf-run-bar"><span style="width:<?php echo max(0, min(100, $af->percent_done)) ?>%"></span></div>

    <div class="tf-run-card tf-run-stats">
        <div><span><?php echo _DOWNLOADSPEED ?></span><b><?php echo $af->down_speed ?></b></div>
        <div><span><?php echo _UPLOADSPEED ?></span><b><?php echo $af->up_speed ?></b></div>
        <div><span>Downloaded</span><b><?php echo formatFreeSpace($af->GetRealDownloadTotal()) ?></b></div>
        <div><span>Uploaded</span><b><?php echo formatFreeSpace($af->uptotal/(1024*1024)) ?></b></div>
        <div><span>Seeds</span><b><?php echo $af->seeds ?></b></div>
        <div><span>Peers</span><b><?php echo $af->peers ?></b></div>
        <div><span><?php echo _SHARING ?></span><b><?php echo $af->sharing ?>%</b></div>
        <div><span>Seed Until</span><b><?php echo $af->seedlimit ?></b></div>
    </div>
<?php
    if ($error != "")
    {
        echo "    <div class=\"tf-run-notice tf-run-notice-error\"><b>Error</b><ul>".$error."</ul></div>\n";
    }
?>
    <div class="tf-run-hint tf-run-owner"><?php echo _USER ?>: <?php echo htmlentities($torrentowner, ENT_QUOTES) ?></div>

</div>

<div class="tf-run-foot">
    <span class="tf-run-summary">Updates every 5 seconds</span>
    <input type="button" class="tf-run-secondary" value="Close" onclick="CloseRun()">
</div>

</div>
<script type="text/javascript">
// refresh the numbers in place (live=1: not counted as a page hit)
setInterval(function () {
    if (document.hidden) return;
    fetch(location.href + (location.search ? "&" : "?") + "live=1", { credentials: "same-origin", cache: "no-store" })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            var doc = new DOMParser().parseFromString(html, "text/html"), fresh = doc.getElementById("tf-dd-live");
            if (!fresh) return;
            document.getElementById("tf-dd-live").innerHTML = fresh.innerHTML;
            document.title = doc.title;
        })
        .catch(function () { });
}, 5000);
</script>
</body>
</html>
