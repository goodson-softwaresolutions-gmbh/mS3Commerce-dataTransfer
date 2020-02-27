<?php

class mS3CommerceDbModifyUtils
{
    /** @var tx_ms3commerce_db */
    var $db;
    /** @var int */
    var $shopId;

    /**
     * mS3CommerceDbModifyUtils constructor.
     * @param tx_ms3commerce_db $db
     * @param int $shopId
     */
    public function __construct($db, $shopId)
    {
        $this->db = $db;
    }

    public function prepareDeleteGroupsRecursiveByStatement($stmt, $tables = null) {
        $this->createDeleteGroupsRecursiveTable();

        $this->db->sql_query("INSERT INTO tmpDeleteGroups SELECT DISTINCT Id FROM ($stmt) x", $tables);
        $this->addDeleteSubgroupsRecursive();

        // Find products exclusevly in this groups, and delete them
        $this->prepareExclusiveProductsInSubgroups();
    }

    public function prepareDeleteGroupsRecursive($groupIds) {
        $this->createDeleteGroupsRecursiveTable();

        $vals = implode(',', array_unique($groupIds));
        $this->db->sql_query("INSERT INTO tmpDeleteGroups (Id) VALUES ($vals)");
        $ths->addDeleteSubgroupsRecursive();

        // Find products exclusevly in this groups, and delete them
        $this->prepareExclusiveProductsInSubgroups();
    }

    public function stepDeleteGroupsRecursive($stepSize = 1000) {
        if (!$this->stepDeleteProducts($stepSize)) {
            return false;
        }

        $res = $this->db->sql_query('SELECT Id FROM tmpDeleteGroups LIMIT '.$stepSize);
        $rows = $res->fetch_all();

        if (empty($rows)) {
            return true;
        }

        $rows = array_map(function($r) { return $r[0]; }, $rows);
        $this->deleteGroups($rows);
        $rows = implode(',', $rows);
        $this->db->sql_query("DELETE FROM tmpDeleteGroups WHERE Id IN ($rows)");
        return false;
    }

    public function finishDeleteGroupsRecursive() {
        $this->db->sql_query('DROP TABLE IF EXISTS tmpDeleteGroups');
    }

    public function deleteGroups($ids) {
        $ids = implode(',', $ids);
        $this->db->sql_query("DELETE FROM DocumentLink WHERE GroupValueId IN (SELECT Id FROM GroupValue WHERE GroupId IN ($ids))", 'DocumentLink, GroupValue');
        if (!MS3C_NO_SMZ) {
            $this->db->sql_query("DELETE FROM FeatureCompValue WHERE GroupId IN ($ids)");
        }
        if (defined('RealURLMap_TABLE') && !empty(RealURLMap_TABLE)) {
            $this->db->sql_query("DELETE FROM ".RealURLMap_TABLE." WHERE asim_mapid IN (SELECT ContextId FROM Menu WHERE GroupId IN ($ids))", 'RealURLMap_TABLE, Menu');
        }
        if (defined('MS3C_SEARCH_BACKEND') && MS3C_SEARCH_BACKEND == 'MySQL') {
            $this->db->sql_query("DELETE FROM FullText_{$this->shopId} WHERE ParentType = 1 AND ParentId IN ($ids)", 'FullText_'.$this->shopId);
        }
        $this->db->sql_query("DELETE FROM Relations WHERE GroupId IN ($ids)", 'Relations');
        $this->db->sql_query("DELETE FROM Relations WHERE DestinationType = 1 AND DestinationId IN ($ids)", 'Relations');
        $this->db->sql_query("DELETE FROM GroupValue WHERE GroupId IN ($ids)", 'GroupValue');
        $this->db->sql_query("DELETE FROM Menu WHERE GroupId IN ($ids)", 'Menu');
        $this->db->sql_query("DELETE FROM Groups WHERE Id IN ($ids)", 'Groups');
    }

    private function createDeleteGroupsRecursiveTable() {
        $this->db->sql_query('CREATE TABLE IF NOT EXISTS tmpDeleteGroups (Id INT NOT NULL PRIMARY KEY)');
        $this->db->sql_query('TRUNCATE TABLE tmpDeleteGroups');
    }

    private function addDeleteSubgroupsRecursive() {
        // Add all Groups that are children of selected gorups, and no where else
        $sql = <<<XXX
INSERT INTO tmpDeleteGroups
SELECT Id FROM (
    SELECT t.GroupId AS Id
    FROM Menu t
    INNER JOIN Menu p ON t.Path LIKE CONCAT(p.Path,'/',p.Id,'%') AND p.GroupId IN (SELECT Id FROM tmpDeleteGroups)
    WHERE t.GroupId IS NOT NULL 
    GROUP BY t.GroupId
    HAVING COUNT(t.GroupId) = COUNT(p.Id)
) t
WHERE t.Id NOT IN (SELECT Id FROM tmpDeleteGroups)
XXX;
        $this->db->sql_query($sql, 'Menu t, Menu p');
    }

    private function prepareExclusiveProductsInSubgroups() {
        // Gets products that are children of selected gorups, and no where else
        $sql = <<<XXX
SELECT t.ProductId AS Id
FROM Menu t
INNER JOIN Menu p ON t.Path LIKE CONCAT(p.Path,'/',p.Id,'%') AND p.GroupId IN (SELECT Id FROM tmpDeleteGroups)
WHERE t.ProductId IS NOT NULL 
GROUP BY t.ProductId
HAVING COUNT(t.ProductId) = COUNT(p.Id)
XXX;
        $this->prepareDeleteProductsByStatement($sql, 'Menu t, Menu p');
    }

    private $productIds = [];
    public function prepareDeleteProductsByStatement($stmt, $tables = null) {
        $res = $this->db->sql_query($stmt, $tables);
        $err = $this->db->sql_error();
        $rows = $res->fetch_all();

        if (empty($rows)) {
            return true;
        }

        $this->productIds = array_map(function($r) { return $r[0]; }, $rows);
    }

    public function prepareDeleteProducts($productIds) {
        $this->productIds = $productIds;
    }

    public function stepDeleteProducts($stepSize = 1000) {
        if (empty($this->productIds))
            return true;
        $bunch = array_splice($this->productIds, 0, $stepSize);
        $this->deleteProducts($bunch);
        if (empty($this->productIds))
            return true;
        return false;
    }

    public function finishDeleteProducts() {
        $this->productIds = [];
    }

    public function deleteProducts($ids) {
        $ids = implode(',', $ids);
        $this->db->sql_query("DELETE FROM DocumentLink WHERE ProductValueId IN (SELECT Id FROM ProductValue WHERE ProductId IN ($ids))", 'DocumentLink, ProductValue');
        if (!MS3C_NO_SMZ) {
            $this->db->sql_query("DELETE FROM FeatureCompValue WHERE ProductId IN ($ids)");
        }
        if (defined('RealURLMap_TABLE') && !empty(RealURLMap_TABLE)) {
            $this->db->sql_query("DELETE FROM ".RealURLMap_TABLE." WHERE asim_mapid IN (SELECT ContextId FROM Menu WHERE ProductId IN ($ids))", 'RealURLMap_TABLE, Menu');
        }
        if (defined('MS3C_SEARCH_BACKEND') && MS3C_SEARCH_BACKEND == 'MySQL') {
            $this->db->sql_query("DELETE FROM FullText_{$this->shopId} WHERE ParentType = 2 AND ParentId IN ($ids)", 'FullText_'.$this->shopId);
        }
        $this->db->sql_query("DELETE FROM Relations WHERE ProductId IN ($ids)", 'Relations');
        $this->db->sql_query("DELETE FROM Relations WHERE DestinationType = 2 AND DestinationId IN ($ids)", 'Relations');
        $this->db->sql_query("DELETE FROM ProductValue WHERE ProductId IN ($ids)", 'ProductValue');
        $this->db->sql_query("DELETE FROM Menu WHERE ProductId IN ($ids)", 'Menu');
        $this->db->sql_query("DELETE FROM Product WHERE Id IN ($ids)", 'Product');
    }
}
