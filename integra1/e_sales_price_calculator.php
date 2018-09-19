<?php
 
	require_once('system/config.php');
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
     <title>eBay Sales Price Calcutor</title>
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
         } 
         .wrapper{
         	margin-top: 100px !important;
         }
      </style>
    </head>
 
 <body ng-app="SalesCalculatorModule">  
 
       
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
	        <div id="navbar" class="navbar-collapse collapse">	
	        </div><!--/.nav-collapse -->
	      </div>
	    </nav>

	 <div class="wrapper">    
	   <h2 align="center"> eBay Sales Price Calculator </h2> <br>
         
		<div class="row" id="main-container" ng-controller="CalculatorCrtl">
		

			   <div class="col-md-12">
				  
				<div class="row"> 
				 <div class="col-md-5">
				   <button class="btn btn-default" type="button" ng-click="addRow()">
		            <i class="fa fa-plus"></i>
		            <span>Add More SKUs / Part #</span>
		          </button>
	             </div>

		          <div class="col-md-7 pull-right">
	                  <div class="panel panel-default">
						   <div class="panel-body ">
						    <h4 class="text-primary">
						      Quantity:  <b>{{ totalQuantity }} </b> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						      Total Sales:    <b>{{ totalSalesPrice.toFixed(2) }} </b>						    
						    </h4>
						     
						   </div>
						</div>

	                  
		          </div>
		        </div><br>
			
				  <table class="table">
				    <tr>
				   	  <td width="2%"></td>
				   	  <td width="30%">SKU / Part #</td>
				   	  <td width="10%">Quantity</td>	
				   	  <td width="5%">

				   	  	     <button class="btn btn-primary btn-sm" type="button" ng-click="calculateAll(items)">				           
					             <span>  Calculate</span>
					         </button>

				   	  </td>
				   	  <td width="4%">
				   	  	  <button class="btn btn-warning btn-xs" type="button" ng-click="reloadPage()">				           
					             <span>Clear</span>
					       </button>
				   	  </td>
				   	  </td>
				   </tr>				
					
				
					<tr ng-repeat="item in items">
					      <td ng-init="item.ctr = ($index)">{{ $index + 1 }}.</td> 
						  <td><input type="text" class="form-control" name="part_number"       ng-model="item.sku" 		         /></td>
					   	  <td><input type="text" class="form-control" name="quantity"          ng-model="item.quantity"          /></td>					   
						  <td>
						     
		                 </td>
						  <td><a href="" ng-show="$index"  title="Delete" class="" ng-click="removeRow(row.id)"><i class="fa fa-trash-o"></i></a></td>
					</tr>
					
				 </table>

			  <!-- Profit  Modal -->
			   <div class="modal fade" id="profitModal" tabindex="-1" role="dialog" aria-labelledby="profitModalLabel" aria-hidden="true">
				  <div class="modal-dialog modal-sm">
				    <div class="modal-content">
				      <div class="modal-header">
				        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				        <h4 class="modal-title" id="profitModalLabel">Profit Percentage Table</h4>
				      </div>
				      <div class="modal-body">

					         <table class="table table-hover">
					           <thead>	
					         	<tr>
					         		<th width="30%">Profit %</th>
					         		<th width="30%">Min Cost</th>
					         		<th width="30%">Max Cost</th>
					         		<th width="10%"></th>
					         	</tr>
                              </thead>
                             
                              <tbody  ng-repeat="row in profitPercentageTable">
					         	<tr id="tr_{{row.id}}" ng-hide="activeEditID === row.id">
					         		<td>{{row.profit}}</td>
					         		<td>{{row.min_cost}}</td>
					         		<td>{{row.max_cost}}</td>
					         		<td>
					         		<a href="" ng-click="setActiveEditID(row.id)"><i class="fa fa-pencil"></i></a>
					         		<a href="" ng-click="deleteProfit(row.id)"><i class="fa fa-trash"></i></a>
					         		</td>
					         	</tr>

					         	<tr ng-show="activeEditID === row.id" ng-if="activeEditID === row.id">
					         		<td><input type="text" ng-model="row.profit" size="12"></td>
					         		<td><input type="text" ng-model="row.min_cost" size="12"></td>
					         		<td><input type="text" ng-model="row.max_cost" size="12"></td>
					         		<td><a href="" ng-click="updateActiveID(row)"><i class="fa fa-save"></i></a></td>
					         	</tr>
					         </tbody>
					         
					         
					         <tfoot>
					          <tr ng-init='newProfit = 1' ng-show="newProfit">
					          	<td colspan="4" align="right"><a href="" ng-click="newProfit = 0"><i class="fa fa-plus"></i></a></td>
					          </tr>
					          <tr ng-hide="newProfit">
					           <td><input type="text" ng-model="newRow.profit" size="12"></td>
					           <td><input type="text" ng-model="newRow.min_cost" size="12"></td>
					           <td><input type="text" ng-model="newRow.max_cost" size="12"></td>	
					           <td><a href="" ng-click="addNewProfit(newRow)"><i class="fa fa-save"></i></a></td>
					          </tr>
					         </tfoot>

					         </table>



				      </div>
				      <div class="modal-footer">
				       
				      </div>
				    </div>
				  </div>
				</div>
			   <!-- End Profit Modal-->


			    <!-- Shipping  Modal -->
			   <div class="modal fade" id="shippingModal" tabindex="-1" role="dialog" aria-labelledby="shippingModalLabel" aria-hidden="true">
				  <div class="modal-dialog modal-sm">
				    <div class="modal-content">
				      <div class="modal-header">
				        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				        <h4 class="modal-title" id="shippingModalLabel">Shipping Rate Table</h4>
				      </div>
				      <div class="modal-body">

					         <table class="table table-hover">
					           <thead>						         
					         	<tr>					         		
					         		<th> Min Weight</th>
					         		<th> Max Weight</th>
					         		<th>Rate</th>					         		
					         		<th width="10%"></th>
					         	</tr>
                              </thead>
                             
                              <tbody  ng-repeat="shipping in shippingRateTable">
					         	<tr id="tr_{{shipping.id}}" ng-hide="activeEditID === shipping.id">
					         		<td>{{shipping.weight_from}}</td>	
					         		<td>{{shipping.weight_to}}</td>					         		
					         		<td>{{shipping.rate}}</td>
					         		<td>
					         		<a href="" ng-click="setActiveEditID(shipping.id)"><i class="fa fa-pencil"></i></a>
					         		<a href="" ng-click="deleteShipping(shipping.id)"><i class="fa fa-trash"></i></a>
					         		</td>
					         	</tr>

					         	<tr ng-show="activeEditID === shipping.id" ng-if="activeEditID === shipping.id">
					         		<td><input type="text" ng-model="shipping.weight_from" size="12"></td>
					         		<td><input type="text" ng-model="shipping.weight_to" size="12"></td>
					         		<td><input type="text" ng-model="shipping.rate" size="12"></td>					         		
					         		<td><a href="" ng-click="updateActiveIDForShipping(shipping)"><i class="fa fa-save"></i></a></td>
					         	</tr>

					         </tbody>

					          <tfoot>
					          <tr ng-init='newShipping = 1' ng-show="newShipping">
					          	<td colspan="4" align="right"><a href="" ng-click="newShipping = 0"><i class="fa fa-plus"></i></a></td>
					          </tr>
					          <tr ng-hide="newShipping">
					           <td><input type="text" ng-model="newShippingRow.weight_from" size="12"></td>
					           <td><input type="text" ng-model="newShippingRow.weight_to" size="12"></td>
					           <td><input type="text" ng-model="newShippingRow.rate" size="12"></td>	
					           <td><a href="" ng-click="addNewShipping(newShippingRow)"><i class="fa fa-save"></i></a></td>
					          </tr>
					         </tfoot>


					         </table>

				      </div>
				      <div class="modal-footer">
				       
				      </div>
				    </div>
				  </div>
				</div>
			   <!-- End Shippin Modal-->


		</div>	
     </div>

 
 <script type="text/javascript" src="js/jquery.min.js"></script>
 <script type="text/javascript" src="js/jquery-ui.min.js"></script>
 <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script> 
 <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.15/angular.min.js"></script>
 <script type="text/javascript" src="js/ui-bootstrap-tpls-0.12.1.min.js"></script>
 <script type="text/javascript" src="js/appSalesCalculator_kit.js"></script>

</body>
</html>

