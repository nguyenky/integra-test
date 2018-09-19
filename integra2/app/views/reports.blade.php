<!DOCTYPE html>
<html lang="en-us" data-ng-app="integraPublicApp">
	<head>
	    <meta charset="utf-8">
	    <!--<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">-->

	    <title>Integra v2</title>
	    <meta name="description" content="">
	    <meta name="author" content="">

	    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

	    <!-- Basic Styles -->
	    <link rel="stylesheet" type="text/css" media="screen" href="/css/bootstrap.min.css">
	    <link rel="stylesheet" type="text/css" media="screen" href="/css/font-awesome.min.css">

	    

	    <!-- FAVICONS -->
	    
	</head>
	<body class="smart-style-3" >
		<div id="main" style="margin:15px;">
		    <div id="content" data-ng-view="" class="view-animate"></div> 
		</div>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js"></script>
		<script>
		    if (!window.jQuery) {
		        document.write('<script src="js/libs/jquery-2.0.2.min.js"><\/script>');
		    }
		</script>

		<!-- MAIN APP JS FILE -->
		<script src="/js/app.config.js"></script>

		<!-- JS TOUCH : include this plugin for mobile drag / drop touch events-->
		<script src="/js/plugin/jquery-touch/jquery.ui.touch-punch.min.js"></script>

		<!-- BOOTSTRAP JS -->
		<script src="/js/bootstrap/bootstrap.min.js"></script>

		
		<!-- JARVIS WIDGETS -->
		<script src="/js/smartwidgets/jarvis.widget.min.js"></script>

		<!-- EASY PIE CHARTS -->
		<script src="/js/plugin/easy-pie-chart/jquery.easy-pie-chart.min.js"></script>

		
		<!-- JQUERY SELECT2 INPUT -->
		<script src="/js/plugin/select2/select2.min.js"></script>

		<!-- JQUERY UI + Bootstrap Slider -->
		
		<!-- AngularJS -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.2.20/angular.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.2.20/angular-route.min.js"></script>
		<!--  -->
		<script src="//cdnjs.cloudflare.com/ajax/libs/angular.js/1.2.20/angular-animate.min.js"></script>
		<script src="/js/libs/angular/ui-bootstrap-custom-tpls-0.11.0.js"></script>
		<script src="/js/libs/angular-select/select.js"></script>
		<script src="/js/libs/angular-loading-bar/loading-bar.js"></script>
		<script src="/js/libs/angular-truncate/truncate.js"></script>
		<script src="/js/libs/angular-chart/chart.js"></script>
		<script src="/js/libs/angular-chart/angular-chart.min.js"></script> 
		<script src="/js/libs/angular/ng-file-upload.js"></script>

		<script src="/js/public/public.app.js"></script>
		<script src="/js/public/controllers.js"></script>
		<script src="/js/ng/ng.directives.js"></script>
	</body>

</html>