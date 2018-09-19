<?php

require_once('../system/config.php');
session_start();
$user = $_SESSION['user'];
if (empty($user)) exit;
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
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	var data = new google.visualization.DataTable();
	data.addColumn('string', 'Date');
	data.addColumn('number', 'Team');
<?php
unset($rows);
unset($shippers);

$q = "SELECT DATE_FORMAT(create_date, '%m/%d'), COUNT(*)";

$res = mysql_query("SELECT DISTINCT s.email, iu.first_name FROM stamps s INNER JOIN integra_users iu ON s.email = iu.email WHERE DATE(create_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
while ($row = mysql_fetch_row($res))
{
	$shippers[$row[0]] = $row[1];
	echo "data.addColumn('number', '" . $row[1] . "');";
	$q .= ", SUM(IF(email = '" . $row[0] . "', 1, 0))";
}

$q .= <<<EOQ
	FROM stamps
	WHERE DATE(create_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
	GROUP BY 1
	ORDER BY 1
EOQ;
$res = mysql_query($q);
while ($row = mysql_fetch_row($res))
{
	$r = "['${row[0]}', ${row[1]}";
	
	for ($i = 0; $i < count($shippers); $i++)
		$r .= ", " . $row[$i + 2];

	$r .= "]";
	
	$rows[] = $r;
}

echo "data.addRows([";

if (!empty($rows))
{
	array_pop($rows);
	echo implode(',', $rows);
}
?>
	]);
	var chart = new google.visualization.LineChart(document.getElementById('shipment_daily'));
	chart.draw(data, { title: '1 Month Daily Shipment Count', hAxis: { textPosition: 'none' }, legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	var data = new google.visualization.DataTable();
	data.addColumn('string', 'Date');
	data.addColumn('number', 'Team');
<?php
unset($rows);
unset($shippers);

$q = "SELECT STR_TO_DATE(CONCAT(YEAR(create_date), WEEKOFYEAR(create_date)-1, ' Monday'), '%X%V %W'), COUNT(*)";

$res = mysql_query("SELECT DISTINCT s.email, iu.first_name FROM stamps s INNER JOIN integra_users iu ON s.email = iu.email WHERE DATE(create_date) >= DATE_SUB(CAST(DATE_FORMAT(NOW(), '%Y-%m-01') AS DATE), INTERVAL 24 WEEK)");
while ($row = mysql_fetch_row($res))
{
	$shippers[$row[0]] = $row[1];
	echo "data.addColumn('number', '" . $row[1] . "');";
	$q .= ", SUM(IF(email = '" . $row[0] . "', 1, 0))";
}

$q .= <<<EOQ
	FROM stamps
	WHERE DATE(create_date) >= DATE_SUB(CAST(DATE_FORMAT(NOW(), '%Y-%m-01') AS DATE), INTERVAL 24 WEEK)
	GROUP BY 1
	ORDER BY 1
EOQ;
$res = mysql_query($q);
while ($row = mysql_fetch_row($res))
{
	$r = "['${row[0]}', ${row[1]}";
	
	for ($i = 0; $i < count($shippers); $i++)
		$r .= ", " . $row[$i + 2];

	$r .= "]";
	
	$rows[] = $r;
}

echo "data.addRows([";

if (!empty($rows))
{
	array_pop($rows);
	echo implode(',', $rows);
}
?>
	]);
	var chart = new google.visualization.LineChart(document.getElementById('shipment_weekly'));
	chart.draw(data, { title: '6 Months Weekly Shipment Count', hAxis: { textPosition: 'none' }, legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
}
</script>
<style>.chart { width: 500px; height: 120px }</style>
</head>
<body>
<div class='chart' id='shipment_daily'></div>
<div class='chart' id='shipment_weekly'></div>
</body>
</html>
