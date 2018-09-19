<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');

date_default_timezone_set("America/New_York");

$today = date('Y-m-d');
$lastRun = date('Y-m-d', strtotime(system("grep ReviseInventoryStatus /var/www/webroot/ROOT/integra1/logs/ebay_inv.txt | tail -1 | cut -f1 -d']'")));

echo "Today: {$today}\n";

if ($today != $lastRun)
{
	$msg = "Last eBay revision by inventory job: {$lastRun}\n";
	SendAdminEmail('No eBay Revisions Today', $msg);
	echo $msg;
}
else
{
	$msg = "Last eBay revision by inventory job: {$lastRun}\n";
	SendAdminEmail('eBay Revisions OK', $msg);
	echo $msg;
}

?>