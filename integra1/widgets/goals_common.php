<?php

require_once('../system/config.php');
session_start();
$user = $_SESSION['user'];
if (empty($user)) exit;

$metrics = array
(
	// Amazon
	0 => 'Daily Sales Count',
	1 => 'Weekly Sales Count',
	2 => 'Monthly Sales Count',
);

$low = 'salmon';
$med = 'orange';
$high = 'lightgreen';

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
