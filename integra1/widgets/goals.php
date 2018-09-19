<?php

require_once('goals_common.php');

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
$metric = 0; // Daily Sales Count
$q = <<<EOD
SELECT goal FROM goals
WHERE metric = %d
AND email IN ('', '%s')
ORDER BY email
DESC LIMIT 1
EOD;
$row = mysql_fetch_row(mysql_query(sprintf($q, $metric, $user)));
$goal = $row[0];

if (!empty($goal)):
$q = <<<EOD
SELECT COUNT(*) FROM sales
WHERE DATE(order_date) = CURDATE()
AND agent = '%s'
EOD;
$row = mysql_fetch_row(mysql_query(sprintf($q, 'Amazon')));
$curValue = $row[0];

if ($curValue >= $goal) $color = $high;
else if (($goal / 2) < $curValue) $color = $med;
else $color = $low;
?>

var curvalue = <?=$curValue?>;
var goal = <?=$goal?>;
var metric = '<?=$metrics[$metric]?>';
var color = '<?=$color?>';
var data = new google.visualization.DataTable();
data.addColumn('string', ''); data.addColumn('number', metric);
data.addColumn('number', 'Goal'); data.addColumn({type:'boolean', role:'certainty'});
data.addRows([['', null, goal, false], ['', curvalue, goal, false], ['', null, goal, false]]);
new google.visualization.BarChart(document.getElementById('chart_<?=$metric?>')).draw(data,
{
	title: metric, legend: 'none', colors: [color, 'blue'], isStacked: false, backgroundColor: {stroke: 'black', strokeWidth: 2},
	series: { 1: { type: 'line', }}, vAxis: { viewWindow: { min: 1, max: 2 } }, hAxis: { minValue: 0 }
});
<?php endif; ?>
//////////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////////
<?php
$metric = 1; // Monthly Sales Count
$q = <<<EOD
SELECT goal FROM goals
WHERE metric = %d
AND email IN ('', '%s')
ORDER BY email
DESC LIMIT 1
EOD;
$row = mysql_fetch_row(mysql_query(sprintf($q, $metric, $user)));
$goal = $row[0];

if (!empty($goal)):
$q = <<<EOD
SELECT COUNT(*) FROM sales
WHERE order_date >= DATE_FORMAT(NOW(), '%%Y-%%m-01')
AND agent = '%s'
EOD;
$row = mysql_fetch_row(mysql_query(sprintf($q, 'Amazon')));
$curValue = $row[0];

if ($curValue >= $goal) $color = $high;
else if (($goal / 2) < $curValue) $color = $med;
else $color = $low;
?>

var curvalue = <?=$curValue?>;
var goal = <?=$goal?>;
var metric = '<?=$metrics[$metric]?>';
var color = '<?=$color?>';
var data = new google.visualization.DataTable();
data.addColumn('string', ''); data.addColumn('number', metric);
data.addColumn('number', 'Goal'); data.addColumn({type:'boolean', role:'certainty'});
data.addRows([['', null, goal, false], ['', curvalue, goal, false], ['', null, goal, false]]);
new google.visualization.BarChart(document.getElementById('chart_<?=$metric?>')).draw(data,
{
	title: metric, legend: 'none', colors: [color, 'blue'], isStacked: false, backgroundColor: {stroke: 'black', strokeWidth: 2},
	series: { 1: { type: 'line', }}, vAxis: { viewWindow: { min: 1, max: 2 } }, hAxis: { minValue: 0 }
});
<?php endif; ?>
//////////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////////
<?php
$metric = 2; // Monthly Sales Value
$q = <<<EOD
SELECT goal FROM goals
WHERE metric = %d
AND email IN ('', '%s')
ORDER BY email
DESC LIMIT 1
EOD;
$row = mysql_fetch_row(mysql_query(sprintf($q, $metric, $user)));
$goal = $row[0];

if (!empty($goal)):
$q = <<<EOD
SELECT SUM(total) FROM sales
WHERE order_date >= DATE_FORMAT(NOW(), '%%Y-%%m-01')
AND agent = '%s'
EOD;
$row = mysql_fetch_row(mysql_query(sprintf($q, 'Amazon')));
$curValue = $row[0];

if ($curValue >= $goal) $color = $high;
else if (($goal / 2) < $curValue) $color = $med;
else $color = $low;
?>

var curvalue = <?=$curValue?>;
var goal = <?=$goal?>;
var metric = '<?=$metrics[$metric]?>';
var color = '<?=$color?>';
var data = new google.visualization.DataTable();
data.addColumn('string', ''); data.addColumn('number', metric);
data.addColumn('number', 'Goal'); data.addColumn({type:'boolean', role:'certainty'});
data.addRows([['', null, goal, false], ['', curvalue, goal, false], ['', null, goal, false]]);
new google.visualization.BarChart(document.getElementById('chart_<?=$metric?>')).draw(data,
{
	title: metric, legend: 'none', colors: [color, 'blue'], isStacked: false, backgroundColor: {stroke: 'black', strokeWidth: 2},
	series: { 1: { type: 'line', }}, vAxis: { viewWindow: { min: 1, max: 2 } }, hAxis: { minValue: 0 }
});
<?php endif; ?>
//////////////////////////////////////////////////////////////////////////////////////////
}
</script>
<style>.chart { width: 300px; height: 70px }</style>
</head>
<body>
<?php
foreach ($metrics as $m => $n)
	echo "<div class='chart' id='chart_${m}'></div>\r\n";
?>
</body>
</html>
