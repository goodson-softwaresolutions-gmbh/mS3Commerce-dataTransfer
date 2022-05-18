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

if (MS3C_TYPO3_TYPE == 'FX') {
	define('MS3C_CMS_DB_FILE', MS3C_ROOT . '/dataTransfer/cms/typo3/class.tx_ms3commerce_typo3_db.php');
} else {
	define('MS3C_CMS_DB_FILE', MS3C_EXT_ROOT . '/typo3conf/ext/ms3commerce/pi1/class.tx_ms3commerce_db.php');
	// Typo3 always needs a TYPO3_MODE set!
	if (!defined('TYPO3_MODE')) {
		define('TYPO3_MODE', 'mS3Commerce');
	}
}

function mS3CMSSwitchDBPostprocess($prodDBId, $arg)
{
	if (MS3C_TYPO3_TYPE == 'FX') {
		if (defined('MS3C_TYPO3_CACHED') && MS3C_TYPO3_CACHED) {
			$classLoader = @include(MS3C_ROOT . '/../autoload.php');
			if (class_exists('\Ms3\Ms3CommerceFx\Service\CacheUtils')
			&& method_exists('\Ms3\Ms3CommerceFx\Service\CacheUtils', 'cleanT3CacheExternal')
			) {
				\Ms3\Ms3CommerceFx\Service\CacheUtils::cleanT3CacheExternal($classLoader);
			}
		}
	}

	return [true, false, ''];
}

?>
