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

class mS3CommerceUtils {
	/**
	 * Escapes an array of strings for SQL
	 * @param tx_ms3commerce_db $db The database link to escape for
	 * @param mixed $values the values to escape as array, or as single string
	 * @param boolean $withQuotes If quotes should be added to escaped values
	 * @return mixed The escaped values
	 */
	public static function array_escape($db, $values, $withQuotes = true) {
		if (is_array($values)) {
			foreach ($values as &$v) {
				if ($v === NULL) {
					$v = 'DEFAULT';
				} else {
					$v = $db->sql_escape($v, $withQuotes);
				}
			}
			return $values;
		} else {
			if ($values === NULL) {
				return 'DEFAULT';
			} else {
				return $db->sql_escape($values, $withQuotes);
			}
		}
	}
	
	/**
	 * Splits a line from CSV into single parts
	 * @param string $line The line to split
	 * @param string $delim The delimiter terminating fields
	 * @param string $escape An optional escaping character for enclosing
	 * values
	 * @return array The input line split into single fields
	 */
	public static function splitCSVLine($line, $delim = ';', $escape = '"') {
		$parts = explode($delim, $line);
		if (!is_null($escape)) {
			for ($i = 0; $i < count($parts); ++$i) {
				$n=substr_count($parts[$i],$escape); 
				
				if (substr($parts[$i], 0, 1) == $escape && $n==1) {
					$j = $i;
					while (($parts[$j] == $escape || substr($parts[$j], -1, 1) != $escape) && $i < count($parts)) {
						$parts[$j].=$delim . $parts[++$i];
						unset($parts[$i]);
					}
					$parts[$j] = substr($parts[$j], 1, strlen($parts[$j])-2);
				}else{
					if($n>1){
						$parts[$i]= str_replace($escape, '', $parts[$i]);
					}
					
				}
			}

			// re-index result
			$parts = array_values($parts);
		}
		return $parts;
	}
}

/**
 * Reads CSV Files line-by-line and returns selected fields as associative array.
 * Handles escaped fields, multi-line fields, etc.
 */
class CSVReader {
	
	private $fp = false;
	private $filename;
	private $map;
	private $utf8;
	private $delim;
	private $escape;
	
	/**
	 * Creates a CSV Reader
	 * @param string $file The file path
	 * @param array $map Field-Mapping. The key is the name in the resulting
	 * map, the value the 0-based index of the field in the CSV
	 * @param string $delim CSV-delimiter character
	 * @param string $escape Optional field enclosing characater
	 * @param boolean $utf8 If the file is in UTF8. If false, all lines will be
	 * encoded using utf8_encode
	 */
	public function __construct($file, $map, $delim = ';', $escape = '"', $utf8 = true) {
		$this->init($file, $map, $delim, $escape, $utf8);
	}
	public function __destruct() {
		$this->close();
	}
	/**
	 * Re-initializes a CSV Reader
	 * @param string $file The file path
	 * @param array $map Field-Mapping. The key is the name in the resulting
	 * map, the value the 0-based index of the field in the CSV
	 * @param string $delim CSV-delimiter character
	 * @param string $escape Optional field enclosing characater
	 * @param boolean $utf8 If the file is in UTF8. If false, all lines will be
	 * encoded using utf8_encode
	 */
	public function init($file, $map, $delim = ';', $escape = '"', $utf8 = true) {
		$this->filename = $file;
		$this->map = $map;
		$this->delim = $delim;
		$this->escape = $escape;
		$this->utf8 = $utf8;
		$this->openFile();
	}
	/**
	 * Closes the underlying file of a CSV Reader 
	 */
	public function close() {
		if ($this->fp) {
			fclose($this->fp);
			$this->fp = false;
		}
	}
	
	public function isValid() {
		return $this->fp != false;
	}
	
	/**
	 * Skips $count lines of input
	 * @param int $count Number of lines to skip
	 */
	public function skipLine($count) {
		if (!$this->fp) {
			return;
		}
		
		$ct = 0;
		while ($ct < $count && !feof($this->fp)) {
			$this->doReadLine();
			$ct++;
		}
	}
	
	/**
	 * Reads a single line and returns a mapped representation.
	 * @return array The mapped line, or null if EOF
	 */
	public function readLine() {
		$line = $this->doReadLine();
		
		if (is_null($line)|| $line=='') {
			return null;
		}
		$parts = mS3CommerceUtils::splitCSVLine($line, $this->delim, $this->escape);
		
		// Map
		$ret = array();
		foreach ($this->map as $k => $v) {
			@$ret[$k] = $parts[$v];
		}
		return $ret;
	}
	
	/**
	 * Reads lines until a complete CSV line is read, handling multiple-line escapes
	 * @return string The read line, or null on EOF 
	 */
	private function doReadLine() {
		if (!$this->fp || feof($this->fp)) {
			return null;
		}
		$line = fgets($this->fp);
			
		// Handle mutli-line escaped fields
		if (!is_null($this->escape)) {
			// Form of '/;"[^"]*$/'
			// Checks if string ends in open escape
			$patternStart = "/{$this->delim}{$this->escape}[^{$this->escape}]*$/";
			// Form of '/[^"]*"/'
			$patternEnd = "/{$this->escape}[^{$this->escape}]*{$this->escape}/";
			if (preg_match($patternStart, $line)) {
				do {
					$l = fgets($this->fp);
					$line .= $l;
				} while (!preg_match($patternEnd, $l) && $l);
			}
		}

		if (!$this->utf8) {
			$line = utf8_encode($line);
		}
		$line = rtrim($line, "\n\r");
		return $line;
	}
	
	/**
	 * Opens the file 
	 */
	private function openFile() {
		$this->close();
		$this->fp = fopen($this->filename, "r");
	}
}


/**
 * Bulk-Inserts records into a table. 
 */
class InsertContainer {
	/** @var tx_ms3commerce_db */
	private $db;
	private $table;
	private $fields;
	private $limit;
	private $values;
	private $errors;
	public $throwOnError = false;
	
	/**
	 * Creates an Insert Container for a given table.
	 * @param tx_ms3commerce_db $db The database link
	 * @param string $table The table name
	 * @param array $fields The fields for inserts
	 * @param int $limit Bulk-limit when to commit the collected values
	 */
	public function __construct($db, $table, $fields, $limit = 500) {
		$this->db = $db;
		$this->init($table, $fields, $limit);
	}
	/**
	 * Re-initializes the container to a new table
	 * @param string $table The table name
	 * @param array $fields The fields for inserts
	 * @param int $limit Bulk-limit when to commit the collected values
	 */
	public function init($table, $fields, $limit = 500) {
		$this->table = $table;
		$this->fields = $fields;
		$this->limit = $limit;
		$this->clearAll();
	}
	/**
	 * Clears all stored values and errors 
	 */
	public function clearAll() {
		$this->errors = array();
		$this->values = array();
	}
	/**
	 * Adds insert-values for a single insert. If the bulk-limit is reached,
	 * all values up to now will be committed to the database
	 * @param array $vals The values. No escaping is done
	 * @return boolean If bulk limit was reached, the result of the write-operation,
	 * otherwise true
	 */
	public function addInsert($vals) {
		$this->values[] = $vals;
		if (count($this->values) > $this->limit) {
			return $this->write();
		}
		return true;
	}
	public function hasError() {
		return count($this->errors) > 0;
	}
	/**
	 * Returns list of collected errors during writing
	 * @return array Collected error messages
	 */
	public function getErrors() {
		return $this->errors;
	}
	/**
	 * Commits all collected inserts into the database.
	 * @return boolean If the write was successful
	 * @throws Exception If $throwOnError is true, an exception is thrown on error
	 * instead of returning false
	 */
	public function write() {
		if (empty($this->values)) {
			return true;
		}
		$sql = "INSERT INTO {$this->table} (".join(',', $this->fields).") VALUES ";
		foreach ($this->values as $row) {
			$sql .= '('.join(',',$row).'),';
		}
		$sql = substr($sql, 0, strlen($sql)-1);
		
		// Clear
		$this->values = array();
		
		$this->db->sql_query("BEGIN");
		if (!$this->db->sql_query($sql)) {
			$err = $this->db->sql_error();
			$this->db->sql_query("ROLLBACK");
			
			// Error handling
			$this->errors[] = $err;
			if ($this->throwOnError) {
				throw new Exception($err);
			}
			return false;
		} else {
			$this->db->sql_query("COMMIT");
		}
		return true;
	}
}

/**
 * Updates records in a table. 
 */
class UpdateContainer {
	/** @var tx_ms3commerce_db */
	private $db;
	private $table;
	private $limit;
	private $values;
	private $errors;
	public $throwOnError = false;
	/**
	 * Creates an Update Container for a given table.
	 * @param tx_ms3commerce_db $db The database link
	 * @param string $table The table name
	 * @param int $limit Bulk-limit when to commit the collected values
	 */
	public function __construct($db, $table, $limit = 500) {
		$this->db = $db;
		$this->init($table, $limit);
	}
	/**
	 * Re-initializes the container to a new table
	 * @param string $table The table name
	 * @param int $limit Bulk-limit when to commit the collected values
	 */
	public function init($table, $limit = 500) {
		$this->table = $table;
		$this->limit = $limit;
		$this->clearAll();
	}
	/**
	 * Clears all stored values and errors 
	 */
	public function clearAll() {
		$this->errors = array();
		$this->values = array();
	}
	/**
	 * Adds an update to the container. If the bulk-limit is reached, all updates
	 * collected so far are committed to the database.
	 * @param array $vals Values to add as key value pairs. The key is the field
	 * to update, the value the value to set. No escaping is performed
	 * @param array $cond Conditions where to update the value as key value pairs.
	 * The key is the condition field, the value the condition value. No
	 * escaping is performed
	 * @return boolean If bulk limit was reached, the result of the write-operation,
	 * otherwise true
	 */
	public function addUpdate($vals, $cond) {
		$this->values[] = array('set' => $vals, 'where' => $cond);
		if (count($this->values) > $this->limit) {
			return $this->write();
		}
		return true;
	}
	public function hasError() {
		return count($this->errors) > 0;
	}
	/**
	 * Returns list of collected errors during writing
	 * @return array Collected error messages
	 */
	public function getErrors() {
		return $this->errors;
	}
	/**
	 * Commits all collected updates into the database.
	 * @return boolean If the write was successful
	 * @throws Exception If $throwOnError is true, an exception is thrown on error
	 * instead of returning false
	 */
	public function write() {
		if (empty($this->values)) {
			return true;
		}
		
		$this->db->sql_query("BEGIN");
		
		$values = $this->values;
		// Clear
		$this->values = array();
		
		foreach ($values as $upd) {
			
			$sql = "UPDATE {$this->table} SET ";
			foreach ($upd['set'] as $f => $v) {
				$sql .= "$f = $v,";
			}
			$sql = substr($sql, 0, strlen($sql)-1);
			$sql .= " WHERE ";
			foreach ($upd['where'] as $f => $c) {
				$sql .= "$f = $c AND ";
			}
			$sql = substr($sql, 0, strlen($sql)-5);
			if (!$this->db->sql_query($sql)) {
				$err = $this->db->sql_error();
				$this->db->sql_query("ROLLBACK");

				// Error handling
				$this->errors[] = $err;
				if ($this->throwOnError) {
					throw new Exception($err);
				}
				return false;
			}
			
		}
		
		$this->db->sql_query("COMMIT");
		return true;
	}
}

?>
