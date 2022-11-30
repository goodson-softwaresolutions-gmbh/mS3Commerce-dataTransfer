<?php
/***************************************************************
* Part of mS3 Commerce
* Copyright (C) 2022 Goodson GmbH <http://www.goodson.at>
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

define('MS3C_CMS_DB_FILE', MS3C_ROOT . '/dataTransfer/cms/woo/class.tx_ms3commerce_ms3woo_db.php');

define('MS3C_MS3W_SEMAPHORE_DIR', MS3C_EXT_ROOT . '/import/mS3/');
define('MS3C_MS3W_IMPORT_READY_FILE', MS3C_MS3W_SEMAPHORE_DIR.'import_ready');
define('MS3C_MS3W_IMPORT_BUSY_FILE', MS3C_MS3W_SEMAPHORE_DIR.'import_busy');

if (defined('MS3C_WOO_ONLY') && MS3C_WOO_ONLY) {
	// Is used by Sweep and Optimize. If only WooCommerce, those are NO-OPs
	$GLOBALS['MS3C_TABLES'] = array();
}

function mS3CMSUploadInitializeprocess($param, $arg)
{
	// Abort if import busy
	if (file_exists(MS3C_MS3W_IMPORT_BUSY_FILE)) {
		return array(false, false, 'mS3 WooCommerce Import Busy');
	}
	
	// Delete import ready
	if (file_exists(MS3C_MS3W_IMPORT_READY_FILE)) {
		$ok = @unlink(MS3C_MS3W_IMPORT_READY_FILE);
		if (!$ok) {
			return array(false, false, 'Cannot delete mS3 WooCommerce Import Ready file');
		}
	}
	
	// Clear m2w Database
	$ok = mS3WooClearTables();
	if (!$ok) {
		return array(false, false, 'mS3 WooCommerce Cannot clear database');
	}
	
	return array(true, false, 'mS3 WooCommerce Ready for Upload');
}

// Nothing to do...
//function mS3CMSUploadFinalizeprocess($param, $arg)
//{
//	
//}

function mS3WooClearTables()
{
	$db = tx_ms3commerce_db_factory_cms::connectToMS3WooDatabase();
	$sql = "SHOW TABLES LIKE 'm2w_%'";
	$rs = $db->sql_query($sql);
	if (!$rs) {
		// Cannot list M2W tables
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
	$ok = @writeZeroFile(MS3C_MS3W_IMPORT_READY_FILE);
	if (!$ok) {
		return array(false, false, 'Cannot create mS3 WooCommerce Import Ready file '.MS3C_MS3W_IMPORT_READY_FILE);
	}
	
	return array(true, false, 'mS3 WooCommerce Import Ready');
}

