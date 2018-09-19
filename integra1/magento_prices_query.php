<?php

  require_once('system/config.php');
    mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
   
	
       $json = file_get_contents('php://input');
       $obj  = json_decode($json);
       $actionType = ['reply', 'compose', 'flagged', 'trash']; //' 'livehelperchat'

       $action = !isset($obj->action) ? $_POST['action'] : $obj->action;
     
       if($action == 'downloadPrices')
       { 

        
        
          /*$stores = implode(',',$obj->data);

          $query = mysql_query("SELECT c.code,d.sku,a.value
                                FROM magento.catalog_product_entity_decimal a
                                LEFT JOIN magento.eav_attribute b ON a.attribute_id = b.attribute_id
                                LEFT JOIN magento.core_store c ON c.store_id = a.store_id
                                LEFT JOIN magento.catalog_product_entity d ON d.entity_id = a.entity_id
                                WHERE b.attribute_code = 'price' AND a.store_id IN ($stores)
                                ORDER BY a.store_id,d.sku,a.value LIMIT 5") or die(mysql_error());
       
         print_r($obj);*/

       }
       elseif($action == 'livehelperchat')
       {
          $graphUser = $_POST['graphUser'];
          $graphDate = $_POST['graphDate'];

          $query = mysql_query("SELECT *FROM magento_chat.lh_msg 
                                WHERE from_unixtime(time,'%m/%d %H:00') = '$graphDate' 
                                AND name_support = '$graphUser';") or die(mysql_error());

           $data = '<table class="table">';
           $i = 1;
           while ($row = mysql_fetch_assoc($query)) 
           {
              $data .= '<tr> <td>'.$i++ . '. '.$row['msg'].'</td> </tr>'; 
           }
           $data .= '</table>';

           echo $data;        
       }
       elseif ( in_array($action, $actionType) ) //['reply', 'compose', 'flagged', 'trash']
       {
          
          $graphUser = $_POST['graphUser'];
          $graphDate = $_POST['graphDate'];
          
          if($action == 'compose')
          {
              
             $query = mysql_query("SELECT *
                                  FROM api_composed_messages
                                  WHERE DATE_FORMAT( cm_timestamp,  '%m/%d %H:00' ) = '$graphDate'
                                  AND cm_user = '$graphUser' 
                                  ORDER BY cm_timestamp DESC") or die(mysql_error());
             
             $data = '';
             $i = 1;
             while ($row = mysql_fetch_assoc($query)) 
             {
                $data .= "<p><i><sup>".$i++.". </sup> To:</i> ". $row['cm_to'];
                
               if( $row['cm_cc']) { $data .= " &nbsp;&nbsp;&nbsp;&nbsp; | <i>Cc:</i> ". $row['cm_cc']; }
               $data.='</p>';
                
                $data .= "<p><i>Subject :</i>". $row['cm_subject']  ."</p>";
                $data .= "<p><textarea class='form-control'>". $row['cm_body'] ."</textarea> </p>";
                $data .= "<hr>";
             }
             

          } 
          else{ 
            $query = mysql_query("SELECT *
                                  FROM api_messages_logs
                                  WHERE DATE_FORMAT( log_timestamp,  '%m/%d %H:00' ) = '$graphDate' 
                                  AND log_activity = '$action' 
                                  AND log_user = '$graphUser'                            
                                  ORDER BY log_timestamp DESC") or die(mysql_error());
             
             $data = '<table class="table"><tr><th>Message ID</th></tr>';
             $i = 1;
             while ($row = mysql_fetch_assoc($query)) 
             {
                $data .= '<tr> <td>'.$i++ . '. '.$row['message_id'].'</td> </tr>'; 
             }
             $data .= '</table>';

             
          }     
         
         echo $data; 

       }

       


   
     

?>