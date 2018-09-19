<?php

require_once('system/e_utils.php');

set_time_limit(0);

$itemId = $_REQUEST['item_id'];

echo EbayUtils::ScrapeItem($itemId);
