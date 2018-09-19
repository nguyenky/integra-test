<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/ssf_utils.php');

$skus = array();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$endHits = 0;

while (true)
{
	unset($skus);
	unset($ok);
	
	$q = <<<EOD
	SELECT mpn FROM ssf_parts
	WHERE ipo_done = 0
	ORDER BY RAND()
	LIMIT 200
EOD;

	$res = mysql_query($q);
	while ($row = mysql_fetch_row($res))
	{
		$skus[] = $row[0];
	}
	
	if (count($skus) == 0)
	{
		echo "All SKUs scraped! Quitting...";
		SendAdminEmail("Scraping Complete", "All SKUs scraped! Program exiting.", false);
		return;
	}

	$ok = SsfUtils::ScrapeItems($skus);

	$res = mysql_query("UPDATE ssf_parts SET ipo_done = 1 WHERE mpn IN ('" . implode("', '", $ok) . "')");

	echo "Scraped " . count($ok) . " items\r\n";
	
	if (count($ok) == 0)
	{
		if ($endHits >= 5)
		{
			echo "5 consecutive 0 results during scraping. Quitting...";
			SendAdminEmail("Scraping Ended", "5 consecutive 0 results during scraping. Program exiting.", false);
			return;
		}
		else
		{
			$endHits++;
		}
	}
	else
		$endHits = 0;
}
