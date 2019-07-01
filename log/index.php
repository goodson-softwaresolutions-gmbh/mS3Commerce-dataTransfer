<?php
/***************************************************************
* Part of mS3 Commerce
* Copyright (C) 2019 Goodson GmbH <http://www.goodson.at>
*  All rights reserved
* 
* Dieses Computerprogramm ist urheberrechtlich sowie durch internationale
* Abkommen geschützt. Die unerlaubte Reproduktion oder Weitergabe dieses
* Programms oder von Teilen dieses Programms kann eine zivil- oder
* strafrechtliche Ahndung nach sich ziehen und wird gemäß der geltenden
* Rechtsprechung mit größtmöglicher Härte verfolgt.
* 
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once __DIR__ .'/../dataTransfer_config.php';
require_once MS3C_ROOT.'/dataTransfer/mS3CommerceSQL.php';

function StartsWith($Haystack, $Needle)
{
    // Recommended version, using strpos
    return strpos($Haystack, $Needle) === 0;
}

function EndsWith( $str, $sub )
{
   return ( substr( $str, strlen( $str ) - strlen( $sub ) ) == $sub );
}

function getLogList()
{
	$ignored = array('.', '..');

	$dir = MS3C_EXT_ROOT.'/dataTransfer/log';
	
    $files = array();    
    foreach (scandir($dir) as $file) {
        if (in_array($file, $ignored)) continue;
		if (!EndsWith(strtolower($file), '.txt')) continue;
        $files[$file] = filemtime($dir . '/' . $file);
    }

    arsort($files);
    $files = array_keys($files);

    return $files;
}

?>
<html>
<head>
<script type="text/javascript">
function showlog(logname)
{
  window.location = "index.php?log=" + logname;
}
</script>
<title>mS3Commerce Log View</title>
</head>
<body>
    <select name="loglist" size="1">
<?php

$logfile = '';
if (array_key_exists('log', $_GET))
	$logfile = $_GET[ 'log' ];
if ( $logfile == '' && count($loglist) > 0)
{
  $logfile = reset($loglist);
}

$loglist = getLogList();
foreach ( $loglist as $val )
{
	$selected = '';
	if ($val == $logfile) {
		$selected = 'selected="selected"';
	}
  echo "<option onclick=\"showlog('$val');\" $selected>$val</option>";
}
?>
    </select>
aktuell sichtbare Datenbank: <font color="red"><?php echo getActiveMS3CommerceDB(); ?></font> unsichtbare Datenbank: <?php echo getNotActiveMS3CommerceDB(); ?>
<br>
<hr noshade size="2"><br>
<?php


if (!empty($logfile)) {
	// Hack fix: only take file name (don't allow ?log=../mS3CommerceDBAccess.php)
	$logfile = pathinfo($logfile, PATHINFO_BASENAME);
	$logpath = MS3C_EXT_ROOT . "/dataTransfer/log/" . $logfile;
	printLogFile($logpath, $logfile);
}

function printLogFile($logpath)
{
	if (!file_exists($logpath)) {
		return;
	}
	$fileSize = filesize( $logpath );
	if (( $handle = fopen( $logpath, "r" ) ) === FALSE )
	{
	die( '' );
	}
	$fileData = fread( $handle, $fileSize );
	fclose( $handle );
	
	$logfile = pathinfo($logpath, PATHINFO_BASENAME);

	echo "<font style=\"font-size: 13.4px; font-family: Arial,sans-serif;\"><b>" . $logfile  . "</b><br></font><table border=\"0\"><tr><td nowrap><font size=\"small\">";
	//echo str_replace( $order, $replace, $fileData );

	$loglines = preg_split( "/\n/",  $fileData  );
	$loglines = array_reverse( $loglines );

	foreach ( $loglines as $key => $val )
	{
	$haserror = strrpos( $val, "Error" );
	if  ( $haserror === false  )
	{
		echo "<font color=\"green\">" . $val . "</font><br>";
	} else {
		echo "<font color=\"red\">" . $val . "</font><br>";
	}
	}

	echo "</font></td></tr></table>";
}
?>
</body>
</html>
