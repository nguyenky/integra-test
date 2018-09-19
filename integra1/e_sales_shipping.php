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
  
    <!-- Fixed navbar -->
      <nav class="navbar navbar-inverse navbar-fixed-top">
        <div class="container">
          <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
              <span class="sr-only">Toggle navigation</span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#">EOC Enterprise</a>
          </div>         
        </div>
      </nav>

  <div id="main-container" ng-controller="ShippingCrtl"> 
	  <h2 align="center">Shipping Dashboard </h2> <br>
	   
	  <div class="row"> 
	   <div class="col-md-12">
	    <table class="table table-hover">
	    	<thead>
	    		<tr>	    		  
	    		  <th>MPN
             
            </th>
	    		  <th>Min. Qty</th>
	    		  <th>Max. Qty</th>
	    		  <th>Shipping Type</th>
	    		  <th>Shipping Fee</th>
            <th>Notes</th>
	    		  <th></th>	    		 
	    		</tr>
	    	</thead>    	


	      <tfoot>
	        
	         <form ng-submit="modifyShipping(newShippingRow, '', 'insertDefinedRates')"> 
               <tr>               		
                 <td><input type="text" class="form-control" ng-model="newShippingRow.mpn"      required></td>
                 <td><input type="text" class="form-control" ng-model="newShippingRow.min_qty"  required></td>
                 <td><input type="text" class="form-control" ng-model="newShippingRow.max_qty"  required></td>
                 <td>
                   <select name="repeatSelect" class="form-control shipping-type" 
                           ng-model="newShippingRow.shippingType" 
                           ng-options="item.key for item in shippingType"                   
                           ng-change="onSelectChange()"
                           required>                      
                   </select>
                </td>
                 <td><input type="text" class="form-control shipping-rate" ng-model="newShippingRow.shipping" required></td>  
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
    <script type="text/javascript" src="js/appShipping2.js"></script>
    
    <script type="text/javascript">
       /* $('.shipping-type').change(function()
        {
          $('.shipping-rate').val($(this).val());
        })*/
    </script>

</body>
</html>

