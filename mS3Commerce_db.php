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

require_once(MS3C_ROOT . "/dataTransfer/dataTransfer_config.php");
require_once MS3C_CMS_DB_FILE;

/**
 * Interface for database access 
 */
abstract class tx_ms3commerce_db
{
	public abstract function sql_affected_rows();
	public abstract function sql_error();
	public abstract function sql_info();
	public abstract function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '');
	public abstract function sql_num_rows($rs);
	public abstract function sql_insert_id();
	public abstract function sql_data_seek($rs, $row);
	public abstract function sql_fetch_all($rs);
	public abstract function sql_fetch_assoc($rs);
	public abstract function sql_fetch_object($rs);
	public abstract function sql_fetch_row($rs);
	public abstract function sql_free_result($rs);
	public abstract function sql_query($query, $tables = null);
	public abstract function map_table_name($name);
	public abstract function map_sql_from_tables($from, $defaultAlias = false, &$tables = array());
	public abstract function sql_escape($value,$quotes = true);
	public abstract function do_map_sql_query($query, $tables);
}

/**
 * Database backend for mysql extension
 */
class tx_ms3commerce_db_mysql extends tx_ms3commerce_db
{
	var $mydb;
	public function __construct($db, $host, $user, $pwd) {
		$this->mydb = mysql_connect($host, $user, $pwd, MS3C_DB_USE_NEW_LINK);
		if (!$this->mydb) {
			throw new Exception('DB Connect error: ' . $this->sql_error());
		}
		if (!mysql_select_db($db, $this->mydb)) {
			throw new Exception('DB Select error: ' . $this->sql_error());
		}
		$this->sql_query("SET NAMES 'utf8'");
	}
	
	public function sql_affected_rows() {
		return mysql_affected_rows($this->mydb);
	}
	public function sql_insert_id() {
		return mysql_insert_id($this->mydb);
	}
	public function sql_error() {
		return mysql_error($this->mydb) . ' (#' . mysql_errno($this->mydb) . ')';
	}
	public function sql_info() {
		return mysql_info($this->mydb);
	}
	public function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '') {
		$sql="SELECT $select FROM $from";
		if(!empty($where))
			$sql.=" WHERE $where";
		if(!empty($group))
			$sql.=" GROUP BY $group";
		if(!empty($order))
			$sql.=" ORDER BY $order";
		if(!empty($limit))
			$sql.=" LIMIT $limit";
		return $this->sql_query($sql);
	}
	public function sql_num_rows($rs) {
		if (!$rs) {
			throw new Exception("Null RS in #Rows");
		}
		return mysql_num_rows($rs);
	}
	public function sql_data_seek($rs, $row) {
		return mysql_data_seek($rs, $row);
	}
	public function sql_fetch_all($rs) {
		$res = array();
		while ($row = mysql_fetch_array($rs)) {
			$res[] = $row;
		}
		return $res;
	}
	public function sql_fetch_assoc($rs) {
		return mysql_fetch_assoc($rs);
	}
	public function sql_fetch_object($rs) {
		return mysql_fetch_object($rs);
	}
	public function sql_fetch_row($rs) {
		return mysql_fetch_row($rs);
	}
	public function sql_free_result($rs) {
		if (!$rs) {
			throw new Exception("Null RS in Free");
		}
		return mysql_free_result($rs);
	}
	public function sql_query($query, $tables = null) {
		return mysql_query($query, $this->mydb);
	}
	public function sql_close() {
		mysql_close($this->mydb);
		$this->mydb = null;
	}
	public function map_table_name($name) {
		return $name;
	}
	public function map_sql_from_tables($from, $defaultAlias = false, &$tables = array()) {
		return $from;
	}
	
	public function do_map_sql_query($query, $tables) {
		return $query;
	}
	public function sql_escape($value,$quotes = true) {
		$val = mysql_real_escape_string($value, $this->mydb);
		if ($quotes) {
			return "'$val'";
		} else {
			return $val;
		}
	}
}

/**
 * Database backend for mysqli extension
 */
class tx_ms3commerce_db_mysqli extends tx_ms3commerce_db
{
	var $mydb;
	var $key;
	static $connectionCache = array();
	public function __construct($db, $host, $user, $pwd) {
		// Find a cached connection
		$this->key = md5(join('::', array($host, $user, $pwd, $db)));
		$conn = self::getCachedConnection($this->key);
		if ($conn) {
			$this->mydb = $conn;
			return;
		}
		
		// Real connect
		$this->mydb = mysqli_connect($host, $user, $pwd, $db);
		if (!$this->mydb) {
			throw new Exception('DB Connect Error: '.mysqli_connect_error() . '(#'.mysqli_connect_errno().')');
		}
		$this->sql_query("SET NAMES 'utf8'");
		mysqli_set_charset($this->mydb, "utf8");
		
		self::cacheConnection($this->key, $this->mydb);
	}
	
	public function sql_affected_rows() {
		return mysqli_affected_rows($this->mydb);
	}
	public function sql_insert_id() {
		return mysqli_insert_id($this->mydb);
	}
	public function sql_error() {
		return mysqli_error($this->mydb) . ' (#' . mysqli_errno($this->mydb) . ')';
	}
	public function sql_info() {
		return mysqli_info($this->mydb);
	}
	public function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '') {
		$sql="SELECT $select FROM $from";
		if(!empty($where))
			$sql.=" WHERE $where";
		if(!empty($group))
			$sql.=" GROUP BY $group";
		if(!empty($order))
			$sql.=" ORDER BY $order";
		if(!empty($limit))
			$sql.=" LIMIT $limit";
		return $this->sql_query($sql);
	}
	public function sql_num_rows($rs) {
		if (!$rs) {
			throw new Exception("Null RS in #Rows");
		}
		return mysqli_num_rows($rs);
	}
	public function sql_data_seek($rs, $row) {
		return mysqli_data_seek($rs, $row);
	}
	public function sql_fetch_all($rs) {
		$res = array();
		while ($row = mysqli_fetch_array($rs)) {
			$res[] = $row;
		}
		return $res;
	}
	public function sql_fetch_assoc($rs) {
		return mysqli_fetch_assoc($rs);
	}
	public function sql_fetch_object($rs) {
		return mysqli_fetch_object($rs);
	}
	public function sql_fetch_row($rs) {
		return mysqli_fetch_row($rs);
	}
	public function sql_free_result($rs) {
		if (!$rs) {
			throw new Exception("Null RS in Free");
		}
		return mysqli_free_result($rs);
	}
	public function sql_query($query, $tables = null) {
		return mysqli_query($this->mydb, $query);
	}
	public function sql_close() {
		self::removeCachedConnection($this->key, $this->mydb);
		$this->mydb = null;
	}
	public function map_table_name($name) {
		return $name;
	}
	public function map_sql_from_tables($from, $defaultAlias = false, &$tables = array()) {
		return $from;
	}
	
	public function do_map_sql_query($query, $tables) {
		return $query;
	}
	public function sql_escape($value,$quotes = true) {
		$val = mysqli_real_escape_string($this->mydb, $value);
		if ($quotes) {
			return "'$val'";
		} else {
			return $val;
		}
	}
	
	private static function getCachedConnection($key) {
		if (MS3C_DB_USE_NEW_LINK) return null;
		if (array_key_exists($key, self::$connectionCache)) {
			$conn = self::$connectionCache[$key]['conn'];
			++self::$connectionCache[$key]['ct'];
			if (mysqli_ping($conn)) {
				return $conn;
			}
		}
		return null;
	}
	
	private static function cacheConnection($key, $conn) {
		if (MS3C_DB_USE_NEW_LINK) return;
		if (array_key_exists($key, self::$connectionCache)) {
			$ct = self::$connectionCache[$key]['ct'];
		} else {
			$ct = 1;
		}
		self::$connectionCache[$key] = array('conn' => $conn, 'ct' => $ct);
	}
	
	private static function removeCachedConnection($key, $conn) {
		if (MS3C_DB_USE_NEW_LINK) {
			mysqli_close($conn);
			return;
		}
		self::$connectionCache[$key]['ct']--;
		if (self::$connectionCache[$key]['ct'] <= 0) {
			mysqli_close(self::$connectionCache[$key]['conn']);
			unset(self::$connectionCache[$key]);
		}
	}
}

/**
 * Decorator for table name mapping, that forwards calls
 * to an internal itx_ms3commerce_db 
 */
class tx_ms3commerce_db_table_decorator extends tx_ms3commerce_db
{
	var $decorated;
	var $pattern;
	public function __construct(tx_ms3commerce_db $inner, $pattern) {
		$this->decorated = $inner;
		$this->pattern = $pattern;
	}
	
	public function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '') {
		$newFrom = $this->map_sql_from_tables($from, true);
		$res = $this->decorated->exec_SELECTquery($select, $newFrom, $where, $group, $order, $limit);
		if (!$res) {
			$i = 0;
		}
		return $res;
	}
	
	public function do_map_sql_query($query, $tables) {
		$mappedTables = array();
		$this->map_sql_from_tables($tables, false, $mappedTables);
		$newQuery = $query;
		foreach ($mappedTables as $map) {
			$newQuery = str_replace($map['origEntry'], $map['table'] . ' ' . $map['alias'], $newQuery);
		}
		return $newQuery;
	}
	
	public function sql_query($query, $tables = null) {
		if ($tables) {
			$query = $this->do_map_sql_query($query, $tables);
		}
		$res = $this->decorated->sql_query($query);
		if (!$res) {
			$i = 0;
		}
		return $res;
	}
	
	public function map_table_name($name) {
		return sprintf($this->pattern, $name);
	}
	
	public function map_sql_from_tables($from, $defaultAlias = false, &$tables = array()) {
		if (!is_array($from)) {
			$from = preg_split('/,/', $from);
		}
		$newFrom = '';
		$comma = '';
		$tables = array();
		foreach ($from as $table) {
			if (preg_match('/^`?(\w+)`?\s*(?:as)?\s*(`?\w+`?)?$/i', trim($table), $matches)) {
				$newTable = strtolower($this->map_table_name($matches[1]));
				if (count($matches) > 2)
					$alias = $matches[2];
				else if ($defaultAlias)
					$alias = $matches[1];
				else
					$alias = '';
				$tables[] = array(
					'origEntry' => trim($matches[0]),
					'origTable' => $matches[1],
					'table' => $newTable,
					'alias' => $alias
				);
				$newFrom .= $comma . $newTable . ' ' . $alias;
				$comma = ',';
			} else {
				throw new Exception('SQL From clause not parsable');
			}
		}
		
		return $newFrom;
	}
	
	// Pass through functions
	public function sql_affected_rows() {
		return $this->decorated->sql_affected_rows();
	}
	public function sql_insert_id() {
		return $this->decorated->sql_insert_id();
	}
	public function sql_error() {
		return $this->decorated->sql_error();
	}
	public function sql_info() {
		return $this->decorated->sql_info();
	}
	public function sql_num_rows($rs) {
		return $this->decorated->sql_num_rows($rs);
	}
	public function sql_data_seek($rs, $row) {
		return $this->decorated->sql_data_seek($rs,$row);
	}
	public function sql_fetch_all($rs) {
		return $this->decorated->sql_fetch_all($rs);
	}
	public function sql_fetch_assoc($rs) {
		return $this->decorated->sql_fetch_assoc($rs);
	}
	public function sql_fetch_object($rs) {
		return $this->decorated->sql_fetch_object($rs);
	}
	public function sql_fetch_row($rs) {
		return $this->decorated->sql_fetch_row($rs);
	}
	public function sql_free_result($rs) {
		return $this->decorated->sql_free_result($rs);
	}
	public function sql_close() {
		return $this->decorated->sql_close();
	}
	public function sql_escape($value,$quotes = true) {
		return $this->decorated->sql_escape($value,$quotes);
	}
}

/**
 * Factory building asim commerce db handlers 
 */
class tx_ms3commerce_db_factory
{
	public static function getDatabaseName($useStageDb = false) {
		$conn = tx_ms3commerce_db_factory_cms::getDatabaseConnectParams( $useStageDb );
		if ( $conn ) {
			return $conn['database'];
		}
		return null;
	}
        
	public static function getProductionDBId($useStageDb = false) {
		switch (MS3COMMERCE_STAGETYPE)
		{
		case 'TABLES': 
			return $useStageDb ? MS3COMMERCE_STAGE_SUFFIX : MS3COMMERCE_PRODUCTION_SUFFIX;
		case 'DATABASES': 
			return $useStageDb ? MS3COMMERCE_STAGE_DB : MS3COMMERCE_PRODUCTION_DB;
		}
		
		return null;
	}
	
	public static function buildDatabase($useCMS, $useStageDb = false) {
		switch (MS3COMMERCE_STAGETYPE)
		{
		case 'DATABASES':
			return tx_ms3commerce_db_factory::buildForDatabases($useCMS, $useStageDb);
		case 'TABLES':
			return tx_ms3commerce_db_factory::buildForTables($useCMS, $useStageDb);
		}
		
		return null;
	}
	
	private static function buildForDatabases($useCMS, $useStageDb) {
		if ($useCMS) {
			return tx_ms3commerce_db_factory_cms::buildForDatabases($useStageDb);
		}
		
		$dbconnect = tx_ms3commerce_db_factory_cms::getDatabaseConnectParams( $useStageDb );
		return self::connectToDatabase($dbconnect);
	}
	
	static function connectToDatabase($dbconnect)
	{
		if (!is_array($dbconnect) || !isset($dbconnect['host']) || !isset($dbconnect['database'])
				|| $dbconnect['host'] == '' || $dbconnect['database'] == '' )
		{
			return null;
		}
		switch (MS3C_DB_BACKEND) {
		case 'mysqli':
			return new tx_ms3commerce_db_mysqli(
					$dbconnect['database'], 
					$dbconnect['host'], 
					$dbconnect['username'], 
					$dbconnect['password']
					);
		case 'mysql':
			return new tx_ms3commerce_db_mysql(
					$dbconnect['database'], 
					$dbconnect['host'], 
					$dbconnect['username'], 
					$dbconnect['password']
					);
		default:
			throw new Exception('Unkown DB Backend');
		}
	}
	
	private static function buildForTables($useCMS, $useStageDb) {
		if ($useCMS) {
			$thedb = tx_ms3commerce_db_factory_cms::buildForTables( $useStageDb );
		} else {
			$thedb = self::buildForDatabases( false, $useStageDb );
		}
		
		if ( $useStageDb ) {
			$suffix = MS3COMMERCE_STAGE_SUFFIX;
		} else {
			$suffix = MS3COMMERCE_PRODUCTION_SUFFIX;
		}
		$pattern = "tx_ms3commerce_%s_$suffix";
		
		return new tx_ms3commerce_db_table_decorator( $thedb, $pattern );
	}
	
}

class mS3CommerceDBLogger
{
	private static $log = array();
	private static $last = null;
	public static function logStart($sql) {
		$sql = str_replace("\n", ' ', $sql);
		$sql = str_replace("\r", ' ', $sql);
		self::$last = array('sql' => $sql, 'start' => microtime(true));
	}
	public static function logEnd() {
		self::$last['end'] = microtime(true);
		self::$last['time'] = self::$last['end'] - self::$last['start'];
		self::$log[] = self::$last;
		self::$last = null;
	}
	public static function dump($ret = false, $pre = '', $post = '', $break = '\n') {
		$c = $pre;
		reset(self::$log);
		while ($cur = next(self::$log)) {
			$c .= $cur['time'].' -- '.$cur['sql'].$break;
		}
		$c .= $post;
		if ( $ret ) {
			return $c;
		}
		echo $c;
	}
}

?>
