(function(){
	var app = angular.module('SalesShippingModule',[]);

	app.controller('ShippingCrtl', function($scope, $http, $sce, $timeout)
    { 
     
      $scope.active_url = ''
      $scope.shippings = {}
      $scope.newShippingRow = {}
      $scope.newShipping = 0
    
      $scope.loadShippingRates = function()
      {
         
        this.postData =  {'action'    : 'getDefinedRates'};           
        $http.post($scope.active_url+'e_sales_price_table_query.php', this.postData)
               .success(function(data)
               {   
                 $scope.shippings = data;
               });
      } 

      $scope.activeEditID = -1
      $scope.setActiveEditID =  function(rid) 
      {
        $scope.activeEditID = rid;
      }
      
      $scope.onSelectChange = function()
      {       
        $scope.newShippingRow.shipping = $scope.newShippingRow.shippingType.value
        $scope.newShippingRow.type = $scope.newShippingRow.shippingType.key
      }  
      
      $scope.newShippingRow = {}
      
      $scope.shippingType = [
      {key: 'First Class Mail', value: ''},
      {key: 'Flat Rate Padded Envelope', value: '5.50'},
      {key: 'Regional Rate Box A - Top Loader', value: '9.00'},
      {key: 'Regional Rate Box A - Side Loader', value: '9.00'},
      {key: 'Regional Rate Box B - Top Loader', value: '20.00'},
      {key: 'Regional Rate Box B - Side Loader', value: '20.00'},
      {key: 'Regional Rate Box C - Top Loader', value: '20.00'},
      {key: 'Medium Flat Rate Box - Top Loader', value: '11.50'},
      {key: 'Medium Flat Rate Box - Side Loader', value: '16.00'},
      {key: 'Large Flat Rate Box - Top Loader', value: '16.00'},
      ]


      $scope.modifyShipping = function(data, index,action)
      {
        
        this.postData =  { 'data': data, 'action': action};   //insertDefinedRates updateDefinedRates        
        $http.post($scope.active_url+'e_sales_price_table_query.php', this.postData)
               .success(function(result)
               {  
               	  
               	  if(action == 'updateDefinedRates') 
               	  { 
                    $scope.shippings[index] = result.data
                  }

                  else if (action =='insertDefinedRates')
                  {
                    $scope.newShippingRow = null
                    $scope.newShipping = 0
                    $scope.shippings.push(result.data)
                  }
                  else if(action == 'deleteDefinedRates')
                  {
                    $scope.shippings.splice(index,1)
                  }

                  $scope.activeEditID = -1;

               });

      }

       $scope.loadShippingRates();
    }) //ShippingCtrl

})();