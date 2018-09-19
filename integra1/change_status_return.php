<?php

require_once('system/config.php');
require_once('system/utils.php');

$id = $_REQUEST['id'];
settype($id, 'integer');

$code = $_REQUEST['code'];
settype($code, 'integer');

if (empty($id))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_query("UPDATE returns SET status = ${code} WHERE id = ${id}");
mysql_close();