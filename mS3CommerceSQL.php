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

//mS3CommerceSQL.php

require_once(MS3C_ROOT.'/dataTransfer/mS3Commerce_db.php');
require_once(MS3C_ROOT.'/dataTransfer/lib/mS3CommerceLib.php');

function reqUploadSQL($uploadedFilePath) {
	$dblink = connectDbByRequestModule();
	$sqlClear = getParameter('clear');

	executeSQL($dblink, "SET NAMES 'utf8'", "set to utf8");
	executeSQL($dblink, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;", "set unique_key check");
	executeSQL($dblink, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;", "set foreign_key check");
	if ($sqlClear != '') {
		return executeSQL($dblink, $sqlClear, $sqlClear);
	}
	$sqlData = getParameter('cmd') . " " . readUploadedFile($uploadedFilePath);
	return executeSQL($dblink, $sqlData, getParameter('cmd'));
}

function reqCreateDatabase($uploadedFilePath) {
	$dblink = connectDbByRequestModule();
	
	executeSQL($dblink, "SET NAMES 'utf8'", "set to utf8");
	executeSQL($dblink, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;", "set unique_key check");
	executeSQL($dblink, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;", "set foreign_key check");

	$create = readUploadedFile($uploadedFilePath);

	$statements = preg_split('/;/', $create);
	$success = "<type>success</type>";
	foreach ($statements as $s) {
		if (strlen(trim($s)) == 0)
			continue;

		if (preg_match('/([^\(]*)/', $s, $cmd)) {
			$cmd = $cmd[1];
		} else {
			$cmd = s;
		}
		$res = executeSQL($dblink, $s, $cmd);

		if (strstr($res, $success) === false) {
			return $res;
		}
	}

	return responseMsg('success', 'Create DB');
}

function reqPostUpload($command) {
	$shop = getParameter('ms3shop');
	if (!$shop) {
		return responseMsg('error', 'parameter "ms3shop" is empty!');
	}
	return callPrePostProcess('Upload', 'Post', $command, $shop);
}

function mS3CoreUploadPostprocess($param, $arg)
{
	$shop = $param;
	// Update ShopInfo to set Import Date
	// ShopInfo only available if not in OXID/Magento Only mode
	if (hasCommerceDatabase()) {
		$db = connectToMS3CommerceDB();
		date_default_timezone_set('Europe/Vienna');
		$uploadDate = date('c');
		$uploadDate = $db->sql_escape($uploadDate);
		$sql = "UPDATE ShopInfo SET UploadDate = $uploadDate WHERE ShopId = $shop";
		$db->sql_query($sql);
	}
	
	return array(true, false, 'Core Post Upload successful');
}

function reqSwitchDatabase() {
	$currentActiveDB = switchMS3CommerceDB();

	// Local DB Config Tempalte
	$dbConfigTmplDir = MS3C_ROOT . "/dataTransfer/dbconfig";
	$dbConfigTmplFile = $dbConfigTmplDir . "/" . $currentActiveDB . ".php";

	if (!copy($dbConfigTmplFile, MS3C_STAGE_CONFIG_FILE)) {
		return responseMsg('error', 'Could not copy database config file: ' . $dbConfigTmplFile . " " . MS3C_STAGE_CONFIG_FILE);
	}

	return responseMsg('success', 'Make database active: ' . $currentActiveDB);
}

function reqPreSwitchDatabase($command) {
	return callPrePostProcess('SwitchDB', 'Pre', $command, getStagingDatabaseId());
}

function reqPostSwitchDatabase($command) {
	return callPrePostProcess('SwitchDB', 'Post', $command, getProductionDatabaseId());
}

function reqImportDB() {
	if (!hasCommerceDatabase()) {
		return responseMsg('success', 'No Commerce installation (no staging DB)');
	} else {
		$importDB = getNotActiveMS3CommerceDB();
		$importId = getStagingDatabaseId();
		return responseMsg('success', 'Current Import Database: ' . $importDB . ' (' . $importId . ')');
	}
}


function reqOptimizeTable($args)
{
	if (empty($args)) {
		return responseMsg('error', 'Staging or Production not specified');
	}
	
	$args = explode(';', $args);
	
	$type = array_shift($args);
	if ($type == "stage") {
		$useStage = true;
	} else if ($type == "production") {
		$useStage = false;
	}
	
	if (empty($args)) {
		$table = @$GLOBALS['MS3C_TABLES'][0];
	} else {
		$table = array_shift($args);
	}
	
	if (empty($table)) {
		return responseMsg('success', 'Optimize finished (no more tables to optimize)');
	}
	
	// OPTIMIZE!!!
	set_time_limit(0);
	$db = tx_ms3commerce_db_factory::buildDatabase(false, $useStage);
	if ($db == null) {
		// Special case if there is OXID/Magento Only: no normal database is valid
		if (!hasCommerceDatabase()) {
			return responseMsg('success', 'Optimize finished (no commerce databases present)');
		} else {
			return responseMsg('error', 'Error in optimize: Cannot connect to database');
		}
	}
	$res = $db->sql_query("OPTIMIZE TABLE `$table`", $table);
	
	$err = null;
	while ($row = $db->sql_fetch_assoc($res)) {
		if ($row['Msg_type'] == 'Error') {
			$err = $row['Msg_text'];
			break;
		}
	}
	$db->sql_free_result($res);
	
	if (!empty($err)) {
		return responseMsg('error', 'Error in optimize: ' .$err);
	}
	
	$nextTable = getNextTable($table);
	if ($nextTable === false) {
		return responseMsg('success', 'Optimize finished');
	}
	return responseMsg('continue', "$type;$nextTable");
}

function executeSQL($dblink, $sql, $infoCmd) {
	if (MS3COMMERCE_STAGETYPE == 'TABLES') {
		$matches = array();
		if (preg_match('/\s*(?:DELETE\b\s*FROM\b\s*|INSERT\b\s*INTO\b\s*|UPDATE\b\s*|ALTER\b\s*TABLE\b\s*)([^\s]*)/i', $sql, $matches)) {
			$result = $dblink->sql_query($sql, $matches[1]);
		} else {
			if (preg_match('#^\s*/\*.*\*/\s*;?\s*$#', $sql) ||
					preg_match('/^\s*SET\b\s*NAMES\b\s*\'UTF8\'\s*;?\s*/i', $sql)) {
				$result = $dblink->sql_query($sql);
			} else {
				// For CREATE DB requests
				if (MS3C_ALLOWCREATE_SQL && preg_match('/\s*(?:CREATE\b\s*TABLE\b\s*|DROP\b\s*TABLE\b\s*\b\s*IF\b\s*EXISTS\b\s*)([^\s]*)/i', $sql, $matches)) {
					$result = $dblink->sql_query($sql, $matches[1]);
				} else {
					return( responseMsg('error', 'Cannot parse table name from SQL: ' . $sql) );
				}
			}
		}
	} else {
		$result = $dblink->sql_query($sql);
	}

	if (!$result) {
		return responseMsg('error', 'query: ' . $dblink->sql_error() . ': ' . $infoCmd);
	}
	return responseMsg('success', 'query: ' . $dblink->sql_info() . '; ' . getNotActiveMS3CommerceDB() . '; ' . $infoCmd);
}

function connectToMS3CommerceDB() {
	global $typo_db_username;
	global $typo_db_host;
	global $typo_db;
	global $typo_db_password;
	return tx_ms3commerce_db_factory::buildDatabase(false, true);
}

function connectToMS3OxidDB() {
	if (MS3C_CMS_TYPE != 'OXID') {
		die('This is not a mS3Oxid installation');
	}
	return tx_ms3commerce_db_factory_cms::connectToMS3OxidDatabase();
}

function connectToMS3MagentoDB() {
	if (MS3C_CMS_TYPE != 'Magento') {
		die('This is not a mS3 Magento installation');
	}
	return tx_ms3commerce_db_factory_cms::connectToMS3MagentoDatabase();
}

function connectToMS3ShopwareDB() {
	if (MS3C_CMS_TYPE != 'Shopware') {
		die('This is not a mS3 Shopware installation');
	}
	return tx_ms3commerce_db_factory_cms::connectToMS3ShopwareDatabase();
}

function connectToMS3WooDB() {
	if (MS3C_CMS_TYPE != 'Woo') {
		die('This is not a mS3 WooCommerce installation');
	}
	return tx_ms3commerce_db_factory_cms::connectToMS3WooDatabase();
}

function connectDbByRequestModule()
{
	$module = getParameter('ms3module');
	if ($module == 'oxid') {
		$dblink = connectToMS3OxidDB();
	} else if ($module == 'magento') {
		$dblink = connectToMS3MagentoDB();
	} else if ($module == 'shopware') {
		$dblink = connectToMS3ShopwareDB();
	} else if ($module == 'woo') {
		$dblink = connectToMS3WooDB();
	} else if ($module == '') {
		$dblink = connectToMS3CommerceDB();
	}
	return $dblink;
}

function closeDB($dblink) {
	$dblink->sql_close();
}

function getNotActiveMS3CommerceDB() {
	if (!hasCommerceDatabase()) {
		return '';
	}
	global $typo_db_username;
	global $typo_db_host;
	global $typo_db;
	global $typo_db_password;
	return tx_ms3commerce_db_factory::getDatabaseName(true);
}

function getActiveMS3CommerceDB() {
	if (!hasCommerceDatabase()) {
		return '';
	}
	global $typo_db_username;
	global $typo_db_host;
	global $typo_db;
	global $typo_db_password;
	return tx_ms3commerce_db_factory::getDatabaseName(false);
}

function switchMS3CommerceDB() {
	return getStagingDatabaseId();
}

function getStagingDatabaseId() {
	if (MS3COMMERCE_STAGETYPE == 'DATABASES') {
		return MS3COMMERCE_STAGE_DB;
	} else if (MS3COMMERCE_STAGETYPE == 'TABLES') {
		return MS3COMMERCE_STAGE_SUFFIX;
	}
}

function getProductionDatabaseId() {
	if (MS3COMMERCE_STAGETYPE == 'DATABASES') {
		return MS3COMMERCE_PRODUCTION_DB;
	} else if (MS3COMMERCE_STAGETYPE == 'TABLES') {
		return MS3COMMERCE_PRODUCTION_SUFFIX;
	}
}

?>
