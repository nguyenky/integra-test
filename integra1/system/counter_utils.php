<?php

set_time_limit(0);
ini_set('memory_limit', '768M');

date_default_timezone_set('America/New_York');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db('integra_prod');

Class CountersUtils{
	public static function insertCounter($service_name,$feature_name,$token){

        $sql ="INSERT INTO ebay_api_call_counters (ebay_service_name,feature_name,token) VALUES ('%s','%s','%s')";

            mysql_query(query($sql,$service_name,$feature_name,$token));

    }

    public static function insertCounterProd($service_name,$feature_name,$token){

        $sql ="INSERT INTO integra_prod.ebay_api_call_counters (ebay_service_name,feature_name,token) VALUES ('%s','%s','%s')";

            mysql_query(query($sql,$service_name,$feature_name,$token));

    }
}
?>