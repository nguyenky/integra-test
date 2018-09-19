<?php
 require_once('system/config.php');
 ini_set('memory_limit', -1 );
 ini_set('max_execution_time', 0);     

 mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
 mysql_select_db(DB_SCHEMA);

/* // output headers so that the file is downloaded rather than displayed
 header('Content-Type: text/csv; charset=utf-8');
 header('Content-Disposition: attachment; filename=compatibilities_'.date('Y-m-d').'.csv');

 // create a file pointer connected to the output stream
 $output = fopen('php://output', 'w');*/


// output the column headings
 $output = fopen("csv_batch/compatibilities_004.csv","w");
 fputcsv($output, array('MPN', 'Compatibilities'));


/*mysql_query("SELECT sku, concat(make, ' ', model, ' ', year, ' ', replace(notes,';','') ) AS compatibilities 
            FROM integra_prod.compatibilities 
            ORDER BY sku  
            LIMIT 50") or die(mysql_error());     
*/
$rows = mysql_query("SELECT sku, concat(make, ' ', model, ' ', year) AS compatibilities 
					  FROM integra_prod.compatibilities	
					  ORDER BY sku	
            LIMIT 1000000,3000000") or die(mysql_error());	


 // loop over the rows, outputting them
 $list = 1; 
 $skus = array();
 $data = array();
 //build the array
 while ($row = mysql_fetch_assoc($rows)){

  if( !in_array($row['sku'], $skus) )
  {
  	array_push($skus, $row['sku']); 
  	$item = array( $row['sku'] => array($row['compatibilities']) );
  	array_push($data, $item);

  }else{     

    $key = array_search($row['sku'], $skus);   
    array_push($data[$key][$row['sku']], $row['compatibilities'] );  

  }

 }
 

 //build the string, concat or split 
 while ($value = current($data)) 
 {  
    $key = key($value);
    $str = implode('; ', $value[$key]).';';
    $str_count = strlen($str);
    
    if( $str_count < 1501 ) {  //Due to some restrictions modify to 1500

      //echo $key. ' = ' . "($str_count) " .  $str ."<br>\n";  

      fputcsv($output, array($key, $str) );
      

    }else{
       
       $div = ceil($str_count/1501);
       $chunk = ceil(count($value[$key])/$div);
      
      //$list = 1;
      //echo $key. ' = ' .  $str_count ." = ". $div ."<br>";      

      $new_arr = array_chunk($value[$key], $chunk );

      for ($c=0; $c < count($new_arr); $c++) { 

       	 $str = implode('; ', $new_arr[$c]).';';
         $str_count = strlen($str);
           
        // echo $list++ .". ".$key. ' = ' . "($str_count) " .  $str ."<br>\n";  

         fputcsv($output, array($key, $str) );

       }   
    }

    next($data);
 }
 
fclose($output);
echo 'Complete!.....'; 

?>