<?php

require_once __DIR__.'/../../../dataTransfer/dataTransfer_config.php';

if (MS3C_SEARCH_BACKEND != 'ElasticSearch') {
	die('ElasticSearch is not used');
}

function getPostVal($name, $def) {
	if (array_key_exists($name, $_POST)) {
		return $_POST[$name];
	} else {
		return $def;
	}
}

function checkChecked($type)
{
	if (array_key_exists('searchType', $_POST)) {
		if ($_POST['searchType'] == $type) {
			return 'checked="checked"';
		}
	} else if ($type == 'term') {
		return 'checked="checked"';
	}
	return '';
}

function checkType($type) {
	if (array_key_exists($type, $_POST) && $_POST[$type]) {
		return 'checked="checked"';
	}
	return '';
}

$shopId = intval(getPostVal('shopId', 1));
$useStage = getPostVal('useStage', 0);
if ($useStage) {
	$stageChecked = 'checked="checked"';
} else {
	$stageChecked = '';
}

$querySingle = getPostVal('querySingle', '');
$queryCustom = getPostVal('queryCustom', '');
$menus = getPostVal('menus', '');

?>
<html>
	<style type="text/css">
		div.scroller {
			width: 400px;
			height: 400px;
			border: 1px solid gray;
			overflow: scroll;
			float: left;
			margin: 5px;
			resize: both;
		}
		textarea#queryCustom {
			width: 810px;
			height: 200px;
			border: 1px solid gray;
			overflow: scroll;
			margin: 5px;
			resize: both;
		}
	</style>
	<body>
		<pre><?php print_r(getESVersion()) ?></pre>
		<form method="POST" action="esTest.php" name="form">
			Use Stage: <input type="checkbox" name="useStage" value="1" <?php echo $stageChecked?>/><br/>
			Shop ID: <input type="text" name="shopId" value="<?php echo $shopId?>"/><br/>
			<input type="checkbox" name="product" id="product" value="1" <?php echo checkType('product')?>/><label for="product">Products</label>&nbsp;
			<input type="checkbox" name="group" id="group" value="1" <?php echo checkType('group')?>/><label for="group">Groups</label><br/>
			Menus (,-separated): <input type="text" name="menus" value="<?php echo $menus ?>"/><br/>
			
			<input type="radio" name="searchType" value="term" id="term" <?php echo checkChecked('term')?>/><label for="term">Single Term</label><br/>
			<input type="radio" name="searchType" value="autocomplete" id="autocomplete"  <?php echo checkChecked('autocomplete')?>/><label for="autocomplete">Autocomplete</label><br/>
			<input type="radio" name="searchType" value="suggest" id="suggest"  <?php echo checkChecked('suggest')?>/><label for="suggest">Suggest</label><br/>
			<input type="radio" name="searchType" value="object" id="object"  <?php echo checkChecked('object')?>/><label for="object">Object ID</label><br/>
			<input type="text" name="querySingle" value="<?php echo $querySingle?>" id="querySingle"/><br/>
			
			<input type="radio" name="searchType" value="custom" id="custom"  <?php echo checkChecked('custom')?>/><label for="custom">Custom</label><br/>
			<textarea name="queryCustom" id="queryCustom"><?php echo $queryCustom?></textarea><br/>
			<input type="submit"/>
		</form>
		
		<?php list($q,$r) = execQuery()?>
		
		<div id="query" class="scroller"><pre><?php echo htmlspecialchars($q)?></pre></div>
		<div id="result" class="scroller"><pre><?php echo htmlspecialchars($r)?></pre></div>
		
	</body>
</html>

<?php

function getESVersion()
{
	$config = MS3ElasticSearchClusterHandler::getESClientConfiguration();
	$esClient = new Elasticsearch\Client($config);
	return prettyPrint($esClient->info());
}

function getMenus($menu, $shopId, $useStage)
{
	if (empty($menu)) return null;
	$menu = explode(',', $menu);
	$paths = array();
	
	$db = tx_ms3commerce_db_factory::buildDatabase(false, $useStage);
	if (count($menu) > 0) {
		foreach ($menu as $m) {
			$m = intval($m);
			$rs = $db->sql_query("SELECT `Path` FROM Menu WHERE Id = $m");
			$row = $db->sql_fetch_row($rs);
			$db->sql_free_result($rs);
			$paths[] = $row[0] . '/' . $m;
		}
	}
	return $paths;
}

function execQuery()
{
	$searchType = getPostVal('searchType', '');
	if ($searchType == '') return array('','');
	
	global $shopId;
	global $useStage;
	global $queryCustom;
	global $querySingle;
	
	$types = array();
	if (getPostVal('product', 0)) $types[] = 'product';
	if (getPostVal('group', 0)) $types[] = 'group';
	$types = join(';', $types);
	$menus = getMenus(getPostVal('menus', ''), $shopId, $useStage);

	$idxName = MS3ElasticSearchClusterHandler::buildIndexName($useStage, $shopId);
	$searcher = new MS3ElasticSearchQueryHandler($idxName);
	
	$query = '';
	try {
		switch ($searchType) {
			case 'term':
				$res = $searcher->searchSingleTerm($querySingle, $types, $menus, null, null, 0, 10, null, $query);
				$query = prettyPrint($query);
				$res = prettyPrint($res);
				break;
			case 'autocomplete':
				$res = $searcher->autocomplete($querySingle, $types, $menus, null, null, $query);
				$query = prettyPrint($query);
				$res = prettyPrint($res);
				break;
			case 'suggest':
				$res = $searcher->suggest($querySingle, $types, false, $query);
				$query = prettyPrint($query);
				$res = prettyPrint($res);
				break;
			case 'object':
				$q = array(
					'query' => array(
						'term' => array(
							'_id' => $querySingle
						)
					)
				);
				$queryCustom = prettyPrint($q);
				// fallthrough
			case 'custom':
				$config = MS3ElasticSearchClusterHandler::getESClientConfiguration();
				$esClient = new Elasticsearch\Client($config);
				$params = array(
					'index' => $idxName,
					'body' => $queryCustom,
				);
				$res = $esClient->search($params);
				$query = $queryCustom;
				$res = prettyPrint($res);
				break;
			default:
				$res = "Unsupported query type $searchType";
				$query = null;
		}
	} catch (Exception $e) {
		$res = $e->getMessage();
		$r = @json_decode($res);
		if ($r) {
			$r = @prettyPrint($r);
			if ($r) {
				$res = $r;
			}
		}
		if (is_array($query)) {
			$query = prettyPrint($query);
		}
	}
	
	return array($query, $res);
}

function prettyPrint($data)
{
	if (version_compare(phpversion(), '5.4.0', '<')) {
		return doPrettyPrint(json_encode($data));
	} else {
		return json_encode($data, JSON_PRETTY_PRINT);
	}
}

function doPrettyPrint( $json )
{
    $result = '';
    $level = 0;
    $in_quotes = false;
    $in_escape = false;
    $ends_line_level = NULL;
    $json_length = strlen( $json );

    for( $i = 0; $i < $json_length; $i++ ) {
        $char = $json[$i];
        $new_line_level = NULL;
        $post = "";
        if( $ends_line_level !== NULL ) {
            $new_line_level = $ends_line_level;
            $ends_line_level = NULL;
        }
        if ( $in_escape ) {
            $in_escape = false;
        } else if( $char === '"' ) {
            $in_quotes = !$in_quotes;
        } else if( ! $in_quotes ) {
            switch( $char ) {
                case '}': case ']':
                    $level--;
                    $ends_line_level = NULL;
                    $new_line_level = $level;
                    break;

                case '{': case '[':
                    $level++;
                case ',':
                    $ends_line_level = $level;
                    break;

                case ':':
                    $post = " ";
                    break;

                case " ": case "\t": case "\n": case "\r":
                    $char = "";
                    $ends_line_level = $new_line_level;
                    $new_line_level = NULL;
                    break;
            }
        } else if ( $char === '\\' ) {
            $in_escape = true;
        }
        if( $new_line_level !== NULL ) {
            $result .= "\n".str_repeat( "  ", $new_line_level );
        }
        $result .= $char.$post;
    }

    return $result;
}

?>