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

// Typo3 always needs a TYPO3_MODE set!
if (!defined('TYPO3_MODE')) {
	define('TYPO3_MODE', 'mS3Commerce');
}

// tt_products specific tables:
$GLOBALS['MS3C_TABLES'][] = 'ShopPrices';
$GLOBALS['MS3C_TABLES'][] = 'ShopAvailability';

function mS3ShopSwitchDBPreprocess( $db, $arg )
{
	// Analyze parameters.
	// Format "(stage)[;(start)]"
	$stage = 1;
	$start = 0;
	$count = 500;
	if ($arg) {
		$matches = array();
		if ( preg_match('/(\d)(?:;(\d+))?/', $arg, $matches) ) {
			$stage = $matches[1];
			if (count($matches) > 2) {
				$start = $matches[2];
			}
		}
	}
	
	// Connect to DBs
	$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
	$ms3db = tx_ms3commerce_db_factory::buildDatabase(false, true);

	// Get Staging Type (Tables / Same Host / Different Host)
	if ( MS3COMMERCE_STAGETYPE == 'TABLES' ) {
		$switchType = 1;
	} else {
		$prod = tx_ms3commerce_db_factory_cms::getDatabaseConnectParams(false);
		$stag = tx_ms3commerce_db_factory_cms::getDatabaseConnectParams(true);
		if ($prod['host'] == $stag['host'] && !MS3C_DB_SWEEP_ALWAYS_ACROSS) {
			$switchType = 2;
		} else {
			$switchType = 3;
		}
	}
	
	switch ($switchType) {
	case 1:
		$stageTable = "tx_ms3commerce_tt_products_stage_" . MS3COMMERCE_STAGE_SUFFIX;
		break;
	case 2:
		$stageTable = tx_ms3commerce_db_factory::getDatabaseName(true).".tt_products_stage";
		break;
	case 3:
		break;
	}

	switch (intval($stage)) {
	case 1:
		// CREATE TEMP TABLE
		$sql = "CREATE TABLE IF NOT EXISTS __MS3C_tt_products_stage_update (`AsimOID` CHAR(36) NOT NULL, `pid` INT(11), `title` TINYTEXT, PRIMARY KEY (`AsimOID`) ) ENGINE=MyISAM, CHARACTER SET='UTF8';";
		if (!$t3db->sql_query($sql)) {
			return array(false,false,'Cannot create tt_products temp stage table: '.$t3db->sql_error());
		}
		$sql = "TRUNCATE TABLE __MS3C_tt_products_stage_update";
		if (!$t3db->sql_query($sql)) {
			return array(false,false,'Cannot empty tt_products temp stage table: '.$t3db->sql_error());
		}
		return array(true,true,"2;0");
		break;

	case 2:
		// INSERT INTO TEMP TABLE
		if ($switchType == 3) {
			// Case 3: DIFFERENT HOST
			// Sweep into
			$ret = sweepAcrossDBs(
					$ms3db,
					$t3db,
					'tt_products_stage',
					'__MS3C_tt_products_stage_update',
					array('AsimOid','pid','title'),
					'AsimOid',
					'',
					$start,
					true);
			if ( $ret == -1 ) {
				// Finsihed
				return array(true,true,"3");
			} else if ( intval($ret) ) {
				// Continue
				return array(true,true,"2;$ret");
			} else {
				// Error
				return array(false,false,$ret);
			}
		} else {
			// Others: Just insert
			$sqlClone = "REPLACE INTO __MS3C_tt_products_stage_update SELECT AsimOid,pid,title FROM $stageTable";
			if (!$t3db->sql_query($sqlClone)) {
				return array(false,false,"Cannot Clone stage table: " . $t3db->sql_error());
			}
			return array(true,true,"3");
		}
		break;
	case 3:
		// MAKE DIFF: Step 1: DELETE FROM tt_products
		$sqlDelete = "DELETE FROM tt_products WHERE AsimOID NOT IN (SELECT AsimOID FROM __MS3C_tt_products_stage_update)";
		if (!$t3db->sql_query($sqlDelete)) {
			return array(false,false,"Cannot delete from tt_products products: " . $t3db->sql_error());
		}
		return array(true,true,"4");
		
	case 4:
		// MAKE DIFF: Step 2: DELETE existing products FROM stage temp
		$sqlDelete = "DELETE FROM __MS3C_tt_products_stage_update WHERE AsimOID IN (SELECT AsimOID FROM tt_products)";
		if (!$t3db->sql_query($sqlDelete)) {
			return array(false,false,"Cannot delete stage products: " . $t3db->sql_error());
		}
		return array(true,true,"5");
		
	case 5:
		// MAKE DIFF: Step 3: INSERT
		$sqlInsert = 
			"INSERT INTO tt_products (AsimOID, pid, title, ".
				/* TEXT CAN'T HAVE DEFAULT! */
				"subtitle, note, note2, image, datasheet, color, color2, color3, size, size2, size3, description, gradings, material, quality, additional)".
			"SELECT AsimOID, pid, title, ".
				/* TEXT CAN'T HAVE DEFAULT! */
				"'', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''".
			" FROM __MS3C_tt_products_stage_update ".
			"LIMIT $start,$count";
		if (!$t3db->sql_query($sqlInsert)) {
			return array(false,false,"Cannot insert tt_products products: " . $t3db->sql_error());
		}
		
		if ( $t3db->sql_affected_rows() >= $count )
		{
			$newstart = $start+$count;
			return array(true,true,"5;$newstart");
		}
		return array(true,true,"6");

	case 6:
		// MAKE LANG OVERLAY
		$ttproductsSysLangUids = array();
		$rs = $t3db->sql_query("SELECT uid FROM sys_language");
		if (!$rs) {
			return array(false,false,"Cannot load sys languages: " . $t3db->sql_error());
		}
		while ($row = $t3db->sql_fetch_row($rs)) {
			$ttproductsSysLangUids[] = $row[0];
		}
		$t3db->sql_free_result($rs);
		
		if (count($ttproductsSysLangUids) > 0)
		{
			$t3db->sql_query('BEGIN');
			$sqlDelete = "TRUNCATE TABLE tt_products_language";
			$res = $t3db->sql_query($sqlDelete);
			if (!$res) {
				return array(false,false,"Cannot clear tt_products_language overlay: " . $t3db->sql_error());
			}
			

			foreach ($ttproductsSysLangUids as $sysLangUid)
			{
				$sqlInsert = 
				"INSERT INTO tt_products_language (prod_uid, sys_language_uid, 
					pid, tstamp, crdate, cruser_id, sorting, deleted, hidden, starttime, endtime, fe_group, title, subtitle, itemnumber, note, note2, unit, image, datasheet, www)
				SELECT uid, $sysLangUid,
					pid, tstamp, crdate, cruser_id, sorting, deleted, hidden, starttime, endtime, fe_group, title, subtitle, itemnumber, note, note2, unit, image, datasheet, www
				FROM tt_products";

				$res = $t3db->sql_query($sqlInsert);
				if (!$res) {
					return array(false,false,"Cannot insert tt_products_language overlay: " . $t3db->sql_error());
				}
			}
			$t3db->sql_query('COMMIT');
		}
		return array(true,true,"7");
		
	case 7:
		// CLEAN UP
		$sql = "DROP TABLE __MS3C_tt_products_stage_update";
		if (!$t3db->sql_query($sql)) {
			return array(false,false,"Cannot delete tt_products temp update table: " . $t3db->sql_error());
		}
		return array(true,false, "tt_products update" );
	}

	// Should never come here
	return array(false,false,"Internal error");
}

if (is_array($_GET) && array_key_exists('ttprodsalone', $_GET) && $_GET['ttprodsalone'] == '1') {
	set_time_limit(0);
	require_once(__DIR__ .'/../dataTransfer_config.php');
	require_once(MS3C_ROOT.'/dataTransfer/mS3Commerce_db.php');
	require_once(MS3C_ROOT.'/dataTransfer/mS3CommerceSweep.php');
	$arg = null;
	
	if (array_key_exists('arg', $_GET)) {
		$arg = $_GET['arg'];
	}
	
	list($ok, $cont, $arg) = mS3ShopSwitchDBPreprocess(null, $arg);
	if (!$ok) {
		echo $arg;
	} else if ($cont) {
		echo '<script>window.parent.location="tt_products.php?ttprodsalone=1&arg=' . $arg . '"</script>';
	} else {
		echo "SUCCESS";
	}
}

?>
