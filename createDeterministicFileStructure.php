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

require_once(__DIR__ .'/dataTransfer_config.php');

$path = MS3C_GRAPHICS_ROOT;

/* 
 * - Alle Verzeichnisse Pic0 - Pic9 in $dir durchgehen.
 * - Jede Datei hat Format nnnnnnn_n.eee (n = Ziffer, e = Extension)
 * - Quersumme bilden (s = n1+n2+...+n8 + 10 (_ = 10))
 * - Modulo 10 ==> S = s mod 10 ==> 0 <= s <= 9
 * - Zielverzeichnis ist $dir/XPicS/
 * - Datei von $dir/PicN/... nach $dir/XPicS/... verschieben.
 * 
 *  
 */

$res=  file_array($path);
$existing = 0;
$moved = 0;
foreach($res as $file){
	$dir=pathinfo($file,PATHINFO_DIRNAME);
	$filename=pathinfo($file,PATHINFO_FILENAME);
	$ext = pathinfo($file, PATHINFO_EXTENSION);
	$string=str_replace('_','',$filename);
	$numero=0;
	$arr=str_split($string);
	foreach($arr as $char){
		$numero+=intval($char);
	}
	$modulo=$numero%10;
	$newdir="XPic".$modulo;
	$filename=$filename.'.'.$ext;
	if(!is_dir($path.$newdir)){
		mkdir($path.$newdir);
	}
	if ( is_file($path.$newdir."/".$filename) ) {
		$existing++;
		echo "Exists: ".$path.$newdir."/".$filename."<br/>";
	} else {
		rename($path.$dir."/".$filename,$path.$newdir."/".$filename);
		$moved++;
	}
	
}

echo "<br/><br/>Moved: $moved, Existing: $existing";

	
function file_array($path, $exclude = ".|..") {
        $path = rtrim($path, "/") . "/";
        $fh1 = opendir($path);
        $exclude_array = explode("|", $exclude);
        $result = array();
        while(false !== ($picdir = readdir($fh1))) {
            if(!in_array(strtolower($picdir), $exclude_array)) {
                if(is_dir($path . $picdir . "/")) {
                   $fh2 = opendir($path. $picdir . "/");
				    while(false !== ($filename = readdir($fh2))) {
						if(!in_array(strtolower($filename), $exclude_array)) {
							$result[]=$picdir."/".$filename;
					}else{
						continue;
					}
                }
            }
			closedir($fh2);
        }else{
			continue;
		}
    }
	closedir($fh1);
	return $result;
}

?>
