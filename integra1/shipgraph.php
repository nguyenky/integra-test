<?php

require_once('system/config.php');
require_once('system/acl.php');

$user = Login('shipgrid');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$graphDays = 2;

if (!empty($_REQUEST['gd']))
    $graphDays = $_REQUEST['gd'];

$people = [];

$q=<<<EOQ
	SELECT DATE_FORMAT( print_date,  '%m/%d %H:00' ) AS HR, email, COUNT(*) AS CNT
	FROM stamps
	WHERE print_date >= DATE_SUB( CURDATE( ) , INTERVAL ${graphDays} DAY )
	GROUP BY 1, 2
	ORDER BY print_date
EOQ;

$result = mysql_query($q);
while ($row = mysql_fetch_row($result))
{
    $people[$row[1]] = 1;
    $shipGraph[$row[0]][$row[1]] = $row[2];
}

mysql_close();

?>

<html>
<head>
	<title>Shipment Production Graph</title>
	<link rel="stylesheet" type="text/css" href="css/stats.css">
</head>
<body>
	<br/>
	<center>
        <h1>Shipment Production Graph</h1>
        <br>
        <form>
            Days in graph:
            <select name="gd">
                <option value="2" <?=($graphDays==2)?'selected':''?>>2</option>
                <option value="5" <?=($graphDays==5)?'selected':''?>>5</option>
                <option value="7" <?=($graphDays==7)?'selected':''?>>7</option>
                <option value="14" <?=($graphDays==14)?'selected':''?>>14</option>
                <option value="21" <?=($graphDays==21)?'selected':''?>>21</option>
            </select>
            <input type="submit" value="Filter">
        </form>

        <div style="height:600px; width: 100%" id="shipGraph"></div>
	</center>
	<br/><br/>
    <script src="https://www.google.com/jsapi" type="text/javascript"></script>
    <script type="text/javascript">
        google.load('visualization', '1.0', {'packages':['corechart']});
        google.setOnLoadCallback(drawGraph);

        function drawGraph()
        {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Hours');

            <?php
                foreach ($people as $p => $x)
                {
                    echo "data.addColumn('number', '{$p}');";
                }
            ?>

            data.addRows([
                <?php
                        for ($i = ($graphDays * 24); $i >= 0; $i--)
                        {
                            $hour = intval(date("H", strtotime("-$i hour")));

                            if ($hour < 7 || $hour > 19)
                                continue;

                            $dh = date("m/d H:00", strtotime("-$i hour"));
                            if (array_key_exists($dh, $shipGraph))
                            {
                                echo "['${dh}', ";
                                $tmp = [];

                                foreach ($people as $p => $x)
                                {
                                    if (array_key_exists($p, $shipGraph[$dh]))
                                        $tmp[] = $shipGraph[$dh][$p];
                                    else $tmp[] = 0;
                                }

                                echo implode(',', $tmp) . "],\n";
                            }
                            else
                            {
                                echo "['${dh}', ";
                                $tmp = [];

                                foreach ($people as $p => $x)
                                {
                                    $tmp[] = '0';
                                }

                                echo implode(',', $tmp) . "],\n";
                            }
                        }

                        echo "['', ";
                        $tmp = [];

                        foreach ($people as $p => $x)
                        {
                            $tmp[] = '0';
                        }

                        echo implode(',', $tmp) . "]\n";
                ?>
            ]);

            var settings =
            {
                title: 'Hourly Productivity',
                hAxis:
                {
                    title: 'Last <?=$graphDays?> Days',
                    slantedText: true,
                    slantedTextAngle: 90
                },
                vAxis:
                {
                    title: 'Stamps Printed',
                    viewWindowMode: 'maximized'
                },
                legend: 'right',
                isStacked: true
            };

            var chart = new google.visualization.ColumnChart(document.getElementById('shipGraph'));
            chart.draw(data, settings);
        }
    </script>
</body>
</html>
