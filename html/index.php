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


include_once("config.php");
include_once("functions.php");

$messages = "";

// set refresh option into the session cookie
if(array_key_exists("pagerefresh", $_GET))
{
    if(getRequestVar("pagerefresh") == "false")
    {
        $_SESSION['prefresh'] = false;
        header("location: index.php");
        exit();
    }

    if(getRequestVar("pagerefresh") == "true")
    {
        $_SESSION["prefresh"] = true;
        header("location: index.php");
        exit();
    }
}
// Check to see if QManager is running if not Start it.
if (checkQManager() == 0 )
{
    if ($cfg["AllowQueing"])
    {
        if (is_dir($cfg["path"]) && is_writable($cfg["path"]))
        {
            AuditAction($cfg["constants"]["QManager"], "QManager Not Running");
            sleep(2);
            startQManager($cfg["maxServerThreads"],$cfg["maxUserThreads"],$cfg["sleepInterval"]);
            sleep(2);
        }
        else
        {
            AuditAction($cfg["constants"]["error"], "Error starting Queue Manager -- TorrentFlux settings are not correct (path is not valid)");
            if (IsAdmin())
            {
                header("location: admin.php?op=configSettings");
                exit();
            }
            else
            {
                $messages .= "<b>Error</b> TorrentFlux settings are not correct (path is not valid) -- please contact an admin.<br>";
            }
        }
    }
}

$torrent = getRequestVar('torrent');

if(!empty($torrent))
{
    include_once("AliasFile.php");

    $spo = getRequestVar('setPriorityOnly');
    // file choices from Torrent Details: only the torrent's owner or an admin
    $mayPrioritize = empty($spo) || IsAdmin() || IsOwner($cfg["user"], getOwner($torrent));

    if ($cfg["enable_file_priority"] && $mayPrioritize)
    {
        include_once("setpriority.php");
        // Process setPriority Request.
        setPriority($torrent);
    }

    if (!empty($spo)){
        // This is a setPriortiyOnly Request (Save on Torrent Details): apply it to the torrent
        // in Transmission if it is running, then go back to the details page
        include_once("torrentview.php");
        $saved = "denied";
        if ($mayPrioritize)
        {
            $t = tfLoadTorrent(SecurityClean(html_entity_decode($torrent, ENT_QUOTES)));
            $wanted = getRequestVar('files');
            $saved = tfApplyFileChoice($t, (is_array($wanted) && count($wanted) > 0) ? $wanted : array_keys($t['files'])) ? "live" : "later";
        }
        header("location: details.php?torrent=".urlencode(html_entity_decode($torrent, ENT_QUOTES))."&saved=".$saved);
        exit();
    }else
    {
        // if we are to start a torrent then do so

        // check to see if the path to the python script is valid
        if (!is_file($cfg["btphpbin"]))
        {
            AuditAction($cfg["constants"]["error"], "Error  Path for ".$cfg["btphpbin"]." is not valid");
            if (IsAdmin())
            {
                header("location: admin.php?op=configSettings");
                exit();
            }
            else
            {
                $messages .= "<b>Error</b> TorrentFlux settings are not correct (path to python script is not valid) -- please contact an admin.<br>";
            }
        }

        $command = "";

        $rate = getRequestVar('rate');
        if (empty($rate))
        {
            if ($rate != "0")
            {
                $rate = $cfg["max_upload_rate"];
            }
        }
        $drate = getRequestVar('drate');
        if (empty($drate))
        {
            if ($drate != "0")
            {
                $drate = $cfg["max_download_rate"];
            }
        }
        $superseeder = getRequestVar('superseeder');
        if (empty($superseeder))
        {
            $superseeder = "0"; // should be 0 in most cases
        }
        $runtime = getRequestVar('runtime');
        if (empty($runtime))
        {
            $runtime = $cfg["torrent_dies_when_done"];
        }

        $maxuploads = getRequestVar('maxuploads');
        if (empty($maxuploads))
        {
            if ($maxuploads != "0")
            {
                $maxuploads = $cfg["max_uploads"];
            }
        }
        $minport = getRequestVar('minport');
        if (empty($minport))
        {
            $minport = $cfg["minport"];
        }
        $maxport = getRequestVar('maxport');
        if (empty($maxport))
        {
            $maxport = $cfg["maxport"];
        }
        $rerequest = getRequestVar("rerequest");
        if (empty($rerequest))
        {
            $rerequest = $cfg["rerequest_interval"];
        }
        $sharekill = getRequestVar('sharekill');

        if ($runtime == "True" )
        {
            $sharekill = "-1";
        }

        if (empty($sharekill))
        {
            if ($sharekill != "0")
            {
                $sharekill = $cfg["sharekill"];
            }
        }
        if ($cfg["AllowQueing"])
        {
            if(IsAdmin())
            {
                $queue = getRequestVar('queue');
                if($queue == 'on')
                {
                    $queue = "1";
                }else
                {
                    $queue = "0";
                }
            }
            else
            {
                $queue = "1";
            }
        }

        $crypto_allowed = getRequestVar('crypto_allowed');
        if (empty($crypto_allowed))
        {
            $crypto_allowed = $cfg["crypto_allowed"];
        }

        $crypto_only = getRequestVar('crypto_only');
        if (empty($crypto_only))
        {
            $crypto_only = $cfg["crypto_only"];
        }

        $crypto_stealth = getRequestVar('crypto_stealth');
        if (empty($crypto_stealth))
        {
            $crypto_stealth = $cfg["crypto_stealth"];
        }

        //$torrent = urldecode($torrent);
        $alias = getAliasName($torrent);
        $owner = getOwner($torrent);

        // The following lines of code were suggested by Jody Steele jmlsteele@stfu.ca
        // This is to help manage user downloads by their user names
        //if the user's path doesnt exist, create it
        if (!is_dir($cfg["path"]."/".$owner))
        {
            if (is_writable($cfg["path"]))
            {
                mkdir($cfg["path"]."/".$owner, 0777);
            }
            else
            {
                AuditAction($cfg["constants"]["error"], "Error -- " . $cfg["path"] . " is not writable.");
                if (IsAdmin())
                {
                    header("location: admin.php?op=configSettings");
                    exit();
                }
                else
                {
                    $messages .= "<b>Error</b> TorrentFlux settings are not correct (path is not writable) -- please contact an admin.<br>";
                }
            }
        }

        // create AliasFile object and write out the stat file
        $af = new AliasFile($cfg["torrent_file_path"].$alias.".stat", $owner);

        if ($cfg["AllowQueing"])
        {
            if($queue == "1")
            {
                $af->QueueTorrentFile();  // this only writes out the stat file (does not start torrent)
            }
            else
            {
                $af->StartTorrentFile();  // this only writes out the stat file (does not start torrent)
            }
        }
        else
        {
            $af->StartTorrentFile();  // this only writes out the stat file (does not start torrent)
        }

        if (usingTornado())
        {
            $command = escapeshellarg($runtime)." ".escapeshellarg($sharekill)." '".$cfg["torrent_file_path"].$alias.".stat' ".$owner." --responsefile '".$cfg["torrent_file_path"].$torrent."' --display_interval 5 --max_download_rate ". escapeshellarg($drate) ." --max_upload_rate ".escapeshellarg($rate)." --max_uploads ".escapeshellarg($maxuploads)." --minport ".escapeshellarg($minport)." --maxport ".escapeshellarg($maxport)." --rerequest_interval ".escapeshellarg($rerequest)." --super_seeder ".escapeshellarg($superseeder)." --crypto_allowed ".escapeshellarg($crypto_allowed)." --crypto_only ".escapeshellarg($crypto_only)." --crypto_stealth ".escapeshellarg($crypto_stealth);

            if(file_exists($cfg["torrent_file_path"].$alias.".prio")) {
                $priolist = explode(',',file_get_contents($cfg["torrent_file_path"].$alias.".prio"));
                $priolist = implode(',',array_slice($priolist,1,$priolist[0]));
                $command .= " --priority ".escapeshellarg($priolist);
            }
            if ($cfg["cmd_options"])
            	$command .= " ".escapeshellarg($cfg["cmd_options"]);
            
            $command .= " > /dev/null &";

            if ($cfg["AllowQueing"] && $queue == "1")
            {
                //  This file is being queued.
            }
            else
            {
                // This flie is being started manually.

                if (! array_key_exists("pythonCmd", $cfg))
                {
                    insertSetting("pythonCmd","/usr/bin/python");
                }

                if (! array_key_exists("debugTorrents", $cfg))
                {
                    insertSetting("debugTorrents", "0");
                }

                if (!$cfg["debugTorrents"])
                {
                    $pyCmd = escapeshellarg($cfg["pythonCmd"]) . " -OO";
                }else{
                    $pyCmd = escapeshellarg($cfg["pythonCmd"]);
                }

                $command = "cd " . $cfg["path"] . $owner . "; HOME=".$cfg["path"]."; export HOME; nohup " . $pyCmd . " " .escapeshellarg($cfg["btphpbin"]) . " " . $command;
            }

        }
        else
        {
            // Must be using the Original BitTorrent Client
            // This is now being required to allow Queing functionality
            //$command = "cd " . $cfg["path"] . $owner . "; nohup " . $cfg["btphpbin"] . " ".$runtime." ".$sharekill." ".$cfg["torrent_file_path"].$alias.".stat ".$owner." --responsefile \"".$cfg["torrent_file_path"].$torrent."\" --display_interval 5 --max_download_rate ". $drate ." --max_upload_rate ".$rate." --max_uploads ".$maxuploads." --minport ".$minport." --maxport ".$maxport." --rerequest_interval ".$rerequest." ".$cfg["cmd_options"]." > /dev/null &";
            $messages .= "<b>Error</b> BitTornado is only supported Client at this time.<br>";
        }

        // write the session to close so older version of PHP will not hang
        session_write_close();

        if($af->running == "3")
        {
            writeQinfo($cfg["torrent_file_path"]."queue/".$alias.".stat",$command);
            AuditAction($cfg["constants"]["queued_torrent"], $torrent."<br>Die:".$runtime.", Sharekill:".$sharekill.", MaxUploads:".$maxuploads.", DownRate:".$drate.", UploadRate:".$rate.", Ports:".$minport."-".$maxport.", SuperSeed:".$superseeder.", Rerequest Interval:".$rerequest);
            AuditAction($cfg["constants"]["queued_torrent"], $command);
        }
        else
        {
            // The following command starts the torrent running! w00t!
            passthru($command);

            AuditAction($cfg["constants"]["start_torrent"], $torrent."<br>Die:".$runtime.", Sharekill:".$sharekill.", MaxUploads:".$maxuploads.", DownRate:".$drate.", UploadRate:".$rate.", Ports:".$minport."-".$maxport.", SuperSeed:".$superseeder.", Rerequest Interval:".$rerequest);

            // slow down and wait for thread to kick off.
            // otherwise on fast servers it will kill stop it before it gets a chance to run.
            sleep(1);
        }

        if ($messages == "")
        {
            if (array_key_exists("closeme",$_POST))
            {
?>
                <script  language="JavaScript">
                    // Run Torrent popup window, or the in-page dialog (rundialog.js)
                    if (window.opener) { window.opener.location.reload(true); window.close(); }
                    else if (window.parent !== window && window.parent.tfRunDone) window.parent.tfRunDone();
                    else location.href = "index.php";
                </script>
<?php
               exit();
            }
            else
            {
                header("location: index.php");
                exit();
            }
        }
        else
        {
            AuditAction($cfg["constants"]["error"], $messages);
        }
    }
}


// Do they want us to get a torrent via a URL?
$url_upload = getRequestVar('url_upload');
// magnet links: undo the form escaping (&amp; -> &) and let Transmission fetch the torrent
$isMagnet = (strncasecmp($url_upload, "magnet:", 7) == 0);
$magnetError = "";
if ($isMagnet)
{
    $url_upload = html_entity_decode($url_upload, ENT_QUOTES);
}

if(! $url_upload == '')
{
    $arURL = explode("/", $url_upload);
    $file_name = urldecode($arURL[count($arURL)-1]); // get the file name
    $file_name = str_replace(array("'",","), "", $file_name);
    $file_name = stripslashes($file_name);
    $ext_msg = "";

    // Check to see if url has something like ?passkey=12345
    // If so remove it.
    if( ( $point = strrpos( $file_name, "?" ) ) !== false )
    {
        $file_name = substr( $file_name, 0, $point );
    }

    $ret = strrpos($file_name,".");
    if ($ret === false)
    {
        $file_name .= ".torrent";
    }
    else
    {
        if(!strcmp(strtolower(substr($file_name, strlen($file_name)-8, 8)), ".torrent") == 0)
        {
            $file_name .= ".torrent";
        }
    }

    $url_upload = str_replace(" ", "%20", $url_upload);

    // This is to support Sites that pass an id along with the url for torrent downloads.
    $tmpId = getRequestVar("id");
    if(!empty($tmpId))
    {
        $url_upload .= "&id=".$tmpId;
    }

    // Call fetchtorrent to retrieve the torrent file (or Transmission, for a magnet link)
    if ($isMagnet)
    {
        list($magnetName, $output) = MagnetToTorrent($url_upload, $magnetError);
        if ($magnetName != "")
        {
            $file_name = $magnetName.".torrent";
        }
    }
    else
    {
        $output = FetchTorrent( $url_upload );
    }

    if (array_key_exists("save_torrent_name",$cfg))
    {
        if ($cfg["save_torrent_name"] != "")
        {
            $file_name = $cfg["save_torrent_name"];
        }
    }

    $file_name = cleanFileName($file_name);

    // if the output had data then write it to a file
    if ((strlen($output) > 0) && (strpos($output, "<br />") === false))
    {
        if (is_file($cfg["torrent_file_path"].$file_name))
        {
            // Error
            $messages .= "<b>Error</b> with (<b>".htmlentities($file_name)."</b>), the file already exists on the server.<br><center><a href=\"".$_SERVER['PHP_SELF']."\">[Refresh]</a></center>";
            $ext_msg = "DUPLICATE :: ";
        }
        else
        {
            // open a file to write to
            $fw = fopen($cfg["torrent_file_path"].$file_name,'w');
            fwrite($fw, $output);
            fclose($fw);
        }
    }
    else
    {
        $messages .= "<b>Error</b> Getting the File (<b>".htmlentities($file_name)."</b>), ".(($isMagnet && $magnetError != "") ? htmlentities($magnetError) : "Could be a Dead URL.")."<br><center><a href=\"".$_SERVER['PHP_SELF']."\">[Refresh]</a></center>";
    }

    if ($messages == "")
    {
        AuditAction($cfg["constants"]["url_upload"], $file_name);
        header("location: index.php");
        exit();
    }
    else
    {
        // there was an error
        AuditAction($cfg["constants"]["error"], $cfg["constants"]["url_upload"]." :: ".$ext_msg.$file_name);
    }
}

// Handle the file upload if there is one
if(!empty($_FILES['upload_file']['name']))
{
    $file_name = stripslashes($_FILES['upload_file']['name']);
    $file_name = str_replace(array("'",","), "", $file_name);
    $file_name = cleanFileName($file_name);
    $ext_msg = "";

    if($_FILES['upload_file']['size'] <= 1000000 &&
       $_FILES['upload_file']['size'] > 0)
    {
        if (preg_match(getFileFilter($cfg["file_types_array"]), $file_name))
        {
            //FILE IS BEING UPLOADED
            if (is_file($cfg["torrent_file_path"].$file_name))
            {
                // Error
                $messages .= "<b>Error</b> with (<b>".htmlentities($file_name)."</b>), the file already exists on the server.<br><center><a href=\"".$_SERVER['PHP_SELF']."\">[Refresh]</a></center>";
                $ext_msg = "DUPLICATE :: ";
            }
            else
            {
                if(move_uploaded_file($_FILES['upload_file']['tmp_name'], $cfg["torrent_file_path"].$file_name))
                {
                    chmod($cfg["torrent_file_path"].$file_name, 0644);

                    AuditAction($cfg["constants"]["file_upload"], $file_name);

                    header("location: index.php");
                    exit();
                }
                else
                {
                    $messages .= "<font class=\"tf-error\" size=3>ERROR: File not uploaded, file could not be found or could not be moved:<br>".$cfg["torrent_file_path"] . htmlentities($file_name)."</font><br>";
                }
            }
        }
        else
        {
            $messages .= "<font class=\"tf-error\" size=3>ERROR: The type of file you are uploading is not allowed.</font><br>";
        }
    }
    else
    {
        $messages .= "<font class=\"tf-error\" size=3>ERROR: File not uploaded, check file size limit.</font><br>";
    }

    if($messages != "")
    {
        // there was an error
        AuditAction($cfg["constants"]["error"], $cfg["constants"]["file_upload"]." :: ".$ext_msg.$file_name);
    }
}  // End File Upload


// if a file was set to be deleted then delete it
$delfile = SecurityClean(getRequestVar('delfile'));
if(! $delfile == '')
{
    $alias_file = SecurityClean(getRequestVar('alias_file'));
    if (($cfg["user"] == getOwner($delfile)) || IsAdmin())
    {
        @unlink($cfg["torrent_file_path"].$delfile);
        @unlink($cfg["torrent_file_path"].$alias_file);
        // try to remove the QInfo if in case it was queued.
        @unlink($cfg["torrent_file_path"]."queue/".$alias_file.".Qinfo");

        // try to remove the pid file
        @unlink($cfg["torrent_file_path"].$alias_file.".pid");
        @unlink($cfg["torrent_file_path"].getAliasName($delfile).".prio");

        AuditAction($cfg["constants"]["delete_torrent"], $delfile);

        header("location: index.php");
        exit();
    }
    else
    {
        AuditAction($cfg["constants"]["error"], $cfg["user"]." attempted to delete ".$delfile);
    }
}

// Did the user select the option to kill a running torrent?
$kill = getRequestVar('kill');
if(! $kill == '' && is_numeric($kill) )
{
    include_once("AliasFile.php");
    include_once("RunningTorrent.php");

    $kill_torrent = getRequestVar('kill_torrent');
    $alias_file = SecurityClean(getRequestVar('alias_file'));
    // We are going to write a '0' on the front of the stat file so that
    // the BT client will no to stop -- this will report stats when it dies
    $the_user = getOwner($kill_torrent);
    // read the alias file
    // create AliasFile object
    $af = new AliasFile($cfg["torrent_file_path"].$alias_file, $the_user);
    if($af->percent_done < 100)
    {
        // The torrent is being stopped but is not completed dowloading
        $af->percent_done = ($af->percent_done + 100)*-1;
        $af->running = "0";
        $af->time_left = "Torrent Stopped";
    }
    else
    {
        // Torrent was seeding and is now being stopped
        $af->percent_done = 100;
        $af->running = "0";
        $af->time_left = "Download Succeeded!";
    }

    // see if the torrent process is hung.
    if (!is_file($cfg["torrent_file_path"].$alias_file.".pid"))
    {
        $runningTorrents = getRunningTorrents();
        foreach ($runningTorrents as $key => $value)
        {
            $rt = new RunningTorrent($value);
            if ($rt->statFile == $alias_file) {
                AuditAction($cfg["constants"]["error"], "Posible Hung Process " . $rt->processId);
            //    $result = exec("kill ".$rt->processId);
            }
        }
    }

    // Write out the new Stat File
    $af->WriteFile();

    AuditAction($cfg["constants"]["kill_torrent"], $kill_torrent);
    $return = getRequestVar('return');
    if (!empty($return))
    {
        sleep(3);
        passthru("kill ".$kill);
        // try to remove the pid file
        @unlink($cfg["torrent_file_path"].$alias_file.".pid");
        header("location: ".$return.".php?op=queueSettings");
        exit();
    }
    else
    {
        header("location: index.php");
        exit();
    }
}

// Did the user select the option to remove a torrent from the Queue?
if(isset($_REQUEST["dQueue"]))
{
    $alias_file = SecurityClean(getRequestVar('alias_file'));
    $QEntry = getRequestVar('QEntry');

    // Is the Qinfo file still there?
    if (file_exists($cfg["torrent_file_path"]."queue/".$alias_file.".Qinfo"))
    {
        // Yes, then delete it and update the stat file.
        include_once("AliasFile.php");
        // We are going to write a '2' on the front of the stat file so that
        // it will be set back to New Status
        $the_user = getOwner($QEntry);
        // read the alias file
        // create AliasFile object
        $af = new AliasFile($cfg["torrent_file_path"].$alias_file, $the_user);

        if($af->percent_done > 0 && $af->percent_done < 100)
        {
            // has downloaded something at some point, mark it is incomplete
            $af->running = "0";
            $af->time_left = "Torrent Stopped";
        }

        if ($af->percent_done == 0 || $af->percent_done == "")
        {
            $af->running = "2";
            $af->time_left = "";
        }

        if ($af->percent_done == 100)
        {
            // Torrent was seeding and is now being stopped
            $af->running = "0";
            $af->time_left = "Download Succeeded!";
        }

        // Write out the new Stat File
        $af->WriteFile();

        // Remove Qinfo file.
        @unlink($cfg["torrent_file_path"]."queue/".$alias_file.".Qinfo");

        AuditAction($cfg["constants"]["unqueued_torrent"], $QEntry);
    }
    else
    {
        // torrent has been started... try and kill it.
        AuditAction($cfg["constants"]["unqueued_torrent"], $QEntry . "has been started -- TRY TO KILL IT");
        header("location: index.php?alias_file=".$alias_file."&kill=true&kill_torrent=".urlencode($QEntry));
        exit();
    }

    header("location: index.php");
    exit();
}

$drivespace = getDriveSpace($cfg["path"]);


/************************************************************

************************************************************/
?>
<html>
<head>
    <title><?php echo $cfg["pagetitle"] ?></title>
    <link rel="icon" href="<?php echo ThemeImages() ?>favicon.ico" type="image/x-icon" />
    <link rel="shortcut icon" href="<?php echo ThemeImages() ?>favicon.ico" type="image/x-icon" />
    <LINK REL="StyleSheet" HREF="themes/<?php echo $cfg["theme"] ?>/style.css?v=<?php echo @filemtime("themes/".$cfg["theme"]."/style.css") ?>" TYPE="text/css">
    <META HTTP-EQUIV="Pragma" CONTENT="no-cache" charset="<?php echo _CHARSET ?>">
<?php
    if(!isset($_SESSION['prefresh']) || ($_SESSION['prefresh'] == true))
    {
        // live.js updates the torrent list in place; browsers without JavaScript fall back to a full refresh
        echo "<noscript><meta http-equiv=\"REFRESH\" content=\"".$cfg["page_refresh"].";URL=index.php\"></noscript>";
?>
<script src="live.js?v=<?php echo @filemtime("live.js") ?>" type="text/javascript" defer></script>
<?php
    }
?>
<div id="overDiv" style="position:absolute;visibility:hidden;z-index:1000;"></div>
<script language="JavaScript">
    var ol_closeclick = "1";
    var ol_close = "<font class=tf-close><b>X</b></font>";
    var ol_fgclass = "fg";
    var ol_bgclass = "bg";
    var ol_captionfontclass = "overCaption";
    var ol_closefontclass = "overClose";
    var ol_textfontclass = "overBody";
    var ol_cap = "&nbsp;Torrent Status";
</script>
<script src="overlib.js" type="text/javascript"></script>
<script language="JavaScript">
function ShowDetails(name_file)
{
  window.open (name_file,'_blank','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=430,height=225')
}
function ConfirmDelete(file)
{
    return confirm("<?php echo _ABOUTTODELETE ?>: " + file)
}
</script>
<script src="rundialog.js?v=<?php echo @filemtime("rundialog.js") ?>" type="text/javascript"></script>
</head>

<body topmargin="8" bgcolor="<?php echo $cfg["main_bgcolor"] ?>">

<div align="center">

<?php
if ($messages != "")
{
?>
<table border="1" cellpadding="10" class="tf-messages">
<tr>
    <td><div align="center"><?php echo $messages ?></div></td>
</tr>
</table><br><br>
<?php
}
?>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:1100px" align="center">
<tr>
    <td>
<table border="1" bordercolor="<?php echo $cfg["table_border_dk"] ?>" cellpadding="4" cellspacing="0" width="100%">
<tr>
    <td colspan="2" background="themes/<?php echo $cfg["theme"] ?>/images/bar.gif">
    <?php DisplayTitleBar($cfg["pagetitle"]); ?>
    </td>
</tr>
<tr>
    <td bgcolor="<?php echo $cfg["table_header_bg"] ?>">
    <table width="100%" cellpadding="3" cellspacing="0" border="0">
    <tr>
        <form name="form_file" action="index.php" method="post" enctype="multipart/form-data">
        <td>
        <?php echo _SELECTFILE ?>:<br>
        <input type="File" name="upload_file" size="40">
        <input type="Submit" value="<?php echo _UPLOAD ?>">
        </td>
        </form>
    </tr>
    <tr>
        <form name="form_url" action="index.php" method="post">
        <td>
        <hr>
        <?php echo _URLFILE ?>:<br>
        <input type="text" name="url_upload" size="50">
        <input type="Submit" value="<?php echo _GETFILE ?>">
        </td>
        </form>
    </tr>
    </table>

    </td>
    <td bgcolor="<?php echo $cfg["table_data_bg"] ?>" width="310" valign="top">
        <table width="100%" cellpadding="1" border="0">
        <tr>
        <td valign="top">
        <b><?php echo _TORRENTLINKS ?>:</b><br>
<?php
        $arLinks = GetLinks();
        if (is_array($arLinks))
        {
            foreach($arLinks as $link)
            {
                echo "<a href=\"".$link['url']."\" target=\"_blank\"><img src=\"".ThemeImages()."arrow.gif\" width=9 height=9 title=\"".$link['url']."\" border=0 align=\"baseline\">".$link['sitename']."</a><br>\n";
            }
        }
        echo "</ul></td>";

        $arUsers = GetUsers();
        $arOnlineUsers = array();
        $arOfflineUsers = array();

        for($inx = 0; $inx < count($arUsers); $inx++)
        {
            if(IsOnline($arUsers[$inx]))
            {
                array_push($arOnlineUsers, $arUsers[$inx]);
            }
            else
            {
                array_push($arOfflineUsers, $arUsers[$inx]);
            }
        }

        echo "<td bgcolor=\"".$cfg["table_data_bg"]."\" valign=\"top\">";
        echo "<b>"._ONLINE.":</b><br>";

        for($inx = 0; $inx < count($arOnlineUsers); $inx++)
        {
            echo "<a href=\"message.php?to_user=".$arOnlineUsers[$inx]."\">";
            echo "<img src=\"".ThemeImages()."user.png\" width=24 height=24 title=\"\" border=0 align=\"bottom\">". $arOnlineUsers[$inx];
            echo "</a><br>\n";
        }

        // Does the user want to see offline users?
        if ($cfg["hide_offline"] == false)
        {
            echo "<b>"._OFFLINE.":</b></br>";
            // Show offline users

            for($inx = 0; $inx < count($arOfflineUsers); $inx++)
            {
                echo "<a href=\"message.php?to_user=".$arOfflineUsers[$inx]."\">";
                echo "<img src=\"".ThemeImages()."user_offline.png\" width=24 height=24 title=\"\" border=0 align=\"bottom\">".$arOfflineUsers[$inx];
                echo "</a><br>\n";
            }
        }

        echo "</td>";
?>
        </tr>
        </table>
    </td>
</tr>
<tr>
    <td id="tf-drivespace" bgcolor="<?php echo $cfg["table_header_bg"] ?>" colspan="2">
<?php
    displayDriveSpaceBar($drivespace);
?>
    </td>
</tr>
<tr>
    <td bgcolor="<?php echo $cfg["table_data_bg"] ?>" colspan="2">
    <div align="center">
    <font face="Arial" size="2">
    <a href="readrss.php">
    <img src="<?php echo ThemeImages() ?>download_owner.gif" width="16" height="16" border="0" title="RSS Torrents" align="absmiddle">RSS Torrents</a>
     |
    <a href="drivespace.php">
    <img src="<?php echo ThemeImages() ?>hdd.gif" width="16" height="16" border="0" title="<?php echo $drivespace ?>% Used" align="absmiddle"><?php echo _DRIVESPACE ?></a>
     |
    <a href="who.php">
    <img src="<?php echo ThemeImages() ?>who.gif" width="16" height="16" title="" border="0" align="absmiddle"><?php echo _SERVERSTATS ?></a>
     |
    <a href="all_services.php">
    <img src="<?php echo ThemeImages() ?>all.gif" width="16" height="16" title="" border="0" align="absmiddle"><?php echo _ALL ?></a>
     |
    <a href="dir.php">
    <img src="<?php echo ThemeImages() ?>folder.png" width="24" height="24" title="" border="0" align="absmiddle"><?php echo _DIRECTORYLIST ?></a>
     |
    <a href="dir.php?dir=<?php echo $cfg["user"] ?>"><img src="<?php echo ThemeImages() ?>folder.png" width="24" height="24" title="My Directory" border="0" align="absmiddle">My Directory</a>
    </font>
    </div>
    </td>
</tr>
</table>
<div id="tf-live">
<?php
    getDirList($cfg["torrent_file_path"]);
?>
<tr><td bgcolor="<?php echo $cfg["table_header_bg"] ?>" colspan="6">

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
    <td valign="top">

    <div align="center">

    <table>
    <tr>
        <td><img src="<?php echo ThemeImages() ?>make.png" width="24" height="24" title="<?php echo _TORRENTDETAILS ?>"></td>
        <td class="tiny"><?php echo _TORRENTDETAILS ?>&nbsp;&nbsp;&nbsp;</td>
        <td><img src="<?php echo ThemeImages() ?>run_on.gif" width="16" height="16" title="<?php echo _RUNTORRENT ?>"></td>
        <td class="tiny"><?php echo _RUNTORRENT ?>&nbsp;&nbsp;&nbsp;</td>
        <td><img src="<?php echo ThemeImages() ?>kill.gif" width="16" height="16" title="<?php echo _STOPDOWNLOAD ?>"></td>
        <td class="tiny"><?php echo _STOPDOWNLOAD ?>&nbsp;&nbsp;&nbsp;</td>
        <?php
        if ($cfg["AllowQueing"])
        {
        ?>
        <td><img src="<?php echo ThemeImages() ?>queued.gif" width="16" height="16" title="<?php echo _DELQUEUE ?>"></td>
        <td class="tiny"><?php echo _DELQUEUE ?>&nbsp;&nbsp;&nbsp;</td>
        <?php
        }
        ?>
        <td><img src="<?php echo ThemeImages() ?>seed_on.gif" width="16" height="16" title="<?php echo _SEEDTORRENT ?>"></td>
        <td class="tiny"><?php echo _SEEDTORRENT ?>&nbsp;&nbsp;&nbsp;</td>
        <td><img src="<?php echo ThemeImages() ?>delete_on.gif" width="16" height="16" title="<?php echo _DELETE ?>"></td>
        <td class="tiny"><?php echo _DELETE ?></td>
        <?php
        if ($cfg["enable_torrent_download"])
        {
        ?>
        <td>&nbsp;&nbsp;&nbsp;<img src="<?php echo ThemeImages() ?>down.gif" width="9" height="9" title="Download Torrent meta file"></td>
        <td class="tiny">Download Torrent</td>
        <?php
        }
        ?>
    </tr>
    </table>


    <table width="100%" cellpadding="5">
    <tr>
        <td width="33%">
            <div class="tiny">
<?php
    if(checkQManager() > 0)
    {
         echo "<img src=\"".ThemeImages()."green.gif\" align=\"absmiddle\" title=\"Queue Manager Running\" align=\"absmiddle\"> Queue Manager Running<br>";
         echo "<strong>".strval(getRunningTorrentCount())."</strong> torrent(s) running and <strong>".strval(getNumberOfQueuedTorrents())."</strong> queued.<br>";
         echo "Total torrents server will run: <strong>".$cfg["maxServerThreads"]."</strong><br>";
         echo "Total torrents a user may run: <strong>".$cfg["maxUserThreads"]."</strong><br>";
         echo "* Torrents are queued when limits are met.<br>";
    }
    else
    {
        echo "<img src=\"".ThemeImages()."black.gif\" title=\"Queue Manager Off\" align=\"absmiddle\"> Queue Manager Off<br><br>";
    }
?>
            </div>
        </td>
        <td width="33%" valign="bottom">
            <div align="center" class="tiny">
<?php

    if(!isset($_SESSION['prefresh']) || ($_SESSION['prefresh'] == true))
    {
        echo "<span id=\"tf-live-status\">Live updates on</span><br>";
        echo "<a href=\"".$_SERVER['PHP_SELF']."?pagerefresh=false\"><font class=\"tiny\">Turn OFF live updates</font></a>";
    }
    else
    {
        echo "<a href=\"".$_SERVER['PHP_SELF']."?pagerefresh=true\"><font class=\"tiny\">Turn ON live updates</font></a>";
    }


    if($drivespace >= 98)
    {

        echo "\n\n<script  language=\"JavaScript\">\n alert(\""._WARNING.": ".$drivespace."% "._DRIVESPACEUSED."\")\n </script>";
    }

    if (!array_key_exists("total_download",$cfg)) $cfg["total_download"] = 0;
    if (!array_key_exists("total_upload",$cfg)) $cfg["total_upload"] = 0;


?>
            </div>
        </td>
        <td valign="top" width="33%" align="right">

            <table>
            <tr>
                <td class="tiny" align="right"><?php echo _CURRENTDOWNLOAD ?>:</td>
                <td class="tiny"><strong><?php echo number_format($cfg["total_download"], 2); ?></strong> kB/s</td>
            </tr>
            <tr>
                <td class="tiny" align="right"><?php echo _CURRENTUPLOAD ?>:</td>
                <td class="tiny"><strong><?php echo number_format($cfg["total_upload"], 2); ?></strong> kB/s</td>
            </tr>
            <tr>
                <td class="tiny" align="right"><?php echo _FREESPACE ?>:</td>
                <td class="tiny"><strong><?php echo formatFreeSpace($cfg["free_space"]) ?></strong></td>
            </tr>
            <tr>
                <td class="tiny" align="right"><?php echo _SERVERLOAD ?>:</td>
                <td class="tiny">
        <?php
            if ($cfg["show_server_load"] && @isFile($cfg["loadavg_path"]))
            {
                $loadavg_array = explode(" ", exec("cat ".escapeshellarg($cfg["loadavg_path"])));
                $loadavg = $loadavg_array[2];
                echo "<strong>".$loadavg."</strong>";
            }
            else
            {
                echo "<strong>n/a</strong>";
            }
        ?>
                </td>
            </tr>
            </table>

        </td>
    </tr>
    </table>





    </div>

    </td>
</tr>
</table>



</td></tr>
</table>
</div>

<?php
    echo DisplayTorrentFluxLink();
    // At this point Any User actions should have taken place
    // Check to see if the user has a force_read message from an admin
    if (IsForceReadMsg())
    {
        // Yes, then warn them
?>
        <script  language="JavaScript">
        if (confirm("<?php echo _ADMINMESSAGE ?>"))
        {
            document.location = "readmsg.php";
        }
        </script>
<?php
    }
?>

    </td>
</tr>
</table>
</body>
</html>
