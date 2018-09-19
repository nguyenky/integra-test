
//configure requirejs
require.config({ 
    baseUrl: '',   
	paths: {
           jquery: [  
                     '//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min', // cdn 
                     'jquery.min'                                               //local
                  ],
           angular: [
                     '//ajax.googleapis.com/ajax/libs/angularjs/1.2.8/angular.min', //cdn
                     'angular.min' //local                    
                   ],
           bootstrap: 'js/angular/ui-bootstrap-tpls-0.11.0.min',
           app_sales: 'js/angular/app_sales'

      }    
 
});

