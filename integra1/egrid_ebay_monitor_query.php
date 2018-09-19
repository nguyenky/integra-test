<?php

  require_once('system/config.php');
    mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
   
	
       $json = file_get_contents('php://input');
       $obj  = json_decode($json);

       $action = !isset($obj->action) ? $_POST['action'] : $obj->action;
     
       if($action == 'viewGridDetails')
       { 
         
          $qid = $_POST['id'];
          $query = mysql_query("SELECT * FROM ebay_research_monitor WHERE query_id = '$qid' AND is_monitor = '1' ORDER BY item_id");
          
          if(mysql_num_rows($query) > 0 ) {
           $data = '<table class="table table-bordered">';
           $i = 1;
            while ($row = mysql_fetch_assoc($query)) 
            {  
              $logs = '';
              if($row['logs'] != ''){
               
               $history = json_decode('['.$row['logs'].']');
               for ($i=0; $i < count($history) ; $i++) { 
                 $h = (array) $history[$i];
                 foreach ($h as $key => $value) {
                   if($key == 'timestamp'){
                    $key = 'date';
                   }
                   $logs .= "<b>". $key.'</b>:'.$value."<br>";  
                 } 
                 $logs .= "<hr>";
                 
                }

              } 
              
             
                $data .= '<tr>';
                $data .= '<td width="20%"><small><sup><i>'.$i++ . '.</sup></i> '.'<img src="'.$row['image_url'].'" class="img-responsive"></td>';
                $data .= '<td>'.$row['item_id'].'</small></td>';
                $data .= '<td><small>'.$row['title'].'</small></td>';
                $data .= '<td><small>'.$row['price'].'</small></td>';
                $data .= '<td><small>'.$row['seller'].'</small></td>';
                $data .= '<td><small>'.$logs.'</small></td>';
                $data .= '</tr>'; 
            }
            $data .= '</table>';
          }
          else{
            $data = 'No Result Found. Monitored item might already updated';
          }

             echo $data;        
       }
       elseif($action == 'deleteGridDetails')
       {
         $keyword = $_POST['key'];
         $user_id = $_POST['user_id'];
         $id = $_POST['id'];

         $sql = mysql_query("SELECT *FROM ebay_research_monitor_query WHERE id = '$id'") or die(mysql_error());
     
           while ( $q = mysql_fetch_assoc($sql) )
           {
              $q_id = $q['id'];
              mysql_query("DELETE FROM ebay_research_monitor_query WHERE id = '$q_id' ") or die(mysql_error());
              mysql_query("DELETE FROM ebay_research_monitor WHERE query_id = '$q_id' ") or die(mysql_error());
            } 
          
           echo '<div class="alert alert-success" role="alert">
           <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
           <p>Query and Listing Successfully Deleted</p>
           </div>';
       }
     

?>