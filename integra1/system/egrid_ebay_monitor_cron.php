<?php

set_time_limit(0);

require_once('system/e_utils.php');
require_once('system/config.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

//get the last query_id, keyword, user_id,
$last = mysql_query("SELECT * FROM ebay_research_monitor_query ORDER BY created_at DESC LIMIT 1");
$lst = mysql_fetch_assoc($last);

$today = date('Y-m-d H:i:s');
$q = $lst['keyword'];
$u = 0;//$lst['user_id']; 0 = cron
$qid = $lst['id'];
$last_key = $q;

mysql_query("INSERT INTO ebay_research_monitor_query (keyword, user_id, created_at) VALUES ('$q', '$u','$today')");
//get the new inserted query id
$new = mysql_query("SELECT * FROM ebay_research_monitor_query ORDER BY created_at DESC LIMIT 1");
$nw = mysql_fetch_assoc($new);

$qry_id = $nw['id'];
$new_key = $nw['keyword'];

settype($u, 'integer');
settype($qry_id, 'integer');

die($u . $qry_id);