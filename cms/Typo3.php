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

define('MS3C_CMS_DB_FILE', MS3C_EXT_ROOT . '/typo3conf/ext/ms3commerce/pi1/class.tx_ms3commerce_db.php');

// Typo3 always needs a TYPO3_MODE set!
if (!defined('TYPO3_MODE')) {
	define('TYPO3_MODE', 'mS3Commerce');
}

?>
