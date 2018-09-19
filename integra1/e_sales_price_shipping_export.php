<?php
 require_once('system/config.php');
 mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
 mysql_select_db(DB_SCHEMA);
 // output headers so that the file is downloaded rather than displayed
 header('Content-Type: text/csv; charset=utf-8');
 header('Content-Disposition: attachment; filename=shipping_rate_'.date('Y-m-d').'.csv');

 // create a file pointer connected to the output stream
 $output = fopen('php://output', 'w');

 // output the column headings
 fputcsv($output, array('MPN', 'Min Qty', 'Max Qty', 'Profit', 'Shipping', 'Shipping Type', 'Notes'));        

 $rows = mysql_query("SELECT mpn, min_qty, max_qty, profit, shipping, type, notes FROM e_shipping_rates  WHERE mpn != '' ORDER BY mpn");

 // loop over the rows, outputting them
 while ($row = mysql_fetch_assoc($rows)) fputcsv($output, $row);

?>