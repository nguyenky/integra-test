<?php

require_once('system/config.php');
require_once('system/utils.php');

$pickupId = $_GET['pickup_id'];
settype($pickupId, 'integer');

if (empty($pickupId))
{
	header('Location: pickups.php');
	return;
}

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT s.record_num, s.email, ps.street, ps.city, ps.state, ps.zip, ps.phone, p.status
FROM sales s, pickups p, pickup_sites ps
WHERE p.id = ${pickupId}
AND s.id = p.sales_id
AND p.site_id = ps.id
EOD;

$rows = mysql_query($q);
$row = mysql_fetch_row($rows);
if (empty($row))
{
	echo "Order not found.";
	return;
}

$recordNum = htmlentities($row[0]);
$email = $row[1];
$street = htmlentities($row[2]);
$city = htmlentities($row[3]);
$state = convert_state(preg_replace('/[^a-zA-Z0-9 ]/s', '', $row[4]), 'abbrev');
$zip = htmlentities($row[5]);
$phone = htmlentities($row[6]);
$status = $row[7];

mysql_query("UPDATE pickups SET status = 3, deliver_date = NOW() WHERE id = $pickupId");

if ($status == 3 || $status == 4)
	return;

if (!empty($recordNum))
	$orderNum = "ORDER NUMBER: <strong>" . htmlspecialchars($recordNum) . "</strong><br />\r\n";

$html = <<<EOD
<html><body>
<p>Dear Customer,</p><br/>
<p>We would like to inform you that your order is now ready for pickup. Don&#39;t forget to visit us at <a href="http://www.qeautoparts.com">www.qeautoparts.com</a> for even better prices.</p><br/>
<p>${orderNum}
PICKUP LOCATION: <strong>${street}, ${city}, ${state} $zip</strong><br />
PICKUP CONTACT NUMBER: <strong>${phone}</strong><br /></p>
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
	FROM sales_items s, pickups p
	WHERE p.id = ${pickupId}
	AND s.sales_id = p.sales_id
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

SendTrackingEmail($email, 'Your order is ready for pickup', $html);
SendTrackingEmail('kbcware@yahoo.com', 'Your order is ready for pickup', $html);