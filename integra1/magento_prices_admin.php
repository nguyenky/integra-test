<?php
 
	require_once('system/config.php');
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
     <title>Magento Prices</title>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
      <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
      
      <style type="text/css">
         fieldset{
         	width: 90%;
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
 <body ng-app="MagentoPriceModule">   

 <fieldset ng-controller="priceController">
   <legend> <h3> Magento Prices </h3> </legend>
    <div class="row">
       <section class="col-md-7">
        <div class="panel panel-primary">
	      <div class="panel-heading">
	        <h3 class="panel-title">Update Prices</h3>
	      </div>
	      <div class="panel-body">
	          
	  <?php

        if(isset($_POST["upload"])  ) 
        {   
            $ds   = DIRECTORY_SEPARATOR; 
            $storeFolder = 'csv/';
            
            $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

            if ( $_FILES["file"]["error"] == 0  && $_POST["store"] != '' && $extension == 'csv' ) 
            {  
                $targetPath = dirname( __FILE__ ) . $ds. $storeFolder . $ds; 
                $store = $_POST["store"];
                $name  = $store;//trim(pathinfo($_FILES['file']['name'], PATHINFO_FILENAME));
                $suffix = '_'.date('ymdHis'); 
                $basename = $name.$suffix.'.'. $extension;                   
                $tempFile = $_FILES['file']['tmp_name'];                      
                $targetFile =  $targetPath. $basename;  
                move_uploaded_file($tempFile,$targetFile);
              
                
                //run load infile     
                $csv = str_replace('\\', '\\\\', $targetFile);
                $created_at = date('Y-m-d H:i:s');  

                mysql_query("INSERT INTO magento_price_admin(file,import_at,created_at) VALUES ('$basename', '$created_at','$created_at') ") or die(mysql_error()); 
                mysql_query("TRUNCATE TABLE magento.price_import") or die(mysql_error()); 

                $sql = "LOAD DATA LOCAL INFILE '$csv'
                        INTO TABLE magento.price_import
                        FIELDS TERMINATED BY ','
                        OPTIONALLY ENCLOSED BY '\"' 
                        LINES TERMINATED BY '\\r\\n'
                        IGNORE 1 LINES 
                        (sku,price)";
                
                mysql_query("$sql") or die(mysql_error());
                $import_at = date('Y-m-d H:i:s'); 
                mysql_query("UPDATE magento_price_admin SET import_at='$import_at' WHERE file='$basename'") or die(mysql_error());

               // mysql_query("CALL magento.update_prices('$store')") or die(mysql_error());
                $call_at = date('Y-m-d H:i:s'); 
                mysql_query("UPDATE magento_price_admin SET call_at = '$call_at' WHERE file='$basename'") or die(mysql_error());
                
                 //run indexer!

	         echo '<div class="alert alert-success alert-dismissible" role="alert">
	              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
	              <strong>Success!</strong> Successfully Uploaded
	            </div>';             
          
            }
            else{

             echo '<div class="alert alert-warning alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <strong>Error!</strong> Both Store and .csv file are required!
            </div>'; 

            }
          } 

         echo '<table class="table">       
       	 	<tr>
       	 	  <th> File </th>
              <th> Uploaded At</th>
              <th> Status </th>
       	 	</tr>'; 
       	  $query = mysql_query("SELECT *FROM magento_price_admin ORDER BY created_at DESC");
          while ($row = mysql_fetch_assoc($query))
          {
          	 echo '<tr>
          	       <td>'.$row['file'].' </td>
	               <td>'.$row['created_at'].' </td>
	               <td>'.$row['status'].' </td>
	       	 	</tr>'; 
          }

        echo '</table>';  
       ?>	 
   

      <form action="magento_prices_admin.php" method="post" enctype="multipart/form-data">
       
         <select class="form-control" name="store"> 
             <option value="" selected="selected"> Select Store</option>    
             <option value="qeautoparts">qeautoparts</option>
             <option value="eocparts">eocparts</option>
             <option value="europortparts">europortparts</option>
             <option value="iapaustralia">iapaustralia</option>
             <option value="iapcanada">iapcanada</option>
             <option value="iapunitedkingdom">iapunitedkingdom</option>
             <option value="iapfrance">iapfrance</option>
             <option value="iapbelgique">iapbelgique</option>
             <option value="iapbrazil">iapbrazil</option>
             <option value="iapdanmark">iapdanmark</option>
             <option value="iapdeutschland">iapdeutschland</option>
             <option value="iapitalia">iapitalia</option>
             <option value="iapnederland">iapnederland</option>
             <option value="iapsverige">iapsverige</option>
             <option value="iapswitzerland">iapswitzerland</option>
             <option value="iapespana">iapespana</option>
             <option value="iaposterreich">iaposterreich</option>
          </select><br>          
          <input type="file" name="file" id="file"><br>
          <button type="file" class="btn btn-primary" name="upload"> <i class="fa fa-cloud-upload"></i> Upload CSV </button>
      
      </form>

	      </div>
	      <div class="panel-footer">
	      	 <div role="alert" class="alert alert-warning">
		      <strong><i class="fa fa-warning"></i> CSV Validation Rule </strong>
		      <ul>
		      	 <li> Contains only 2 Columns </li>
		      	 <li> 1st column must be MPN, 2nd is the Value </li>
		      	 <li> Strictly No headers </li>
		      	 <li> Only 1 csv per Magento Store </li>
		      </ul>
		    </div>
	      </div>
	  </div>
 </section>


       <section class="col-md-5">
        <div class="panel panel-success">
	      <div class="panel-heading">
	        <h3 class="panel-title">Download (csv)</h3>
	      </div>
	      <div class="panel-body">	       
	      
		     <section class="col-md-12">
		        <span class="text text-primary"> Select Store :</span> 
		        <p ng-repeat="ct in core_store"> 
	              &nbsp;&nbsp;<input type="checkbox" value="{{ct.id}}" ng-model="ct.selected"> {{ct.name}}
		        </p>
		         <hr>
		         <button type="submit" class="btn btn-success btn-lg" ng-click="downloadCSVPrices(core_store)">
	          	 <i class="fa fa-cloud-download"></i>
	          	  Download Store Prices 
	          	 </button>
	          </section> 

	         
	      
	      </div>
	    </div>
     </section>

  </div>
 </fieldset>
 
 <script type="text/javascript" src="js/jquery.min.js"></script>
 <script type="text/javascript" src="js/jquery-ui.min.js"></script>
 <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script> 
 <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.15/angular.min.js"></script>
 <script type="text/javascript" src="js/ui-bootstrap-tpls-0.12.1.min.js"></script>
 <script type="text/javascript" src="js/appMagentoPrices.js"></script>

</body>
</html>