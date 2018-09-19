<?php
 
	require_once('system/config.php');
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

  $query_id = $_REQUEST['query_id'];
  $user_id  = $_REQUEST['user_id'];
  $keyword  = $_REQUEST['keyword'];
  $action   = $_REQUEST['action'];
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
         .panel-heading {
          padding: 5px 10px !important;
        }

     
      </style>
    </head>
 
 <body> 
    <div id="main-container" ng-controller="ShippingCrtl"> 
      <h3 align="center"> eBay Research Monitor Admin </h3> <hr>
    
   <?php 
    if ($action == 'delete')
    {
     
     $sql = mysql_query("SELECT *FROM ebay_research_monitor_query WHERE keyword = '$keyword' AND user_id = '$user_id' ") or die(mysql_error());
     
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
<form id="monitorAdminForm" method="GET">
 <input type="hidden"  name="query_id" class="query_id" value="">
 <input type="hidden"  name="keyword" class="keyword" value="">
 <input type="hidden"  name="action" class="action" value="">
 <input type="hidden"  name="user_id" class="user_id" value="">
</form>

<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">

<?php             
  $head_query = mysql_query("SELECT a.*, b.email,c.is_monitor FROM ebay_research_monitor_query a 
                          LEFT JOIN integra_users b ON a.user_id = b.id 
                          LEFT JOIN ebay_research_monitor c ON c.query_id = a.id AND c.is_monitor = 1
                          WHERE a.user_id != 0  AND a.is_monitor = 1 GROUP BY c.query_id                    
                          ORDER BY a.created_at DESC") or die(mysql_error()); // GROUP BY a.keyword,a.user_id    
    
  $list = 1;
  while($field = mysql_fetch_assoc($head_query) ) 
  { 
   
    if($field['is_monitor'] == 1 ){
     $qid = $field['id'];

?>

      <div class="panel panel-default">
       
        <div class="panel-heading" role="tab" id="headingTwo">
         <section class="row panel-title">
           <span class="col-md-6">         
              <a class="collapsed text-primary" role="button" 
                 data-toggle="collapse" data-parent="#accordion" 
                 href="#collapse_<?php echo $qid ?>" aria-expanded="false" 
                 aria-controls="collapse_<?php echo $qid ?>">
                 <small><?php echo "<sup><i>".$list++.".</i></sup> ".$field['keyword'];?></small>
              </a>             
           </span>
           
           <span class="col-md-2">
            <small> <?php echo $field['email'];?> </small>
           </span>
           <span class="col-md-2">
             <small><?php echo $field['created_at'];?></small>
           </span>

            <span class="col-md-2">
             <?php echo 
               "<a title='Delete Query' class='delete-query' data-query-id=".$field['id']." data-user-id=".$field['user_id']."  data-query-keyword=".$field['keyword']."><i class='fa fa-times btn btn-warning btn-sm'></i></a>";
             ?>               
            </span>

         </section>
        
        </div>

        <div id="collapse_<?php echo $qid ?>" class="panel-collapse collapse" role="tabpanel">
          <div class="panel-body">
            <?php
                       
              $query = mysql_query("SELECT * FROM ebay_research_monitor WHERE query_id = '$qid' AND is_monitor = '1' ORDER BY item_id");
              
              if(mysql_num_rows($query) > 0 ) {
               $data = '<table class="table table-bordered">';
               $item_list = 1;
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
                    $data .= '<td width="20%"><small><sup><i>'.$item_list++ . '.</sup></i> '.'<img src="'.$row['image_url'].'" class="img-responsive"></td>';
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

            ?>
         </div>
        </div>
      </div>

    <?php 
      } //if is_monitor
     } //while
    ?>

    </div>

 </div>



    <div class="modal fade" id="queryModal">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
         
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">Modal title</h4>
          </div>

          <div class="modal-body">       
          </div>    

        </div><!-- /.modal-content -->
      </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

     <script type="text/javascript" src="js/jquery.min.js"></script>
     <script type="text/javascript" src="js/jquery-ui.min.js"></script>
     <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script> 
     
    <script type="text/javascript">
    
     $(document).ready(function()
     {
        $('.delete-query').click(function()
        { 
          var query_id = $(this).data('query-id');
          var query_key = $(this).data('query-keyword');
       
          var del = confirm("Are you sure, you want to delete '"+ query_key + "'' ?")
          
          if(del)
          {  

             var postData ={
              'id' : $(this).data('query-id'),
              'user_id' : $(this).data('user-id'),
              'key' : query_key,
              'action' : 'deleteGridDetails',
             }

            $.ajax({
              url: 'egrid_ebay_monitor_query.php',
              type: "POST",                                      
              data:postData,           
              success: function(response)
               {                                 
                 alert("Query and Listing Successfully Deleted");
                 location.reload();
               }
            });   
            
          
         }
          else{
            return false;
          }       
        });
       
       $('.view-query').click(function()
       {
          var postData ={
              'id' : $(this).data('query-id'),
              'key' : $(this).data('query-keyword'),
              'action' : 'viewGridDetails',
          }
         
          $('#queryModal').modal('show')
          $('#queryModal').find('.modal-title').text($(this).data('query-user') +' - '+ $(this).data('query-keyword'));
          $('#queryModal').find('.modal-body').html('<i class="fa fa-cog fa-2x fa-spin"></i>');  

           $.ajax({
              url: 'egrid_ebay_monitor_query.php',
              type: "POST",                                      
              data:postData,           
              success: function(response)
               {                                 
                 $('#queryModal').find('.modal-body').html(response);
               }
            });       
       });

                    
     });
   </script>

 </body>
</html> 