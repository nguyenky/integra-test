<?php

require_once('system/e_utils.php');

set_time_limit(0);

$q = $_REQUEST['q'];
$u = $_REQUEST['u'];
settype($u, 'integer');

if (empty($u) || empty($q))
	return;

EbayUtils::ResearchKeyword($q, $u);