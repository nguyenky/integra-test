<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');

set_time_limit(0);
ini_set('memory_limit', '256M');

file_put_contents(LOGS_DIR ."a_scrape.log", "============== A scrape JOB start at ". date('Y-m-d H:i:s') ." ================ \r\n", FILE_APPEND);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT asin
FROM amazon_listings
WHERE active = 1
AND last_try < DATE_SUB(NOW(), INTERVAL 8 HOUR)
ORDER BY last_scraped ASC
LIMIT 70
EOD;

$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
	$asins[] = $row[0];

file_put_contents(LOGS_DIR ."a_scrape.log", "TOTAL OF RESULTS: ".count($asins) ." \r\n", FILE_APPEND);

if (count($asins) == 0)
	return;

foreach ($asins as $asin)
{
	file_put_contents(LOGS_DIR ."a_scrape.log", "Scrape for : ".$asin ." \r\n", FILE_APPEND);
	$file = "/tmp/${asin}";
	$url = "http://www.amazon.com/gp/offer-listing/${asin}";
	$agent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36";
	
	exec("wget -q -O ${file} -e use_proxy=yes -e http_proxy=switchproxy.proxify.net:7496 --user-agent='${agent}' '${url}'");
	if (IsValidFile($asin, 'p'))
		continue;

	exec("wget -q -O ${file} --user-agent='${agent}' '${url}'");
	if (IsValidFile($asin, '1'))
		continue;

	exec("wget -q -O ${file} --bind-address 64.131.70.94 --user-agent='${agent}' '${url}'");
	if (IsValidFile($asin, '2'))
		continue;

	exec("wget -q -O ${file} --bind-address 207.58.181.60 --user-agent='${agent}' '${url}'");
	if (IsValidFile($asin, '3'))
		continue;

	exec("wget -q -O ${file} --bind-address 207.58.181.61 --user-agent='${agent}' '${url}'");
	if (IsValidFile($asin, '4'))
		continue;

	mysql_query(sprintf("INSERT INTO amazon_scraper_log (ts, route, asin) VALUES (NOW(), '%s', '%s')", '-', $asin));
	
	file_put_contents(LOGS_DIR ."a_scrape.log", "Failed to scrape ${asin} \r\n", FILE_APPEND);

	echo "Failed to scrape ${asin}\n";
}

mysql_query("UPDATE amazon_scraper sc, amazon_merchants me SET sc.seller = me.name WHERE sc.seller_code = me.id AND sc.seller = ''");
mysql_close();

file_put_contents(LOGS_DIR ."a_scrape.log", "============== A scrape JOB DONE at ". date('Y-m-d H:i:s') ." ================ \r\n", FILE_APPEND);

return;

function IsValidFile($asin, $route)
{

	file_put_contents(LOGS_DIR ."a_scrape.log", "============== IN IsValidFile at ". date('Y-m-d H:i:s') ." ================ \r\n", FILE_APPEND);

	try {

		mysql_query("UPDATE amazon_listings SET last_try = NOW() WHERE asin = '${asin}'");

		$file = "/tmp/${asin}";

		if (!file_exists($file))
			return false;

		$data = file_get_contents($file);

		file_put_contents(LOGS_DIR ."a_scrape.log", "Data: ".$data. " \r\n", FILE_APPEND);

		if (file_exists($file)) unlink($file);
		
		if (empty($data) || stripos($data, 'captcha') || stripos($data, 'OfferListing') === FALSE)
			return false;
			
		$re = '/olpOfferPrice[^$]+(?P<price>[^<]+)<.+?((olpShippingPrice[^$]+(?P<shipping>[^<]+)<)|(?P<free>FREE)).+?olpCondition[^>]+>(?P<condition>[^<]+)<.+?seller=(?P<seller>[^"]+)"><b>(?P<rating>[^\)]+\))/is'; 
		
		
		preg_match_all($re, $data, $matches, PREG_SET_ORDER);

		file_put_contents(LOGS_DIR ."a_scrape.log", "Match: ". serialize($matches) . " \r\n", FILE_APPEND);	
		
		if (empty($matches))
			return false;

		mysql_query("UPDATE amazon_listings SET last_scraped = NOW() WHERE asin = '${asin}'");
		mysql_query("DELETE FROM amazon_scraper WHERE asin = '${asin}'");

		foreach ($matches as $m)
		{
			$price = str_replace('$', '', trim($m['price']));
			if (!empty($m['free']))
				$shipping = '0';
			else
				$shipping = str_replace('$', '', trim($m['shipping']));
			$condition = trim($m['condition']);
			$seller = trim($m['seller']);
			$rating = trim(str_replace('</b></a>', '', $m['rating']));

			echo "$asin - $price + $shipping shipping - $condition - $seller - $rating\n";

			mysql_query(sprintf("INSERT INTO amazon_scraper (asin, price, shipping, cond, seller_code, rating) VALUES('%s', '%s', '%s', '%s', '%s', '%s')",
				$asin, $price, $shipping, $condition, $seller, $rating));

		}

		mysql_query(sprintf("INSERT INTO amazon_scraper_log (ts, route, asin) VALUES (NOW(), '%s', '%s')", $route, $asin));

	} catch(Exception $ex) {

		file_put_contents(LOGS_DIR ."a_scrape.log", "======= ERROR: ".$ex->getMessage());
		file_put_contents(LOGS_DIR ."a_scrape.log", "======= ERROR: ".$ex->getTraceAsString());

	}
	

	file_put_contents(LOGS_DIR ."a_scrape.log", "============== DONE IsValidFile at ". date('Y-m-d H:i:s') ." ================ \r\n", FILE_APPEND);

	return true;
}

?>
