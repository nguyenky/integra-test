<?php

require_once('config.php');
require_once('mage_utils.php');

set_time_limit(0);
ini_set('memory_limit', '512M');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT el.sku, eg.big_image_url
FROM eoc.ebay_listings el, eoc.ebay_grid eg
WHERE el.item_id = eg.item_id
AND el.active = 1
AND eg.seller = 'qeautoparts1'
AND eg.big_image_url > ''
EOD;
	
$res = mysql_query($q);
while ($row = mysql_fetch_row($res))
	$items[$row[0]] = $row[1];

$c = 1;
foreach ($items as $sku => $url)
{
	MageUtils::ReplacePicture($sku, $url);
	//mysql_query("UPDATE ebay_grid SET img_done = 1 WHERE item_id = '" . $items[$sku] . "'");
	echo "img2: $c - $sku\n";
	$c++;
	exit;
}
