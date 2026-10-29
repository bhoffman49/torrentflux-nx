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

// Run Torrent: opened in a dialog over the main page (rundialog.js), or as a popup window.
// Only the options the Transmission bridge uses are shown (rates, completion, share ratio);
// index.php fills in the old BitTornado-only options from the settings.

include_once("config.php");
include_once("functions.php");
require_once("torrentview.php");

$torrent = SecurityClean(getRequestVar('torrent'));

if (!file_exists($cfg["torrent_file_path"].$torrent))
{
    echo htmlentities($torrent)." could not be found or does not exist.";
    die();
}

$t = tfLoadTorrent($torrent);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="<?php echo _CHARSET ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo _RUNTORRENT ?> - <?php echo htmlentities($torrent, ENT_QUOTES) ?></title>
    <link rel="StyleSheet" href="themes/<?php echo $cfg["theme"] ?>/style.css?v=<?php echo @filemtime("themes/".$cfg["theme"]."/style.css") ?>" type="text/css">

<?php tfDialogPageScript(); ?>
<script type="text/javascript">
function isNumber(sText)
{
    return /^[0-9]+$/.test(sText);
}

function StartTorrent()
{
    var f = document.theForm, msg = "";
    if (!isNumber(f.drate.value)) msg += "* Max Download Rate must be a number.\n";
    if (!isNumber(f.rate.value)) msg += "* Max Upload Rate must be a number.\n";
    if (!isNumber(f.sharekill.value)) msg += "* Keep seeding until ratio must be a number.\n";
    if (document.getElementById("allfiles") && selectedFiles().length == 0) msg += "* Tick at least one file to download.\n";
    if (msg != "")
    {
        alert("Please check the following:\n\n" + msg);
        return;
    }
    document.getElementById("startbtn").disabled = true;
    f.submit();
}

function CheckShareState()
{
    var keep = document.theForm.runtime.value == "False";
    document.getElementById("sharekiller").style.display = keep ? "" : "none";
    if (inDialog() && window.parent.tfRunFit) window.parent.tfRunFit();
}
</script>
<?php tfFileListScript(); ?>
</head>

<body class="tf-run">
<form name="theForm" action="index.php" method="POST" class="tf-run-form">
<input type="hidden" name="closeme" value="true">
<input type="hidden" name="torrent" value="<?php echo htmlentities($torrent, ENT_QUOTES) ?>">

<div class="tf-run-head">
    <div>
        <div class="tf-run-kicker"><?php echo _RUNTORRENT ?></div>
        <h1><?php echo htmlentities($t['name'], ENT_QUOTES) ?></h1>
    </div>
    <button type="button" class="tf-run-x" title="Close" onclick="CloseRun()">&#x2715;</button>
</div>

<div class="tf-run-body">

    <div class="tf-run-grid">
        <div class="tf-run-field">
            <label for="drate">Max Download Rate</label>
            <div class="tf-run-input"><input type="text" id="drate" name="drate" maxlength="6" value="<?php echo $cfg["max_download_rate"] ?>"> kB/s</div>
            <div class="tf-run-hint">0 = unlimited</div>
        </div>
        <div class="tf-run-field">
            <label for="rate">Max Upload Rate</label>
            <div class="tf-run-input"><input type="text" id="rate" name="rate" maxlength="6" value="<?php echo $cfg["max_upload_rate"] ?>"> kB/s</div>
            <div class="tf-run-hint">0 = unlimited</div>
        </div>
        <div class="tf-run-field">
            <label for="runtime">When Finished</label>
            <select id="runtime" name="runtime" onchange="CheckShareState()">
                <option value="True">Stop</option>
                <option value="False"<?php echo ($cfg["torrent_dies_when_done"] == "False") ? " selected" : "" ?>>Keep Seeding</option>
            </select>
        </div>
        <div class="tf-run-field" id="sharekiller">
            <label for="sharekill">Seed Until Ratio</label>
            <div class="tf-run-input"><input type="text" id="sharekill" name="sharekill" maxlength="4" value="<?php echo $cfg["sharekill"] ?>"> %</div>
            <div class="tf-run-hint">0 = seed forever</div>
        </div>
<?php
    if ($cfg["AllowQueing"] == true)
    {
        if (IsAdmin())
        {
            echo "        <label class=\"tf-run-check\"><input type=\"checkbox\" name=\"queue\" checked> Add to Queue</label>\n";
        }
        else
        {
            // non-admins are always queued
            echo "        <input type=\"hidden\" name=\"queue\" value=\"1\">\n";
        }
    }
?>
    </div>

    <div class="tf-run-section">
        <h2>Details</h2>
<?php tfShowDetailsCard($t); ?>
    </div>

    <div class="tf-run-section">
        <h2>Files</h2>
<?php tfShowFileList($t, $cfg["enable_file_priority"]); ?>
    </div>

</div>

<div class="tf-run-foot">
    <span class="tf-run-summary" id="summary"><?php echo formatBytesToKBMGGB($t['size']) ?></span>
    <input type="button" class="tf-run-secondary" value="Cancel" onclick="CloseRun()">
    <input type="button" id="startbtn" value="<?php echo _RUNTORRENT ?>" onclick="StartTorrent()">
</div>

</form>
<script type="text/javascript">
    CheckShareState();
    UpdateFiles();
    document.getElementById("startbtn").focus();
</script>
</body>
</html>
