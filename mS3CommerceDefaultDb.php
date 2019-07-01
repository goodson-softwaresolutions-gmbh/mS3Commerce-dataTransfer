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

require_once MS3C_ROOT.'/dataTransfer/mS3Commerce_db.php';

class tx_ms3commerce_db_factory_cms
{
	static function getDatabaseConnectParams($useStageDb = false)
	{
		$dbConf = MS3C_DB_ACCESS();
		switch (MS3COMMERCE_STAGETYPE) {
			case 'TABLES': 
				$db = 'tables'; 
				break;
			case 'DATABASES':
				if ($useStageDb) {
					$db = MS3COMMERCE_STAGE_DB;
				} else {
					$db = MS3COMMERCE_PRODUCTION_DB;
				}
		}
		
		$conf = $dbConf[$db];
		return $conf;
	}
}
?>
