<?php

require_once('system/config.php');
require_once('system/utils.php');

$id = $_REQUEST['id'];
settype($id, 'integer');

$on = $_REQUEST['on'];

if ($on != '0' && $on != '1' && $on != '2')
	return;

if (empty($id))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

if ($on == '1') {
	mysql_query(<<<EOQ
INSERT IGNORE INTO ebay_monitor (item_id, keywords, last_scraped, cur_title, prev_title, cur_price, prev_price)
(SELECT item_id, keywords, last_scraped, title, title, price, price FROM ebay_research WHERE item_id = '${id}')
EOQ
	);
	mysql_query("UPDATE ebay_monitor SET disable = 0 WHERE item_id = '${id}'");
}
else if ($on == '0')
	mysql_query("UPDATE ebay_monitor SET disable = 1 WHERE item_id = '${id}'");
else if ($on == '2')
	mysql_query("UPDATE ebay_monitor SET prev_title = cur_title, prev_price = cur_price WHERE item_id = '${id}'");

mysql_close();

echo $on;