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

require_once(__DIR__ . '/dataTransfer_config.php');

error_reporting(0);

$fa = file_array(MS3C_GRAPHICS_ROOT);

foreach ($fa as $file) {
	echo utf8_encode($file) . "\n";
}

function file_array($path, $exclude = ".|..") {
	$path = rtrim($path, "/") . "/";
	$fh1 = opendir($path);
	if (!$fh1) {
		die("Cannot open directory $path");
	}
	$exclude_array = explode("|", $exclude);
	$result = array();
	while (false !== ($picdir = readdir($fh1))) {
		if (!in_array(strtolower($picdir), $exclude_array)) {
			if (is_dir($path . $picdir . "/")) {
				$fh2 = opendir($path . $picdir . "/");
				while (false !== ($filename = readdir($fh2))) {
					if (!in_array(strtolower($filename), $exclude_array)) {
						if (is_file($path . $picdir . "/" . $filename)) {
							$result[] = $picdir . "/" . $filename;
						}
					} else {
						continue;
					}
				}
			}
			closedir($fh2);
		} else {
			continue;
		}
	}
	closedir($fh1);
	return $result;
}

?>
