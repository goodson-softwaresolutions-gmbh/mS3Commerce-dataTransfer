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

/**
 * To view SMZ Tree Structure
 *
 * @author alagukannan.kumaresan
 */
require_once(__DIR__ .'/dataTransfer_config.php');
require_once(MS3C_ROOT.'/dataTransfer/mS3Commerce_db.php');

$_version = MS3C_VERSION;

if (MS3COMMERCE_STAGETYPE == 'TABLES')
	$stageDb = MS3COMMERCE_STAGE_SUFFIX;
else
	$stageDb = MS3COMMERCE_STAGE_DB;

$useStage = false;
$bytype = false;

$stageDbName = tx_ms3commerce_db_factory::getDatabaseName(true);

$smzname = '';
$shopid = 1;
$byType = 0;

if ( $_POST )
{
    $useStage = array_key_exists('useStage', $_POST) && $_POST['useStage'];
	if ( array_key_exists('smz', $_POST) && isset($_POST['smz'])) {
		$smzname = $_POST['smz'];
	}
	if (!empty($_POST['shopid'])) {
		$shopid = $_POST['shopid'];
	}
	$byType = array_key_exists('bytype', $_POST) && $_POST['bytype'];
} else {
    if (array_key_exists('id',$_GET)) {
        $byType = 0;
        $id = $_GET['id'];
        if (array_key_exists('useStage', $_GET) && $_GET['useStage']) {
            $useStage = true;
        }
        
        $db = tx_ms3commerce_db_factory::buildDatabase(false, $useStage);
        $db->sql_query("SET NAMES UTF8");
        
        $sql = "
    SELECT f.Name, i.ShopId
    FROM featureCompilation f
    INNER JOIN ShopInfo i ON i.StartId <= f.Id AND f.Id < i.EndId
    WHERE f.Id = ".$db->sql_escape($id);
        
        $res = $db->sql_query($sql, array('featureCompilation f', 'ShopInfo i'));
        $row = $db->sql_fetch_assoc($res);
        if ($row) {
            $shopid = $row['ShopId'];
            $smzname = $row['Name'];
        }
    }
}


$useStageChecked = $useStage ? "checked" : "";

echo <<<EOT
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<script language="JavaScript" type="text/javascript">
function findByType()
{
	document.getElementById('bytype').value = '1';
	document.forms["smztree"].submit();
}
function findByName()
{
	document.getElementById('bytype').value = '0';
	document.forms["smztree"].submit();
}
function goBack()
{
    window.history.back()
}
</script>
<style>
html, body{
    margin: 0;
    padding: 0;
    height: 100%;
    font-size: 15px;
    font-family: Arial;
}
#wrapper{
    margin: 0;
    padding: 3px;
    padding-top: 10px;
    overflow-y: auto;
    overflow-x: hidden;
    /*height: 100%;*/
}
#fixedHeader{
    margin: 0;
    top:0px;
    background: #eee;
    width: 100%;
    padding: 3px;
    border-bottom: 2px solid #555;
    box-shadow: 0px 5px 8px 1px #666;
    z-index: 10;
    position: relative;
}
#fixedHeader input[type=text]{
    width: 250px;
}
#fixedHeader #shopidinput{
    width: 30px;
}
#toplink {
    position: fixed;
    bottom: 10px;
    right: 10px;
    line-height: 30px;
    height: 30px;
    color: white;
    background: black;
}
</style>
</head>
	<body>
	<div id="fixedHeader">
	<div id="headerText"><big>mS3 Commerce dataTransfer Version ${_version}</big></div>
	<form action="smzTreeSql.php" method="POST" name="smztree" id="smztree">
    <label title="$stageDbName: $stageDb">
                    <input type="checkbox" onclick="document.forms['smztree'].submit();" name="useStage" value="useStage" $useStageChecked>StageDB</label>
	Shop Id: <input name="shopid" id="shopidinput" type="text" value="$shopid"/> Name: <input type="text" name="smz" value="$smzname"/> <input type="submit" value="By Name" onclick='findByName();'/> <input type="button" value="By Type" onclick="findByType();"/>
			<input type="button" value="Back" onclick="goBack()"/>
            <input type="hidden" name="bytype" value="$byType" id="bytype"/>
		</form>
		</div>
<div id="wrapper">
EOT;
if ($shopid != 0 && !empty($smzname)) {
    if ($byType) {
        printByType($smzname, $shopid, $useStage);
    } else {
        printByName($smzname, $shopid, $useStage);
    }
}
echo "	
</div>
</body>
</html>";

function printByName( $smzName, $shop, $useStage )
{
    $db = tx_ms3commerce_db_factory::buildDatabase(false, $useStage);
    $db->sql_query("SET NAMES UTF8");
    
    $shopid = $db->sql_escape($shop);
    $name = $db->sql_escape($smzName);
    
    $query = 
"SELECT fcf.IsNode, fcf.FeatureId, fcf.HierarchyType, fcf.Level, fcf.SubGroup, fcf.featureid, 
    f.Name, f.LanguageId, f.MarketId, fc.Name AS SMZName, fc.Type
FROM featureComp_feature fcf 
INNER JOIN Feature f ON fcf.featureid = f.id 
INNER JOIN featureCompilation fc ON fc.id = fcf.featurecompid
INNER JOIN ShopInfo i ON i.StartId <= f.Id AND f.Id < i.EndId
WHERE fc.name = $name AND i.ShopId = $shopid
ORDER BY fcf.HierarchyType,fcf.sort";

    $tablearray = array('featureComp_feature fcf', 'Feature f', 'featureCompilation fc', 'ShopInfo i');	
	$res = $db->sql_query($query, $tablearray);
	if (!$res) {
		echo "!! ".$db->sql_error();
		return;
	}	
	$tree = array();
	while ($row = $db->sql_fetch_assoc($res)) {
		$tree[$row['HierarchyType']][] = $row;
		$type = $row['Type'];
	}
	if (!$tree) {
		echo "<span style='color:#F5071F;text-align:center;'>No SMZ found for name <em>$smzName</em> in Shop $shop</span>";
		return;
	}
	echo "SMZ Structure for <em>$smzName</em> (Type <em>$type</em>):";
	print printSMZTree($tree);
}


function printByType( $smzType, $shop, $useStage )
{
    $db = tx_ms3commerce_db_factory::buildDatabase(false, $useStage);
    $db->sql_query("SET NAMES UTF8");
    
    $shopid = $db->sql_escape($shop);
    $type = $db->sql_escape($smzType);
    
    $query = 
"SELECT fc.Name, fc.Id 
FROM featureCompilation fc
INNER JOIN ShopInfo i ON i.StartId <= fc.Id AND fc.Id < i.EndId
WHERE fc.type = $type AND i.ShopId = $shopid
ORDER BY fc.Id";

    $tablearray = array('featureCompilation fc', 'ShopInfo i');

	$res = $db->sql_query($query, $tablearray);
	if (!$res) {
		echo "!! ".$db->sql_error();
		return;
	}
	
	$treebytype = array();
	while ($row = $db->sql_fetch_assoc($res)) {
	$treebytype[] = $row;
	}
	if (!$treebytype) {
		echo "<span style='color:#F5071F;text-align:center;'>No SMZs found for type <em>$smzType</em> in Shop $shop</span>";
		return;
	}
	echo "SMZs for Type <em>$smzType</em>: ";
	print printSMZTreebyType($treebytype, $useStage);
}

function printSMZTree ($tree) {
    $last_level = -1;
	$typenames = array(1=>'FGF', 2=>'SF', 3=>'RGF');
	$br = reset($tree);
	$ro = reset($br);
	
	echo "<ul>";
	foreach ($tree as $type => $branch) {
		$typename = $typenames[$type];
		echo "<li>$typename";
		foreach ($branch as $v) {
			$diff = $v['Level'] - $last_level;
			if ($diff == 0)  {
				echo '<li>' .$v['Name']."</li>\n";
			}
			elseif ($diff > 0) {
				for ($i = 0; $i < $diff; $i++) {
					echo "<ul>\n";
				}
				echo '<li>' .$v['Name']."</li>\n" ;
			}
			else {
				for ($i = 0; $i > $diff; $i--) {
					echo '</ul>';
				}
				echo '<li>' .$v['Name']."</li>\n" ;
			}
			$last_level = $v['Level'];
		}
		while ($last_level-- > 0) {
			echo "</ul>\n";
		}
		echo "</ul></li>\n";
		$last_level=-1;
	}
	echo "</ul>";
}


function printSMZTreebyType ($treebytype, $useStage) {
    echo "<ul>";
    foreach ($treebytype as $v) {
		$link = "smzTreeSql.php?id={$v['Id']}" . ($useStage ? "&useStage=1" : "");
        echo "<li><a href=\"$link\">{$v['Name']}</a></li>";
    }
        
}



?>
