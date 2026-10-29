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

// Torrent Details: the details card and file list (torrentview.php). The torrent's owner or an admin
// can change which files to download; Save posts to index.php (setPriorityOnly), which also applies
// the choice to the torrent in Transmission if it is running, then comes back here with ?saved=.

include_once("config.php");
include_once("functions.php");
require_once("torrentview.php");

global $cfg;

$torrent = SecurityClean(getRequestVar('torrent'));

DisplayHead(_TORRENTDETAILS);

echo "<table width=\"740\" border=0 cellpadding=0 cellspacing=0><tr><td>";

echo displayDriveSpaceBar(getDriveSpace($cfg["path"]));

echo "</td></tr></table>";
echo "<br>";

if (empty($torrent) || !file_exists($cfg["torrent_file_path"].$torrent))
{
    echo _NORECORDSFOUND;
    DisplayFoot();
    exit();
}

$t = tfLoadTorrent($torrent);
$live = tfLiveTorrent($t);
$canEdit = $cfg["enable_file_priority"] && $t['multi'] && (IsAdmin() || IsOwner($cfg["user"], getOwner($torrent)));

// Transmission status numbers: 0 stopped, 1-2 checking, 3-4 downloading, 5-6 seeding
if ($live)
{
    $states = array("Stopped", "Waiting to check", "Checking files", "Waiting to download", "Downloading", "Waiting to seed", "Seeding");
    $status = ($states[$live['status']] ?? "Running")." \u{00b7} ".floor($live['percentDone'] * 100)."%";
}
else
{
    $status = "Not running";
}

$notices = array(
    "live"   => "File selection saved and applied to the running torrent.",
    "later"  => "File selection saved. It will be used the next time the torrent starts.",
    "denied" => "Only the torrent's owner or an admin can change its files.",
);
$saved = getRequestVar('saved');

tfFileListScript();
?>
<div class="tf-run tf-run-page">

    <div class="tf-run-pagehead">
        <div class="tf-run-kicker"><?php echo _TORRENTDETAILS ?></div>
        <h1><?php echo htmlentities($t['name'], ENT_QUOTES) ?></h1>
        <div class="tf-run-status"><?php echo $status ?></div>
    </div>
<?php
    if (isset($notices[$saved]))
    {
        echo "    <div class=\"tf-run-notice".($saved == "denied" ? " tf-run-notice-error" : "")."\">".$notices[$saved]."</div>\n";
    }
?>

    <div class="tf-run-section">
        <h2>Details</h2>
<?php tfShowDetailsCard($t); ?>
    </div>

    <form name="priority" action="index.php" method="POST" onsubmit="return CheckSave()">
    <input type="hidden" name="torrent" value="<?php echo htmlentities($torrent, ENT_QUOTES) ?>">
    <input type="hidden" name="setPriorityOnly" value="true">
    <div class="tf-run-section">
        <h2>Files</h2>
<?php tfShowFileList($t, $canEdit, $live); ?>
    </div>
<?php
    if ($canEdit)
    {
?>
    <div class="tf-run-actions">
        <span class="tf-run-summary" id="summary"></span>
        <input type="submit" value="Save">
    </div>
<?php
    }
?>
    </form>

</div>
<script type="text/javascript">
function CheckSave()
{
    if (selectedFiles().length == 0)
    {
        alert("Tick at least one file to download.");
        return false;
    }
    return true;
}
UpdateFiles();
</script>
<?php

DisplayFoot();

?>
