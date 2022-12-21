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

require_once(__DIR__ .'/dataTransfer_config.php');
require_once(MS3C_ROOT.'/dataTransfer/mS3Commerce_db.php');

if (MS3COMMERCE_STAGETYPE == 'TABLES')
	$stageDb = MS3COMMERCE_STAGE_SUFFIX;
else
	$stageDb = MS3COMMERCE_STAGE_DB;

$query = '';
$useStage = false;

if ( $_POST )
{
	if ( array_key_exists('query', $_POST) && isset($_POST['query']) ) {
		$query = $_POST['query'];
		//$query = urldecode($query);
		//$query = str_replace("\\\"", "\"", $query);
		//$query = str_replace("\\'", "'", $query);
		echo $query;
	}
	if ( array_key_exists('useStage', $_POST) && isset($_POST['useStage']) ) {
		$useStage = intval($_POST['useStage']) == 1;
	}
}

$stageChecked = $useStage ? "checked" : "";

$tablemap = "";
$addFields = "";
if (MS3COMMERCE_STAGETYPE == 'TABLES') {
	if ( array_key_exists('tables', $_POST) && isset($_POST['tables']) ) {
		$tablemap = $_POST['tables'];
		//$tablemap = urldecode($tablemap);
	}
	$addFields = 'Tables: <input type="text" name="tables" value="'.$tablemap.'"/><br/>';
}

echo <<<EOT
<html>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<head></head>
	<body>
		<form action="execsql.php" method="post">
			<textarea name="query" cols="80" rows="7">$query</textarea><br/>
			$addFields
			<input type="checkbox" value="1" name="useStage" $stageChecked/> Use Stage</br>
			<input type="submit" value="execute"/><br/>
		</form>
EOT;

if ($query && checkquery( $query ))
{
	exec_sql($query, $tablemap, $useStage);
}

echo "	</body>
</html>";

function exec_sql( $query, $tablemap, $useStage )
{
	$db = tx_ms3commerce_db_factory::buildDatabase(false, $useStage);
	$db->sql_query("SET NAMES UTF8");
	$db->sql_query('SET SQL_BIG_SELECTS=1;');
	$res = $db->sql_query($query, $tablemap);
	if (!$res) {
		echo "!! ".$db->sql_error();
		return;
	}

	$o = $db->sql_fetch_assoc($res);
	if (!$o) {
		echo "(empty set)";
		return;
	}
	
	echo "<table border=\"1\"><tr>";
	$row = "";
	foreach ($o as $k => $v)
	{
		if (empty($v)) $v = "&nbsp;";
		echo "<td>$k</td>";
		$row .= "<td>$v</td>";
	}
	echo "</tr><tr>$row</tr>";
	while ($o = $db->sql_fetch_assoc($res))
	{
		echo "<tr>";
		foreach ($o as $v)
		{
			if (empty($v)) $v = "&nbsp;";
			echo "<td>$v</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
}

function checkquery( $query )
{
	$querylow = strtolower($query);
	return
		doCheck($querylow, 'delete') &&
		doCheck($querylow, 'insert') &&
		doCheck($querylow, 'update') &&
		doCheck($querylow, 'alter') &&
		doCheck($querylow, 'create') &&
		doCheck($querylow, 'drop') &&
		doCheck($querylow, 'truncate') &&
		doCheck($querylow, 'transaction') &&
		doCheck($querylow, 'commit') &&
		doCheck($querylow, 'rollback') &&
		doCheck($querylow, 'use') &&
		doCheck($querylow, 'rename') &&
		doCheck($querylow, 'call') &&
		doCheck($querylow, 'do') &&
		doCheck($querylow, 'handler') &&
		doCheck($querylow, 'load\s+data') &&
		doCheck($querylow, 'replace') &&
		doCheck($querylow, 'savepoint') &&
		doCheck($querylow, 'lock') &&
		doCheck($querylow, 'unlock') &&
		doCheck($querylow, 'show') &&
		doCheck($querylow, 'purge') &&
		doCheck($querylow, 'flush') &&
		doCheck($querylow, 'reset') &&
		doCheck($querylow, 'prepare') &&
		doCheck($querylow, 'execute') &&
		doCheck($querylow, 'deallocate') &&
		doCheck($querylow, 'declare') &&
		doCheck($querylow, 'begin') &&
		doCheck($querylow, 'set')
		;
}

function doCheck($query,$key)
{
	if (preg_match("/\b$key\b/i",$query)) {
		echo 'Query must not contain "'.$key.'"';
		return false;
	}
	return true;
}

?>
