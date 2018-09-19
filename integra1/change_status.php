<?php

require_once('system/config.php');
require_once('system/utils.php');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

$code = $_REQUEST['code'];
settype($code, 'integer');

if (empty($salesId))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_query("UPDATE sales SET status = ${code} WHERE id = ${salesId}");
mysql_close();