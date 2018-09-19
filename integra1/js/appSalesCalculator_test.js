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
     
     
      function checkIfPredefined(sku,quantity)
      {

        var keepGoing = true;
        var is_predefined = 0;
        var profit = 0; 
        var shipping_rate = 0;
        angular.forEach($scope.predefinedRateTable, function(element)
        {
          if( keepGoing ) {
           if( element.mpn == sku  && ( element.min_qty <= quantity && element.max_qty >= quantity ) )
           {
             is_predefined =  1;

             shipping_rate =  element.shipping;
             profit =  element.profit;

             keepGoing = false;                        
           }
          }
        });  

        return [{'is_predefined':is_predefined,'profit':profit,'shipping_rate':shipping_rate}];

      }
      
      function getShippingRate(weight,cost,request)
      {
          
          var keepGoing = true; 
          var shipping_rate;              
           
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

           return shipping_rate;

      }

      function getProfitPercentageRate(cost)
      {
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
          return profit;
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
               var cost     = 0 
               var weight   = 0
               var core_charge = 0
               var shipping_rate = 0

               var qty = !obj.quantity ? 1 : obj.quantity;                   
               this.postData =  {'sku' : obj.sku, 'quantity': qty, 'action' : 'getKitComponents', 'index': obj.ctr};

               $http.post($scope.active_url+'e_sales_price_table_query.php', this.postData)
               .success(function(response)
                { 
                   var quantity = response.quantity
                   var index    = response.index 
                   var main_sku = response.sku
                   var main_request = response.main_request
                   
                   angular.forEach(response.components,function(obj)
                   { 
                      var kit_qty = obj.kit_quantity
                      var request = obj.content.request_type                        
                            
                         //console.log(result)
                       cost  = parseFloat(cost) + (obj.content.item.price * (quantity*kit_qty) );
                       weight  = parseFloat(weight) + (obj.content.item.weight * (quantity*kit_qty) );                      
                      
                       if(obj.content.item.core) {core = obj.content.item.core;}
                       else{core=0}
                       core_charge = parseFloat(core_charge) + core;

                      console.log('SKU = ' + obj.skus + ', KIT QTY = ' + kit_qty + ', Cost = '+ obj.content.item.price +', Weight =' + obj.content.item.weight + ', API='+request );
                      

                   }); //end second foreach  

                      var is_predefined = 0;
                      //check if main_sku is predefined
                      pref = checkIfPredefined(main_sku,quantity); //returns array object
                      angular.forEach(pref,function(itm){
                           shipping_rate = itm.shipping_rate;
                           profit = itm.profit;
                           is_predefined = itm.is_predefined;                           
                      });                     

                      if( is_predefined == 0 )
                      {                       
                        shipping_rate = getShippingRate(weight,cost,main_request);
                        profit = getProfitPercentageRate(cost);   
                      }//end if not predefined                   
                     
                     console.log('is_predefined:'+is_predefined)
                   

                    var n1 = ( (profit/100) * (cost) + (cost) + (core_charge*quantity) + shipping_rate);
                    sales_price = (n1 / 0.936);  

                   $scope.items[index].quantity = quantity;
                   $scope.items[index].cost = parseFloat(cost).toFixed(2);  
                   $scope.items[index].core_charge = parseFloat(core_charge).toFixed(2);
                   $scope.items[index].weight = parseFloat(weight).toFixed(2);  
                   $scope.items[index].profit_percentage = profit;
                   $scope.items[index].shipping = shipping_rate;  
                   $scope.items[index].sales_price = sales_price.toFixed(2);                   

                   total_quantity = parseInt(total_quantity) + quantity;
                   total_cost = parseFloat(total_cost) + cost;
                   total_weight = parseFloat(total_weight) + weight;
                   total_core_charge = parseFloat(total_core_charge)+core_charge;                 

                  var total_profit = 0;
                  var total_shipping_rate = 0;   
                 
                  if( is_predefined == 1)
                  {
                      total_shipping_rate = shipping_rate;
                      total_profit = profit;                      
                  }
                  else
                  {   
                    total_shipping_rate = getShippingRate(total_weight,total_cost,main_request);
                    total_profit = getProfitPercentageRate(total_cost); 
                  } //end else 

                   var n1 = ( (total_profit/100) * (total_cost) + (total_cost) + (total_core_charge*total_quantity) + total_shipping_rate);
                   total_sales_price = (n1 / 0.936);                    
                  
                   $scope.totalQuantity = total_quantity;
                   $scope.totalCost =  total_cost;    
                   $scope.totalCoreCharge = total_core_charge;
                   $scope.totalWeight = total_weight;
                   $scope.totalProfit = total_profit;
                   $scope.totalShipping = total_shipping_rate;
                   $scope.totalSalesPrice = total_sales_price;

               }) //end getKitComponents
          
          })
     
      }

      function getIPOdetails(data, quantity, index)
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
             var profit = 0;      
             var request = 'ssf_';
             var sku = obj.sku.toString();

             if (sku.indexOf('.') == -1) {request = 'imc_'; }   

              $http.get("http://integra.eocenterprise.com/"+request+"ajax.php?sku="+obj.sku)
                   .success(function(result) {
                    //console.log(result)
                      
                      cost    = result.price * quantity;                 
                      weight  = result.weight * quantity;

                      if(result.core) {core_charge = result.core; }
                      
                        //search if SKU/MPN is in predifed Tables                  
                         pref = checkIfPredefined(obj, quantity); //returns array object
                         angular.forEach(pref,function(itm){
                              profit = itm.profit;
                              is_predefined = itm.is_predefined;
                              //console.log(itm)
                          });                     

                       if( is_predefined == 0 )
                       {                       
                         shipping_rate = getShippingRate(weight,cost,request);
                         profit = getProfitPercentageRate(cost);   
                       }//end if not predefined                   

                
                var n1 = ( (profit/100) * (cost) + (cost) + (core_charge*quantity) + shipping_rate);
                sales_price = (n1 / 0.936);  
              
                  /*$scope.items[index].quantity = quantity;          
                  $scope.items[index].cost = parseFloat(cost);
                  $scope.items[index].profit_percentage = profit;
                  $scope.items[index].shipping = shipping_rate;
                  $scope.items[index].weight = weight;
                  $scope.items[index].core_charge = core_charge;
                  $scope.items[index].sales_price = sales_price.toFixed(2); 
                  console.log('INDEX HERE IS '+ index);*/

                 total_quantity =  parseInt(quantity);  //parseInt(total_quantity) +       
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
                    total_shipping_rate = getShippingRate(total_weight,total_cost, request);
                    total_profit = getProfitPercentageRate(total_cost); 
                  } //end else 

                 
                 var n1 = ( (total_profit/100) * (total_cost) + (total_cost) + (total_core_charge*total_quantity) + total_shipping_rate);
                 total_sales_price = (n1 / 0.936);    

                 $scope.items[index].quantity = total_quantity;          
                 $scope.items[index].cost = parseFloat(total_cost).toFixed(2); 
                 $scope.items[index].profit_percentage = total_profit;
                 $scope.items[index].shipping = total_shipping_rate;
                 $scope.items[index].weight = total_weight.toFixed(2); 
                 $scope.items[index].core_charge = total_core_charge;
                 $scope.items[index].sales_price = total_sales_price.toFixed(2); 
               
                 $scope.totalQuantity = total_quantity;
                 $scope.totalCost =  total_cost;
                 $scope.totalProfit = total_profit;
                 $scope.totalShipping =  total_shipping_rate;   
                 $scope.totalWeight =  total_weight;                
                 $scope.totalSalesPrice = total_sales_price;
             

              })//end ajax call    
             
           })//end forEach loop    
           

      }//end getIPOdetails
    

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
   
      $scope.loadRates();

    });



})();