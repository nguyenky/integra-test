<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');
require_once(__DIR__ . '/../system/ebay_api.php');

set_time_limit(0);
ini_set('memory_limit', '768M');

while (true)
{
	$queue = array();

	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$q = <<<EOD
SElECT item_id
FROM
(
SELECT el.item_id
FROM ebay_listings el
WHERE el.active = 1
and not exists (select 1 from ebay_grid_summary egs where el.item_id = egs.item_id)
LIMIT 500
) x
ORDER BY RAND()
LIMIT 20
EOD;
/*
	$q = <<<EOD
SELECT DISTINCT egs.item_id
FROM ebay_grid_summary egs, ebay_grid eg
WHERE eg.item_id = egs.item_id
AND eg.seller =  'qeautoparts1'
AND egs.active =1
ORDER BY DATE(eg.timestamp) ASC, RAND()
LIMIT 50
EOD;*/
	$res = mysql_query($q);

	while ($row = mysql_fetch_row($res))
		$queue[] = $row[0];

	EbayUtils::scrapteItemsV2($queue);

	//$ctr = 1;

	/*
	foreach ($queue as $q)
	{
		try
		{
			echo "$ctr / " . count($queue) . " - Scraping $q - ";
			echo EbayUtils::ScrapeItem($q);
			echo "\n";

			$ctr++;
		}
		catch (Exception $e)
		{
			error_log($e->getMessage());
		}
	}
	*/

	mysql_close();
}

return;

?>
