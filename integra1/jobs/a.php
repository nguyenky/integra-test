<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$salesIds = array();
$components = array();
$querySkus = array();
$miamiAvail = array();
$miamiOrders = array();
$miamiSales = array();
$miamiExtras = array();
$pompanoAvail = array();
$pompanoOrders = array();
$pompanoSales = array();
$pompanoExtras = array();
$baltiAvail = array();
$baltiOrders = array();
$baltiSales = array();
$baltiExtras = array();
$prices = array();

$useIpo = array();
$componentsIpo = array();
$salesIdsIpo = array();
$querySkusIpo = array();
$extraIpo = array();

$res = mysql_query("SELECT mpn FROM eoc.imc_use_ipo");
echo "---a---\n";
echo mysql_error();
while ($row = mysql_fetch_row($res))
    $useIpo[] = $row[0];

$q = <<<EOQ
SELECT s.id, s.total, SUM(si.quantity)
FROM eoc.sales s, eoc.sales_items si
WHERE s.id = si.sales_id
AND s.fulfilment = 3
AND s.status = 1
GROUP BY s.id, s.total
ORDER BY 3 DESC, 2 DESC
EOQ;

$res = mysql_query($q);
echo "---b---\n";
echo mysql_error();
while ($row = mysql_fetch_row($res))
	$salesIds[] = $row[0];

foreach ($salesIds as $index => $salesId)
{
	$supplierId = CheckOrderSuppliers($salesId);
	
	// pure IMC
	if ($supplierId == 1)
	{
		$parts = GetOrderComponents($salesId);
		
		foreach ($parts as $sku => $qty)
		{
			if ($sku == IMC_FILLERITEM)
				continue;

			$components[$salesId][startsWith($sku, 'EOC') ? substr($sku, 3) : $sku] = $qty;
			$querySkus[$sku] = 1;
		}
	}
	else
	{
		//SetRemarks($salesId, 99, 'For now, only IMC orders are supported for automatic processing for EOC fulfilment.');
		unset($salesIds[$index]);
	}
}
exit;
$q = <<<EOQ
SELECT e.id, e.mpn, e.quantity, (SELECT s.id FROM eoc.sales s WHERE s.record_num = e.remarks ORDER BY id DESC LIMIT 1) AS sales_id
FROM eoc.extra_orders e
WHERE e.supplier = 1
AND (e.order_id IS NULL OR e.order_id = '')
EOQ;

$res = mysql_query($q);
echo "---c---\n";

echo mysql_error();
$extraOrders = [];

while ($row = mysql_fetch_row($res))
{
    $sku = (startsWith($row[1], 'EOC') ? substr($row[1], 3) : $row[1]);

    $extraOrders[] = ['extra_id' => $row[0],
        'sku' => $sku,
        'qty' => $row[2],
        'sales_id' => $row[3]];

    $querySkus[$sku] = 1;
}

print_r($extraOrders);
