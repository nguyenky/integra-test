<?php

require_once('system/config.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

     
     $is_vaid = 0;    
     if (!$_FILES['csvFile']['error'])
     {

         $info = pathinfo($_FILES['csvFile']['name']);
         $ext = $info['extension'];         
     
          if ($ext == 'csv')
          { 
             $filename = date('y-m-d');
             $target_path = "csv/".$filename.'.csv';

            if(move_uploaded_file($_FILES['csvFile']['tmp_name'], $target_path)) 
            {
                $is_valid = 1;
                $error = '';

            }else{ 
               $error =  "There was an error uploading the file, please try again!";           
            }

          }else{
              $error =  "Only .csv file allowed";
          }

      }else{
         
         //echo $_FILES['csvFile']['error']; 
         $error =  "There was an error uploading the file, please try again!";
      }


  

if ($is_valid) 
{
 
 $load_data = <<<EOD
 LOAD DATA LOCAL INFILE '$target_path'
 INTO TABLE e_batch_data
 FIELDS TERMINATED BY ',' 
 ENCLOSED BY '\"'
 LINES TERMINATED BY '\r\n'
(itemID)
EOD;
   
    try{  
       
        $dateTime = date('Y-m-d H:i:s');
        mysql_query("INSERT INTO e_batch_process (name, status, created_timestamp) VALUES('Ebay', '0', '$dateTime')");
        $batch_id = mysql_insert_id();

        mysql_query($load_data) or die(mysql_error());
        mysql_query("UPDATE e_batch_data SET batch_id = '$batch_id', created_timestamp = '$dateTime' WHERE batch_id = '' ");

        exec('nohup php e_batch_research_execute_scraper.php '.$batch_id. ' > /dev/null &');
       
        header('Location: e_batch_research.php');

    }
    catch (Exception $e)
     {
           echo '<pre>';
           var_dump($e->getMessage());
          echo '</pre>';
      }
}
else{
  echo $error;
}



?>
