<?php

require_once('system/config.php');
require_once('system/acl.php');

function query()
{
    $args = func_get_args();
    if (count($args) < 2)
        return false;
    $q = array_shift($args);
    $args = array_map('mysql_real_escape_string', $args);
    array_unshift($args, $q);
    $q = call_user_func_array('sprintf', $args);
    return $q;
}

$user = Login('shipgrid');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

if (empty($salesId)) return;

?>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
</head>
<body>
<table class="table table-condensed table-bordered table-hover">
    <thead>
    <tr>
        <th>Date</th>
        <th>Entered By</th>
        <th>Remarks</th>
    </tr>
    </thead>
    <tbody id="order_history">
    <?
    $res = mysql_query(query(<<<EOQ
SELECT oh.ts, REPLACE(oh.email, '@eocenterprise.com', '') AS email, oh.remarks
FROM integra_prod.order_history oh, integra_prod.users u
WHERE oh.order_id = '%s'
AND u.email = '%s'
AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
AND oh.remarks > ''
ORDER BY oh.ts
EOQ
        , $salesId, $user));

    while ($row = mysql_fetch_row($res)):
        ?>
        <tr>
            <td><?=$row[0]?></td>
            <td><?=$row[1]?></td>
            <td class="wrap"><?=nl2br($row[2])?></td>
        </tr>
        <?
    endwhile;
    ?>
    </tbody>
</table>
</body>
</html>