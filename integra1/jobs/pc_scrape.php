<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/e_utils.php');

set_time_limit(0);
ini_set('memory_limit', '768M');

while (true)
{
	$queue = array();

	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$q = <<<EOD
SELECT mpn
FROM all_mpns
WHERE done = 0
ORDER BY RAND()
LIMIT 50
EOD;
	$res = mysql_query($q);

	while ($row = mysql_fetch_row($res))
		$queue[] = $row[0];

	$c = 1;

	foreach ($queue as $mpn)
	{
		try
		{
			echo "$c / " . count($queue) . " - Scraping $mpn - ";
			$compItems = EbayUtils::SearchSellerItems('partscontainer', $mpn);
			mysql_query("UPDATE all_mpns SET done = 1 WHERE mpn = '$mpn'");
			
			echo "Found " . count($compItems) . " listings.\n";
			
			if (empty($compItems))
			{
				$c++;
				continue;
			}
				
			$ctr = 1;
			
			foreach ($compItems as $i)
			{
				$q = <<<EOD
	INSERT INTO eoc.ebay_grid (item_id, this_item, active, title, image_url, big_image_url, price, shipping, seller, score, rating, top, pos, num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, placement, brand)
	VALUES('%s', '%s', 1, '%s', %s, %s, '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s')
	ON DUPLICATE KEY UPDATE
		active = 1,
		title = VALUES(title),
		image_url = VALUES(image_url),
		big_image_url = VALUES(big_image_url),
		price = VALUES(price),
		shipping = VALUES(shipping),
		seller = VALUES(seller),
		score = VALUES(score),
		rating = VALUES(rating),
		top = VALUES(top),
		pos = VALUES(pos),
		num_hit = GREATEST(num_hit, VALUES(num_hit)),
		num_sold = VALUES(num_sold),
		num_compat = VALUES(num_compat),
		num_avail = VALUES(num_avail),
		timestamp = NOW(),
		category = VALUES(category),
		category = VALUES(category),
		mpn = VALUES(mpn),
		ipn = VALUES(ipn),
		opn = VALUES(opn),
		placement = VALUES(placement),
		brand = VALUES(brand)
EOD;
				$qw = sprintf($q,
					cleanup('PC' . $mpn),
					cleanup($i['id']),
					cleanup($i['title']),
					empty($i['picture_small']) ? 'NULL' : "'" . cleanup($i['picture_small']) . "'",
					empty($i['picture_big']) ? 'NULL' : "'" . cleanup($i['picture_big']) . "'",
					cleanup($i['price']),
					cleanup($i['shipping_cost']),
					cleanup($i['seller_id']),
					cleanup($i['seller_score']),
					cleanup($i['seller_rating']),
					cleanup($i['seller_top']),
					$ctr++,
					($i['num_hit'] == '-1') ? 0 : $i['num_hit'],
					cleanup($i['num_sold']),
					cleanup($i['num_compat']),
					cleanup($i['num_avail']),
					cleanup($i['category']),
					cleanup($i['mpn']),
					cleanup($i['ipn']),
					cleanup($i['opn']),
					cleanup($i['placement']),
					cleanup($i['brand']));
				//echo $qw . "\n";
				mysql_query($qw);
			}
			
			$c++;
		}
		catch (Exception $e)
		{
			error_log($e->getMessage());
		}
	}

	mysql_close();
}

return;

?>
