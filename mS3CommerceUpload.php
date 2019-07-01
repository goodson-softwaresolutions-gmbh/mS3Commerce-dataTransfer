<?php // mS3CommerceUpload.php
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

// Suppress outputs from including files
// Result XML must not even contain a leading NewLine!
ob_start();

// #### SETUP ##############
require_once __DIR__.'/dataTransfer_config.php';    // mS3Commerce Config
// #### SETUP ##############

// dataTransfer PHP Library
require_once MS3C_ROOT.'/dataTransfer/lib/mS3CommerceLib.php';
require_once MS3C_ROOT.'/dataTransfer/mS3CommerceProtocol.php';

ob_end_clean();

// Save possbile Uploaded Files
$upload   = saveUploadedFiles( 'uplFile', getParameter( 'md5check' ) );

// Handle request
$response = mS3CommerceRequest( $upload, getParameter( 'mainRequest' ),  getParameter( 'subRequest' ) );

// Cleanup Uploaded Files
mS3CommerceCleanup( $upload );

// Response to request
echo $response;
?>
