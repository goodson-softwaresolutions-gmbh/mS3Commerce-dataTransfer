<?php
/***************************************************************
 * Part of mS3 Commerce 6.0
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

require_once(MS3C_ROOT . '/dataTransfer/mS3Commerce_db.php');

/**
 * Factory building mS3 commerce db handlers
 */
class tx_ms3commerce_db_factory_cms
{
    static function getDatabaseConnectParams($useStageDb = false)
    {
        $dbConf = MS3C_DB_ACCESS();
        switch (MS3COMMERCE_STAGETYPE)
        {
            case 'DATABASES':
                // Find out to which db we're mapping
                if ($useStageDb) {
                    $stageDbAlias = MS3COMMERCE_STAGE_DB;
                } else {
                    $stageDbAlias = MS3COMMERCE_PRODUCTION_DB;
                }

                $dbAccess = $dbConf[$stageDbAlias];
                return $dbAccess;
                break;
            case 'TABLES':
                $dbAccess = $dbConf['tables'];
                return $dbAccess;
                break;
        }
        return null;
    }

    static function buildForDatabases($useStageDb)
    {
        return tx_ms3commerce_db_factory::buildDatabase(false, $useStageDb);
    }

    static function buildForTables($useStageDb)
    {
        return self::buildForDatabases($useStageDb);
    }

    static function connectToTypo3Database()
    {
        $conf = require_once(MS3C_EXT_ROOT . '/typo3conf/LocalConfiguration.php');
        $db = $conf['DB']['Connections']['Default'];
        $dbAccess = array(
            'host' => $db['host'],
            'username' => $db['user'],
            'password' => $db['password'],
            'database' => $db['dbname'],
        );
        return tx_ms3commerce_db_factory::connectToDatabase($dbAccess);
    }
}
