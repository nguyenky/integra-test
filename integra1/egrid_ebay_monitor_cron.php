<?php

error_reporting(E_ALL);
set_time_limit(0);

require_once('system/e_utils.php');
require_once('system/config.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

//delete all the result from the previous cron results
mysql_query("DELETE FROM ebay_research_monitor WHERE query_id = '0';") or die(mysql_error());

//get all latest the keywords of each user
$last = mysql_query("SELECT a.*,b.email
                      FROM ebay_research_monitor_query a 
                      LEFT JOIN integra_users b ON a.user_id = b.id  
                      GROUP BY a.user_id,a.keyword 
                      ORDER BY a.created_at DESC") or die(mysql_error()); 

$monitored_query_id = [];
$users = []; 
$user_email = [];
$keywords = [];
echo "Running.... \n";
while ($lst = mysql_fetch_assoc($last))
{
  $q = $lst['keyword'];
  $u = $lst['user_id'];
  $qid = $lst['id'];

  $monitored_query_id[] = $qid;
  $users[] = $u;  
  $user_email[] = $lst['email'];
  $keywords[] = $q;
  //run the ebay api, and insert the new listing into ebay_research_monitor
  echo 'Q: '.$q.'; U: '.$u."\n";
  EbayUtils::MonitorResearchKeyword($q, $u, 0);
  
}

$m_qids = implode(',', $monitored_query_id);
echo "<br>\n QUERY IDs: ".$m_qids;
echo "<br>\n Keyword: ".implode(',',$keywords);
echo "<br>\n USER IDs: ".implode(',',$users);
echo "<br>\n Emails: ".implode(',',$user_email);
echo "<hr>";

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
     <title>eBay Research Monitor Admin</title>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">      
      <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" />
	 
      
      <style type="text/css">
         fieldset{
         	width: 80%;
         	margin: 0 auto;
         	padding: 10px 15px;
         }
         .disabled{
         	  pointer-events: none;  
			  opacity: 0.5;
			  background: #CCC;
			  padding: 10px;
         }
         .note{
         	color: red;
         }
         #main-container{
          	padding: 0px 15px;            
         }  
         .table tr td , .table tr th{
           font-size: 12px;
         }



     
      </style>
    </head>
 
 <body> 
    <div id="main-container"> 

       <?php
       
        for($key = 0; $key < count($users); $key++)
        {
           //get all the monitored listing by user
           $user = $users[$key];
           $u_email = $user_email[$key];
           $keyword = $keywords[$key];
           $prev_qry = mysql_query("SELECT a.*,b.email 
                                    FROM ebay_research_monitor a
                                    LEFT JOIN integra_users b ON a.user_id = b.id  
                                    WHERE a.is_monitor = 1                                    
                                    AND a.user_id = '$user' 
                                    ANd a.query_id IN ($m_qids)
                                    ORDER BY a.user_id;") or die(mysql_error());
          $head ='<span><h4>'.$u_email." ($keyword) ".'</h4></span>
                <table class="table table-bordered table-striped" width="100%">
                <tr>
                <th>#</th>
                <th>Image</th>
                <th>Item  ID</th>
                <th>Title</th>
                <th>Category</th>
                <th>Brand</th>
                <th>Seller</th>
                <th>Price</th>
                <th>Shipping</th>
                <th>Qty Sold</th> 
                <th>Compatible Vehicles</th> 
                <th>MPN</th>
                <th>OPN</th>
                <th>IPN</th> 
                </tr>';
                  
                 $body = '';
                 $list = 1;
                 $changes = 0;
                 while( $prev = mysql_fetch_assoc($prev_qry) )
                 {

                  $history  = [];
                  $history['timestamp'] = date('Y-m-d H:i:s');

                  $item_changed = 0;
                  $itemID = $prev['item_id'];
                  $userID = $prev['user_id'];
                  $prevID = $prev['id'];
                  $new_qry  = mysql_query("SELECT *FROM ebay_research_monitor 
                                  WHERE item_id = '$itemID' AND user_id = '$userID' AND query_id = 0 LIMIT 1") or die(mysql_error()); 
                      
                  if(mysql_num_rows($new_qry) > 0 ) 
                  { 
                      $nq = mysql_fetch_assoc($new_qry);   

                      $img_url = $nq['image_url'];
                      if ($prev['image_url'] !=  $nq['image_url'])
                      { 
                        $history['image_url'] = $prev['image_url'];
                        $img_url = $nq['image_url']; 
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET image_url = '$img_url' WHERE id = '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_item_id = $nq['item_id'];
                      if ( ($prev['item_id']   !=  $nq['item_id'])  ) 
                      {
                        $history['item_id'] = $prev['item_id'];
                        $item_id = $nq['item_id'];
                        $new_item_id = "<i class=text-warning><b>".$item_id."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET item_id = '$item_id' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_title = $nq['title'];
                      if ( ($prev['title']   !=  $nq['title'])  ) {
                       
                        $history['title'] = $prev['title'];
                        $title = $nq['title'];
                        $new_title = "<i class=text-warning><b>".$title."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET title = '$title' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_category = $nq['category'];
                      if ( ($prev['category'] !=  $nq['category'])  ) {
                        $history['category'] = $prev['category'];
                        $category = $nq['category'];
                        $new_category = "<i class=text-warning><b>".$category."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET category = '$category' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_brand = $nq['brand'];
                      if ( ($prev['brand']  !=  $nq['brand'])  ) {
                        $history['brand'] = $prev['brand'];
                        $brand = $nq['brand'];
                        $new_brand = "<i class=text-warning><b>".$brand."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET brand = '$brand' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_seller = $nq['seller'];
                      if ( ($prev['seller'] !=  $nq['seller'])  ) {
                        $history['seller'] = $prev['seller'];
                        $seller = $nq['seller'];
                        $new_seller = "<i class=text-warning><b>".$seller."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET seller = '$seller' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_price = $nq['price'];
                      if ( ($prev['price']  !=  $nq['price'])  ) {
                        $history['price'] = $prev['price'];
                        $price = $nq['price'];
                        $new_price = "<i class=text-warning><b>".$price."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET price = '$price' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_shipping = $nq['shipping'];
                      if ( ($prev['shipping']  !=  $nq['shipping'])  ) {
                        $history['shipping'] = $prev['shipping'];
                        $shipping = $nq['shipping'];
                        $new_shipping = "<i class=text-warning><b>".$shipping."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET shipping = '$shipping' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_num_sold = $nq['num_sold'];
                      if ( ($prev['num_sold']  !=  $nq['num_sold'])  ) {
                        $history['num_sold'] = $prev['num_sold'];
                        $num_sold = $nq['num_sold'];
                        $new_num_sold = "<i class=text-warning><b>".$num_sold."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET num_sold = '$num_sold' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_num_compat = $nq['num_compat'];
                      if ( ($prev['num_compat']!=  $nq['num_compat'])  ) {                        
                        $history['num_compat'] = $prev['num_compat'];
                        $num_compat = $nq['num_compat'];
                        $new_num_compat = "<i class=text-warning><b>".$num_compat."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET num_compat = '$num_compat' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_mpn = $nq['mpn'];
                      if ( ($prev['mpn']   !=  $nq['mpn'])  ) {
                        $history['mpn'] = $prev['mpn'];
                        $mpn = $nq['mpn'];
                        $new_mpn = "<i class=text-warning><b>".$mpn."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET mpn = '$mpn' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_opn = $nq['opn'];
                      if ( ($prev['opn'] !=  $nq['opn'])  ) {
                        $history['opn'] = $prev['opn'];
                        $opn = $nq['opn'];
                        $new_opn = "<i class=text-warning><b>".$opn."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET opn = '$opn' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }

                      $new_ipn = $nq['ipn'];
                      if ( ($prev['ipn'] !=  $nq['ipn']) ) {
                        $history['ipn'] = $prev['ipn'];
                        $ipn = $nq['ipn'];
                        $new_ipn = "<i class=text-warning><b>".$ipn."</b></i>";
                        $changes = 1;
                        $item_changed = 1;
                        mysql_query("UPDATE ebay_research_monitor SET ipn = '$ipn' WHERE id= '$prevID'; ") or die(mysql_error()); 
                      }
                 }  
                 else{
                      $changes = 0;
                      $item_changed = 0;
                      $id = $prev['query_id'];
                      mysql_query("UPDATE ebay_research_monitor_query SET status = 'ok' WHERE id = '$id' ") or die(mysql_error());
                  } 
                    
                    if($changes && $item_changed)
                    {
                      
                      $h = json_encode($history);
                      echo $h;

                      $id = $prev['query_id'];
                      mysql_query("UPDATE ebay_research_monitor_query SET status = 'revised' WHERE id = '$id' ") or die(mysql_error());
                      
                      $qry = mysql_query("SELECT logs FROM ebay_research_monitor WHERE id = '$prevID'") or die(mysql_error());
                      $lq  = mysql_fetch_assoc($qry); 

                      if ($lq['logs'] == '')
                      {                       
                       $logs = $h;
                      }else{
                       $logs = $lq['logs'] . ','. $h;
                      }

                      mysql_query("UPDATE ebay_research_monitor SET logs = '$logs' WHERE id = '$prevID' ") or die(mysql_error());
                      
                       $body .= "       
                                  <tr>
                                      <td><b>prev</b></td>
                                      <td><img src='".$prev['image_url']."'></td>
                                      <td>".$prev['item_id'].   "</td>
                                      <td>".$prev['title'].     "</td>
                                      <td>".$prev['category'].  "</td>
                                      <td>".$prev['brand'].     "</td>
                                      <td>".$prev['seller'].    "</td>
                                      <td>".$prev['price'].     "</td>
                                      <td>".$prev['shipping'].  "</td>
                                      <td>".$prev['num_sold'].  "</td>
                                      <td>".$prev['num_compat']."</td> 
                                      <td>".$prev['mpn'].       "</td>
                                      <td>".$prev['opn'].       "</td>
                                      <td>".$prev['ipn'].       "</td> 
                                  </tr>

                                  <tr>
                                      <td><i>new</i> </td>
                                      <td><img src='".$img_url."'></td>
                                      <td>" .$new_item_id. "</td>
                                      <td>" .$new_title. "</td>
                                      <td>" .$new_category. "</td>
                                      <td>" .$new_brand. "</td>
                                      <td>" .$new_seller. "</td>
                                      <td>" .$new_price. "</td>
                                      <td>" .$new_shipping. "</td>
                                      <td>" .$new_num_sold. "</td>
                                      <td>". $new_num_compat. "</td> 
                                      <td>" .$new_mpn. "</td>
                                      <td>" .$new_opn. "</td>
                                      <td>" .$new_ipn. "</td> 
                                  </tr>
                                 <tr>
                                 <td colspan='14'><hr></td>
                               </tr>";      
                 
                    } //end if changes 
                    else{
                      $id = $prev['query_id'];
                      mysql_query("UPDATE ebay_research_monitor_query SET status = 'ok' WHERE id = '$id' ") or die(mysql_error());
                    }

                 }//end while mysql_fetch_assoc($prev_qry)     
               
               $foot = '</table>';


               echo $head.$body.$foot;

               
               if($changes) 
               {
               
               $to = $u_email;
               $subject = "eBay Monitor Listing - Cron";
               $txt = $head.$body.$foot;     

               $headers  = "From: management@eocparts.com \r\n";
               $headers .= "Reply-To: management@eocparts.com \r\n"; 
               $headers .= "CC: reyn@eocenterprise.com \r\n";
               $headers .= 'MIME-Version: 1.0' . "\r\n";
               $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

                mail($to,$subject,$txt,$headers);
                echo 'STATUS : Revised';

               }
               else{
                echo 'STATUS : OK';
               }
               echo "<hr><br>";

       }//end for users loop
              

     ?>
   

   </div>
 </body>
</html>
