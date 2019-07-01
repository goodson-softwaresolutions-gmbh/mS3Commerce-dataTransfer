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

require_once MS3C_ROOT.'/dataTransfer/lib/mS3CommerceLib.php';
require_once MS3C_EXT_ROOT.'/dataTransfer/search/elasticsearch/ElasticSearch_config.php';
require_once MS3C_ROOT.'/dataTransfer/search/elasticsearch/elasticsearch_handler.php';

if (defined('MS3C_ELASTICSEARCH_MANAGEMENT_TIMEOUT')) {
    define('ES_MANAGEMENT_TIMEOUT', MS3C_ELASTICSEARCH_MANAGEMENT_TIMEOUT);
} else {
    // default 10 seconds timeout for management actions
    define('ES_MANAGEMENT_TIMEOUT', 10*1000);
}

function mS3SearchUploadPostprocess($shop, $arg)
{
	if (!$shop) {
		return array(false, false, "No Shop given");
	}
	
	$dir = getShopExtensionDir($shop).'/ElasticSearch';
	$files = getIndexFiles($dir);
	
	$indexName = MS3ElasticSearchClusterHandler::buildIndexName(true, $shop);
	$client = new MS3ElasticSearchIndexHandler($indexName, mS3ESGetCustomIdxHandler(), ES_MANAGEMENT_TIMEOUT);
	
	
	// RECREATE INDEX on initial call
	if (empty($arg)) {
		// Delete
		$res = $client->deleteIndex();
		if ($res !== true) {
			return array(false, false, "Cannot delete old index: $res");
		}
		
		// Create
		$res = $client->createIndex();
		if ($res !== true) {
			return array(false, false, "Cannot create index: $res");
		}
		
		if (empty($files)) {
			// if nothing to index, we are finished now
			return array(true, false, "Index Elastic Search: No index files");
		}
		
		// Call again with first file
		return array(true, true, $files[0]);
	}
	
	// Get file
	$curFile = $arg;
	$idx = array_search($curFile, $files);
	
	// Check if last file
	if ($idx == count($files)-1) {
		$isLast = true;
	} else {
		$isLast = false;
	}
	
	// INDEX!
	$ret = $client->bulkIndex($dir.'/'.$curFile, $isLast);
	if ($ret !== true) {
		return array(false, true, "Error updating ElasticSearch Index");
	}
	
	if ($isLast) {
		return array(true, false, "Index Elastic Search");
	} else {
		// Continue with next file
		return array(true, true, $files[$idx+1]);
	}
}

function getIndexFiles($dir)
{
	$hDir = opendir($dir);
	$files = array();
	while (false !== ($file = readdir($hDir))) {
		if ($file != '.' && $file != '..') {
			$files[] = $file;
		}
	}
	
	sort($files);
	return $files;
}

//function mS3SearchSwitchDBPreprocess($db, $arg)
//{
//}
//
function mS3SearchSwitchDBPostprocess($db, $arg)
{
	$mgr = new MS3ElasticSearchClusterHandler(ES_MANAGEMENT_TIMEOUT);
	$indices = $mgr->getIndices();
	$ok = $mgr->deleteAllAliases($indices, $err);
	
	if (!$ok) {
		return array(false, false, join(',', $err));
	}
	
	// Get shops from indices
	$shopIdcs = array();
	foreach ($indices as $idx => $ignore) {
		$ct = preg_match('/s\d_shop(\d+)/', $idx, $match);
		if ($ct) {
			// Store shop index
			$shopIdcs[$match[1]] = true;
		}
	}
	
	// Create aliases for all shops
	$err = array();
	foreach ($shopIdcs as $idx => $ignore) {
		$indexName = MS3ElasticSearchClusterHandler::buildIndexName(false, $idx);
		$alias = 'shop'.$idx;
		$ok = $mgr->createAlias($indexName, $alias);
		if ($ok !== true) {
			$err[] = print_r($ok, true);
		}
	}
	
	if (count($err) > 0) {
		return array(false, false, join(',', $err));
	}
	
	return array(true, false, "Elastic Search Aliases");
}

function mS3SearchSweepDBPostprocess($shops, $arg)
{
	$mgr = new MS3ElasticSearchClusterHandler(ES_MANAGEMENT_TIMEOUT);
	if (!empty($shops)) {
		// Comma-separated list of shops, first value is range (ignore)
		$shops = explode(',', $shops);
		array_shift($shops);
	} else {
		// All shops... Find from indices
		$indices = $mgr->getIndices();
		$shops = array();
		foreach ($indices as $idx => $ignore) {
			$ct = preg_match('/s\d_shop(\d+)/', $idx, $match);
			if ($ct) {
				// Store shop index
				$shops[$match[1]] = true;
			}
		}
		$shops = array_keys($shops);
		sort($shops);
	}
	
	if (empty($arg)) {
		$curShop = $shops[0];
		$key = null;
		$initIndex = true;
	} else {
		$argList = explode(';', $arg);
		$curShop = $argList[0];
		if (count($argList) > 1) {
			$key = $argList[1];
			$initIndex = false;
		} else {
			$initIndex = true;
		}
	}
	
	$stageIdName = MS3ElasticSearchClusterHandler::buildIndexName(true, $curShop);
	if ($initIndex) {
		// Re-Create Stage index
		$stageIndex = new MS3ElasticSearchIndexHandler($stageIdName, mS3ESGetCustomIdxHandler(), ES_MANAGEMENT_TIMEOUT);

		$ok = $stageIndex->deleteIndex();
		if (!$ok) {
			return array(false, false, "Cannot delete old index: $ok");
		}

		$ok = $stageIndex->createIndex();
		if (!$ok) {
			return array(false, false, "Cannot create index: $ok");
		}
	}
	
	// Re-Index Production into Stage
	$prodIdName = MS3ElasticSearchClusterHandler::buildIndexName(false, $curShop);
	$prodIndex = new MS3ElasticSearchIndexHandler($prodIdName, mS3ESGetCustomIdxHandler(), ES_MANAGEMENT_TIMEOUT);
	
	$ret = $prodIndex->reindexInto($stageIdName, $key);
	
	if ($ret === true) {
		$nextShopIdx = array_search($curShop, $shops)+1;
		if ($nextShopIdx >= count($shops)) {
			return array(true, false, "Elastic Search Sweep finished");
		} else {
			return array(true, true, $shops[$nextShopIdx]);
		}
	} else if ($ret !== false) {
		// More to do in this shop
		return array(true, true, $curShop.';'.$ret);
	} else {
		return array(false, false, "Elastic Search Sweep failed");
	}
	
}

function mS3ESGetCustomIdxHandler()
{
	if (function_exists("mS3CustomESGetCustomIndexHandler")) {
		return mS3CustomESGetCustomIndexHandler();
	}
	return null;
}

?>
