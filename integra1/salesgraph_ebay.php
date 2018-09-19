<?php

require_once('system/config.php');
session_start();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

?>

<html>
<head>
    <title>eBay Sales</title>
    <script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
        google.load('visualization', '1', {'packages':['corechart']});
        google.setOnLoadCallback(drawChart);
        function drawChart()
        {
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Date');
            data.addColumn('number', 'Sales');
            data.addRows([
                <?php
                unset($rows);
$q=<<<EOQ
SELECT DATE(order_date) AS d, SUM(total) AS s
FROM sales
WHERE DATE(order_date) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
AND store = 'eBay'
GROUP BY 1
ORDER BY 1
EOQ;
                $res = mysql_query($q);
                while ($row = mysql_fetch_row($res)) $rows[] = "['${row[0]}', ${row[1]}]";
                if (!empty($rows))
                {
                    array_pop($rows);
                    echo implode(',', $rows);
                }
                ?>
            ]);
            var chart = new google.visualization.ColumnChart(document.getElementById('g7d'));
            chart.draw(data, { title: 'Last 7 Days', hAxis:
            {
                slantedText: true,
                slantedTextAngle: 90
            }, legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Date');
            data.addColumn('number', 'Sales');
            data.addRows([
                <?php
                unset($rows);
$q=<<<EOQ
SELECT DATE(order_date) AS d, SUM(total) AS s
FROM sales
WHERE order_date >= CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m'), '-01')
AND store = 'eBay'
GROUP BY 1
ORDER BY 1
EOQ;
                $res = mysql_query($q);
                while ($row = mysql_fetch_row($res)) $rows[] = "['${row[0]}', ${row[1]}]";
                if (!empty($rows))
                {
                    array_pop($rows);
                    echo implode(',', $rows);
                }
                ?>
            ]);
            var chart = new google.visualization.ColumnChart(document.getElementById('gmtd'));
            chart.draw(data, { title: 'Month to Date', hAxis:
            {
                slantedText: true,
                slantedTextAngle: 90
            }, legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Date');
            data.addColumn('number', 'Sales');
            data.addRows([
                <?php
                unset($rows);
$q=<<<EOQ
SELECT DATE_FORMAT(order_date, '%Y-%m') AS d, SUM(total) AS s
FROM sales
WHERE DATE(order_date) >= CONCAT(DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 MONTH), '%Y-%m'), '-01')
AND store = 'eBay'
GROUP BY 1
ORDER BY 1
EOQ;
                $res = mysql_query($q);
                while ($row = mysql_fetch_row($res)) $rows[] = "['${row[0]}', ${row[1]}]";
                if (!empty($rows))
                {
                    //array_pop($rows);
                    echo implode(',', $rows);
                }
                ?>
            ]);
            var chart = new google.visualization.ColumnChart(document.getElementById('g3m'));
            chart.draw(data, { title: 'Last 3 Months', hAxis:
            {
                slantedText: true,
                slantedTextAngle: 90
            }, legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        }
    </script>
    <style>.chart { width: 100%; height: 600px }</style>
</head>
<body>
<center>
<h1>eBay Sales</h1>
<div class='chart' id='g7d'></div>
<div class='chart' id='gmtd'></div>
<div class='chart' id='g3m'></div>
</center>
</body>
</html>
