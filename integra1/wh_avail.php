<?php

require_once('system/config.php');
require_once('system/utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$sku = $_GET['sku'];
if (empty($sku)) return;

$from = $_GET['from'];
if (empty($from)) $from = '';

$ret = [];

$res = file_get_contents('http://integra.eocenterprise.com/imc_ajax.php?sku=' . $sku);

if (!empty($res))
{
    $json = json_decode($res, true);

    foreach ($json['sites'] as $key => $name)
    {
        $site = [];
        $site['supplier_id'] = 1;
        $site['code'] = $key . '';
        $site['name'] = 'W1 | ' . $name;
        $site['quantity'] = $json['site_' . $key];
        $site['distance'] = 9999999;

        $ret[] = $site;
    }
}

$res = file_get_contents('http://integra.eocenterprise.com/ssf_ajax.php?sku=' . $sku);

if (!empty($res))
{
    $json = json_decode($res, true);

    foreach ($json['sites'] as $key => $name)
    {
        $site = [];
        $site['supplier_id'] = 2;
        $site['code'] = $key;
        $site['name'] = 'W2 | ' . $name;
        $site['quantity'] = $json['site_' . $key];
        $site['distance'] = 9999999;

        $ret[] = $site;
    }
}

$q = <<<EOQ
SELECT w.code, CONCAT(w.name, ' (shelf ', pw.isle, pw.row, pw.column, ')') AS name, pw.quantity
FROM integra_prod.warehouses w, integra_prod.product_warehouse pw, integra_prod.products p
WHERE w.supplier_id = 5
AND w.is_active = 1
AND pw.warehouse_id = w.id
AND p.sku = '%s'
AND p.id = pw.product_id
ORDER BY pw.quantity DESC
EOQ;

$rows = mysql_query(query($q, $sku));
while ($row = mysql_fetch_row($rows))
{
    $site = [];
    $site['supplier_id'] = 5;
    $site['code'] = $row[0];
    $site['name'] = 'W0 | ' . $row[1];
    $site['quantity'] = intval($row[2]);
    $site['distance'] = 9999999;

    $ret[] = $site;
}

$q = <<<EOQ
SELECT wt.supplier_id, wt.code, wd.distance_miles
FROM integra_prod.warehouses wf, integra_prod.warehouses wt, integra_prod.warehouse_distances wd
WHERE wf.id = wd.from_id
AND wf.code = '%s'
AND wt.id = wd.to_id
EOQ;

$rows = mysql_query(query($q, $from));
while ($row = mysql_fetch_row($rows))
{
    foreach ($ret as &$r)
    {
        if ($r['supplier_id'] == $row[0] && $r['code'] == $row[1])
        {
            $r['distance'] = intval($row[2]);
            break;
        }
    }
}

mysql_close();

function cmp($a, $b)
{
    if ($a["distance"] == $b["distance"]) {
        return 0;
    }
    return ($a["distance"] < $b["distance"]) ? -1 : 1;
}

usort($ret, "cmp");

echo json_encode($ret);
