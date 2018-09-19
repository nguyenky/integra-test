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

$complete = $_POST['complete'];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

if ($complete == 'ok')
{
	mysql_query("UPDATE pickups SET status = 4, pickup_date = NOW() WHERE status = 3 AND id = ${pickupId}");
}

$q = <<<EOD
SELECT p.order_date, s.record_num, s.buyer_name, s.street, s.city, s.state, s.zip, s.phone, s.email, ps.name, s.buyer_id, IFNULL(p.pickup_date, NOW()), p.status
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

$orderDate = $row[0];
$recordNum = htmlentities($row[1]);
$name = htmlentities($row[2]);
$street = htmlentities($row[3]);
$city = htmlentities($row[4]);
$state = convert_state(preg_replace('/[^a-zA-Z0-9 ]/s', '', $row[5]), 'abbrev');
$zip = htmlentities($row[6]);
$phone = htmlentities($row[7]);
$email = htmlentities($row[8]);
$location = htmlentities($row[9]);
$buyerId = htmlentities($row[10]);
$pickupDate = $row[11];
$status = $row[12];

if ($status != 3 && $status != 4)
{
	echo "Order is not yet ready for pickup.";
	return;
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Local Pickup - Invoice &amp; Proof of Pickup</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		body
		{
			font-family: tahoma, verdana;
			font-size: 12px;
		}
		@media print
		{
			input
			{
				display:none;
			}
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<br/>
<p>Thank you for ordering from us. Please sign this form upon pickup of your order.</p>
<br/>

<table border="1" cellpadding="6" cellspacing="0">
	<tr>
		<td>Record Number:</td>
		<td><?=$recordNum?></td>
	</tr>
	<tr>
		<td>eBay ID:</td>
		<td><?=$buyerId?></td>
	</tr>
	<tr>
		<td>Pickup Location:</td>
		<td><?=$location?></td>
	</tr>
	<tr>
		<td>Order Date:</td>
		<td><?=$orderDate?></td>
	</tr>
	<tr>
		<td>Name:</td>
		<td><?=$name?></td>
	</tr>
	<tr>
		<td>Address:</td>
		<td><?="${street}, ${city}, ${state} ${zip}"?></td>
	</tr>
	<tr>
		<td>Phone:</td>
		<td><?=$phone?></td>
	</tr>
	<tr>
		<td>Email:</td>
		<td><?=$email?></td>
	</tr>
	<tr>
		<td>Pickup Date:</td>
		<td><?=$pickupDate?></td>
	</tr>
</table>
<br/>
<br/>
<table border="1" cellpadding="6" cellspacing="0">
	<thead>
		<tr>
			<th>QUANTITY</th>
			<th>ITEM</th>
		</tr>
	</thead>
	<tbody>
<?
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

	echo "<tr><td>${qty}</td><td>" . htmlentities($name) . "</td></tr>\r\n";
}
?>

	</tbody>
</table>
<br/>
<br/>
<br/>
__________________________________<br/>
Customer's Signature
<br/>
<br/>
<br/>
<form method="POST" onsubmit="return confirm('Click OK if the customer has picked up the item and signed the proof of pickup form.');">
	<input type="button" value="Print Page" onclick="printpage()" />
<?php
	if ($status == 3)
	{
?>
	<input type="hidden" name="complete" value="ok" />
	<input type="submit" value="Complete Order" />
<?php
	}
?>
	<input type="button" onclick="window.location='pickups.php';" value="Back to Orders List" />
</form>
</center>
<script>
function printpage()
{
	window.print();
}
</script>
</body>
</html>