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

require_once MS3C_EXT_ROOT.'/dataTransfer/search/elasticsearch/ElasticSearch_config.php';
require_once MS3C_ELASTICSEARCH_API_DIR.'/autoload.php';

interface MS3ElasticSearchCustomExtensionHandler
{
	public function adjustIndexMapping(&$mapping);
	public function adjustSingleTermRequest(&$req);
	public function adjustAutoComplete(&$query);
	public function adjustSuggest(&$query);
}

class MS3ElasticSearchClusterHandler
{
	public static function getESClientConfiguration($timeout = 1000)
	{
		$es_hosts = getElasticSearchHosts();
		$es_auth = getElasticSearchAuth();
		$params = array(
			'hosts' => $es_hosts,
			'connectionClass' => '\Elasticsearch\Connections\CurlMultiConnection',
			'connectionPoolClass' => '\Elasticsearch\ConnectionPool\StaticNoPingConnectionPool',
			'selectorClass' => '\Elasticsearch\ConnectionPool\Selectors\StickyRoundRobinSelector',
			'connectionPoolParams'  => array(
				'randomizeHosts' => true
			),
			'connectionParams' => array(
				'auth' => $es_auth,
				'timeout' => $timeout
			)
		);
		return $params;
	}
	
	public static function buildIndexName($stage, $shop) {
		if ($stage) {
			$prefix = MS3C_ELASTICSEARCH_STAGE_IDX;
		} else {
			$prefix = MS3C_ELASTICSEARCH_PRODUCTION_IDX;
		}
		
		return $prefix.'_shop'.$shop;
	}
	
	/**
	 * @var \Elasticsearch\Client
	 */
	public $client;
	function __construct($timeout = 1000) {
		$params = self::getESClientConfiguration($timeout);
		$this->client = new Elasticsearch\Client($params);
	}
	
	public function getIndices() {
		$stat = $this->client->indices()->status();
		$ret = array();
		foreach ($stat['indices'] as $idx => $conf) {
			$alias = $this->client->indices()->getAliases(array('index'=>$idx));
			if (!empty($alias)) {
				$alias = array_keys($alias[$idx]['aliases']);
			} else {
				$alias = array();
			}
			$ret[$idx] = $alias;
		}
		return $ret;
	}
	
	public function deleteAllAliases($idxConfig = null, &$err = null) {
		if (is_null($idxConfig)) {
			$idxConfig = $this->getIndices();
		}
		
		$ok = true;
		$err = "";
		foreach ($idxConfig as $idx => $aliases) {
			foreach ($aliases as $alias) {
				$params = array(
					'index' => $idx,
					'name' => $alias
				);
				try {
					$this->client->indices()->deleteAlias($params);
				} catch (Exception $e) {
					$ok = false;
					$err[] = $e->getMessage();
				}
			}
		}
		
		return $ok;
	}
	
	public function createAlias($idx, $alias) {
		$params = array(
			'index' => $idx,
			'name' => $alias
		);
		try {
			$ret = $this->client->indices()->putAlias($params);
			if ($ret['acknowledged']) {
				return true;
			}
			return $ret;
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}
}

class MS3ElasticSearchIndexHandler
{
	/**
	 * @var \Elasticsearch\Client
	 */
	private $client;
	private $indexName;
	/** @var MS3ElasticSearchCustomExtensionHandler */
	private $custom;
	
	function __construct($idxName, $custom = null, $timeout = 1000) {
		$params = MS3ElasticSearchClusterHandler::getESClientConfiguration($timeout);
		$this->client = new Elasticsearch\Client($params);
		$this->indexName = $idxName;
		$this->custom = $custom;
		
		if (empty($this->indexName)) {
			throw new Exception("No index name specified");
		}
	}
	
	public function deleteIndex()
	{
		$params = array('index' => $this->indexName);
		try {
			$res = $this->client->indices()->delete($params);
			if ($res['acknowledged']) {
				return true;
			}
		} catch (Exception $e) {
			if ($e instanceof \Elasticsearch\Common\Exceptions\Missing404Exception) {
				// Index didn't exist anyway
				return true;
			}
			return $e->getMessage();
		}
	}
	
	public function createIndex()
	{
		// 
		$params = array(
			'index' => $this->indexName,
			'body' => array(
				'settings' => array(
					'analysis' => array(
						'filter' => array(
							'autocomp_filter' => array(
								//'type' => 'edge_ngram',
								'type' => 'ngram',
								'min_gram' => '2',
								'max_gram' => '20'
							)
						),
						'analyzer' => array(
							'default' => array(
								'type' => 'standard'
							),
							'autocomp_indexer' => array(
								'type' => 'custom',
								'tokenizer' => 'whitespace',
								'filter' => array(
									'lowercase', 'asciifolding', 'autocomp_filter'
								)
							),
							'autocomp_searcher' => array(
								'type' => 'custom',
								'tokenizer' => 'whitespace',
								'filter' => array(
									'lowercase', 'asciifolding'
								)
							)
						)
					),
				),
				
				'mappings' => array(
					'product' => $this->createTypeMap('prd'),
					'group' => $this->createTypeMap('grp'),
					'document' => $this->createTypeMap('doc')
				)
			)
		);
		
		if ($this->custom) {
			$this->custom->adjustIndexMapping($params);
		}
		
		try {
			$res = $this->client->indices()->create($params);
			if (!$res['acknowledged']) {
				return $res;
			}
			
			// Wait for YELLOW status (so the index is ready for indexing)
			$res = $this->client->cluster()->health(array('wait_for_status'=>'yellow'));
			
			return true;
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}
	
	public function bulkIndex($bulkFile, $forceReload = false)
	{
		$bulkFileContent = file_get_contents($bulkFile);
		if (empty($bulkFileContent)) {
			return true;
		}
		$bulk = array(
			'index' => $this->indexName,
			'body' => $bulkFileContent,
			'refresh' => $forceReload,
		);

		$res = $this->client->bulk($bulk);
		if ($res['errors'] !== false) {
			return $res['errors'];
		}
		return true;
	}
	
	public function reindexInto($indexName, $token = null) {
		if (!$token) {
			// Initial Request, open Scroll
			// Scroll will handle request-size / #shards at once. Limit by # Shards
			$nrShards = $this->getNumberOfShards();
			
			$params = array(
				'index' => $this->indexName,
				'body' => array(
					'query' => array('match_all' => array()),
					'size' => 1000/$nrShards,
				),
				'scroll' => '1m',
				'search_type' => 'scan'
			);
			try {
				$ret = $this->client->search($params);
				$token = $ret['_scroll_id'];
			} catch (Exception $e) {
				return $e->getMessage();
			}
			
		}
		
		$params = array(
			'scroll_id' => $token,
			'scroll' => '1m'
		);
		
		$ret = $this->client->scroll($params);
		
		//$data = array();
		foreach ($ret['hits']['hits'] as $item) {
			/*
			$data[] = array(
				'index' => array(
					'_id' => $item['_id'],
					'_type' => $item['_type']
				)
			);
			$data[] = $item['_source'];
			*/
			$params = array(
				'index' => $indexName,
				'type' => $item['_type'],
				'id' =>  $item['_id'],
				'body' => $item['_source']
			);
			
			$this->client->index($params);
		}
		
		if (empty($ret['hits']['hits'])) {
			try {
				$this->client->clearScroll(array('scroll_id' => $token));
			} catch (Exception $e) {
				if ($e instanceof \Elasticsearch\Common\Exceptions\Missing404Exception) {
					// This is ok
					return true;
				}
				return false;
			}
			return true;
		} else {
			return $ret['_scroll_id'];
		}
	}
	
	var $nrOfShards = 0;
	private function getNumberOfShards()
	{
		if ($this->nrOfShards == 0) {
			$settings = $this->client->indices()->getSettings(array('index'=>$this->indexName));
			$nrShards = intval($settings[$this->indexName]['settings']['index']['number_of_shards']);
			if (!$nrShards) {
				$nrShards = 1;
			}
			$this->nrOfShards = $nrShards;
		}
		return $this->nrOfShards;
	}
	
	private function createTypeMap($prefix)
	{
		$mapping = array(
			// _source-field is required for sweeping...
//			'_source' => array(
//				'enabled' => false
//			),
//			
			// We never search in all fields
			'_all' => array('enabled' =>  false),
			// Do not allow additional fields
			'dynamic' => 'strict',
			'properties' => array(
				$prefix.'_primary' => array(
					'type' => 'string',
					'analyzer' => 'standard',
				),
				$prefix.'_secondary' => array(
					'type' => 'string',
					'analyzer' => 'standard',
				),
				$prefix.'_tertiary' => array(
					'type' => 'string',
					'analyzer' => 'standard',
				),
				$prefix.'_display' => array(
					'type' => 'string',
					'analyzer' => 'standard',
					'fields' => array(
						'autocomp' => array(
							'type' => 'string',
							'index_analyzer' => 'autocomp_indexer',
							'search_analyzer' => 'autocomp_searcher'
						),
					)
				),
				$prefix.'_suggest' => array(
					'type' => 'completion',
					'analyzer' => 'simple'
				),
				'exact' => array(
					'type' => 'string',
					'index' => 'not_analyzed'
				),
				'menuPaths' => array(
					'type' => 'string',
					'index' => 'not_analyzed'
				),
				'display' => array(
					'type' => 'string',
					'index' => 'not_analyzed'
				)
			)
		);
		
		if (MS3C_ELASTICSEARCH_HANDLES_ACCESS_RIGHTS) {
			$mapping['properties']['userRights'] = array(
					'type' => 'string',
					'index' => 'not_analyzed'
				);
			$mapping['properties']['marketRestr'] = array(
					'type' => 'string',
					'index' => 'not_analyzed'
				);
		}
		
		return $mapping;
	}
	
}


class MS3ElasticSearchQueryHandler
{
	/**
	 * @var \Elasticsearch\Client
	 */
	private $client;
	private $indexName;
	/** @var MS3ElasticSearchCustomExtensionHandler */
	private $custom;
	
	function __construct($idxName, $custom = null, $timeout = 1000) {
		$params = MS3ElasticSearchClusterHandler::getESClientConfiguration($timeout);
		$this->client = new Elasticsearch\Client($params);
		$this->indexName = $idxName;
		$this->custom = $custom;
		
		if (empty($this->indexName)) {
			throw new Exception("No index name specified");
		}
	}
	
	public function autocomplete($term, $type = 'product;group;document', $menus = array(), $userVals = null, $marketVals = null, &$queryOut = '')
	{
		$query = $this->getDisplayAutoCompQuery($term, $type);
		
		// Add a filter to only look at requested type
		$filter = $this->getTypeFilter($type);
		// Add restrictions
		$query = $this->addRestrictionFilters($query, $filter, $menus, $userVals, $marketVals);
		
		$query['aggs'] = array(
			'autocomp' => array(
				'terms' => array(
					'field' => 'display'
				),
				// Requires ES 1.3+
				'aggs' => array(
					'tops' => array(
						'top_hits' => array(
							'size' => 1
						)
					)
				)
			)
		);
		
		if ($this->custom) {
			$this->custom->adjustAutoComplete($query);
		}
		
		$queryOut = $query;
		$params = array(
			'index' => $this->indexName,
			'body' => $query,
			// Only with top_hits aggs
			'search_type' => 'count'
		);
		try {
			$start = microtime(true);
			$res = $this->client->search($params);
			$end = microtime(true);
			$res['API'] = intval($end*1000-$start*1000);
			return $res;//['hits'];
		} catch (Exception $e) {
			return null;
		}
	}
	
	public function searchSingleTermScrolled($term, $type = 'product;group;document', $menus = array(), $size = 10, $source = null, $userVals = null, $marketVals = null)
	{
		$query = $this->getSingleTermQuery($term, $type, $menus, $userVals, $marketVals);
		$params = array(
			'index' => $this->indexName,
			'body' => $query,
			'scroll' => '10s',
			'size' => $size
		);
		
		$this->set_Source($params, $source, $type);
		
		try {
			$res = $this->client->search($params);
			/*$ret = array(
				'_scroll_id' => $res['_scroll_id'],
				'total' => $res['hits']['total']
			);*/
			return $res;
		} catch (Exception $e) {
			return null;
		}
		
	}
	
	public function scrollSingleTerm($scrollKey)
	{
		$params = array(
			'scroll_id' => $scrollKey,
			'scroll' => '10s',
		);
		
		try {
			$res = $this->client->scroll($params);
		} catch (Exception $e) {
			throw $e;
		}
		return $res;
	}
	
	public function closeScroll($scrollKey)
	{
		$params = array(
			'scroll_id' => $scrollKey
		);
		try {
			$this->client->clearScroll($params);
		} catch (Exception $e) {
			
		}
	}
	
	public function searchSingleTerm($term, $type = 'product;group;document', $menus = array(), $userVals = null, $marketVals = null, $from = 0, $size = 10, $source = null, &$queryOut = '')
	{
		$query = $this->getSingleTermQuery($term, $type, $menus, $userVals, $marketVals);
		$queryOut = $query;
		
		$params = array(
			'index' => $this->indexName,
			'body' => $query,
			'from' => $from,
			'size' => $size
		);
		
		$this->set_Source($params, $source, $type);
		
		return $this->client->search($params);
	}
	
	public function suggest($term, $type = 'product;group;document', $consolidate = false, &$queryOut = '')
	{
		list(,,,$p,$g,$d) = $this->getTypes($type);
		$conf = array('prd'=>$p,'grp'=>$g,'doc'=>$d);
		
		$query = array(
			'text' => $term
		);
		foreach ($conf as $prefix => $set) {
			if (!$set) continue;
			
			$query[$prefix] = array(
				'completion' => array(
					'fuzzy' => true,
					'field' => $prefix.'_suggest'
				)
			);
		}
		
		if ($this->custom) {
			$this->custom->adjustSuggest($query);
		}
		
		$params = array(
			'index' => $this->indexName,
			//'body' => $query
			'body' => array('suggest'=>$query),
			'search_type' => 'count'
		);
		$queryOut = $params['body'];
		try {
			$start = microtime(true);
			//$res = $this->client->suggest($params);
			$res = $this->client->search($params);
			$end = microtime(true);
			
			if ($consolidate) {
				$data = array();
				foreach($conf as $prefix => $set) {
					if (!$set) continue;
					$data = array_merge($res['suggest'][$prefix][0]['options'], $data);
				}
				$res['consolidated'] = $data;
			}
			$res['API'] = intval($end*1000-$start*1000);
			
			return $res;//['hits'];
		} catch (Exception $e) {
			return null;
		}
	}
	
	private function getSingleTermQuery($term, $type = 'product;group;document', $menus = array(), $userVals = null, $marketVals = null)
	{
		// Match in all search fields, applying different boost factors ("^" terms)
		$exact = $this->getExactQuery($term);
		$fields = $this->getSearchFieldsQuery($term, $type);
		$display = $this->getDisplayAutoCompQuery($term, $type);
		
		$query = array(
			'bool' => array(
				'should' => array(
					$exact, $fields, $display
				)
			)
		);
		
		// Add type filter
		$filter = $this->getTypeFilter($type);
		// Add restrictions
		$query = $this->addRestrictionFilters($query, $filter, $menus, $userVals, $marketVals);
		
		if ($this->custom) {
			$this->custom->adjustSingleTermRequest($query);
		}
		
		return $query;
	}
	
	private function getDisplayAutoCompQuery($term, $type)
	{
		list($prefix, , $multi) = $this->getTypes($type);
		if ($multi) {
			// Match any _display field
			$query = array(
				'multi_match' => array(
					'query' => $term,
					'fields' => '*_display.autocomp',
					//'operator' => 'and',
					'type' => 'cross_fields'
				)
			);
		} else {
			// Only match required _display field
			$query = array(
				'match' => array(
					$prefix.'_display.autocomp' => array(
						'query' => $term,
						//'operator' => 'and'
					)
				)
			);
		}
		return $query;
	}
	
	private function getExactQuery($term)
	{
		$query = array(
			'term' => array(
				'exact' => array(
					'value' => $term,
					'boost' => 15
				)
			)
		);
		return $query;
	}
	
	private function getSearchFieldsQuery($term, $type)
	{
		$fields = array('_display^10','_primary^7','_secondary^3','_tertiary');
		list($prefix, , $multi) = $this->getTypes($type);
		$query = array(
			'multi_match' => array(
				'type' => 'cross_fields',
				'fields' => $this->addPrefix($fields, $prefix, $multi),
				'query' => $term,
				'operator' => 'and'
				)
			);
		return $query;
	}
	
	private function addPrefix($fields, $prefix, $multi)
	{
		if ($multi) $prefix = '*';
		foreach ($fields as &$f) {
			$f = $prefix.$f;
		}
		return $fields;
	}
	
	private function getTypes($types) {
		$prd = $grp = $doc = $multi = false;
		if (!is_array($types)) {
			$types = explode(';', $types);
		}
		$ct = 0;
		foreach ($types as $t) {
			switch ($t) {
			case 'product': $prd = true; $ct++; $prefix = 'prd'; break;
			case 'group': $grp = true; $ct++; $prefix = 'grp'; break;
			case 'document': $doc = true; $ct++; $prefix = 'doc'; break;
			}
		}
		if ($ct > 1) {
			$multi = true;
			$prefix = '*';
		}
		$all = $prd && $grp && $doc;
		return array($prefix, $all, $multi, $prd, $grp, $doc);
	}
	
	private function addRestrictionFilters($query, $filter, $menus = array(), $userVals = null, $marketVals = null)
	{
		$filters = array();
		// Start with given filter, if present
		if (!empty($filter)) $filters[] = $filter;
		
		// Handle user rights / market restirctions
		if (MS3C_ELASTICSEARCH_HANDLES_ACCESS_RIGHTS) {
			// If no user rights are given, only non-restricted items are visible
			$usr = $this->compileRestrictionFilter('userRights', $userVals, true);
			// If no market restriction is given, everything is visible (from market view)
			$mrk = $this->compileRestrictionFilter('marketRestr', $marketVals, false);
			
			// Add market and user rights to given filter in a bool filter.
			// But if there are no market/user rights, just use the given one
			if (!empty($usr)) $filters[] = $usr;
			if (!empty($mrk)) $filters[] = $mrk;
		}
		
		// Add menu filters, if present
		$mnu = $this->compileMenuFilter($menus);
		if (!empty($mnu)) $filters[] = $mnu;
		
		// Compile filtered query
		if (count($filters) == 0) {
			return array(
				'query' => $query
			);
		} else {
			return array(
				'query' => array(
					'filtered' => array(
						'filter' => array(
							'bool' => array(
								'must' => $filters
							),
						),
						'query' => $query
					)
				)
			);
		}
	}
	
	private function compileRestrictionFilter($field, $vals, $restrictIfEmpty = true) {
		if (empty($vals)) {
			if (!$restrictIfEmpty) {
				// No values given, but this means everything is visible => no filter
				return null;
			}
			// No values given (user rights / market visibility)
			// Only show non-restricted objects
			$fltr = array(
				'missing' => array(
					'field' => $field
					)
				);
		} else {
			// Values given => filter for matching + non-restricted objects
			$fltr = array(
				'bool' => array(
					'should' => array(
						array('terms' => array(
							$field => array_values($vals)
						)),
						array('missing' => array(
							'field' => $field
						))
					)
				)
			);
		}
		return $fltr;
	}
	
	private function compileMenuFilter($menus)
	{
		// Allow single menu id
		if (!is_array($menus) && !empty($menus)) $menus = array($menus);
		// If nothing given, finished
		if (!is_array($menus) || count($menus) == 0) return null;
		
		if (count($menus) > 1) {
			$filter = array();
			foreach ($menus as $m) {
				$filter[] = array(
					'prefix' => array(
						'menuPaths' => $m
					)
				);
			}

			$fltr = array(
				'bool' => array(
					'should' => array(
						$filter
					)
				)
			);
		} else {
			$fltr = array(
				'prefix' => array(
					'menuPaths' => $menus[0]
				)
			);
		}
		
		return $fltr;
	}
	
	private function getTypeFilter($type)
	{
		list(,$all,$multi, $prd, $grp, $doc) = $this->getTypes($type);
		
		if ($all) {
			return null;
		}
		
		if ($prd) {
			$prd = array('type'=>array('value'=>'product'));
		} else {
			$prd = array();
		}
		if ($grp) {
			$grp = array('type'=>array('value'=>'group'));
		} else {
			$grp = array();
		}
		if ($doc) {
			$doc = array('type'=>array('value'=>'document'));
		} else {
			$doc = array();
		}
		
		
		if (!$multi) {
			// Filter by single type
			$val = array_merge($prd, $grp, $doc);
			$filter = $val;
		} else {
			$filters = array();
			if ($prd) {
				array_push($filters, $prd);
			}
			if ($grp) {
				array_push($filters, $grp);
			}
			if ($doc) {
				array_push($filters, $doc);
			}
			$filter = array(
				'bool' => array(
					'should' => $filters,
					'minimum_should_count' => 1
				)
			);
		}
		
		return $filter;
	}
	
	private function set_Source(&$params, $source, $types)
	{
		if (is_null($source)) {
			return;
		}
		
		$dest = array();
		foreach ($source as $field) {
			if ($field[0] == '*') {
				foreach ($types as $type) {
					$dest[] = str_replace('*', $this->type2Prefix($type), $field);
				}
			} else {
				$dest[] = $field;
			}
		}
		
		$params['_source'] = $dest;
	}
	
	private function type2Prefix($type) {
		switch ($type) {
		case 'product': return 'prd'; break;
		case 'group': return 'grp'; break;
		case 'document': return 'doc'; break;
		}
	}
}

?>
