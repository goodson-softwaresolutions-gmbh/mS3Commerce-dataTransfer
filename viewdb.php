<?php
/* * *************************************************************
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
 * ************************************************************* */

require_once(__DIR__ .'/dataTransfer_config.php');
require_once(MS3C_ROOT .'/dataTransfer/mS3Commerce_db.php');

date_default_timezone_set('Europe/Vienna');

$menuId = -1;
$groupId = -1;
$productId = -1;
$documentId = -1;
$contextId = -1;
$searchTerm = "";

$useStage = false;

$dialog = "";

if (MS3COMMERCE_STAGETYPE == 'TABLES')
    $stageDb = MS3COMMERCE_STAGE_SUFFIX;
else
    $stageDb = MS3COMMERCE_STAGE_DB;

if ($_POST) {
    if (array_key_exists('MenuId', $_POST) && isset($_POST['MenuId'])) {
        $menuId = $_POST['MenuId'];
    }

    if (array_key_exists('useStage', $_POST) && isset($_POST['useStage'])) {
        $useStage = $_POST['useStage'];
    }

    if (array_key_exists('GroupId', $_POST) && isset($_POST['GroupId'])) {
        $groupId = $_POST['GroupId'];
    }

    if (array_key_exists('ProductId', $_POST) && isset($_POST['ProductId'])) {
        $productId = $_POST['ProductId'];
    }

    if (array_key_exists('DocumentId', $_POST) && isset($_POST['DocumentId'])) {
        $documentId = $_POST['DocumentId'];
    }

    if (array_key_exists('ContextId', $_POST) && isset($_POST['ContextId'])) {
        $contextId = $_POST['ContextId'];
    }

    if (array_key_exists('search', $_POST) && isset($_POST['search'])) {
        $searchTerm = $_POST['search'];
    }
}

$db = tx_ms3commerce_db_factory::buildDatabase(false, $useStage);

findMenuId($menuId, $groupId, $productId, $documentId, $contextId, $searchTerm);

function printShopInfo($db) {
    $ret = '<div class="tableArea"><div class="tableHeader">Shop Status:</div><div class="tableContent">';
    $ret .= '<table border="0">';
    $ret .= '<tr class="firstline">';
    $ret .= '<td>Shop Id</td>';
    $ret .= '<td>Root Id</td>';
    $ret .= '<td>Base Export Date</td>';
    $ret .= '<td>Import Date</td>';
    $ret .= '<td>Upload Date</td>';
    $ret .= '</tr>';

    $sql = "SELECT * FROM ShopInfo ORDER BY ShopId";
    $rs = $db->sql_query($sql);

    while ($row = $db->sql_fetch_assoc($rs)) {
        $ret .= '<tr class="line">';
        $ret .= "<td>{$row['ShopId']}</td>";
        $ret .= "<td>{$row['RootGroupId']}</td>";
        $ret .= '<td>' . date('Y-m-d, H:i:s', strtotime($row['BaseExportDate'])) . '</td>';
        $ret .= '<td>' . date('Y-m-d, H:i:s', strtotime($row['ImportDate'])) . '</td>';
        $ret .= '<td>' . date('Y-m-d, H:i:s', strtotime($row['UploadDate'])) . '</td>';
        $ret .= '</tr>';
    }

    $db->sql_free_result($rs);

    $ret .= '</table>';
    $ret .= '</div></div>';
    echo $ret;
}

function getElementType($menuId, $db, &$elemId) {
    $SQL_Menu = 'SELECT GroupId, ProductId, DocumentId FROM Menu WHERE Id = ' . $db->sql_escape($menuId);
	$res = $db->sql_query($SQL_Menu, 'Menu');
	$row = $db->sql_fetch_assoc($res);
	$db->sql_free_result($res);
	$elemId = 0;
	if ($row) {
		if ($row['GroupId']) {
            $elemId = $row['GroupId'];
			return 'G';
		} else if ($row['ProductId']) {
            $elemId = $row['ProductId'];
			return 'P';
		} else if ($row['DocumentId']) {
            $elemId = $row['DocumentId'];
			return 'D';
		} else {
            $elemId = 0;
			return '';
		}
	}
}

function printElement($menuId, $db) {
	$elemId = 0;
	switch(getElementType($menuId, $db, $elemId)) {
		case 'G':
			$tablePrefix = $elemType = 'Group';
			$table = 'Groups';
			break;
		case 'P':
			$table = $tablePrefix = $elemType = 'Product';
			break;
		case 'D':
			$table = $tablePrefix = $elemType = 'Document';
			break;
        default:
			return;
	}
	
	$SQL_Name = "SELECT Name, AuxiliaryName FROM `$table` WHERE Id = $elemId";
	$res = $db->sql_query($SQL_Name, $table);
	$row = $db->sql_fetch_assoc($res);
	$db->sql_free_result($res);
	
	echo '<div class="tableArea"><a name="element"></a><div class="tableHeader">' . $elemType . ' "' . $row['Name'] . '"';
	if (!empty($row['AuxiliaryName'])) echo ' - "' . $row['AuxiliaryName'] . '"';
	echo '</div><div class="tableContent">';
	
	$SQL_Feature = "
        SELECT f.Name AS FeatureName, v.ContentPlain AS FeatureValue
        FROM {$tablePrefix}Value v
        INNER JOIN Feature f ON f.Id = v.FeatureId
        INNER JOIN StructureElement s ON s.Id = f.StructureElementId
        WHERE v.{$tablePrefix}Id = '$elemId'
        ORDER BY s.OrderNr, f.Name";
	
	$res = $db->sql_query($SQL_Feature, array('Feature f',$tablePrefix.'Value v','StructureElement s'));
	
	if ($res) {
        echo '<table border="0">
        <tr class="firstline"><td>Feature</td><td>Value</td></tr>';
        while ($row = $db->sql_fetch_assoc($res)) {
            echo '<tr class="line"><td>' . $row['FeatureName'] . '</td><td>' . $row['FeatureValue'] . '</td></tr>';
        }
        echo '</table>';
        
        $db->sql_free_result($res);
	}
	echo '</div>';
	echo '</div>';
}

function printChildren($parentMenuId, $table, $prefix, $db, $useLinks, $isDoc = false) {

    echo '<div class="tableArea"><a name="' . $table . '"></a><div class="tableHeader">' . $table . ':</div><div class="tableContent">';

    if ($isDoc) {
        $add = "c.FilePath";
    } else {
        $add = "''";
    }
    $SQL_Menu = 'SELECT m.Id, c.Name, c.AuxiliaryName, c.AsimOid, m.ContextId, c.Id AS ChildId, ' . $add . ' AS Path
		FROM Menu m
		INNER JOIN `' . $table . '` c
			ON c.Id = m.' . $prefix . 'Id
		WHERE m.ParentId ';


    $SQL_Feature = 'SELECT DISTINCT f.`Name`, s.OrderNr
		FROM Feature f
		INNER JOIN ' . $prefix . 'Value cv
			ON f.Id = cv.FeatureId
		INNER JOIN StructureElement s
		    ON f.StructureElementId = s.Id
		INNER JOIN `' . $table . '` c
			ON c.Id = cv.' . $prefix . 'Id
		INNER JOIN Menu m
			ON m.' . $prefix . 'Id = c.Id
		WHERE m.ParentId ';

    $SQL_FeatVal = 'SELECT f.`Name`, cv.ContentPlain AS Value
		FROM ' . $prefix . 'Value cv
		INNER JOIN Feature f
			ON cv.FeatureId = f.Id
		WHERE cv.' . $prefix . 'Id = ';

    if ($parentMenuId > 0) {
        $SQL_Menu .= "= $parentMenuId";
        $SQL_Feature .= "= $parentMenuId";
    } else {
        $SQL_Menu .= "IS NULL";
        $SQL_Feature .= "IS NULL";
    }

    $SQL_Feature .= " ORDER BY s.OrderNr, f.name";

    $SQL_Menu.=" ORDER BY m.Id";

    echo '<table border="0">
	<tr class="firstline"><td>MenuId</td><td>' . $prefix . 'Id</td><td>asim OID</td><td>Name</td><td>Auxiliary Name</td>
	';
    if ($isDoc)
        echo '<td>File</td>';

    # Get all Features that appear in all children => into array
    $featrs = $db->sql_query($SQL_Feature, array('Feature f', $prefix . 'Value cv', "`$table` c", 'Menu m', 'StructureElement s'));
    $feats = array();
    while ($featrow = $db->sql_fetch_object($featrs)) {
        echo "<td>$featrow->Name</td>";
        $feats[] = $featrow->Name;
    }
    echo '</tr>';
    $db->sql_free_result($featrs);

    # Iterate through all children
    $mrs = $db->sql_query($SQL_Menu, array('Menu m', "`$table` c"));
    while ($mrow = $db->sql_fetch_object($mrs)) {
        echo '<tr class="line"><td>';
        if ($useLinks) {
            echo "<a href=\"javascript:toMenu($mrow->Id);\">$mrow->Id</a>";
        } else {
            echo $mrow->Id;
        }
        echo "</td><td>$mrow->ChildId</td><td>$mrow->ContextId</td><td>$mrow->Name</td><td>$mrow->AuxiliaryName</td>";
        if ($isDoc)
            echo "<td>$mrow->Path&nbsp;</td>";

        # Get Featurevalues for children
        $fvrs = $db->sql_query($SQL_FeatVal . $mrow->ChildId, array($prefix . 'Value cv', 'Feature f'));
        $curvalues = array();
        while ($fvrow = $db->sql_fetch_object($fvrs)) {
            $curvalues[$fvrow->Name] = $fvrow->Value;
        }
        $db->sql_free_result($fvrs);

        # Print feature values
        foreach ($feats as $feature) {
            echo '<td>';
            if (array_key_exists($feature, $curvalues)) {
                echo preg_replace(array('/</', '/>/'), array('&lt;', '&gt;'), $curvalues[$feature]);
            }
            echo '&nbsp;';
            echo '</td>';
        }
    }

    echo '</table>';
    echo '</div></div>';
}

function printSMZs($menuId, $db, $useStage) {
    if (defined('MS3C_NO_SMZ') && MS3C_NO_SMZ) {
        // No SMZs
        return;
    }

    $elemId = 0;
    $elemType = getElementType($menuId, $db, $elemId);
    if ($elemType != 'G' && $elemType != 'P') 
        return;
    
    echo '<div class="tableArea"><a name="smz"></a><div class="tableHeader">SMZs:</div><div class="tableContent">';
    
    if (!$elemId) {
        echo "&lt;Global SMZs&gt;";
        $SQL_SMZ = "SELECT smz.Id, smz.Name, smz.Type FROM featureCompilation smz " .
                "WHERE smz.Id NOT IN (" .
                "SELECT s.FeatureCompId FROM FeatureCompValue s" .
                ") ORDER BY smz.Id";
    } else {
        if ($elemType == 'G') {
            $field = 'GroupId';
        } else if ($elemType == 'P') {
            $field = 'ProductId';
        }

        $SQL_SMZ = "SELECT smz.Id, smz.Name, smz.Type FROM featureCompilation smz " .
                "INNER JOIN FeatureCompValue s ON smz.id = s.FeatureCompId " .
                "WHERE s.$field = $elemId ORDER BY smz.Id";
    }

    echo "<table border='0'><tr class=\"firstline\"><td>Id</td><td>Name</td><td>Typ</td></tr>";
    $smzrs = $db->sql_query($SQL_SMZ, array('featureCompilation smz', 'FeatureCompValue s'));
    if ($smzrs) {
        while ($smzrow = $db->sql_fetch_object($smzrs)) {
            $link = "smzTreeSql.php?id={$smzrow->Id}";
            if ($useStage) $link .= '&useStage=1';
            echo "<tr class=\"line\"><td><a href=\"$link\">{$smzrow->Id}</a></td><td>{$smzrow->Name}</td><td>{$smzrow->Type}</td></tr>";
        }
        $db->sql_free_result($smzrs);
    }
    echo "</table>";
    echo '</div></div>';
}

function printRelations($menuId, $db, $useStage) {
    $sql = "SELECT r.*, COALESCE(dg.Name, dp.Name, dd.Name) As DestName, COALESCE(dg.AuxiliaryName, dp.AuxiliaryName, dd.AuxiliaryName) As DestAuxiliaryName
FROM Relations r
INNER JOIN Menu m ON r.GroupId = m.GroupId OR r.ProductId = m.ProductId OR r.DocumentId = m.DocumentId
LEFT JOIN `Groups` dg ON dg.Id = r.DestinationId AND r.DestinationType = 1
LEFT JOIN Product dp ON dp.Id = r.DestinationId AND r.DestinationType = 2
LEFT JOIN Document dd ON dd.Id = r.DestinationId AND r.DestinationType = 3
WHERE m.Id = $menuId AND r.IsMother = 1
ORDER BY r.OrderNr, r.Name, r.DestinationId";

    echo '<div class="tableArea"><a name="relations"></a><div class="tableHeader">Relations:</div><div class="tableContent">';

    echo "<table border='0'><tr class=\"firstline\"><td>Id</td><td>Type</td><td>Dir</td><td>Target</td><td>Text1</td><td>Text2</td><td>Amount</td></tr>";
    $rs = $db->sql_query($sql, array('featureCompilation smz', 'FeatureCompValue s'));

    if ($rs) {
        while ($row = $db->sql_fetch_object($rs)) {
            switch ($row->DestinationType) {
                case 1: $link = "byGroupId({$row->DestinationId});"; break;
                case 2: $link = "byProductId({$row->DestinationId});"; break;
                case 3: $link = "byDocumentId({$row->DestinationId});"; break;
            }

            echo "<tr class=\"line\">
                    <td>{$row->Id}</td>
                    <td>{$row->Name}</td>
                    <td>".($row->IsMother ?'TO':'FROM')."</td>
                    <td><a href=\"javascript:$link\">{$row->DestName} {$row->DestAuxiliaryName}</a></td>
                    <td>{$row->Text1}</td>
                    <td>{$row->Text2}</td>
                    <td>{$row->Amount}</td>
                </tr>";
        }

        $db->sql_free_result($rs);
    }
    echo "</table>";
    echo '</div></div>';
}

## Find MenuId for Group/Product Id

function findMenuId(&$menuId, $groupId, $productId, $documentId, $contextId, $searchTerm) {
    global $db;
    $tables = 'Menu';
    if ($menuId <= 0) {
        $SQL_FindMenu = false;
        if ($groupId > 0) {
            $SQL_FindMenu = '
		SELECT ParentId AS mId, Id
		FROM Menu
		WHERE GroupId = ' . $groupId;
        } else if ($productId > 0) {
            $SQL_FindMenu = '
		SELECT ParentId AS mId, Id
		FROM Menu
		WHERE ProductId = ' . $productId;
        } else if ($documentId > 0) {
            $SQL_FindMenu = '
		SELECT ParentId AS mId, Id
		FROM Menu
		WHERE DocumentId = ' . $documentId;
        } else if ($contextId != '' && $contextId != -1) {
            $SQL_FindMenu = '
		SELECT ParentId AS mId, Id
		FROM Menu
		WHERE ContextId = "' . $contextId . '"';
        } else if ( strlen((trim($searchTerm))) > 0 ) {
            $term = $db->sql_escape("%$searchTerm%");
          $SQL_FindMenu = "
          SELECT m.ParentId AS mId, m.Id
          FROM Menu m
          LEFT JOIN `Groups` g ON g.Id = m.GroupId AND (g.Name LIKE $term OR g.AuxiliaryName LIKE $term)
          LEFT JOIN Product p ON p.Id = m.ProductId AND (p.Name LIKE $term OR p.AuxiliaryName LIKE $term)
          LEFT JOIN Document d ON d.Id = m.DocumentId AND (d.Name LIKE $term OR d.AuxiliaryName LIKE $term)
          WHERE g.Id IS NOT NULL OR p.Id IS NOT NULL OR d.Id IS NOT NULL
          ";
          $tables = '`Groups` g, Product p, Document d, Menu m';
          }
        if ($SQL_FindMenu) {
            $sql = "SELECT mm.Id mId, mm.ParentId, s.Name StructureElement, 
                    COALESCE(gg.Name, pp.Name, dd.Name) AS Name, COALESCE(gg.AuxiliaryName, pp.AuxiliaryName, dd.AuxiliaryName) AS AuxiliaryName , 
                    CASE WHEN gg.Id IS NOT NULL THEN 'G' WHEN pp.Id IS NOT NULL THEN 'P' ELSE 'D' END AS Type 
                    FROM Menu mm
                    INNER JOIN StructureElement s ON mm.StructureElementId = s.Id 
                    INNER JOIN ($SQL_FindMenu) AS sub ON sub.Id = mm.Id
                    LEFT JOIN `Groups` gg ON gg.Id = mm.GroupId
                    LEFT JOIN Product pp ON pp.Id = mm.ProductId
                    LEFT JOIN Document dd ON dd.Id = mm.DocumentId
                    ORDER BY s.Name, mm.Id
                    ";
            $tables .= ', Menu mm, `Groups` gg, Product pp, Document dd';

            $fmrs = $db->sql_query($sql, $tables);
            while ($row = $db->sql_fetch_object($fmrs)) {
                $fmrow[] = $row;
            }
            if (isset($fmrow) && $fmrow) {
                if (count($fmrow) == 1) {
                    $menuId = $fmrow[0]->mId;
                } else {
                    global $dialog;
                    $dialog .= "<b>Results: </b><br/>";
                    foreach ($fmrow as $row) {
                        $dialog .= "<a href=\"javascript:toMenu({$row->mId})\">MenuId: {$row->mId} -> {$row->StructureElement}: {$row->Name} {$row->AuxiliaryName}</a></br>";
                    }
                }
            } else {
                global $dialog;
                $dialog .= "<b>Item not found!</b>";
            }
            $db->sql_free_result($fmrs);
        }
    }
}

function toParent($menuId) {
    if ($menuId > 0) {
        global $db;
        $SQL_Parent = 'SELECT ParentId 
        FROM Menu
        WHERE Id = ' . $menuId;

        $prs = $db->sql_query($SQL_Parent, 'Menu');
        $prow = $db->sql_fetch_object($prs);
        if ($prow) {
            $parentId = -1;
            if ($prow->ParentId) {
                $parentId = $prow->ParentId;
            }
            echo "<input type=\"submit\" value=\"<- To Parent\" onclick=\"toMenu($parentId);\">";
        }
        $db->sql_free_result($prs);
    }
}
?>

<!DOCTYPE html>
<html>
	<head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
        <style>
            html, body{
                margin: 0;
                padding: 0;
                height: 100%;
                font-size: 15px;
                font-family: Arial;
            }
            body{
                overflow: hidden;
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
            #wrapper{
                margin: 0;
                padding: 3px;
                padding-top: 10px;
                overflow-y: auto;
                overflow-x: hidden;
                /*height: 100%;*/
            }
            #fixedHeader input[type=text]{
                width: 60px;
            }
            a{
                color: #000;
            }
            a:hover{
                text-decoration: none;
                color: #666;
            }
            .tableArea{
                margin: 10px;
                overflow: hidden;
            }
            .tableHeader{
                background: #eee;
                font-size: 16px;
                font-weight: bold;
                padding: 4px;
            }
            .tableContent{
                overflow-x: auto;
		max-height: 600px;
            }
            .tableContent table{
                background: #fff;
            }
            .tableContent table .firstline{
                background: #ccc;
                font-weight: bold;
            }
            .tableContent table .line{
                background: #eee;
            }
            .tableContent table .line:hover{
                background: #ddd;
            }
            .tableContent table tr td{
                padding: 2px;
                vertical-align: top;
            }
            #dialog{
                position: absolute;
                z-index: 100;
                top: 20%;
                left: 15%;
                width: 70%;
                height: 40%;
                overflow: scroll;
                background: #eee;
                margin-left: -75px;
                margin-top: -50px;
                padding: 10px;
                box-shadow: 0px 0px 8px 2px #000;
                border-radius: 3px;
            }
            #closeDialog{
                cursor: pointer;
                position: absolute;
                top:3px;
                right: 3px;
            }
        </style>
        <script>
            function dosubmit( )
            {
                document.f.ProductId.value = -1;
                document.f.GroupId.value = -1;
                document.f.DocumentId.value = -1;
                document.f.ContextId.value = -1;
                document.f.search.value = "";
                document.f.submit();
            }
            function toMenu(id)
            {
                document.f.MenuId.value = id;
                document.f.ProductId.value = -1;
                document.f.GroupId.value = -1;
                document.f.DocumentId.value = -1;
                document.f.ContextId.value = -1;
                document.f.search.value = "";
                document.f.submit();
            }
            function byGroup( )
            {
                document.f.MenuId.value = -1;
                document.f.ProductId.value = -1;
                document.f.DocumentId.value = -1;
                document.f.ContextId.value = -1;
                document.f.search.value = "";
                document.f.submit();
            }
            function byGroupId(id) {
                document.f.GroupId.value = id;
                byGroup();
            }
            function byProduct( )
            {
                document.f.MenuId.value = -1;
                document.f.GroupId.value = -1;
                document.f.DocumentId.value = -1;
                document.f.ContextId.value = -1;
                document.f.search.value = "";
                document.f.submit();
            }
            function byProductId(id) {
                document.f.ProductId.value = id;
                byProduct();
            }
            function byDocumentId(id) {
                document.f.DocumentId.value = id;
                byDocument();
            }
            function byDocument( )
            {
                document.f.MenuId.value = -1;
                document.f.GroupId.value = -1;
                document.f.ProductId.value = -1;
                document.f.ContextId.value = -1;
                document.f.search.value = "";
                document.f.submit();
            }
            function byContext( )
            {
                document.f.MenuId.value = -1;
                document.f.GroupId.value = -1;
                document.f.ProductId.value = -1;
                document.f.DocumentId.value = -1;
                document.f.search.value = "";
                document.f.submit();
            }
            function bySearch( )
            {
                document.f.MenuId.value = -1;
                document.f.GroupId.value = -1;
                document.f.ProductId.value = -1;
                document.f.DocumentId.value = -1;
                document.f.ContextId.value = -1;
                document.f.submit();
            }

            window.onresize = function (event) {
                setWrapperHeight();
            }

            function setWrapperHeight() {
                var header = document.getElementById("fixedHeader");
                var wrapper = document.getElementById("wrapper");
                wrapper.style.height = (window.innerHeight - header.offsetHeight - 13) + 'px';
            }

            function closeDialog() {
                var dialog = document.getElementById("dialog");
                dialog.style.display = 'none';
            }
        </script>
    </head>

    <body onload="setWrapperHeight()">
        <div id="fixedHeader">
            <div id="headerText"><big>mS3 Commerce dataTransfer Version <?php echo MS3C_VERSION ?></big></div>
            <form action="viewdb.php" method="POST" name="f">
                <?php echo toParent($menuId) ?>
                <label title="<?php echo tx_ms3commerce_db_factory::getDatabaseName(true) . ': ' . $stageDb ?>">
                    <input type="checkbox" onclick="document.f.submit();" name="useStage" value="useStage"
                    <?php
                    if ($useStage)
                        echo 'checked';
                    ?>
                           >StageDB</label>

                <input type="text" name="MenuId" value="<?php echo $menuId ?>">			
                <input type="submit" value="By Menu" onClick="dosubmit();" style="width:100px;">	

                <input type="text" name="GroupId" value="<?php echo $groupId ?>">
                <input type="button" onClick="byGroup();" value="By Group"  style="width:100px;">

                <input type="text" name="ProductId" value="<?php echo $productId ?>">
                <input type="button" onClick="byProduct();" value="By Product" style="width:100px;">

                <input type="text" name="DocumentId" value="<?php echo $documentId ?>">
                <input type="button" onClick="byDocument();" value="By Document" style="width:100px;">

                <input type="text" name="ContextId" value="<?php echo $contextId ?>">
                <input type="button" onClick="byContext();" value="By Context" style="width:100px;">

                <input type="text" name="search" value="<?php echo $searchTerm ?>">
                <input type="button" onClick="bySearch();" value="Search" style="width:100px;">

            </form>
            <div id="wrapperMenu">
                <a href="#Groups">To Groups</a> | 
                <a href="#Product">To Products</a> | 
                <a href="#Document">To Documents</a> | 
                <a href="#smz">To SMZs</a> |
                <a href="#relations">To Relations</a>
            </div>
        </div>
        <div id="wrapper">
            <a name="top"></a>
            <?php
            if ($menuId <= 0) {
                printShopInfo($db);
            } else {
				printElement($menuId, $db);
			}

            printChildren($menuId, 'Groups', 'Group', $db, true);

            printChildren($menuId, 'Product', 'Product', $db, true);

            printChildren($menuId, 'Document', 'Document', $db, false, true);

            printSMZs($menuId, $db, $useStage);

            printRelations($menuId, $db, $useStage);

            $db->sql_close();

            ###################################
            ?>
        </div>
        <?php
        if ($dialog) {
            echo '<div id="dialog">' . $dialog . '<div id="closeDialog" title="Close" onclick="closeDialog();">X</div></div>';
        }
        ?>
    </body>
</html>