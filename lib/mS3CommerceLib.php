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

function getParameter( $paraName )
{
  if ( isset( $_POST[ $paraName ] ) ) {
    return base64_decode( $_POST[ $paraName ] );
  } else {
    return '';
  }
}

function responseMsg( $msgType, $msgDescription )
{
  // xml encoded  	
  $msg = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n";
  $msg .= "<mS3CommerceResponse>\r\n";
  $msg .= "<type>" . $msgType . "</type>\r\n";
  $msg .= "<description>" . htmlentities( $msgDescription ) . "</description>\r\n";
  $msg .= "</mS3CommerceResponse>";
  return $msg;   
}

function decodeBase64File( $inputfile, $outputfile, $md5hash ) 
{ 
   /* read data (binary) */ 
   if (( $rawHandler = fopen( $inputfile, "rb" ) ) === FALSE )
   {
      return '';
   }
  
   $fileSize = filesize( $inputfile );
   if ( $fileSize == 0 )
   {
      return '';
   }
   
   $fileData = fread( $rawHandler, $fileSize );
   fclose( $rawHandler ); 

   /* encode & write data (binary) */ 
   if (( $orgHandler = fopen( $outputfile, "wb" ) ) === FALSE  )
   {
      return '';
   } 
   
   $encodedData = base64_decode( $fileData );
   
   // Test MD5 Hash
   if ( $md5hash != '' )
   { 
     if ( md5( $encodedData ) !=  $md5hash )
     {
	echo responseMsg( 'error', "MD5 Hash ist not right: Send: " . $md5hash . " Org: " . md5( $encodedData ) );
	end;
     }
   }
   
   fwrite( $orgHandler, $encodedData ); 
   fclose( $orgHandler );
   
   // Cleanup
   unlink( $inputfile );
   
   /* return output filename */ 
   return( $outputfile ); 
}

function deleteFilesInFolder( $target_path, $filter )
{
  $files = glob( $target_path . $filter );
  foreach( $files as $file )
  {
    @unlink( $file );
  }
}

// Handle Uploaded Files
function saveUploadedFiles( $uploadName, $md5hash )
{
  $target_path = MS3C_EXT_ROOT . "/dataTransfer/uploads/";
  $target_name = basename( $_FILES[ $uploadName ]['name'] );
  $target_path_base64  = $target_path . $target_name . ".b64"; 
  $target_path_encoded = $target_path . $target_name; 
  
  deleteFilesInFolder( $target_path, "$target_name*" );
  
  $ret = array();
  $ret[ 'error' ] = true;  
  
  if (  !($_FILES[ $uploadName ]['error'] === UPLOAD_ERR_OK  ) )
  {
      return $ret;
  }
  
  if ( move_uploaded_file( $_FILES[ $uploadName ][ 'tmp_name' ], $target_path_base64 ) ) 
  {
      $ret[ 'error' ]          = false;
      $ret[ 'size' ]           = $_FILES[ $uploadName ][ 'size' ];
      $ret[ 'uploadfilepath' ] = decodeBase64File( $target_path_base64, $target_path_encoded, $md5hash );
      return $ret;
  } 
   
  return $ret;
}

function readUploadedFile( $uploadFile )
{
   if ( $uploadFile[ 'error' ] ) return '';
   if ( $uploadFile[ 'size' ] == 0 ) return '';
   
   if (( $ifp = fopen( $uploadFile[ 'uploadfilepath' ], "rb" )) === FALSE )
   {
       return '';
   } 
 
   $filesize = filesize( $uploadFile[ 'uploadfilepath' ] );
   if ( $filesize == 0 )
   {
     die(  $uploadFile[ 'uploadfilepath' ] );
   }

   $imageData = fread( $ifp, $filesize ); 
   fclose( $ifp ); 
   return $imageData;
}

function appendLocalFile( $uploadFile, $filePath )
{
   if ( $uploadFile[ 'error' ] ) return '';

   $filesize = filesize( $uploadFile[ 'uploadfilepath' ] );
   if ( $filesize == 0 )
   {
     echo( 'filesize == 0' );
     return false;
   }

   if (( $uploadHandler = fopen( $uploadFile[ 'uploadfilepath' ], "rb" )) === FALSE )
   {
     echo( 'failed to open' . $uploadFile[ 'uploadfilepath' ] );
     return false;
   } 
 
   if (( $localHandler = fopen( $filePath, "ab" )) === FALSE )  
   {
     echo( 'failed to open: ' . $filePath );
     return false;
   }
   
   fwrite( $localHandler, fread( $uploadHandler, $filesize ) );
   fclose( $localHandler  );
   fclose( $uploadHandler ); 

   return true;
}

function mS3CommerceCleanup( $uploadFile )
{
   if ( $uploadFile[ 'error' ] ) return;
   if ( file_exists( $uploadFile[ 'uploadfilepath' ] ) ) 
   {
      unlink( $uploadFile[ 'uploadfilepath' ] );   
   }
}

function writeZeroFile( $filePath )
{
   if (( $zeroHandler = fopen( $filePath, "wb" ) ) === FALSE  )
   {
      return false;
   } 
   fwrite( $zeroHandler, "" ); 
   fclose( $zeroHandler );  
   return true;    
}

function getShopExtensionDir($shop, $useStage = true)
{
	if ($useStage) {
		$baseDir = MS3C_STAGE_EXT_DIR;
	} else {
		$baseDir = MS3C_PRODUCTION_EXT_DIR;
	}
	$extDir = MS3C_EXT_ROOT . '/dataTransfer/ext/' . $baseDir;
	return $extDir . DIRECTORY_SEPARATOR . 'shop' . $shop;
}

function callPrePostProcess($process, $step, $command, $param, $prefix = '') {
	
	$commands = array('Core', 'CMS', 'Shop', 'Search', 'Custom');
	
	$arg = '';
	if (!empty($command)) {
		$pos = strpos($command, ';');
		if ($pos === false) {
			$cmd = $command;
		} else {
			$cmd = substr($command, 0, $pos);
			$arg = substr($command, $pos + 1);
		}
	} else {
		$cmd = $commands[0];
	}

	if (array_search($cmd, $commands) === false) {
		return responseMsg('error', "Unknown $process {$step}-Process: $cmd");
	}

	if ($prefix != "") {
		$prefix .= ';';
	}
	
	$funcName = "mS3{$cmd}{$process}{$step}process";
	if (function_exists($funcName)) {
		list($ok, $continue, $msg) = $funcName($param, $arg);
		if (!$ok) {
			return responseMsg('error', $msg);
		}
		if ($continue) {
			return responseMsg('continue', "$prefix$cmd;$msg");
		}
	}

	// Command successful, check for next in chain
	$idx = array_search($cmd, $commands)+1;
	if ($idx < count($commands)) {
		$nextCmd = $commands[$idx];
	} else {
		$nextCmd = false;
	}

	if ($nextCmd) {
		return responseMsg('continue', $prefix.$nextCmd);
	} else {
		// No more function
		return responseMsg('success', "$process{$step}process");
	}
}

function hasCommerceDatabase()
{
	if (defined('MS3C_OXID_ONLY') && MS3C_OXID_ONLY) {
		return false;
	}
	if (defined('MS3C_MAGENTO_ONLY') && MS3C_MAGENTO_ONLY) {
		return false;
	}
	if (defined('MS3C_SHOPWARE_ONLY') && MS3C_SHOPWARE_ONLY) {
		return false;
	}
    if (defined('MS3C_WOO_ONLY') && MS3C_WOO_ONLY) {
        return false;
    }
	return true;
}

?>
