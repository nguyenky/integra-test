<?php

require_once('system/config.php');
require_once('system/utils.php');

$value = $_POST['value'];
$id = $_POST['id'];

if (empty($id))
	return;

$i = explode('_', $id);
$asin = $i[1];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
UPDATE amazon_grid
SET remarks = '%s'
WHERE asin = '%s'
EOD;

mysql_query(sprintf($q, cleanup($value), cleanup($asin)));

echo $value;
