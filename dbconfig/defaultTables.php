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

//default ms3commerce tables
$GLOBALS['MS3C_TABLES'] = array (
	'Feature',
	'FeatureValue',
	'Groups',
	'GroupChildGroups',
	'GroupChildProducts',
	'GroupValue',
	'Menu',
	'Product',
	'ProductValue',
	'Document',
	'DocumentValue',
	'DocumentLink',
	'ShopInfo',
	'Relations'
);

if (!defined('MS3C_NO_SMZ') || MS3C_NO_SMZ != true) {
	$GLOBALS['MS3C_TABLES'][] = 'featureComp_feature';
	$GLOBALS['MS3C_TABLES'][] = 'featureCompilation';
	$GLOBALS['MS3C_TABLES'][] = 'FeatureCompValue';
}

if (defined('RealURLMap_TABLE')) {
	$GLOBALS['MS3C_TABLES'][] = RealURLMap_TABLE;
}

?>
