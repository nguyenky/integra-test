<?php
 require_once('system/config.php');
 mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
 mysql_select_db(DB_SCHEMA);
 
 $stores = $_GET['store'];

 // output headers so that the file is downloaded rather than displayed
 header('Content-Type: text/csv; charset=utf-8');
 header('Content-Disposition: attachment; filename=store_price_'.date('Y-m-d').'.csv');

 // create a file pointer connected to the output stream
 $output = fopen('php://output', 'w');

 // output the column headings
fputcsv($output, array('store', 'mpn', 'value'));        

 $rows =  mysql_query("SELECT c.code,d.sku,a.value
                       FROM magento.catalog_product_entity_decimal a
                       LEFT JOIN magento.eav_attribute b ON a.attribute_id = b.attribute_id
                       LEFT JOIN magento.core_store c ON c.store_id = a.store_id
                       LEFT JOIN magento.catalog_product_entity d ON d.entity_id = a.entity_id
                       WHERE b.attribute_code = 'price' AND a.store_id IN ($stores)
                       ORDER BY a.store_id,d.sku,a.value LIMIT 1000000") or die(mysql_error());

 // loop over the rows, outputting them
 while ($row = mysql_fetch_assoc($rows)) fputcsv($output, $row);

?>