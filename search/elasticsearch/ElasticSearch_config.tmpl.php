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

// Assume that these constants will always be s1 / s2!
define('MS3C_ELASTICSEARCH_STAGE_IDX', MS3C_STAGE_EXT_DIR);
define('MS3C_ELASTICSEARCH_PRODUCTION_IDX', MS3C_PRODUCTION_EXT_DIR);


define('MS3C_ELASTICSEARCH_HANDLES_ACCESS_RIGHTS', false);

define('MS3C_ELASTICSEARCH_API_DIR', __DIR__.'/es_phpapi');

function getElasticSearchHosts() {
	$es_hosts = array(
		'localhost:9200'
	);
	return $es_hosts;
}

function getElasticSearchAuth() {
	$es_auth = array(
	);
	return $es_auth;
}

?>
