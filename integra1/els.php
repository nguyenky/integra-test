<?php

require_once('system/config.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

session_start();

$currentUser = str_replace("'", '', $_SESSION['user']);
if (empty($currentUser)) {
    header("Location: /");
    exit();
}

$user = $currentUser;

if (!empty($_GET['user'])) {
    $q=<<<EOQ
SELECT group_name
FROM integra_prod.users
WHERE email = '{$currentUser}'
EOQ;

    $result = mysql_query($q);
    $row = mysql_fetch_row($result);
    if (!empty($row) && ($row[0] == 'Admin' || $row[0] == 'IT')) {
        $user = str_replace("'", '', $_GET['user']);
    }
}

$q=<<<EOQ
SELECT first_name, last_name
FROM integra_prod.users
WHERE email = '{$user}'
EOQ;

$result = mysql_query($q);
$row = mysql_fetch_row($result);
if (empty($row)) {
    echo "Invalid user";
    exit;
}
else {
    $firstName = $row[0];
    $lastName = $row[1];
}

$q=<<<EOQ
SELECT month_created, SUM(sold) AS total_sold, COUNT(1) - SUM(sold) AS total_unsold
FROM
(SELECT DATE_FORMAT(created_on, '%Y-%m') AS month_created, eel.item_id, IFNULL((SELECT 1 FROM eoc.sales_items si WHERE si.ebay_item_id = eel.item_id LIMIT 1), 0) AS sold
FROM eoc.ebay_edit_log eel
WHERE eel.is_new = 1
AND created_by = '{$user}'
AND created_on >= '2017-05-01') x
GROUP BY 1
EOQ;

$soldRows = [];
$result = mysql_query($q);
while ($row = mysql_fetch_row($result))
{
    $soldRows[] = $row;
}

mysql_close();

?>

<html>
<head>
	<title>eBay Listings Created Stats</title>
    <link rel="stylesheet" type="text/css" href="css/stats.css">
</head>
<body>
	<br/>
	<center>
        <h1>eBay New Listing Stats for <?=$firstName?> <?=$lastName?></h1>
        <div style="height:600px; width: 100%" id="soldGraph"></div>
	</center>
	<br/><br/>
    <script src="https://www.google.com/jsapi" type="text/javascript"></script>
    <script type="text/javascript">
        google.load('visualization', '1.0', {'packages':['corechart']});
        google.setOnLoadCallback(drawGraph);

        function drawGraph()
        {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Month');
            data.addColumn('number', 'Sold');
            data.addColumn('number', 'Unsold');
            data.addRows([

                <?php
                    foreach ($soldRows as $row) {
                        echo "['{$row[0]}', {$row[1]}, {$row[2]}],\n";
                    }
                ?>
            ]);

            var formatPercent = new google.visualization.NumberFormat({
                pattern: '#,##0.0%'
            });

            var view = new google.visualization.DataView(data);
            view.setColumns([0,
                // series 0
                1, {
                    calc: function (dt, row) {
                        return dt.getValue(row, 1) + ' (' + formatPercent.formatValue(dt.getValue(row, 1) / (dt.getValue(row, 1) + dt.getValue(row, 2))) + ')';
                    },
                    type: "string",
                    role: "annotation"
                },
                // series 1
                2, {
                    calc: function (dt, row) {
                        return dt.getValue(row, 2) + ' (' + formatPercent.formatValue(dt.getValue(row, 2) / (dt.getValue(row, 1) + dt.getValue(row, 2))) + ')';
                    },
                    type: "string",
                    role: "annotation"
                }
            ]);

            var chart = new google.visualization.BarChart(document.getElementById('soldGraph'));
            chart.draw(view, {
                isStacked: 'percent'
            });
        }
    </script>
</body>
</html>

