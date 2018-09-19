<?php

require_once('system/config.php');
require_once('system/utils.php');

$value = $_POST['value'];
$id = $_POST['id'];

if (empty($id))
	return;

$i = explode('_', $id);
$itemId = $i[1];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
UPDATE sales
SET loss_solution = '%s'
WHERE id = '%s'
EOD;

mysql_query(sprintf($q, cleanup($value), cleanup($itemId)));

echo $value;
