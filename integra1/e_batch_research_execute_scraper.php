<?php
require_once('system/config.php');
require_once('system/e_utils.php');


mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

set_time_limit(0);



ini_set('memory_limit', '768M');

$ctr = 1;
$batch_id = $argv[1]; //$_GET['batch_id'];
$query =  mysql_query("SELECT itemID FROM e_batch_data WHERE status != 1 AND batch_id = '$batch_id'");

while ($row = mysql_fetch_array($query) )
{ 
	

   $q = trim($row[0]);
		
		try
		{   
			echo 'BATCH ID: ' . $batch_id;
			$start = microtime(true);
			echo "$ctr / " . count($row) . " - Scraping $q - ";
			echo EbayUtils::ScrapeItem($q);
			echo "\n";
	       
	        $time_elapsed = microtime(true) - $start;
	        $dateTime = date('Y-m-d H:i:s');
			mysql_query("UPDATE e_batch_data SET status = 1, total_execution_time = '$time_elapsed', updated_timestamp = '$dateTime' WHERE itemID = '$q' AND batch_id = '$batch_id'");
			$ctr++;
		}

		catch (Exception $e)
		{
			error_log($e->getMessage());
		}
}


echo "DONE!\n\n\n";

$dateTime = date('Y-m-d H:i:s');
mysql_query("UPDATE e_batch_process SET status = 1, updated_timestamp = '$dateTime' WHERE id = '$batch_id'");
return;
?>

