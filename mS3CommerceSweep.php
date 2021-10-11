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

function reqSweepTables( $command )
{
	$first = false;
	$all = true;
	$curOff = 0;
	$sameDb = false;
	$curTable = "";
	$range = 0;
	$shops = array();
	$allShops = "";
	
	// FIND PARAMETERS
	// 1st Call: Shop-Ranges (1000000,1,2,3) OR Empty (=All)
	// 
	// Next Calls for TABLES:
	//   {ShopRanges OR Empty};2;Table-Name
	// Next Calls if in same DB:
	//   {ShopRanges OR Empty};1;Table-Name
	// Next Calls if in different DBs:
	//   {ShopRanges OR Empty};0;Table-Name;Offset
	// [Range,S1,S2,S3,...][;Type;Table[;Offset]]
	$pattern = '/((?:\d+,?)*)(?:;([012]);([^;]+)(?:;(\d+))?)?/';
	$matches = array();
	if ( preg_match($pattern, $command, $matches) ) {
		if (count($matches) <= 2) {
			// First Call
			$first = true;
		} else {
			// Later Call
			$first = false;
			$sameDb = $matches[2];
			$curTable = $matches[3];
			if (count($matches) > 4) {
				$curOff = $matches[4];
			}
		}
		
		$allShops = $matches[1];
		if ( $allShops != "" ) {
			$all = false;
			$shops = $allShops;
		}
	}
	
	if (!$all) {
		$shops = preg_split('/,/', $matches[1]);
		$range = array_shift($shops);
	}
	
	
	// BUILD WHERE CLAUSE
	$where = '';
	if (!$all) {
		foreach ($shops as $s) {
			$start = $range*$s;
			$end = $start+$range;
			$where .= "OR (Id >= $start AND Id < $end) ";
		}
		$where = ' WHERE '.substr($where, 2);
	}
	
	// GET INITIAL TABLE FOR FIRST CALL
	if ( $first ) {
		$curTable = $GLOBALS['MS3C_TABLES'][0];
	}
	
	// TABLE SWITCHED
	if ( MS3COMMERCE_STAGETYPE == 'TABLES') {
		return sweepTables($curTable, $where, $allShops);
	}
	
	// DB SWITCHED
	if ( $first ) {
		$paramsStage = tx_ms3commerce_db_factory_cms::getDatabaseConnectParams( true );
		$paramsProd = tx_ms3commerce_db_factory_cms::getDatabaseConnectParams( false );
		
		if ($paramsProd['host'] != $paramsStage['host']) {
			$sameDb = false;
		} else {
			// Very special case: SAME SCHEMA!
			if ( $paramsProd['database'] == $paramsStage['database'] ) {
				// Nothing to do at all!
				return responseMsg('success', 'Sweep NOP: Same Schema');
			}
			if ( MS3C_DB_SWEEP_ALWAYS_ACROSS ) {
				$sameDb = false;
			} else {
				$sameDb = true;
			}
		}
		
	}
	
	
	if ( $sameDb ) {
		return sweepSameDb($curTable, $where, $allShops);
	} else {
		return sweepDifferentDb($curTable, $curOff, $where, $allShops);
	}
}

function reqPostSweepDatabase($command)
{
	if (preg_match('/^((?:\d+,?)*);?(.*)/', $command, $matches)) {
		$prefix = $matches[1];
		$cmd = $matches[2];
	} else {
		$prefix = '';
		$cmd = $command;
	}
	
	return callPrePostProcess('SweepDB', 'Post', $cmd, $prefix, $prefix);
}

function sweepTables($table, $where, $allShops)
{
	$dbProd = tx_ms3commerce_db_factory::buildDatabase(false, false);
	$dbStage = tx_ms3commerce_db_factory::buildDatabase(false, true);
	
	$tableProd = $dbProd->map_sql_from_tables($table);
	$tableStage = $dbStage->map_sql_from_tables($table);
	
	if (strlen($where) == 0) {
		$sqlDel = "TRUNCATE TABLE $tableStage";
	} else {
		$sqlDel = "DELETE FROM $tableStage $where";
	}
	
	if ($table == RealURLMap_TABLE) {
		// Special case: If RealURLMap Table has an auto-increment (Primary) Key,
		// don't include it in field list!
		$sqlMeta = "SHOW COLUMNS FROM $tableStage";
		$rs = $dbStage->sql_query($sqlMeta);
		if (!$rs) {
			return "Cannot get Meta Data from table $tableStage: {$dbStage->sql_error()}";
		}
		$fields = "";
		while ($row = $dbStage->sql_fetch_assoc($rs)) {
			$fields .= ",{$row['Field']}";
			if ($row['Extra'] == 'auto_increment') {
				$fields .= " = NULL";
			}
		}
		$fields = substr($fields, 1);
		$dbStage->sql_free_result($rs);
	} else {
		$fields = "*";
	}
	$sqlIns = "INSERT INTO $tableStage SELECT $fields FROM $tableProd $where";
	
	$dbStage->sql_query("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;");
	$dbStage->sql_query("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */");
	$dbStage->sql_query("BEGIN");
	
	if (!$dbStage->sql_query($sqlDel)) {
		$res = responseMsg('error', "Cannot clear table $table: {$dbStage->sql_error()}");
		$dbStage->sql_query("ROLLBACK");
		return $res;
	}
	
	if (!$dbStage->sql_query($sqlIns)) {
		$res = responseMsg('error', "Cannot sweep into table $table: {$dbStage->sql_error()}");
		$dbStage->sql_query("ROLLBACK");
		return $res;
	}
	
	$dbStage->sql_query("COMMIT");
	
	$nextTable = getNextTable($table);
	if (!$nextTable) {
		return responseMsg('success','Table Sweep (same DB)');
	} else {
		return responseMsg('continue',"$allShops;2;$nextTable");
	}
}

function sweepSameDb($table, $where, $allShops)
{
	$dbProdName = tx_ms3commerce_db_factory::getDatabaseName(false);
	$dbStageName = tx_ms3commerce_db_factory::getDatabaseName(true);
	$dbStage = tx_ms3commerce_db_factory::buildDatabase(false, true);
	
	if (strlen($where) == 0) {
		$sqlDel = "TRUNCATE TABLE `$table`";
	} else {
		$sqlDel = "DELETE FROM `$table` $where";
	}
	
	if (defined('RealURLMap_TABLE') && $table == RealURLMap_TABLE) {
		// Special case: If RealURLMap Table has an auto-increment (Primary) Key,
		// don't include it in field list!
		$sqlMeta = "SHOW COLUMNS FROM `$table`";
		$rs = $dbStage->sql_query($sqlMeta);
		if (!$rs) {
			return "Cannot get Meta Data from table $table: {$dbStage->sql_error()}";
		}
		$fields = "";
		while ($row = $dbStage->sql_fetch_assoc($rs)) {
			$fields .= ",{$row['Field']}";
			if ($row['Extra'] == 'auto_increment') {
				$fields .= " = NULL";
			}
		}
		$fields = substr($fields, 1);
		$dbStage->sql_free_result($rs);
	} else {
		$fields = "*";
	}
	
	$sqlIns = "INSERT INTO $dbStageName.`$table` SELECT $fields FROM $dbProdName.`$table` $where";
	
	$dbStage->sql_query("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;");
	$dbStage->sql_query("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */");
	$dbStage->sql_query("BEGIN");
	
	if (!$dbStage->sql_query($sqlDel)) {
		$res = responseMsg('error', "Cannot clear table $table: {$dbStage->sql_error()}");
		$dbStage->sql_query("ROLLBACK");
		return $res;
	}
	
	if (!$dbStage->sql_query($sqlIns)) {
		$res = responseMsg('error', "Cannot sweep into table $table: {$dbStage->sql_error()}");
		$dbStage->sql_query("ROLLBACK");
		return $res;
	}
	
	$dbStage->sql_query("COMMIT");
	
	$nextTable = getNextTable($table);
	if (!$nextTable) {
		return responseMsg('success','Table Sweep (same DB)');
	} else {
		return responseMsg('continue',"$allShops;1;$nextTable");
	}
}

function sweepDifferentDb($table, $off, $where, $allShops)
{
	$dbProd = tx_ms3commerce_db_factory::buildDatabase(false, false);
	$dbStage = tx_ms3commerce_db_factory::buildDatabase(false, true);

	$newOff = sweepAcrossDBs( 
			$dbProd,
			$dbStage,
			$table,
			$table,
			"*",
			"Id",
			$where,
			$off);
	
	if ($newOff == -1) {
		// NO VALUES! Continue with next table
		$nextTable = getNextTable($table);
		if (!$nextTable) {
			return responseMsg('success', 'Table Sweep (different DB)');
		} else {
			return responseMsg('continue', "$allShops;0;$nextTable");
		}
	}
	
	
	if ( intval($newOff) != 0 )	{
		return responseMsg('continue', "$allShops;0;$table;$newOff");
	}
	
	// ERROR
	return responseMsg('error', $newOff);
}

function sweepAcrossDBs(tx_ms3commerce_db $source, tx_ms3commerce_db $dest, $srcTable, $destTable, $fields, $order, $where, $off, $doReplace)
{
	$source->sql_query("SET NAMES 'UTF8'");
	$dest->sql_query("SET NAMES 'UTF8'");
	
	$count = 500;
	
	// DELETE
	if (strlen($where) == 0) {
		$sqlDel = "TRUNCATE TABLE `$destTable`";
	} else {
		$sqlDel = "DELETE FROM `$destTable` $where";
	}
	if ( $off == 0 ) {
		if ( !$dest->sql_query($sqlDel) )
		{
			return "Cannot clear table $destTable: {$dest->sql_error()}";
		}
	}
	
	// TOTAL
	$sqlTotal = "SELECT COUNT(*) FROM `$srcTable` $where";
	$rs = $source->sql_query($sqlTotal);
	if (!$rs) {
		return "Cannot get total from table $srcTable: {$source->sql_error()}";
	}
	$row = $source->sql_fetch_row($rs);
	$total = $row[0];
	unset($row);
	$source->sql_free_result($rs);
	
	// META
	$sqlMeta = "SHOW COLUMNS FROM `$srcTable`";
	$rs = $source->sql_query($sqlMeta);
	if (!$rs) {
		return "Cannot get Meta Data from table $srcTable: {$source->sql_error()}";
	}
	$cols = array();
	$fieldsTypes = array();
	while ($row = $source->sql_fetch_assoc($rs)) {
		// CHECK FIELDS
		if ( $fields != "*" ) {
			if (array_search($row['Field'], $fields) === false) {
				// Not in update list!
				continue;
			}
		}
		
		$cols[] = $row['Field'];
		$type = strtolower($row['Type']);
		if (strstr($type, "text") || strstr($type, "char") || strstr($type, "blob") || strstr($type, "clob")) {
			$fieldsTypes[] = 'T';
		} else {
			$fieldsTypes[] = 'N';
		}
	}
	unset($row);
	$source->sql_free_result($rs);
	
	// SELECT
	if ($fields != "*") {
		$fields = implode(',', $fields);
	}
	$sqlSel = "SELECT $fields FROM `$srcTable` $where ORDER BY $order LIMIT $off,$count";
	$rs = $source->sql_query($sqlSel);
	if (!$rs) {
		return responseMsg('error', "Cannot get total from table $srcTable: {$source->sql_error()}");
	}
	$bulk = '';
	while ($row = $source->sql_fetch_assoc($rs)) {
		$values = '';
		for ($i = 0; $i < count($cols); $i++) {
			$val = $row[$cols[$i]];
			if ( $fieldsTypes[$i] == 'T' ) {
				$val = $dest->sql_escape($val, true);
			}
			if (strlen($val) == 0) {
				$val = "NULL";
			}
			$values .= $val . ',';
		}
		$values = substr($values, 0, -1);
		$bulk .= "($values),";
	}
	
	if (!$bulk) {
		return -1;
	}
	
	if ( $doReplace ) {
		$INSERT = "REPLACE";
	} else {
		$INSERT = "INSERT";
	}
	$bulk = substr($bulk, 0, -1);
	$bulk = "$INSERT INTO `$destTable` (".implode(',', $cols).") VALUES " . $bulk;
	unset($row);
	$source->sql_free_result($rs);
	
	$dest->sql_query("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;");
	$dest->sql_query("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */");
	$dest->sql_query("BEGIN");
	if (!$dest->sql_query($bulk)) {
		$res = responseMsg('error', "Cannot insert into table $destTable: {$dest->sql_error()}");
		$dest->sql_query("ROLLBACK");
		return $res;
	}
	$dest->sql_query("COMMIT");
	
	$off += $count;
	
	if ($off >= $total) {
		return -1;
	} else {
		return $off;
	}
}

function getNextTable($oldTable)
{
	$tables = $GLOBALS['MS3C_TABLES'];
	$idx = array_search($oldTable, $tables);
	$idx++;
	if ( $idx >= count($tables)) {
		return false;
	}
	return $tables[$idx];
}

?>