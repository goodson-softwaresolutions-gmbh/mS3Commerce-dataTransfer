<?php

define('MS3C_EXT_ROOT', realpath(dirname(dirname(__FILE__))));
// Must not include dataTransfer! Check tool loads it on demand
require_once(MS3C_EXT_ROOT . '/../vendor/ms3commerce/dataTransfer/mS3CommerceCheck.php');
