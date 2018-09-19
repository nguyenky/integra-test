<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$days = intval($_REQUEST['days']);

if (empty($days)) $days = 3;

$user = Login('sales');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db('integra_prod');

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=supplier_orders.csv");
header("Pragma: no-cache");
header("Expires: 0");

$q = <<<EOQ
SELECT CONCAT('W', IF(si.supplier_id != 1, si.supplier_id, IF(si.po_num LIKE '%_web', '1E', '1'))) AS supplier_id,
si.invoice_num,
si.order_num,
si.po_num,
IFNULL((SELECT order_date FROM eoc.direct_shipments ds WHERE ds.order_id = si.order_num), si.order_date) AS order_date,
sii.sku,
sii.quantity,
sii.quantity_shipped,
sii.unit_price,
sii.quantity_shipped * sii.unit_price AS charged
FROM integra_prod.supplier_invoices si, integra_prod.supplier_invoice_items sii
WHERE si.id = sii.supplier_invoice_id
AND si.order_date >= DATE_SUB(CURDATE(), INTERVAL ${days} DAY)
EOQ;

$rows = mysql_query($q);

echo "supplier_id,invoice_num,order_num,po_num,order_date,sku,quantity,quantity_shipped,unit_price,charged\r\n";

while ($row = mysql_fetch_row($rows))
{
    echo implode(',', $row) . "\r\n";
}

mysql_close();

?>
