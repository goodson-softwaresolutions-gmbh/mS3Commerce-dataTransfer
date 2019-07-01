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

require_once(__DIR__  .'/../dataTransfer_config.php');

$fa = file_array(MS3C_EXT_ROOT.'/dataTransfer/diff/');

foreach ($fa as $file) {
  echo utf8_encode($file). "\n";
}

function file_array($path) {
	$path = rtrim($path, "/") . "/";
	$fh = opendir($path);
	$result = array();

	while(false !== ($filename = readdir($fh))) {
		if (preg_match("/.*\.db$/i", $filename)) {
			$result[]=$filename;
		} else {
			continue;
		}

    }
	closedir($fh);
	return $result;
}
?>
