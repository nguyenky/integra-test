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
	  <link rel="stylesheet" type="text/css" href="css/jquery.fileupload.css">

      
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
         
      </style>
    </head>
 
 <body ng-app="SalesCalculatorModule">  
 

	   <h2 align="center"> eBay Sales Price Calcutator </h2> <br>
         
		<div class="row" id="main-container" ng-controller="CalculatorCrtl">
		

			   <div class="col-md-12" ng-controller="CalculatorCrtl">
				  
				<div class="row"> 
				 <div class="col-md-6">
				   <button class="btn btn-default" type="button" ng-click="addRow()">
		            <i class="fa fa-plus"></i>
		            <span>Add More SKUs / Part #</span>
		          </button>
	             </div>

		          <div class="col-md-6 pull-right">
	                  <div class="panel panel-default">
						   <div class="panel-body">
						    <h4 class="text-primary">
	                         Total Weight:   <b>{{ totalWeight.toFixed(2) }} </b> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	                         Total Shipping: <b>{{ totalShipping.toFixed(2) }} </b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						     Total Sales:    <b>{{ totalSalesPrice.toFixed(2) }} </b>
						    </h4>
						     
						   </div>
						</div>

	                  
		          </div>
		        </div><br>
			
				  <table class="table">
				    <tr>
				   	  <td width="2%"></td>
				   	  <td width="15%">SKU / Part #</td>
				   	  <td width="10%">Quantity</td>
				   	  <td width="7%"><a href="" data-toggle="modal" data-target="#profitModal">Profit % </a></td>
				   	  <td width="7%">Cost </td>
				   	  <td width="7%">Core Charge </td>
				   	  <td width="7%">Weight </td>
				   	  <td width="7%"><a href="" data-toggle="modal" data-target="#shippingModal">Shipping</a> </td>
				   	  <td class="text-danger"><h6><b>Sales Price</b> <small>(PROFIT % * (COST*QTY) + (COST*QTY) + (Core Charge*QTY) + SHIPPING ) / 0.936</small></h6>
				   	  <td width="5%"></td>
				   	  <td width="4%"></td>
				   	  </td>
				   </tr>				
					
				
					<tr ng-repeat="item in items">
					      <td>{{ $index + 1 }}.</td> 
						  <td><input type="text" class="form-control" name="part_number"       ng-model="item.sku" 		         /></td>
					   	  <td><input type="text" class="form-control" name="quantity"          ng-model="item.quantity"          /></td>
					   	  <td><input type="text" class="form-control" name="profit_percentage" ng-model="item.profit_percentage" /></td>
					   	  <td><input type="text" class="form-control" name="cost"              ng-model="item.cost" 			 /></td>
					   	  <td><input type="text" class="form-control" name="core_charge" 	   ng-model="item.core_charge" 		 /></td>
					   	  <td><input type="text" class="form-control" name="weight" 		   ng-model="item.weight" 		 /></td>
					   	  <td><input type="text" class="form-control" name="shipping" 		   ng-model="item.shipping" 		 /></td>
					   	  <td><input type="text" class="form-control" name="sales_price"  	   ng-model="item.sales_price" 		 /></td>
						  <td>
						     <button class="btn btn-primary btn-sm" type="button" ng-click="calculateSalesPrice(item.sku,item.quantity, $index)">				           
					            <span>  Calculate</span>
					          </button>
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
					         		<th>Profit %</th>
					         		<th>Min Cost</th>
					         		<th>Max Cost</th>
					         		<th width="5%"></th>
					         	</tr>
                              </thead>
                             
                              <tbody  ng-repeat="row in profitPercentageTable">
					         	<tr id="tr_{{row.id}}" ng-hide="activeEditID === row.id">
					         		<td>{{row.profit}}</td>
					         		<td>{{row.min_cost}}</td>
					         		<td>{{row.max_cost}}</td>
					         		<td><a href="" ng-click="setActiveEditID(row.id)"><i class="fa fa-pencil"></i></a></td>
					         	</tr>

					         	<tr ng-show="activeEditID === row.id" ng-if="activeEditID === row.id">
					         		<td><input type="text" ng-model="row.profit"></td>
					         		<td><input type="text" ng-model="row.min_cost"></td>
					         		<td><input type="text" ng-model="row.max_cost"></td>
					         		<td><a href="" ng-click="updateActiveID(row)"><i class="fa fa-save"></i></a></td>
					         	</tr>

					         </tbody>

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
					         		<th>Weight</th>
					         		<th>Rate</th>
					         		
					         		<th width="5%"></th>
					         	</tr>
                              </thead>
                             
                              <tbody  ng-repeat="shipping in shippingRateTable">
					         	<tr id="tr_{{shipping.id}}" ng-hide="activeEditID === shipping.id">
					         		<td>{{shipping.weight}}</td>					         		
					         		<td>{{shipping.rate}}</td>
					         		<td><a href="" ng-click="setActiveEditID(shipping.id)"><i class="fa fa-pencil"></i></a></td>
					         	</tr>

					         	<tr ng-show="activeEditID === shipping.id" ng-if="activeEditID === shipping.id">
					         		<td><input type="text" ng-model="shipping.weight"></td>
					         		<td><input type="text" ng-model="shipping.rate"></td>					         		
					         		<td><a href="" ng-click="updateActiveIDForShipping(shipping)"><i class="fa fa-save"></i></a></td>
					         	</tr>

					         </tbody>

					         </table>

				      </div>
				      <div class="modal-footer">
				       
				      </div>
				    </div>
				  </div>
				</div>
			   <!-- End Shippin Modal-->


		</div>	
 
 <script type="text/javascript" src="js/jquery.min.js"></script>
 <script type="text/javascript" src="js/jquery-ui.min.js"></script>
 <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script> 
 <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.15/angular.min.js"></script>
 <script type="text/javascript" src="js/ui-bootstrap-tpls-0.12.1.min.js"></script>
 <script type="text/javascript" src="js/appSalesCalculator.js"></script>

</body>
</html>

