(function(){
	var app = angular.module('SalesCalculatorModule',['ui.bootstrap']);
   
    app.controller('CalculatorCrtl', function($scope, $http, $sce, $timeout)
    { 
      
       $scope.active_url = ''; // 'http://integra.eocenterprise.com/'; //http://localhost/eoc_api/integra/'
       $scope.activeEditID = -1;
       $scope.totalSalesPrice = 0;
       $scope.totalWeight = 0;
       $scope.totalShipping = 0;
       $scope.totalQuantity = 0;
       $scope.totalProfit = 0;
       $scope.totalCoreCharge = 0;
       $scope.totalCost = 0;

       $scope.rows = [{'id':1}];
       $scope.counter = 1;      
       $scope.items =  [{'sku':'', 'quantity':'', 'profit_percentage': '', 'cost': '', 'core_charge': '', 'shipping': '', 'sales_price': '', 'weight':''}];

       $scope.profitPercentageTable = {};
       $scope.shippingRateTable = {};
       $scope.predefinedRateTable  = {};
      
       $scope.addRow = function()
       {
          $scope.counter++;  
          $scope.items.push( {'sku':'', 'quantity':'', 'profit_percentage': '', 'cost': '', 'core_charge': '', 'shipping': '', 'sales_price': '', 'weight':''});
       }

       $scope.removeRow = function(id)
       {
            
            var index = -1;		
      			var rowArr = eval( $scope.rows );
      			for( var i = 0; i < rowArr.length; i++ ) {
      				if( rowArr[i].id == id ) {
      					index = i;
      					break;
      				}
      			}
    			if( index === -1 ) {
    				//alert( "Something gone wrong" );
    			}
    			$scope.items.splice( index, 1 );	
       }
     
       $scope.loadRates = function() {
           //load both profit percentage and shipping rate
           $http.get($scope.active_url+'e_sales_price_calculator_query.php?action=getProfitPercentage')
                   .success(function(data){                   
                     $scope.profitPercentageTable = data['profit'];
                     $scope.shippingRateTable = data['shipping'];
                     $scope.predefinedRateTable = data['predefined'];
                   });

       }



       $scope.setActiveEditID =  function(rid) {
                $scope.activeEditID = rid;
       }

       $scope.deleteProfit = function(rid){

           this.delData = {}; 
           this.delData =  {
                       'id'        : rid,  
                       'action'    : 'deleteProfit',                                    
                     };

           $scope.activeEditID = -1;
           $http.post($scope.active_url+'e_sales_price_table_query.php', this.delData)
                   .success(function(data){  
                       $scope.loadRates();                     
                   });
       }
        $scope.deleteShipping = function(rid){

           this.delData = {}; 
           this.delData =  {
                       'id'        : rid,  
                       'action'    : 'deleteShipping',                                    
                     };

           $scope.activeEditID = -1;
           $http.post($scope.active_url+'e_sales_price_table_query.php', this.delData)
                   .success(function(data){  
                       $scope.loadRates();                     
                   });
       }
     
       $scope.addNewProfit = function(data)
       {
           this.newData = {}; 
           this.newData =  {
                       'id'        : data.id,
                       'profit'    : data.profit,
                       'min_cost'  : data.min_cost,
                       'max_cost'  : data.max_cost,
                       'table'     : data.table,  
                       'action'    : 'addNewProfit',                   
                     };

           $scope.activeEditID = -1;
           $http.post($scope.active_url+'e_sales_price_table_query.php', this.newData)
                   .success(function(data){  
                       $scope.loadRates();
                       $scope.newProfit = 1;
                       $scope.newData = {}
                   });
       }

       $scope.addNewShipping = function(data)
       {
           this.newData = {}; 
           this.newData =  {
                       'id'        : data.id,
                       'weight_from'    : data.weight_from,
                       'weight_to'  : data.weight_to,
                       'rate'  : data.rate,                       
                       'action'    : 'addNewShippingRate',                   
                     };

           $scope.activeEditID = -1;
           $http.post($scope.active_url+'e_sales_price_table_query.php', this.newData)
                   .success(function(data){  
                       $scope.loadRates();
                       $scope.newShipping = 1;
                       $scope.newData = {}
                   });
       }


       $scope.updateActiveID = function(data)
       {
        
           this.updateData = {}; 
           this.updateData =  {
                       'id'        : data.id,
                       'profit'    : data.profit,
                       'min_cost'  : data.min_cost,
                       'max_cost'  : data.max_cost,
                       'table'     : data.table,  
                       'action'    : data.action,                   
                     };

           $scope.activeEditID = -1;
           $http.post($scope.active_url+'e_sales_price_table_query.php', this.updateData)
                   .success(function(data){                   
                      //$scope.profitPercentageTable = data;
                   });

       
       }
       
       $scope.updateActiveIDForShipping = function(data)
       {
        
           this.updateData = {}; 
           this.updateData =  {
                       'id'        : data.id,
                       'weight_from'    : data.weight_from,
                       'weight_to'    : data.weight_to,
                       'rate'      : data.rate,                       
                       'table'     : data.table,  
                       'action'    : data.action,                   
                     };

           $scope.activeEditID = -1;
           $http.post($scope.active_url+'e_sales_price_table_query.php', this.updateData)
                   .success(function(data){                   
                      //$scope.profitPercentageTable = data;
                     
                   });

       
       }

      $scope.calculateAll = function(data)
      { 
         var total_quantity = 0;
         var total_profit   = 0;
         var total_cost     = 0;
         var total_weight   = 0;
         var total_shipping = 0;
         var total_core_charge = 0;    
         var total_sales_price = 0;     
         
         angular.forEach(data,function(obj)
         {
             var shipping_index = '';
             var sales_price = 0;
             var core_charge  = 0;
             var cost    = 0;                 
             var weight  = 0;
             var shipping_rate = 0;
             var sales_price = 0;                  

             var quantity = !obj.quantity ? 1 : obj.quantity;
             var request = 'ssf_';
             if (obj.sku.indexOf('.') == -1) 
             {
              request = 'imc_'; 
             }

             $http.get("http://integra.eocenterprise.com/"+request+"ajax.php?sku="+obj.sku)
              .success(function(result) { 

                 cost    = result.price * quantity;                 
                 weight  = result.weight * quantity;

                 if(result.core) {core_charge = result.core; }

                  //search if SKU/MPN is in predifed Tables
                   var keepGoing = true;
                   var is_predefined = 0;
                   angular.forEach($scope.predefinedRateTable, function(element)
                    {
                      if( keepGoing ) {
                       if( element.mpn == obj.sku  && ( element.min_qty <= quantity && element.max_qty >= quantity ) )
                       {
                         is_predefined =  1;

                         shipping_rate =  element.shipping;
                         profit =  element.profit;

                         keepGoing = false;                        
                       }
                      }
                   });                  

                 if( is_predefined == 0 )
                 {
                  //search shipping rate 
                   var keepGoing = true;               
                   angular.forEach($scope.shippingRateTable, function(element)
                    {
                      if( keepGoing ) {
                       if( element.weight_from <= weight &&  element.weight_to >= weight )
                       {
                         shipping_rate =  element.rate;
                         keepGoing = false;                        
                       }
                      }
                   });

                  //search percentage profit rate             
                   var keepGoing = true;
                   var profit = 0;
                   angular.forEach($scope.profitPercentageTable, function(element)
                    {
                      if( keepGoing ) {
                       if( element.min_cost < cost && element.max_cost > cost)
                       {
                         profit =  element.profit;
                         keepGoing = false;
                       }
                      }
                   });

                  if(request == 'imc_')
                  {
                    // If total cost over 50$ shipping is free
                     if( cost > 50 )
                     {
                       shipping_rate = 0;
                     }

                  } else if(request == 'ssf_') {
                   // If total cost over 100$ shipping is free
                     if(cost > 100 ) 
                     {
                       shipping_rate = 0;
                     }
                  } 

                }//end if not predefined  

                console.log(obj.sku +' = '+ is_predefined);
                console.log('shipping ='+shipping_rate);
                console.log('profit =' + profit);
                
                console.log('core_charge =' + core_charge);              
                var n1 = ( (profit/100) * (cost) + (cost) + (core_charge*quantity) + shipping_rate);
                sales_price = (n1 / 0.936);  

                index = obj.ctr;
                $scope.items[index].quantity = quantity;          
                $scope.items[index].cost = parseFloat(cost);
                $scope.items[index].profit_percentage = profit;
                $scope.items[index].shipping = shipping_rate;
                $scope.items[index].weight = weight;
                $scope.items[index].core_charge = core_charge;
                $scope.items[index].sales_price = sales_price.toFixed(2); 

               total_quantity =  parseInt(total_quantity) + parseInt(quantity);             
               total_cost     =  parseFloat(total_cost) + parseFloat(cost);
               total_weight   =  parseFloat(total_weight) + parseFloat(weight);              
               total_core_charge = parseFloat(total_core_charge) + parseFloat(core_charge);     
             
                var total_profit = 0;
                var total_shipping_rate = 0;      
               

                if( is_predefined == 1)
                {
                    total_shipping_rate = shipping_rate;
                    total_profit = profit;
                    console.log('IS PREF');
                }
                else
                {   
                  //search shipping rate 
                   var keepGoing = true; 
                   angular.forEach($scope.shippingRateTable, function(element)
                   {
                      if( keepGoing ) {
                       if( element.weight_from <= total_weight &&  element.weight_to >= total_weight )
                       {
                         total_shipping_rate =  element.rate;
                         keepGoing = false;                        
                       }
                      }
                   });

                  //search percentage profit rate             
                   var keepGoing = true;                  
                   angular.forEach($scope.profitPercentageTable, function(element)
                    {
                      if( keepGoing ) {
                       if( element.min_cost < total_cost && element.max_cost > total_cost)
                       {
                         total_profit =  element.profit;
                         keepGoing = false;
                       }
                      }
                   });


                  if(request == 'imc_')
                  {
                    // If total cost over 50$ shipping is free
                     if( total_cost > 50 )
                     {
                       total_shipping_rate = 0;
                     }

                  } else if(request == 'ssf_') {
                   // If total cost over 100$ shipping is free
                     if(total_cost > 100 ) 
                     {
                       total_shipping_rate = 0;
                     }
                  } 

               } //end else 

                console.log('total_core_charge:' + total_core_charge);
                var n1 = ( (total_profit/100) * (total_cost) + (total_cost) + (total_core_charge*total_quantity) + total_shipping_rate);
                total_sales_price = (n1 / 0.936);

               $scope.totalQuantity = total_quantity;
               $scope.totalCost = total_cost;
               $scope.totalProfit = total_profit;
               $scope.totalShipping = total_shipping_rate;   
               $scope.totalWeight = total_weight;                
               $scope.totalSalesPrice = total_sales_price;


             }); //end http.get();               

         
        });

         

      }

      $scope.getShippingRate = function(weight)
      {
                
          angular.forEach($scope.shippingRateTable, function(element)
          {
             if( element.weight_from <= weight &&  element.weight_to >= weight )
             {
               console.log(element.rate);
               return element.rate;

             }

         });         

     }


      $scope.reloadPage = function(){window.location.reload();}

    

      $scope.calculateSalesPrice = function(sku, quantity, index)
      {     
         $scope.loading = true;  
         
         //check if sku is_kit = 1
   
          var request = 'ssf_';
          if (sku.indexOf('.') == -1) {
            request = 'imc_';
          }

          quantity = !quantity ? 1 : quantity;
             
           $http.get("http://integra.eocenterprise.com/"+request+"ajax.php?sku="+sku)
              .success(function(result) {   
                  
                  $scope.loading = false;  
                  
                  var core_charge  = 0;
                  var cost    = result.price;                 
                  var weight  = result.weight * quantity;
                  var profit  = 0;
                  var shipping_index = '';
                  var sales_price = 0;

                   if(result.core_charge)
                   {
                     core_charge = result.core_charge;
                   }  


                   this.responseData = {}; 
                   this.responseData =  {
                               'cost'      : cost * quantity,
                               'action'    : 'getProfitPercentageForCost',                   
                             };

                   $scope.activeEditID = -1;
                   $http.post($scope.active_url+'e_sales_price_table_query.php', this.responseData)
                   .success(function(data) {                   
                       profit = data.profit;
                    
                    //another http request for db driven shipping rate
                    this.responseData =  {
                               'weight'    : weight,
                               'action'    : 'getShippingRateForWeight',                   
                             };

                         $scope.activeEditID = -1;
                         $http.post($scope.active_url+'e_sales_price_table_query.php', this.responseData)
                         .success(function(data){   

                             shipping_rate = data.rate;

                            total_cost = cost * quantity;

                            if(request == 'imc_')
                            {
                              // If total cost over 50$ shipping is free
                               if( total_cost > 50 )
                               {
                                shipping_rate = 0;
                               }
                            }
                            else if(request == 'ssf_')
                            {
                              // If total cost over 100$ shipping is free
                               if(total_cost > 100 ) 
                               {
                                shipping_rate = 0;
                               }
                            }
                           
                           console.log('request:'+request+' cost: '+  total_cost+' shipping_rate:'+ shipping_rate);


                             $scope.items[index].quantity = quantity;
                             $scope.items[index].cost = parseFloat(cost);
                             $scope.items[index].profit_percentage = profit;
                             $scope.items[index].shipping = shipping_rate;
                             $scope.items[index].weight = weight;
                             $scope.items[index].core_charge = core_charge;                            
                             
                             $scope.totalWeight   = $scope.totalWeight + weight;
                             $scope.totalQuantity =  parseInt($scope.totalQuantity) + parseInt(quantity);                            
                             $scope.totalCost     = parseFloat($scope.totalCost) + parseFloat(total_cost);
                             console.log('$scope.totalCost'+$scope.totalCost);

                              



                              //another http request for db driven shipping rate
                          this.responseData =  {
                               'weight'    : $scope.totalWeight,
                               'action'    : 'getShippingRateForWeight',                   
                             };

                         $scope.activeEditID = -1;
                         $http.post($scope.active_url+'e_sales_price_table_query.php', this.responseData)
                         .success(function(data){                   
                             total_shipping_rate = data.rate;

                              if(request == 'imc_')
                              {
                                // If total cost over 50$ shipping is free
                                 if( $scope.totalCost > 50 )
                                 {
                                  total_shipping_rate = 0;
                                 }
                              }
                              else if(request == 'ssf_')
                              {
                                // If total cost over 100$ shipping is free
                                 if( $scope.totalCost > 100 ) 
                                 {
                                   total_shipping_rate = 0;
                                 }
                              }



                        var n1 = ( (profit/100) * (cost*quantity) + (cost*quantity) + (core_charge*quantity) + shipping_rate);
                        sales_price = (n1 / 0.936);                      
                      
                        $scope.totalShipping = total_shipping_rate;
                        $scope.items[index].sales_price = sales_price.toFixed(2);

                      
                           this.responseData = {}; 
                           this.responseData =  {
                                       'cost'      : $scope.totalCost,
                                       'action'    : 'getProfitPercentageForCost',                   
                                     };

                           $scope.activeEditID = -1;
                           $http.post($scope.active_url+'e_sales_price_table_query.php', this.responseData)
                           .success(function(data) {                   
                               $scope.totalProfit = data.profit;

                               //var n1_total = ( ($scope.totalProfit/100) * ($scope.totalCost*$scope.totalQuantity) + ($scope.totalCost*$scope.totalQuantity) + (core_charge*$scope.totalQuantity) +  $scope.totalShipping);
                                var n1_total = ( ($scope.totalProfit/100) * ($scope.totalCost) + ($scope.totalCost) + (core_charge*$scope.totalQuantity) +  $scope.totalShipping);
                                
                                total_sales_price = (n1_total / 0.936);     

                                 $scope.totalSalesPrice = total_sales_price;


                            })

                        })

                     })

                  })               
                  
             }) 

       }     
   
      $scope.loadRates();

    });



})();