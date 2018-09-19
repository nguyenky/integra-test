<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/eu2.php');

set_time_limit(0);
ini_set('memory_limit', '768M');

$eids = explode("\n", file_get_contents('kits.txt'));

$ctr = 1;

foreach ($eids as $eid)
{
	$q = trim($eid);

	try
	{
		echo "$ctr / " . count($eids) . " - Scraping $q - ";
		$x=EbayUtils::ScrapeItem($q);
		file_put_contents('kitdb.txt', $q . '~' . $x['title'] . "\n", FILE_APPEND);
		//$x=EbayUtils::ScrapeItem($q);
//		print_r($x);
		echo "\n";

		$ctr++;
	}
	catch (Exception $e)
	{
		error_log($e->getMessage());
	}
}

echo "DONE!\n";

return;

?>
