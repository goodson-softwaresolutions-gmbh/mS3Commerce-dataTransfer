#!/bin/bash
cd "${0%/*}/.."
WEBDIR=../../../$1
cp -r jumpfiles/* "$WEBDIR"
cp -n runtime_config.tmpl.php "$WEBDIR/dataTransfer/runtime_config.php"
cp -n mS3CommerceStage.tmpl.php "$WEBDIR/dataTransfer/mS3CommerceStage.php"
cp -n mS3CommerceDBAccess.tmpl.php "$WEBDIR/dataTransfer/mS3CommerceDBAccess.php"
cp -n search/elasticsearch/ElasticSearch_config.tmpl.php "$WEBDIR/dataTransfer/search/elasticsearch/ElasticSearch_config.php"

mkdir -p "$WEBDIR/Graphics"
mkdir -p "$WEBDIR/dataTransfer/ext"
mkdir -p "$WEBDIR/dataTransfer/uploads"
