<?php

require_once('../system/config.php');
session_start();
$user = 'diana@eocenterprise.com';//$_SESSION['user'];
if (empty($user)) exit;

$low = 'salmon';
$med = 'orange';
$high = 'lightgreen';

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

?>

<html>
<head>
<script type='text/javascript' src='http://www.google.com/jsapi'></script>
<script type='text/javascript'>
google.load('visualization', '1', {'packages':['corechart']});
google.setOnLoadCallback(drawChart);
function drawChart()
{
//////////////////////////////////////////////////////////////////////////////////////////
<?php
$goal = 160;

if (!empty($goal)):
$q = <<<EOD
SELECT COUNT(*) FROM stamps
WHERE DATE(print_date) = CURDATE()
AND email = '%s'
EOD;
$row = mysql_fetch_row(mysql_query(sprintf($q, $user)));
$curValue = $row[0];
$curValue = 120;

if ($curValue >= $goal) $color = $high;
else if (($goal / 2) < $curValue) $color = $med;
else $color = $low;
?>

var curvalue = <?=$curValue?>;
var goal = <?=$goal?>;
var color = '<?=$color?>';
var data = new google.visualization.DataTable();
data.addColumn('string', ''); data.addColumn('number', 'Shipments');
data.addColumn('number', 'Goal'); data.addColumn({type:'boolean', role:'certainty'});
data.addRows([['', null, goal, false], ['', curvalue, goal, false], ['', null, goal, false]]);
new google.visualization.BarChart(document.getElementById('chart')).draw(data,
{
	legend: 'none', colors: [color, 'blue'], isStacked: false, backgroundColor: {stroke: 'black', strokeWidth: 2},
	series: { 1: { type: 'line' }}, vAxis: { viewWindow: { min: 1, max: 2 } }, hAxis: { ticks: [ ], viewWindow: { min: 0, max: 160 }, minValue: 0 },
    tooltip : { trigger: 'none' }
});
<?php endif; ?>
//////////////////////////////////////////////////////////////////////////////////////////

}
</script>
<style>.chart { width: 300px; height: 70px }</style>
</head>
<body>
<?php
	echo "<div class='chart' id='chart'></div>\r\n";
?>
</body>
</html>
