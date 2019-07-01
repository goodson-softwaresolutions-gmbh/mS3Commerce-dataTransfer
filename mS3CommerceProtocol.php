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

require_once MS3C_ROOT.'/dataTransfer/mS3CommerceSQL.php';
require_once MS3C_ROOT.'/dataTransfer/mS3CommerceSweep.php';

function mS3CommerceRequest($uploadedFilePath, $mainRequest, $subRequest) {
	switch ($mainRequest) {
		case 'UPLOAD':
			return reqUpload($subRequest, $uploadedFilePath);
			break;
		case 'INFO':
			return reqInfo($subRequest, $uploadedFilePath);
			break;
		case 'COMMAND':
			return reqCommand($subRequest, $uploadedFilePath);
			break;
		default;
			return responseMsg('error', 'Unknown main request: ' . $mainRequest);
			break;
	}
}

function reqUpload($subRequest, $uploadedFilePath) {
	switch ($subRequest) {
		case 'SQL';
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			return reqUploadSQL($uploadedFilePath);
			break;
		case 'MEDIA';
			if (MS3C_DISABLEREQUEST_MEDIA == 1)
				return responseMsg('success', 'Media Reqeusts are disabled on server!');
			return reqUploadMedia($uploadedFilePath);
			break;
		case 'GRAPHICDIFFDB';
			return reqSaveDiffDB($uploadedFilePath);
			break;
		case 'LOG';
			return reqSaveLog($uploadedFilePath);
			break;
		case 'EXT';
			return reqSaveExt($uploadedFilePath);
			break;
		case 'CLEANEXT';
			return reqCleanExtShop();
			break;
		case 'INITIALIZE':
			$command = readUploadedFile($uploadedFilePath);
			return reqInitializeUpload($command);
			break;
		case 'FINALIZE':
			$command = readUploadedFile($uploadedFilePath);
			return reqFinalizeUpload($command);
			break;
		default;
			return responseMsg('error', 'Unknown UPLOAD sub request: ' . $subRequest);
			break;
	}
}

function reqCleanExtShop() {
	$delShop = getParameter('ms3shop');
	if ($delShop) {
		$dir = getShopExtensionDir($delShop);
		if (!deleteDir($dir)) {
			return responseMsg('error', 'could not delete EXT files');
		}
	} else {

		return responseMsg('error', 'parameter \'shop\' is empty');
	}
	return responseMsg('success', 'EXT files Deleted');
}

function deleteDir($dir, $deleteThis = false) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		if ($objects) {
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
						deleteDir($dir . DIRECTORY_SEPARATOR . $object, true);
					} else {
						if (!unlink($dir . DIRECTORY_SEPARATOR . $object)) {
							return false;
						}
					}
				}
			}
			reset($objects);
			if ($deleteThis) {
				rmdir($dir);
			}
		}

		return true;
	} else if (is_file ($dir)) {
		if (!unlink($dir)) {
			return false;
		}
	}
	return true;
	
}

function reqSaveExt($uploadedFilePath) {
	$msgCount = getParameter('ms3msgcount');
	$msgTotal = getParameter('ms3msgtotal');
	$shop = getParameter('ms3shop');
	if (!$shop) {
		return responseMsg('error', 'parameter "ms3shop" is empty!');
	}
	$extDir = getShopExtensionDir($shop);
	$extFile = $extDir . "/" . getParameter('ms3folder') ."/". getParameter('ms3file');
	if (!is_dir(dirname($extFile))) {
		if (!mkdir(dirname($extFile), 0777, true)) {
			$err = error_get_last();
			return responseMsg('error', 'could not create ExtDir ' . $extDir . $err['message']);
		}
	}
	// Create new File because of first send
	if ($msgCount == '0') {
		writeZeroFile($extFile);
	}

	// Append to File
	if (!appendLocalFile($uploadedFilePath, $extFile)) {
		return responseMsg('error', 'upload Ext db failed!');
	}

	// Verify MD5
	if ($msgCount == $msgTotal-1) {
		$totalMD5 = getParameter('ms3totalmd5');
		if ($totalMD5 != "") {
			$localMD5 = md5_file($extFile);
			if ($totalMD5 != $localMD5) {
				@unlink($extFile);
				return responseMsg('error', 'Wrong MD5 checksum!: ' . $extFile);
			}
		}
	}
	
	return responseMsg('success', $extFile);
}

function reqSaveDiffDB($uploadedFilePath) {
	$msgCount = getParameter('ms3msgcount');
	$diffDBDir = MS3C_EXT_ROOT . "/dataTransfer/diff";
	$diffDBFile = $diffDBDir . "/" . getParameter('ms3file');

	if (!file_exists($diffDBDir)) {
		if (!mkdir($diffDBDir)) {
			return responseMsg('error', 'could not create diffDBFolder!' . $diffDBDir);
		}
	}

	// Create new File because of first send
	if ($msgCount == '0') {
		writeZeroFile($diffDBFile);
	}

	// Append to File
	if (!appendLocalFile($uploadedFilePath, $diffDBFile)) {
		return responseMsg('error', 'upload diff db failed!');
	}

	return responseMsg('success', $diffDBFile);
}

function reqSaveLog($uploadedFilePath) {
	$msgCount = getParameter('ms3msgcount');
	$logDir = MS3C_EXT_ROOT . "/dataTransfer/log";
	$logFile = $logDir . "/" . getParameter('ms3file');
	$msgTotal = getParameter('ms3msgtotal');
	if (!file_exists($logDir)) {
		if (!mkdir($logDir)) {
			return responseMsg('error', 'could not create log folder!' . $logDir);
		}
	}

	// Create new File because of first send
	if ($msgCount == '0') {
		writeZeroFile($logFile);
	}

	// Append to File
	if (!appendLocalFile($uploadedFilePath, $logFile)) {
		return responseMsg('error', 'upload log failed!');
	}

	if ($msgCount + 1 == $msgTotal) {
		//last log part
		//check for kewords in file
		statusNotification($logFile);
	}

	return responseMsg('success', $logFile);
}

function reqUploadMedia($uploadedFilePath) {
	if (!defined('MS3C_GRAPHICS_ROOT') || MS3C_GRAPHICS_ROOT == '')
		return responseMsg('error', 'MS3C_GRAPHICS_ROOT not set!');
	$folder = getParameter('ms3folder');
	$file = getParameter('ms3file');
	$msgCount = getParameter('ms3msgcount');
	$taskId = getParameter('ms3clientid');
	$totalMsg = getParameter('ms3msgtotal');
	$totalMD5 = getParameter('ms3totalmd5');
	$graphicsFolder = MS3C_GRAPHICS_ROOT . $folder;

	$taskIdDecorated = '_' . $taskId;

	// Create Folder
	if (!file_exists($graphicsFolder)) {
		if (!mkdir($graphicsFolder)) {
			return responseMsg('error', 'could not create graphics folder!' . $graphicsFolder);
		}
	}

	// Create new File because of first send
	if ($msgCount == '0') {
		writeZeroFile($graphicsFolder . "/" . $file . $taskIdDecorated);
	}

	// Append to File
	appendLocalFile($uploadedFilePath, $graphicsFolder . "/" . $file . $taskIdDecorated);

	if ($totalMsg - 1 == $msgCount) {
		if ($totalMD5 != "") {
			$localMD5 = md5_file($graphicsFolder . "/" . $file . $taskIdDecorated);
			if ($totalMD5 != $localMD5) {
				@unlink($graphicsFolder . "/" . $file . $taskIdDecorated);
				return responseMsg('error', 'Wrong MD5 checksum!: ' . $graphicsFolder . "/" . $file . $taskIdDecorated);
			}
		}
		if (!rename($graphicsFolder . "/" . $file . $taskIdDecorated, $graphicsFolder . "/" . $file)) {
			return responseMsg('error', 'could not rename File!: ' . $graphicsFolder . "/" . $file . $taskIdDecorated);
		}
	}

	return responseMsg('success', $graphicsFolder . "/" . $file);
}

function reqInitializeUpload($command)
{
	return callPrePostProcess('Upload', 'Initialize', $command, '');
}

function reqFinalizeUpload($command)
{
	return callPrePostProcess('Upload', 'Finalize', $command, '');
}

function reqCommand($subRequest, $uploadedFilePath) {
	switch ($subRequest) {
		case 'CREATEDB':
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			// Double check, as CREATEDB can execute ANY SQL statement! (including drop database...)
			if (MS3C_ALLOWCREATE_SQL !== 1)
				return responseMsg('error', 'SQL Create DB is not allowed on server!');
			return reqCreateDatabase($uploadedFilePath);
			break;

		case 'POSTUPLOAD':
			$command = readUploadedFile($uploadedFilePath);
			return reqPostUpload($command);
			break;
			
		case 'PRESWITCH':
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			$command = readUploadedFile($uploadedFilePath);
			return reqPreSwitchDatabase($command);
			break;
		case 'SWITCHDB';
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			return reqSwitchDatabase();
			break;
		case 'POSTSWITCH':
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			$command = readUploadedFile($uploadedFilePath);
			return reqPostSwitchDatabase($command);
			break;

		case 'SWEEP':
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			$command = readUploadedFile($uploadedFilePath);
			return reqSweepTables($command);
			break;
		case 'POSTSWEEP':
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			$command = readUploadedFile($uploadedFilePath);
			return reqPostSweepDatabase($command);
			break;

		case 'OPTIMIZETABLES':
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			$command = readUploadedFile($uploadedFilePath);
			return reqOptimizeTable($command);
			break;

		default;
			return responseMsg('error', 'Unknown sub request: ' . $subRequest);
			break;
	}
}

function reqInfo($subRequest, $uploadedFilePath) {
	switch ($subRequest) {
		case 'IMPORTDB';
			if (MS3C_DISABLEREQUEST_SQL == 1)
				return responseMsg('success', 'SQL Reqeusts are disabled on server!');
			return reqImportDB();
			break;
		default;
			return responseMsg('error', 'Unknown sub request: ' . $subRequest);
			break;
	}
}

function statusNotification($logFile) {
	//read file
	$handle = fopen($logFile, 'rb');
	$size = filesize($logFile);
	$content = fread($handle, $size);
	fclose($handle);
	$fileParts = pathinfo($logFile);
	$fileatt_type = "application/txt"; // File Type
	$fileatt_name = $fileParts['filename'] . '.' . $fileParts['extension'];

	$posSucc = strpos($content, "##UPLOAD SUCCESSFUL##", 1);
	$posFail = strpos($content, "##UPLOAD FAILED##", 1);

	$host = $_SERVER['SERVER_NAME'];

	if (($posSucc !== false) && (($posSucc > $posFail) || ($posFail === false))) {
		$email_text = "The Data Transfer process was succesfully finished";
		$emailSubject = "Data transfer success at $host";
	} else if ($posFail !== false) {
		$email_text = "The Data Transfer process WAS NOT SUCCESFULL, please check the log under: $logFile";
		$emailSubject = "Data transfer FAILED at $host";
	} else {
		$email_text = "The Data Transfer process was finished with unknown state. Please check the attached logfile";
		$emailSubject = "Data transfer finished at $host";
	}

	$addresses = explode(';', MS3C_LOG_NOTIFICATION_ADDRESSES); //reciever list as array

	$content = chunk_split(base64_encode($content));
	$hash = md5(time());
	$mime_boundary = "==Multipart_Boundary_x{$hash}x";
	$headers =
			"From: ".MS3C_LOG_EMAIL_SENDER."\r\n" .
			"MIME-Version: 1.0\r\n" .
			"Content-Type: multipart/mixed; boundary=\"{$mime_boundary}\"";

	$emailContent =
			"--{$mime_boundary}\r\n" .
			"Content-Type:text/html; charset=\"iso-8859-1\"\r\n" .
			"Content-Transfer-Encoding: 7bit\r\n" .
			"\r\n" .
			$email_text .
			"\r\n\r\n" .
			"--{$mime_boundary}\r\n" .
			"Content-Type: {$fileatt_type}; name=\"{$fileatt_name}\"\r\n" .
			"Content-Transfer-Encoding: base64\r\n" .
			"\r\n" .
			$content . "\r\n" .
			"--{$mime_boundary}--\r\n\r\n";

	//send each reciever one mail with attachment (log file) 
	$mailRet = false;
	foreach ($addresses as $emailEmpfaenger) {
		@$mailRet |= mail($emailEmpfaenger, $emailSubject, $emailContent, $headers);
	}
	if ($mailRet == true) {
		return responseMsg('success', 'Log uploaded. mail sent');
	} else {
		return responseMsg('success', 'log uploaded. mail sent failed');
	}
}
?>
