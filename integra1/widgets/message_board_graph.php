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

/*auto-response ,compose ,ebay_order_confirmation ,email_order_confirmation ,flagged ,followup ,reply ,trash*/

$q=<<<EOQ
	SELECT DATE_FORMAT( log_timestamp,  '%m/%d %H:00' ) AS HR, log_user, COUNT(*) AS CNT
	FROM api_messages_logs
	WHERE log_timestamp >= DATE_SUB( CURDATE( ) , INTERVAL ${graphDays} DAY )
    AND log_activity = 'reply'
	GROUP BY 1, 2
	ORDER BY log_timestamp
EOQ;

$result = mysql_query($q) or die(mysql_error());
while ($row = mysql_fetch_row($result))
{
    $people[$row[1]] = 1;
    $shipGraph[$row[0]][$row[1]] = $row[2];
}

mysql_close();

?>

<html>
<head>
	<title>Message Board Graph</title>
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script> 
    <script src="https://www.google.com/jsapi" type="text/javascript"></script>	
</head>
<body>
	
	<div class="col-md-12" align="center">

        <h2>Message Board Graph</h2>
        <br>
        <form class="form-inline">
          <div class="form-group">
            <label for="hourlyFilter">Days in graph:</label>
            <select name="gd" class="form-control" id="hourlyFilter">
                <option value="2" <?=($graphDays==2)?'selected':''?>>2</option>
                <option value="5" <?=($graphDays==5)?'selected':''?>>5</option>
                <option value="7" <?=($graphDays==7)?'selected':''?>>7</option>
                <option value="14" <?=($graphDays==14)?'selected':''?>>14</option>
                <option value="21" <?=($graphDays==21)?'selected':''?>>21</option>
            </select>
           </div>
          <input type="submit" value="Filter" class="btn btn-primary">
        </form>

        <div style="height:600px; width: 100%" id="shipGraph"></div>


	</div>
	<br/><br/>

    
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
                    title: 'Reply',
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
