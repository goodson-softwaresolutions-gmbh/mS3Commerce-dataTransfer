# mS3 Commerce dataTransfer

dataTransfer is the common server side module for all mS3 Commerce publications.

It provides the necessary modules to upload data (PIM data and Graphics), and provides
the basic configuration for various publication modules.

It can expose a staging system that enables updating of PIM data into a separate
staging database, without interrupting live operation, and fast switching between stages.

## Contents
- [Helpers](#helpers)
  - [mS3CommerceCheck](#ms3commercecheckphp)
  - [ViewDB](#viewdbphp)
  - [Log Viewer](#logindexphp)
  - [ES Tester](#searchelasticsearchestestphp)
- [Installation](#installation)
  - [Manual installation](#manual-installation)
  - [Composer installation](#composer-installation)
- [Configuration](#configuration)
  - [Database Access](#database-access)
  - [Runtime configuration](#runtime-configuration)
    - [Main Parameters](#main-parameters)
    - [Typo3 specific parameters](#typo3-specific-parameters)
    - [Database structure configuration](#database-structure-configuration)
    - [Fine-grained parameters](#fine-grained-parameters)


## Helpers
### mS3CommerceCheck.php
This script will verify your server and installation.

### viewdb.php
This script allows you to view the content of Typo3/API data contained in the databse

### log/index.php
This script allows you to view Upload Log files. Note that log files are printed
bottom-to-top, so the final success/failed message is on top

### search/elasticsearch/esTest.php
Test script for querying the ES search index

## Installation
dataTransfer comes with templates for central configuration files. These have to
be adjusted for a working system.

### Manual installation
Rename the following files to remove the `.tmpl` part:
- `runtime_config.tmpl.php`
- `mS3CommerceDBAccess.tmpl.php`
- `mS3CommerceStage.tmpl.php`

### Composer installation
Add the following script as a composer Post-Install and Post-Update step 
(assuming the website is hosted in a directory called `public`):

	"scripts":{
        "setupDataTransfer": "bash vendor/ms3commerce/dataTransfer/scripts/setup_composer.sh public",
        "post-install-cmd": [
            "@setupDataTransfer"
        ],
        "post-update-cmd": [
            "@setupDataTransfer"
        ]
    }

## Configuration
### Database Access
Adjust the file `mS3CommerceDBAccess.php` to return credentials for the different databases, e.g.:

    <?php
    
    function MS3C_DB_ACCESS() {
    return array(
        'stagedb1' => array(
            'host' => 'localhost',
            'username' => 'user',
            'password' => 'pwd',
            'database' => 'commerce_ms1',
        ),
        'stagedb2' => array(
            'host' => 'localhost',
            'username' => 'user',
            'password' => 'pwd',
            'database' => 'commerce_ms2',
        )
    )

The file can configure any number of connections. However, the standard modules
expect the following connections:
- Typo3/API with Database Staging: `stagedb1`, `stagedb2`
- Typo3/API with Table Staging: `tables`
- Magento: `ms3magento`

### Runtime configuration
Adjust the file `runtime_configuration.php` according to you requirements.

#### Main parameters:
- `MS3C_CMS_TYPE`: Type of CMS integration
  - `None`: No CMS integration (e.g. API)
  - `Typo3`: Typo3 integration
  - `Magento`: Magento 2 integration
- `MS3C_MAGENTO_ONLY`: If CMS is Magento, this indicates there is only Magento, and no Typo3/API data

#### Typo3 specific parameters:
- `MS3C_TYPO3_RELEASE`: Typo3 Version (10, 11)
- `MS3C_TYPO3_TYPE`: mS3 Commerce Typo3 Extension type (FX)
- `MS3C_TYPO3_CACHED`: If the Typo3 Extension should use page cache
- `MS3C_SHOP_SYSTEM`: Type of Typo3 Shop integration
  - `None`: No integration
  - `tx_cart`: Cart integration
- `MS3C_ENABLE_OCI`: Enable Typo3 OCI (Open Catalogue Interface) integration

#### Database structure configuration:
This is only needed if Typo3 or API data is used
- `MS3C_NO_SMZ`: Data does not have Categorization (i.e. Categorization Finisher is not activated in mS3 Commerce)
- `RealURLMap_TABLE`: Table name for Real URL and other Extensions (i.e. Feature Pivot Table Generator is activated in mS3 Commerce)
- `MS3C_SEARCH_BACKEND`: Backend for full-text searches
  - `None`: No Fulltext Backend
  - `MySQL`: Use MySQL Full Text search (i.e. Fulltext Finisher is activated in mS3 Commerce)
  - `ElasticSearch`: Use Elastic Search (i.e. Elastic Search Finisher is activated in mS3 Commerce)

#### Fine grained parameters:

- `MS3COMMERCE_STAGETYPE`:
  - `DATABASES`: Uses 2 different database for staging (default, recommended)
  - `TABLES`: Uses 1 database with 2 sets of tables for staging
- `MS3C_DB_BACKEND`: DB Backend to use (`mysqli`)
- `MS3C_DB_SWEEP_ALWAYS_ACROSS`: Don't use fast mode when copying data between stage DBs
  (required when using DB Staging, but credentials are not shared between DBs)
- `MS3C_DB_USE_NEW_LINK`: Always create new DB connection for statements (not recommended)
- `MS3C_DISABLEREQUEST_SQL`: If 1, prevent uploading of PIM DB data
- `MS3C_DISABLEREQUEST_MEDIA`: If 1, prevent uploading of PIM Media data
- `MS3C_ALLOWCREATE_SQL`: If 1, allow initializing of database (DROP/CREATE).  
   **ATTENTTION:** This should be 0 at all times, except when initializing the system 
- `MS3C_LOG_NOTIFICATION_ADDRESSES`: `;`-separated list of receivers for Update Logs
- `MS3C_LOG_EMAIL_SENDER`: Sender for Update Logs

