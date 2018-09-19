<?php

require_once('system/config.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$graphDays = 2;

if (!empty($_REQUEST['gd']))
    $graphDays = $_REQUEST['gd'];

$people = [];
$editGraph = [];
$newGraph = [];

$q=<<<EOQ
	SELECT DATE_FORMAT( created_on,  '%m/%d %H:00' ) AS HR, created_by, COUNT(DISTINCT item_id) AS CNT
	FROM ebay_edit_log
	WHERE created_on >= DATE_SUB( CURDATE( ) , INTERVAL ${graphDays} DAY )
	AND is_new = 0
	GROUP BY 1, 2
	ORDER BY created_on
EOQ;

$result = mysql_query($q);
while ($row = mysql_fetch_row($result))
{
    $people[$row[1]] = 1;
    $editGraph[$row[0]][$row[1]] = $row[2];
}

$q=<<<EOQ
	SELECT DATE_FORMAT( created_on,  '%m/%d %H:00' ) AS HR, created_by, COUNT(DISTINCT item_id) AS CNT
	FROM ebay_edit_log
	WHERE created_on >= DATE_SUB( CURDATE( ) , INTERVAL ${graphDays} DAY )
	AND is_new = 1
	GROUP BY 1, 2
	ORDER BY created_on
EOQ;

$result = mysql_query($q);
while ($row = mysql_fetch_row($result))
{
    $people[$row[1]] = 1;
    $newGraph[$row[0]][$row[1]] = $row[2];
}

mysql_close();

?>

<html>
<head>
	<title>eBay Editing Productivity Graph</title>
	<link rel="stylesheet" type="text/css" href="css/stats.css">
</head>
<body>
	<br/>
	<center>
        <h1>eBay Editing Productivity Graph</h1>
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

        <div style="height:600px; width: 100%" id="editGraph"></div>

        <div style="height:600px; width: 100%" id="newGraph"></div>
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
                            if (array_key_exists($dh, $editGraph))
                            {
                                echo "['${dh}', ";
                                $tmp = [];

                                foreach ($people as $p => $x)
                                {
                                    if (array_key_exists($p, $editGraph[$dh]))
                                        $tmp[] = $editGraph[$dh][$p];
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
                    title: 'Edited Listings',
                    viewWindowMode: 'maximized'
                },
                legend: 'right',
                isStacked: true
            };

            var chart = new google.visualization.ColumnChart(document.getElementById('editGraph'));
            chart.draw(data, settings);

            ////////////////////////////////////////////////////////////////////////////////////////

            data = new google.visualization.DataTable();
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
                            if (array_key_exists($dh, $newGraph))
                            {
                                echo "['${dh}', ";
                                $tmp = [];

                                foreach ($people as $p => $x)
                                {
                                    if (array_key_exists($p, $newGraph[$dh]))
                                        $tmp[] = $newGraph[$dh][$p];
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

            settings =
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
                    title: 'New Listings',
                    viewWindowMode: 'maximized'
                },
                legend: 'right',
                isStacked: true
            };

            chart = new google.visualization.ColumnChart(document.getElementById('newGraph'));
            chart.draw(data, settings);
        }
    </script>
</body>
</html>
