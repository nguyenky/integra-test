<?php
 
	require_once('system/config.php');
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
     <title>Batch Research</title>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
      
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

      </style>
    </head>
 <body>   

 <fieldset>
	<legend> <h3> Ebay Batch Research </h3> </legend><br>
		
		<?php

         $query   = mysql_query("SELECT * FROM e_batch_process");
        
         print '<br><table border ="1" width="100%" cellpadding="4">';     
	            	print "<tr>
	          	            <th>Batch ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Entry</th>
                            <th>Completed</th>
	          	          </tr>";
              
              $status = 'Done';
              $upload_file = 0;
	          while ($row = mysql_fetch_array($query) )
	          { 
	          	if($row['status'] == 0 )
	          	{
	          	   $upload_file = 1;	 
                   $active_batch_id = $row['id'];
                   $running_query   = mysql_query("SELECT COUNT(id) AS total, SUM(IF(status = 1, 1, 0)) AS scraped_items 
								                   	FROM e_batch_data 
								                   	WHERE batch_id = '$active_batch_id' GROUP BY batch_id");
		           $field     = mysql_fetch_array($running_query);   
				   $total   = $field['total'];  
				   $scraped = $field['scraped_items']; 

				   $status = '('. $scraped . '/' . $total . ') <i class="fa fa-cog fa-spin"></i> Processing...';
 
	          	}

	          	print "<tr>
	          	           <td>".$row['id']."</td>
                           <td>".$row['name']."</td>
                           <td>".$status."</td>
                           <td>".$row['created_timestamp']."</td>
                           <td>".$row['updated_timestamp']."</td>
	          	       </tr>";
	            
	           }
	     print '</table> <br> <hr>';
         
       $class = '';
       $note = '';
	       if($upload_file)
	       { 
	         $class = 'disabled';
	         $note  = '<p><i class="fa fa-bell note"> Cannot upload file, when scraper is still running. </i><br></p>';
	         header("refresh:30");  
	       }


      ?>
     <?php echo $note;?>
	   <div class="<?php echo $class; ?>">	  
		    <form action="e_batch_research_upload.php" method="post" enctype="multipart/form-data">
				 <label> <i> CSV File (itemIDs): </i>&nbsp;&nbsp;&nbsp;</label>
				 <input type="file" name="csvFile">
				 <input type="submit" value="Upload">
				
			</form>
	  </div>

		
  </fieldset>
</body>
</html>