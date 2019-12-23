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

define('MS3C_CMS_DB_FILE', MS3C_ROOT . '/dataTransfer/cms/oxid/class.tx_ms3commerce_ms3oxid_db.php');

define('MS3C_MS3O_SEMAPHORE_DIR', MS3C_EXT_ROOT . '/import/mS3/');
define('MS3C_MS3O_IMPORT_READY_FILE', MS3C_MS3O_SEMAPHORE_DIR.'import_ready');
define('MS3C_MS3O_IMPORT_BUSY_FILE', MS3C_MS3O_SEMAPHORE_DIR.'import_busy');

if (defined('MS3C_OXID_ONLY') && MS3C_OXID_ONLY) {
	// Is used by Sweep and Optimize. If only OXID, those are NO-OPs
	$GLOBALS['MS3C_TABLES'] = array();
}

function mS3CMSUploadInitializeprocess($param, $arg)
{
	// Abort if import busy
	if (file_exists(MS3C_MS3O_IMPORT_BUSY_FILE)) {
		return array(false, false, 'mS3Oxid Import Busy');
	}
	
	// Delete import ready
	if (file_exists(MS3C_MS3O_IMPORT_READY_FILE)) {
		$ok = @unlink(MS3C_MS3O_IMPORT_READY_FILE);
		if (!$ok) {
			return array(false, false, 'Cannot delete mS3Oxid Import Ready file');
		}
	}
	
	// Clear a2o Database
	$ok = mS3OxidClearTables();
	if (!$ok) {
		return array(false, false, 'mS3Oxid Cannot cleat database');
	}
	
	return array(true, false, 'mS3Oxid Ready for Upload');
}

// Nothing to do...
//function mS3CMSUploadFinalizeprocess($param, $arg)
//{
//	
//}

function mS3OxidClearTables()
{
	$db = tx_ms3commerce_db_factory_cms::connectToMS3OxidDatabase();
	$sql = "SHOW TABLES LIKE 'a2o_%'";
	$rs = $db->sql_query($sql);
	if (!$rs) {
		// Cannot list A2O tables
		return false;
	}
	
	while ($row = $db->sql_fetch_row($rs)) {
		$table = $row[0];
		$sql = "TRUNCATE TABLE $table";
		$result = $db->sql_query($sql);
		if (!$result) {
			// Cannot truncate table
			return false;
		}
	}
	
	$db->sql_free_result($rs);
	return true;
}

function mS3CMSSwitchDBPreprocess($prodDBId, $arg)
{
	$ok = @writeZeroFile(MS3C_MS3O_IMPORT_READY_FILE);
	if (!$ok) {
		return array(false, false, 'Cannot create mS3Oxid Import Ready file '.MS3C_MS3O_IMPORT_READY_FILE);
	}
	
	return array(true, false, 'mS3Oxid Import Ready');
}

?>
