<?php


class mS3CommerceDbBulkInserter
{
    /** @var tx_ms3commerce_db */
    private $db;
    /** @var string */
    private $table;
    /** @var int */
    private $bulkSize;
    /** @var string[] */
    private $colums = [];
    /** @var string[][] */
    private $values = [];
    /** @var string[] */
    private $errors = [];

    public function __construct($db, $table, $bulkSize = 500) {
        $this->db = $db;
        $this->table = $table;
        $this->bulkSize = $bulkSize;
    }

    /**
     * @param string[] $columns
     */
    public function setColumns($columns) {
        $this->colums = $columns;
    }

    /**
     * @param string[] $values
     */
    public function add($values) {
        $this->values[] = $values;
        if (count($this->values) > $this->bulkSize) {
            $this->commit();
        }
    }

    public function cleanp() {
        $this->values = [];
        $this->errors = [];
    }

    public function getErrors() {
        return $this->errors;
    }

    public function finish() {
        $this->commit();
    }

    private function commit() {
        $sql = "INSERT INTO {$this->table}";
        if ($this->colums) {
            $sql .= ' (' . implode(',', $this->colums) . ')';
        }

        $sql .= ' VALUES ';
        $comma = '';
        foreach ($this->values as $v) {
            $v = array_map(function($x) {return $this->db->sql_escape($x);}, $v);
            $v = implode(',', $v);
            $sql .= "$comma($v)";
            $comma = ',';
        }

        $this->values = [];

        $res = $this->db->sql_query($sql, $this->table);
        if (!$res) {
            $this->errors[] = $this->db->sql_error();
        }
    }
}
