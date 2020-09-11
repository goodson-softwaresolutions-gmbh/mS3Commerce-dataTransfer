<?php
header('Content-Type: text/html; charset=utf-8');
$error = false;

if (!defined('MS3C_EXT_ROOT')) {
	define('MS3C_EXT_ROOT', __DIR__.'/..');
}

function startTest() {
	global $error;
	testPHPVersion();
	testUploadSizes();
	testRuntimeFiles();
	if (!$error) {
		testDB();
		testES();
		testFolder();
		testCMS();
		if (!$error) {
			testDBTables();
		}
	}
	outputHeadline("Ergebnis");
	if ($error) {
		outputMessage("Da Fehler gefunden wurden wird ihr System nicht funktionieren!", 2);
	} else {
		if(isset($_GET['run'])){
			require_once __DIR__.'/dataTransfer_config.php';
			outputMessage("Laufzeittest wurde bestanden!", 0);
		}
		outputMessage("Gratuliere, Sie haben ihr System richtig konfiguriert!", 0);
	}
}

/**
 * Überorüfen der PHP Version!
 */
function testPHPVersion() {
	outputHeadline("PHP Version");
	$version = explode(".", phpversion());
	if ($version[0] == 5) {
        outputMessage("Sie benutzen eine veraltete PHP Version " . phpversion() . ". In ihrer Version kann es zu Fehlern kommen da mS3 Commerce nicht für diese Version ausgelegt ist.", 1);
	} else if ($version[0] == 7) {
	    if ($version[1] <= 4) {
            outputMessage("Ihre PHP Version " . phpversion() . " ist korrekt!", 0);
        } else {
            outputMessage("Sie benutzen eine veraltete PHP Version " . phpversion() . ". In ihrer Version kann es zu Fehlern kommen da mS3 Commerce nicht für diese Version ausgelegt ist.", 1);
        }
	} else {
		outputMessage("Ihre PHP Version " . phpversion() . " wird nicht unterstützt. Sie sollten auf PHP 7.0 - 7.4 updaten!", 2);
	}
}

/**
 * Überprüft ob die Konfigurationsdateien existieren
 */
function testRuntimeFiles() {
	outputHeadline("Konfigurationsdateien");
	checkFileExists("runtime_config.php");
	checkFileExists("mS3CommerceDBAccess.php");
	checkFileExists("mS3CommerceStage.php");
}

/**
 * Überprüft Upload Größen
 */
function testUploadSizes() {
	outputHeadline("Upload Größe");
	testUploadMinSize("post_max_size");
	testUploadMinSize("upload_max_filesize");
}

function testUploadMinSize($name) {
	$sizeStr = ini_get($name);
	$sizeNr = getSizeInBytes($sizeStr);
	
	$minSize = 512*1024;
	
	if ($sizeNr < 0) {
		outputMessage("$name kann nicht gelesen werden ($sizeStr)!", 1);
	} else if ($sizeNr < $minSize) {
		outputMessage("$name ist zu klein (min. 512kB)!", 2);
	} else {
		outputMessage("$name ist korrekt ($sizeStr)!", 0);
	}
}

function getSizeInBytes($size) {
	$matches = array();
	$ok = preg_match('/^\s*(\d+)(K|M|G)?\s*$/i', $size, $matches);
	if ($ok) {
		$fac = 1;
		if (count($matches) == 3) {
			switch (strtoupper($matches[2])) {
			case 'K':
				$fac = 1024;
				break;
			case 'M':
				$fac = 1024*1024;
				break;
			case 'G':
				$fac = 1024*1024*1024;
				break;
			default:
				return -1;
			}
		}
		
		return $matches[1]*$fac;
	}
	
	return -1;
}

/**
 * Überprüft welche Erweiterungen eingestellt ist
 */
function testCMS() {
	outputHeadline("Erweiterungen");
	switch (MS3C_CMS_TYPE) {
		case 'None':
			outputMessage("Sie verwenden keine Erweiterung!", 0);
			break;
		case 'Typo3':
			outputMessage("Sie verwenden Typo3!", 0);
			testTypo3();
			break;
		case 'OXID':
			outputMessage("Sie verwenden OXID!", 0);
			break;
		case 'Magento':
			outputMessage("Sie verwenden Magento!", 0);
			break;
		case 'Shopware':
			outputMessage("Sie verwenden Shopware!", 0);
			break;
		default:
			outputMessage("Die Variabel MS3C_CMS_TYPE hat einen ungültigen Wert!", 2);
			break;
	}
}

/**
 * Testet die Typo3 spezifischen anforderungen
 */
function testTypo3() {
    if (MS3C_TYPO3_TYPE == 'FX') {
        if(file_exists(MS3C_EXT_ROOT."/typo3conf/ext/ms3commercefx/ext_emconf.php")){
            outputMessage("Die mS3 Commerce Typo3 FX Erweiterung existiert!", 0);
        }else{
            outputMessage("Die mS3 Commerce Typo3 FX Erweiterung wurde nicht gefunden!", 2);
        }
        switch (MS3C_SHOP_SYSTEM) {
            case 'None':
                outputMessage("Typo3 verwendet keinen Shop!", 0);
                break;
            case 'tx_cart':
                outputMessage("Typo3 verwendet tx_cart als Shop!", 0);
                testTXCarts();
                break;

            default:
                outputMessage("Die Variabel MS3C_SHOP_SYSTEM hat einen ungültigen Wert!", 2);
                break;
        }
    } else {
        outputMessage("Sie verwenden die veraltete mS3 Commerce Extension. Funktionalität und Support kann nicht garantiert werden.", 1);
        if(file_exists(MS3C_EXT_ROOT."/typo3conf/ext/ms3commerce/pi1/class.tx_ms3commerce_db.php")){
            outputMessage("Die mS3 Commerce Typo3 Erweiterung existiert!", 0);
        }else{
            outputMessage("Die mS3 Commerce Typo3 Erweiterung wurde nicht gefunden!", 2);
        }
        switch (MS3C_SHOP_SYSTEM) {
            case 'None':
                outputMessage("Typo3 verwendet keinen Shop!", 0);
                break;
            case 'tt_products':
                outputMessage("Typo3 verwendet tt_products als Shop!", 0);
                testTTProducts();
                break;

            default:
                outputMessage("Die Variabel MS3C_SHOP_SYSTEM hat einen ungültigen Wert!", 2);
                break;
        }
    }
}

/**
 * Testen die Anforderungen von TT_Products
 */
function testTTProducts() {
	checkFileExists('shop/tt_products.php');
}

function testTXCarts() {
    if (file_exists(MS3C_EXT_ROOT."/typo3conf/ext/cart/ext_emconf.php")) {
        outputMessage("Die tx_cart Extension existiert!", 0);
    } else {
        outputMessage("Die tx_cart Extension wurde nicht gefunden!", 2);
    }
}

/**
 * Stellt fest ob eine Verbindung zum MYSQL Server möglich ist
 */
function testDB() {
	outputHeadline("Datenbank");
	$dbs = getDBConnections();
	switch (MS3C_DB_BACKEND) {
		case 'mysqli':
			if (function_exists('mysqli_connect')) {
				outputMessage("Das MYSQLI Modul ist installiert!", 0);
			} else {
				outputMessage("Das MYSQLI Modul ist nicht installiert!", 2);
			}
			testDBConnection($dbs, 0);
			break;
		case 'mysql':
			if (function_exists('mysql_connect')) {
				outputMessage("Das MYSQL Modul ist installiert!", 0);
			} else {
				outputMessage("Das MYSQL Modul ist nicht installiert!", 2);
			}
			if (function_exists('mysqli_connect')) {
				outputMessage("Sie sollten MS3C_DB_BACKEND auf mysqli ändern!", 1);
			}
			testDBConnection($dbs, 1);
			break;
		default:
			outputMessage("Die Variabel MS3C_DB_BACKEND hat einen ungültigen Wert!", 2);
			break;
	}
}

function testDBConnection($dbs, $type) {
	foreach ($dbs as $dbname => $dbc) {
		if ($dbc['host'] == "" || $dbc['username'] == "" || $dbc['database'] == "") {
			outputMessage("Für die Verbdindung zur $dbname fehlen manche Zugangsdaten!", 2);
			continue;
		}
		$method = ($type == 0) ? "testDBConnectionWithMYSQLI" : "testDBConnectionWithMYSQL";
		if ($method($dbc['host'], $dbc['username'], $dbc['password'], $dbc['database'])) {
			outputMessage("Verbindung von " . $dbname . " auf die Datenbank ist möglich!", 0);
			//testPrivileges($dbc, $type);
		} else {
			outputMessage("Verbindung von " . $dbname . " auf die Datenbank ist nicht möglich!", 2);
		}
	}
}

function testDBConnectionWithMYSQLI($host, $username, $password, $database) {
	if (@mysqli_connect($host, $username, $password, $database)) {
		return true;
	}
	return false;
}

function testDBConnectionWithMYSQL($host, $username, $password, $database) {
	if ($handler = @mysql_connect($host, $username, $password)) {
		if (@mysql_select_db($database, $handler)) {
			return true;
		}
	}
	return false;
}

/**
 * Überprüfen der ElasticSearch anforderungen
 */
function testES() {
	outputHeadline("ElasticSearch");
	if (MS3C_SEARCH_BACKEND == "ElasticSearch") {
		outputMessage("ElasticSearch wird verwendet!", 0);
		if (checkFileExists("search/elasticsearch/ElasticSearch_config.php")) {
			if (is_array(getElasticSearchHosts())) {
				outputMessage("Hosts wurde gefunden!", 0);
				if (checkFileExists(MS3C_ELASTICSEARCH_API_DIR."/autoload.php")) {
					try {
						$client = new Elasticsearch\Client();
						$health = $client->cluster()->health();
						if ($health['status'] == 'red') {
							outputMessage("Der ElasticSearch Cluster hat einen Fehler!", 2);
						}else{
							outputMessage("ElasticSearch Abfrage funktioniert!", 0);
						}
					} catch (Exception $e) {
						outputMessage("Es konnte keine Verbindung zum Cluster aufgebaut werden!", 2);
					}
				}
			} else {
				outputMessage("Keine Hosts wurde gefunden!", 2);
			}
		}
	} else {
		outputMessage("ElasticSearch wird nicht verwendet!", 0);
	}
}

/**
 * Überprüft ob der MySQL User die benötigten Rechte hat
 * @param type $dbc
 * @param type $type
 */
function testPrivileges($dbc, $type) {
	$must_privileges = array("CREATE", "UPDATE", "DELETE", "INSERT", "CREATE", "DROP", "ALTER", "SELECT");
	$method = ($type == 0) ? "mysqliQuery" : "mysqlQuery";
	$sql = "SELECT PRIVILEGE_TYPE FROM INFORMATION_SCHEMA.USER_PRIVILEGES WHERE GRANTEE = \"'" . $dbc['username'] . "'@'" . $dbc['host'] . "'\"";
	$privileges_table = $method($sql, $dbc);
	$have_privileges = array();
	foreach ($privileges_table as $privileges) {
		$have_privileges[] = $privileges['PRIVILEGE_TYPE'];
	}
	$error = false;
	foreach ($must_privileges as $privilege) {
		if (!in_array($privilege, $have_privileges)) {
			outputMessage("Der Benutzer '" . $dbc['username'] . "'@'" . $dbc['host'] . "' hat das Recht " . $privilege . " nicht!", 2);
			$error = true;
		}
	}
	if (!$error) {
		outputMessage("Der Benutzer '" . $dbc['username'] . "'@'" . $dbc['host'] . "' hat alle benötigten Rechte!", 0);
	}
}

/**
 * SQL Query über MySQLI
 * @param type $sql
 * @param type $dbc
 * @return type
 */
function mysqliQuery($sql, $dbc) {
	$ret = array();
	$conn = mysqli_connect($dbc['host'], $dbc['username'], $dbc['password'], $dbc['database']);
	$result = mysqli_query($conn, $sql);
	if ($result &&mysqli_num_rows($result) > 0) {
		while ($row = mysqli_fetch_assoc($result)) {
			$ret[] = $row;
		}
	}
	return $ret;
}

/**
 * MySQL Query über MYSQL
 * @param type $sql
 * @param type $dbc
 * @return type
 */
function mysqlQuery($sql, $dbc) {
	$ret = array();
	if ($handler = @mysql_connect($dbc['host'], $dbc['username'], $dbc['password'])) {
		if (@mysql_select_db($dbc['database'], $handler)) {
			$result = mysql_query($sql, $handler);
			if (mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					$ret[] = $row;
				}
			}
		}
	}
	return $ret;
}

/**
 * Überprüft die Schreibrechte auf bestimmten Ordner und Dateien
 */
function testFolder() {
	outputHeadline("Schreibrechte");
	$error = false;
	checkWriteAccess("../dataTransfer/log", $error);
	checkWriteAccess("../dataTransfer/uploads", $error);
	checkWriteAccess("../dataTransfer/diff", $error);
	checkWriteAccess("../dataTransfer/ext", $error);
	checkWriteAccess("../dataTransfer/mS3CommerceStage.php", $error);
	checkWriteAccess("../Graphics", $error);
	if (!$error) {
		outputMessage("Sie haben Schreibrechte auf alle benötigten Ordner und Dateien!", 0);
	}
}

function checkWriteAccess($file, &$error) {
	if (is_dir($file)) {
		if (!is_writable($file)) {
			outputMessage("Auf den Ordner $file kann nicht zugegriffen werden!", 2);
			$error = true;
		}
	} else if (file_exists($file)) {
		if (!is_writable($file)) {
			outputMessage("Die Datei $file kann nicht verändert werden!", 2);
			$error = true;
		}
	} else {
		outputMessage("$file existiert nicht!", 2);
		$error = true;
	}
}

/**
 * Schaut nach ob alle benötigten Tabellen vorhanden sind
 */
function testDBTables() {
	outputHeadline("Datenbankstruktur");
	$dbs = getDBConnections();
	checkFileExists(__DIR__.'/dbconfig/defaultTables.php');
	if(MS3C_ALLOWCREATE_SQL == 1){
		outputMessage("MS3C_ALLOWCREATE_SQL sollte auf 0 stehen!", 1);
	}else{
		outputMessage("MS3C_ALLOWCREATE_SQL steht auf 0!", 0);
	}
	$tables = $GLOBALS['MS3C_TABLES'];
	if (isOXIDOnly() || isMagentoOnly() || isShopwareOnly()) {
		$tables = array();
	}
	if(MS3COMMERCE_STAGETYPE == "TABLES"){
		$tables_new = array();
		foreach ($tables as $table){
			$table = strtolower($table);
			$tables_new[] = "tx_ms3commerce_".$table."_s1";
			$tables_new[] = "tx_ms3commerce_".$table."_s2";
		}
		$tables = $tables_new;
	}
	$method = (MS3C_DB_BACKEND == "mysqli") ? "mysqliQuery" : "mysqlQuery";
	foreach ($dbs as $dbname => $dbc) {
		$error = false;
		foreach ($tables as $table) {
			$result = $method("SHOW TABLES LIKE '$table'", $dbc);
			if (count($result) == 0) {
				outputMessage("Die Tabelle $table existiert nicht auf der Datenbank " . $dbc['database'] . "!", 1);
				$error = true;
			}
		}
		if (!$error) {
			outputMessage("Die Datenbank " . $dbc['database'] . " beinhaltet alle benötigten Tabellen!", 0);
		}
		$culumnname = "";
		switch(MS3C_REALURL_SHOP_CHECK_TYPE){
			case "SysLanguageUid":
				$culumnname = "sys_language_uid";
				break;
			case "ShopId":
				$culumnname = "ShopId";
				break;
			case "ContextId":
				$culumnname = "asim_mapid";
				break;
			default:
				continue(2);
		}
		$realresult = $method("show columns from ".RealURLMap_TABLE." like '$culumnname'",$dbc);
		if(!$realresult){
			outputMessage("Die Tabelle ".RealURLMap_TABLE." hat die Spalte $culumnname nicht!", 1);
		}
	}
	
}

function isOXIDOnly() {
	return MS3C_CMS_TYPE == "OXID" && defined('MS3C_OXID_ONLY') && MS3C_OXID_ONLY;
}

function isMagentoOnly() {
	return MS3C_CMS_TYPE == "Magento" && defined('MS3C_MAGENTO_ONLY') && MS3C_MAGENTO_ONLY;
}

function isShopwareOnly() {
	return MS3C_CMS_TYPE == "Shopware" && defined('MS3C_SHOPWARE_ONLY') && MS3C_SHOPWARE_ONLY;
}

function getDBConnections() {
	$dbs = MS3C_DB_ACCESS();
	if (isOXIDOnly() || isMagentoOnly()) {
		unset($dbs['tables']);
		unset($dbs['stagedb1']);
		unset($dbs['stagedb2']);
	} else {
		switch (MS3COMMERCE_STAGETYPE) {
			case "DATABASES":
				unset($dbs['tables']);
				break;
			case "TABLES":
				unset($dbs['stagedb1']);
				unset($dbs['stagedb2']);
				break;
		}
	}
	return $dbs;
}

function checkFileExists($file) {
	if (file_exists($file)) {
		outputMessage("Die Datei $file existiert!", 0);
		include_once $file;
		return true;
	} else {
		outputMessage("Die Datei $file existiert nicht!", 2);
		return false;
	}
}

function outputHeadline($headline) {
	echo '<h2>' . $headline . '</h2>';
}

/**
 * 
 * @param type $message
 * @param type $status 0:OK 1:Warning 2:Fehler
 */
function outputMessage($message, $status) {
	echo '<div class="message ';
	switch ($status) {
		case 2:
			echo 'error';
			global $error;
			$error = true;
			break;
		case 1:
			echo 'warning';
			break;
		default :
			echo 'ok';
			break;
	}
	echo '" >' . $message . '</div>';
}
?>
<!DOCTYPE html>
<html>
	<head>
		<style>
			head,body{
				margin:0;
				padding:0;
				font-family: arial;
				background: #eee;
			}
			h1{
				font-size: 30px;
				border-bottom: 1px solid #000;
			}
			h2{
				font-size: 20px;
				margin:0;
				border-bottom: 1px solid #000;
				margin-top: 10px;
				margin-bottom: 4px;
			}
			.message{
				font-size: 15px;
				border: 1px solid;
				padding: 5px;
				margin: 10px;
			}
			#testResults .ok{
				color: #00cc00
			}
			#testResults .warning{
				color: #ff9900
			}
			#testResults .error{
				color: #ff0033
			}
			#testResults{
				margin: 0px auto;
				width: 800px;
				margin-top: 30px;
				border: 1px solid #000;
				padding: 20px;
				background: #fff;
				box-shadow: 0 0 8px 2px #000;
				border-radius: 3px;
				margin-bottom: 20px;
			}
			button{
				margin:10px;
				font-size: 17px;
			}
		</style>
	</head>
	<body>
		<div id="testResults">
			<h1>mS3 Commerce Check Tool</h1>
			<?php
			startTest();
			?>
			<button onclick="location.reload()">Neu Laden</button>
			<?php
			if(!$error){
				echo '<button onclick="window.location.href=\''.basename(__FILE__).'?run=test\'">Laufzeittest</button>';
			}
			?>
		</div>
	</body>
</html>