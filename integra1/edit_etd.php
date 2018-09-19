<?php

require_once('system/e_utils.php');
require_once('system/acl.php');

$user = Login('shipgrid');

$value = $_POST['value'];
$id = $_POST['id'];

if (empty($id))
	return;

$i = explode('_', $id);
$id = $i[1];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$date = strtotime($value);
if (!$date) {
    mysql_query(query("UPDATE sales SET etd = NULL WHERE id = %d", $id));
    echo '';
    return;
}

$dt = date('Y-m-d', $date);

mysql_query(query("UPDATE sales SET etd = '%s' WHERE id = %d", $dt, $id));
echo $dt;
