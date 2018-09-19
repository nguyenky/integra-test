<?php

require_once('system/config.php');
require_once('system/utils.php');

$salesId = $_GET['sales_id'];
settype($salesId, 'integer');

if (empty($salesId))
	exit;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q=<<<EOQ
	SELECT store, tracking_num, carrier, email, record_num
	FROM sales
	WHERE id = %d
EOQ;

$row = mysql_fetch_row(mysql_query(sprintf($q, $salesId)));
if (empty($row))
{
	echo "Invalid sales ID.";
	exit;
}

$store = $row[0];
$tracking = $row[1];
$carrier = $row[2];
$email = $row[3];
$recordNum = $row[4];

if (empty($tracking))
{
	echo "No tracking information saved on the order.";
	return;
}
	
if ($store != 'eBay')
{
	echo "Can't send tracking information for non-eBay orders.";
	return;
}
	
if (empty($email))
{
	echo "No email address saved on the order.";
	return;
}

if (stripos($email, 'amazon.com') !== false)
{
	echo "Can't send tracking information to Amazon proxy email addresses.";
	return;
}

$orderNum = "";

if (!empty($recordNum))
	$orderNum = "ORDER NUMBER: <strong>" . htmlspecialchars($recordNum) . "</strong><br />\r\n";

$html = <<<EOD
<html><body>
<p>Dear Customer,</p><br/>
<p>We would like to inform you that your order has been shipped with the following tracking number. Don&#39;t forget to visit us at <a href="http://www.qeautoparts.com">www.qeautoparts.com</a> for even better prices.</p><br/>
<p>${orderNum}TRACKING NUMBER: <strong>${tracking}</strong><br />
CARRIER: <strong>${carrier}</strong></p><br/>
<p>ORDER DETAILS:</p>
<table border="1" cellpadding="6" cellspacing="0">
	<thead>
		<tr>
			<th>QUANTITY</th>
			<th>ITEM</th>
		</tr>
	</thead>
	<tbody>
EOD;

$q = <<<EOD
	SELECT description, quantity
	FROM sales_items
	WHERE sales_id = ${salesId};
EOD;
$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$name = $row[0];
	$qty = $row[1];

	$html .= '<tr><td>' . $qty . '</td><td>' . htmlspecialchars($name) . "</td></tr>\r\n";
}

$html .= <<<EOD
	</tbody>
</table><br/>
<p>We appreciate your business and hope to hear from you soon.</p>
<p><a href="http://www.qeautoparts.com">www.qeautoparts.com</a></p>
</body></html>
EOD;

SendTrackingEmail($email, 'Your Order Has Shipped', $html);
SendTrackingEmail('kbcware@yahoo.com', '[EOCTEST] Your Order Has Shipped', $html);

echo "Tracking information sent via email!";

?>