<?php

require_once('system/config.php');

$id = $_REQUEST['id'];
settype($id, 'integer');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
$rows = mysql_query("SELECT body FROM templates WHERE id = ${id}");
$row = mysql_fetch_row($rows);
if (!empty($row))
	echo $row[0];