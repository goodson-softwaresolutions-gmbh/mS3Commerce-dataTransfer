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

define('MS3C_ROOT', realpath(dirname(dirname(__FILE__))));
if (!defined('MS3C_EXT_ROOT')) {
	define('MS3C_EXT_ROOT', MS3C_ROOT);
}

require_once(MS3C_ROOT.'/dataTransfer/mS3CommerceVersion.php');

require_once(MS3C_EXT_ROOT.'/dataTransfer/runtime_config.php');

require_once(MS3C_EXT_ROOT . "/dataTransfer/mS3CommerceStage.php");
require_once(MS3C_ROOT . "/dataTransfer/dbconfig/defaultTables.php");

define('MS3C_EXT_DIRECTORY', MS3C_EXT_ROOT.'/ext/'.MS3C_PRODUCTION_EXT_DIR);
// graphics root folder
define('MS3C_GRAPHICS_ROOT', MS3C_EXT_ROOT."/Graphics/");
// DB Config
define('MS3C_STAGE_CONFIG_FILE', MS3C_EXT_ROOT."/dataTransfer/mS3CommerceStage.php");

// CMS Specific setups
@include_once(MS3C_ROOT . "/dataTransfer/cms/".MS3C_CMS_TYPE.".php");

// Shop Specific setups
@include_once(MS3C_ROOT . "/dataTransfer/shop/".MS3C_SHOP_SYSTEM.".php");

@include_once(MS3C_ROOT . "/dataTransfer/search/".MS3C_SEARCH_BACKEND.".php");

// Custom setups
@include_once(MS3C_EXT_ROOT . "/dataTransfer/custom/mS3CommerceCustom.php");

if (!defined('MS3C_CMS_DB_FILE')) {
	define('MS3C_CMS_DB_FILE', MS3C_ROOT . '/dataTransfer/mS3CommerceDefaultDb.php');
}

require_once(MS3C_EXT_ROOT. '/dataTransfer/mS3CommerceDBAccess.php');

?>
