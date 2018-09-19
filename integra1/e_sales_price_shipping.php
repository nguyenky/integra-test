<?php
 
	require_once('system/config.php');
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
     <title>eBay Sales Price Shipping</title>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">      
      <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
	 
      
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
            margin-top: 120px !important;
         } 
         
      </style>
    </head>
 
 <body ng-app="SalesShippingModule">  
  
  <?php include('e_navigation.php');?>  
  <div id="main-container" ng-controller="ShippingCrtl"> 
	 <div class="row"> 
     <div class="col-md-8">
       <h2 align="center"> eBay Shipping Dashboard </h2> <br>

       <?php

        if(isset($_POST["upload"])) 
        {   
            $ds   = DIRECTORY_SEPARATOR; 
            $storeFolder = 'csv/';

            if ( isset($_FILES["file"])) 
            {  
                $targetPath = dirname( __FILE__ ) . $ds. $storeFolder . $ds; 
               
                $name       = trim(pathinfo($_FILES['file']['name'], PATHINFO_FILENAME));
                $extension  = trim(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

                $suffix = '_'.date('ymdHis'); 
                $basename = $name.'_'.$suffix.'.'. $extension;                   
                $tempFile = $_FILES['file']['tmp_name'];                      
                $targetFile =  $targetPath. $basename;  
                move_uploaded_file($tempFile,$targetFile);
              
                //run load infile     
                $csv = str_replace('\\', '\\\\', $targetFile);
                $created_at = date('Y-m-d H:i:s');

                $sql = "LOAD DATA LOCAL INFILE '$csv'
                        INTO TABLE e_shipping_rates
                        FIELDS TERMINATED BY ','
                        OPTIONALLY ENCLOSED BY '\"' 
                        LINES TERMINATED BY '\\r\\n'
                        IGNORE 1 LINES 
                        (mpn, min_qty, max_qty, profit, shipping, @notes, @created_by, @created_at)
                        SET created_at = '$created_at',
                            created_by = 1, 
                            notes = NULLIF(@notes, 'null')";
                mysql_query("$sql") or die(mysql_error());
             ?>

            <div class="alert alert-success alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <strong>Success!</strong> CSV File Successfully Uploaded
            </div>

       <?php
            }
          }          
       ?>
	   </div>
     
     <div class="col-md-4">
       <div ng-init="file_upload = 0" ng-hide="file_upload" class="pull-right">        
         <form action="e_sales_price_shipping_export.php" method="post">         
           <a href="" title="Upload .csv" class="btn btn-success btn-sm" ng-click="file_upload = 1" ><i class="fa fa-file-text"></i></a>
          <button type="submit" title="Export to csv" class="btn btn-primary btn-sm" name="export"><i class="fa fa-download"></i></button>
         </form>
       </div>
       <div ng-show="file_upload==1" class="pull-right"> 
          <form action="e_sales_price_shipping.php" method="post" enctype="multipart/form-data">
            <input type="file" name="file" id="file">
            <input type="submit" value="Upload CSV" name="upload" class="btn btn-primary btn-sm">
            <input type="button" value="Cancel" name="cancel" class="btn btn-info btn-sm" ng-click="file_upload = 0">
          </form>
       </div>
     </div>
   </div>  
	  <div class="row"> 
	   <div class="col-md-12">
	    <table class="table table-hover">
	    	<thead>
	    		<tr>
	    		  <th width="5%">#</th>
	    		  <th width="15%">MPN <input type="text" class="form-control" ng-model="search.mpn"></th>
	    		  <th>Min. Qty</th>
	    		  <th>Max. Qty</th>
	    		  <th>Profit %</th>
	    		  <th>Shipping Fee</th>
            <th>Shipping Type</th>
            <th>Notes</th>
	    		  <th></th>	    		 
	    		</tr>
	    	</thead>

	    	<tbody ng-repeat="ship in shippings | filter:search:strict" >
	    	 
	    	  <tr id="tr_{{ship.id}}" ng-hide="activeEditID === ship.id">
	    		 <td width="5%">{{$index+1}}</td>	    		
                 <td>{{ship.mpn}}</td>
                 <td>{{ship.min_qty}}</td>
                 <td>{{ship.max_qty}}</td>
                 <td>{{ship.profit}}</td>
                 <td>{{ship.shipping}}</td>
                 <td>{{ship.type}}</td>
                 <td>{{ship.notes}}</td> 	    	
	    	     <td>
                 <a href="" ng-click="setActiveEditID(ship.id)" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i></a>
                 <a href="" ng-click="modifyShipping(ship.id, $index, 'deleteDefinedRates')" class="btn btn-warning btn-sm"><i class="fa fa-trash"></i></a>
            </td>
	    	 </tr>

	    	 <tr ng-show="activeEditID === ship.id" ng-if="activeEditID === ship.id">
                 <td width="5%">{{$index+1}}</td>	    		
                 <td><input type="text" class="form-control" ng-model="ship.mpn"></td>
                 <td><input type="text" class="form-control" ng-model="ship.min_qty" size="3"></td>
                 <td><input type="text" class="form-control" ng-model="ship.max_qty" size="3"></td>
                 <td><input type="text" class="form-control" ng-model="ship.profit" size="3"></td>
                 <td><input type="text" class="form-control" ng-model="ship.shipping" size="5"></td>
                 <td>
                  <select name="repeatSelect" class="form-control"                            
                           ng-init="ship.Shipping_Type={key:ship.type}"
                           ng-model="ship.Shipping_Type" 
                           ng-options="item.key for item in shippingType track by item.key"    
                           ng-selected ="ship.type"               
                           ng-change="ship.shipping = ship.Shipping_Type.value; ship.type = ship.Shipping_Type.key"
                           required>                      
                   </select>      
                   </td>
                 <td><input type="text" class="form-control" ng-model="ship.notes"></td>
	    		       <td><a href="" ng-click="modifyShipping(ship, $index, 'updateDefinedRates')" class="btn btn-success btn-sm"><i class="fa fa-save"></i></a></td>
	    	 </tr>

	      </tbody>


	      <tfoot>
	         
	         <form ng-submit="modifyShipping(newShippingRow, '', 'insertDefinedRates')">               
               <tr ng-show="newShipping == 0">
                 <td colspan="9" align="right"><a href="" ng-click="newShipping = 1"class="btn btn-primary btn-sm"><i class="fa fa-plus"></i></a></td>
               </tr>

               <tr ng-show="newShipping">
                 <td width="5%"></td>	    		
                 <td><input type="text" class="form-control" ng-model="newShippingRow.mpn"      required></td>
                 <td><input type="text" class="form-control" ng-model="newShippingRow.min_qty"  required size="3"></td>
                 <td><input type="text" class="form-control" ng-model="newShippingRow.max_qty"  required size="3"></td>
                 <td><input type="text" class="form-control" ng-model="newShippingRow.profit"   required size="3"></td>
                 <td><input type="text" class="form-control" ng-model="newShippingRow.shipping" required size="5"></td>
                 <td> 
                  <select name="repeatSelect" class="form-control" 
                           ng-model="newShippingRow.shippingType" 
                           ng-options="item.key for item in shippingType"                   
                           ng-change="onSelectChange()"
                           required>                      
                   </select>                   
                 <td><input type="text" class="form-control" ng-model="newShippingRow.notes"></td>  
                 <td><button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i></button></td>
               </tr>
             </form> 	
             	
	     </tfoot>

	   </table>

	 </div>	  
	</div>
</div>
	      
	 
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script type="text/javascript" src="js/jquery-ui.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script> 
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.15/angular.min.js"></script>
    <script type="text/javascript" src="js/ui-bootstrap-tpls-0.12.1.min.js"></script>
    <script type="text/javascript" src="js/appShipping.js"></script>
  

</body>
</html>

