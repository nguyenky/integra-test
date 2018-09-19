(function(){
  var app = angular.module('MagentoPriceModule',['ui.bootstrap']);
  
   

  app.controller('priceController', function($scope, $http, $sce, $timeout,$location)
  { 
    //magento.core_store
    $scope.core_store =  
    [
	  {'id':1,'selected': false, 'code': 'qeautoparts', 'name':'QE Auto Parts'},                         
	  {'id':3,'selected': false, 'code': 'eocparts', 'name':'EOC Parts' }, 
	  {'id':4,'selected': false, 'code': 'europortparts', 'name': 'Euro Port Parts' },  
	  {'id':7,'selected': false, 'code': 'iapaustralia', 'name':'International Auto Parts Australia' },
	  {'id':8,'selected': false, 'code': 'iapcanada', 'name':'International Auto Parts Canada' },
	  {'id':9,'selected': false, 'code': 'iapunitedkingdom', 'name': 'International Auto Parts UK' },
	 ]; 

	 $scope.downloadCSVPrices = function(data)
	 {
       var selectedStores = [];
       angular.forEach(data, function(element)
       {
          if(element.selected == true){
           selectedStores.push(element.id) 
          }

       })

       if ( selectedStores.length ) 
       {
          window.location = 'magento_prices_export.php?store='+selectedStores.join();  

       }else{
         alert('Please Select Store');
       }

      
	 }


  })   
  
  

})();