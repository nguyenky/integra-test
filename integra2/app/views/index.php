<!DOCTYPE html>
<html lang="en-us" data-ng-app="integraApp">
<head>
    <meta charset="utf-8">
    <!--<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">-->

    <title>Integra v2</title>
    <meta name="description" content="">
    <meta name="author" content="">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <!-- Basic Styles -->
    <link rel="stylesheet" type="text/css" media="screen" href="css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" media="screen" href="css/font-awesome.min.css">

    <!-- SmartAdmin Styles : Please note (smartadmin-production.css) was created using LESS variables -->
    <link rel="stylesheet" type="text/css" media="screen" href="css/smartadmin-production.min.css">
    <link rel="stylesheet" type="text/css" media="screen" href="css/smartadmin-skins.min.css">

    <link rel="stylesheet" type="text/css" media="screen" href="js/libs/xeditable/xeditable.css">
    <link rel="stylesheet" type="text/css" media="screen" href="js/libs/ng-table/ng-table.css">
    <link rel="stylesheet" type="text/css" media="screen" href="js/libs/angular-select/select.min.css">
    <link rel="stylesheet" type="text/css" media="screen" href="js/libs/angular-loading-bar/loading-bar.css">

    <!-- customizations -->
    <link rel="stylesheet" type="text/css" media="screen" href="css/integra.css">

    <!-- FAVICONS -->
    <link rel="shortcut icon" href="img/favicon/favicon.ico" type="image/x-icon">
    <link rel="icon" href="img/favicon/favicon.ico" type="image/x-icon">

    <!-- GOOGLE FONT -->
    <link rel="stylesheet" href="http://fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,300,400,700">

    <!-- Specifying a Webpage Icon for Web Clip
         Ref: https://developer.apple.com/library/ios/documentation/AppleApplications/Reference/SafariWebContent/ConfiguringWebApplications/ConfiguringWebApplications.html -->
    <link rel="apple-touch-icon" href="img/splash/sptouch-icon-iphone.png">
    <link rel="apple-touch-icon" sizes="76x76" href="img/splash/touch-icon-ipad.png">
    <link rel="apple-touch-icon" sizes="120x120" href="img/splash/touch-icon-iphone-retina.png">
    <link rel="apple-touch-icon" sizes="152x152" href="img/splash/touch-icon-ipad-retina.png">

    <!-- iOS web-app metas : hides Safari UI Components and Changes Status Bar Appearance -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">

    <!-- Startup image for web apps -->
    <link rel="apple-touch-startup-image" href="img/splash/ipad-landscape.png" media="screen and (min-device-width: 481px) and (max-device-width: 1024px) and (orientation:landscape)">
    <link rel="apple-touch-startup-image" href="img/splash/ipad-portrait.png" media="screen and (min-device-width: 481px) and (max-device-width: 1024px) and (orientation:portrait)">
    <link rel="apple-touch-startup-image" href="img/splash/iphone.png" media="screen and (max-device-width: 320px)">

    <script type="text/javascript">
        var paypalUrl = '<?= Config::get('paypal')['url'] ?>';
        var acl = <?= json_encode($acl) ?>;
        console.log('=========== in INDEX =============');
        console.log(acl);
        var stores = <?= json_encode($stores) ?>;
    </script>
</head>
<body class="smart-style-3" data-ng-controller="IntegraAppController">

<header id="header" data-ng-include="'includes/header.html'"></header>
<aside id="left-panel"><span data-ng-include="'/users/nav'"></span></aside>

<div id="main" role="main">
    <div id="content" data-ng-view="" class="view-animate"></div>
</div>

<!-- PACE LOADER - turn this on if you want ajax loading to show (caution: uses lots of memory on iDevices)
<script data-pace-options='{ "restartOnRequestAfter": true }' src="js/plugin/pace/pace.min.js"></script>-->

<!-- Link to Google CDN's jQuery + jQueryUI; fall back to local -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js"></script>
<script>
    if (!window.jQuery) {
        document.write('<script src="js/libs/jquery-2.0.2.min.js"><\/script>');
    }
</script>

<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
<script>
    if (!window.jQuery.ui) {
        document.write('<script src="js/libs/jquery-ui-1.10.3.min.js"><\/script>');
    }
</script>

<!-- MAIN APP JS FILE -->
<script src="js/app.config.js"></script>

<!-- JS TOUCH : include this plugin for mobile drag / drop touch events-->
<script src="js/plugin/jquery-touch/jquery.ui.touch-punch.min.js"></script>

<!-- BOOTSTRAP JS -->
<script src="js/bootstrap/bootstrap.min.js"></script>

<!-- CUSTOM NOTIFICATION -->
<script src="js/notification/SmartNotification.min.js"></script>

<!-- JARVIS WIDGETS -->
<script src="js/smartwidgets/jarvis.widget.min.js"></script>

<!-- EASY PIE CHARTS -->
<script src="js/plugin/easy-pie-chart/jquery.easy-pie-chart.min.js"></script>

<!-- SPARKLINES -->
<script src="js/plugin/sparkline/jquery.sparkline.min.js"></script>

<!-- JQUERY VALIDATE -->
<script src="js/plugin/jquery-validate/jquery.validate.min.js"></script>

<!-- JQUERY MASKED INPUT -->
<script src="js/plugin/masked-input/jquery.maskedinput.min.js"></script>

<!-- JQUERY SELECT2 INPUT -->
<script src="js/plugin/select2/select2.min.js"></script>

<!-- JQUERY UI + Bootstrap Slider -->
<script src="js/plugin/bootstrap-slider/bootstrap-slider.min.js"></script>

<!-- browser msie issue fix -->
<script src="js/plugin/msie-fix/jquery.mb.browser.min.js"></script>

<!-- FastClick: For mobile devices: you can disable this in app.js -->
<script src="js/plugin/fastclick/fastclick.min.js"></script>

<!--[if IE 8]>
<h1>Your browser is out of date, please update your browser by going to www.microsoft.com/download</h1>
<![endif]-->

<!-- AngularJS -->
<script src="//cdnjs.cloudflare.com/ajax/libs/angular.js/1.2.20/angular.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/angular.js/1.2.20/angular-route.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/angular.js/1.2.20/angular-animate.min.js"></script>
<script src="js/libs/angular/ui-bootstrap-custom-tpls-0.11.0.js"></script>
<script src="js/libs/ng-table/ng-table.js"></script>
<script src="js/libs/xeditable/xeditable.min.js"></script>
<script src="js/libs/angular-select/select.js"></script>
<script src="js/libs/angular-loading-bar/loading-bar.js"></script>
<script src="js/libs/angular-truncate/truncate.js"></script>
<script src="js/libs/angular-chart/chart.js"></script>
<script src="js/libs/angular-chart/angular-chart.min.js"></script>
<script src="js/libs/angular/ng-file-upload.js"></script>
<script src="js/libs/underscore/underscore.js"></script>

<script src="js/app.js"></script>

<script src="js/ng/ng.app.js"></script>
<script src="js/ng/ng.controllers.js"></script>
<script src="js/ng/ng.directives.js"></script>

<script type="text/javascript">

    var _gaq = _gaq || [];
    _gaq.push(['_setAccount', 'UA-8286512-8']);
    _gaq.push(['_trackPageview']);

    (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
    })();

</script>

<script type="text/ng-template" id="ng-table/filters/number.html">
    <span class="nowrap">
        <input class="form-control input-filter filter-num" type="number" step="0.01" ng-model="params.filter()[name + '@num']" />
        <select class="form-control filter-exp" ng-model="params.filter()['__' + name]" ng-options="o as o for o in ['=', '>', '>=', '<', '<=']" ng-init="params.filter()['__' + name]='='" />
    </span>
</script>

<script type="text/ng-template" id="ng-table/filters/date.html">
    <span class="nowrap">
        <input type="text" class="form-control filter-date-from" datepicker-popup="yyyy-MM-dd"
               ng-model="params.filter()[name + '@fromdate']" is-open="from_date_filter_opened" placeholder="From" ng-click="from_date_filter_opened = true"/>
        <input type="text" class="form-control filter-date-to" datepicker-popup="yyyy-MM-dd"
               ng-model="params.filter()[name + '@todate']" is-open="to_date_filter_opened" placeholder="To" ng-click="to_date_filter_opened = true"/>
    </span>
</script>

</body>

</html>