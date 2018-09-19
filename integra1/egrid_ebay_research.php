<?php
require_once('system/e_utils.php');


set_time_limit(0);


$q = $_REQUEST['q'];
$u = $_REQUEST['u'];
$qry_id = $_REQUEST['qry_id'];

settype($u, 'integer');
settype($qry_id, 'integer');

if (empty($u) || empty($q))
	return;

EbayUtils::MonitorResearchKeyword($q, $u, $qry_id);