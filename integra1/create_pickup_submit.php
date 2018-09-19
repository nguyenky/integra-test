<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/imc_utils.php');

foreach ($_POST as $key => $val)
{
	if (empty($val))
		continue;

	if (!startsWith($key, 'w1order_'))
		continue;

	$fields = explode('_', $key);
	
	if (empty($fields) || count($fields) != 3)
		continue;
	
	$siteId = $fields[1];
	$sku = $fields[2];

	$skus[$sku] = $val;
	$descs[$sku] = $_POST["w1desc_${sku}"];
	$prices[$sku] = $_POST["w1price_${sku}"];
}

if (empty($skus))
	return;

$recordNum = $_POST['recordNum'];
$name = $_POST['name'];
$email = trim(strtolower($_POST['email']));
$phone = $_POST['phone'];
$pickupSiteId = $_POST['site_id'];
$agent = $_POST['agent'];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q=<<<EOQ
SELECT recipient_name, street, city, state, zip, phone, ipo_username, ipo_password
FROM pickup_sites
WHERE id = '%s'
EOQ;
$row = mysql_fetch_row(mysql_query(sprintf($q,
	cleanup($pickupSiteId))));

$shipName = $row[0];
$shipStreet = $row[1];
$shipCity = $row[2];
$shipState = $row[3];
$shipZip = $row[4];
$shipPhone = $row[5];
$ipoUsername = $row[6];
$ipoPassword = $row[7];

$results = ImcUtils::OrderItems($siteId, $skus, "${name} c/o ${shipName}", $shipStreet, $shipCity, $shipState, $shipZip, $shipPhone, $recordNum, "OUR TRUCK", $ipoUsername, $ipoPassword);

if ($results['success'] == 1)
{
	$internalId = $results['message'];
	$salesId = SaveManualOrder($skus, $prices, $descs, $internalId, $recordNum, $email, $name, $shipStreet, $shipCity, $shipState, $shipZip, $shipPhone, "Pickup", $agent);
	
	$q = <<<EOD
	INSERT INTO pickups (buyer_id, sku, site_id, status, sales_id, added_date, order_date)
	VALUES ('%s', '%s', '%s', 2, '%s', '%s', '%s')
EOD;
	mysql_query(sprintf($q,
		cleanup($email),
		cleanup(implode(',', array_keys($skus))),
		cleanup($pickupSiteId),
		cleanup($salesId),
		cleanup(gmdate('Y-m-d H:i:s')),
		cleanup(gmdate('Y-m-d H:i:s'))));
	
	mysql_query("UPDATE sales SET auto_order = 2 WHERE id = ${salesId}");
	SaveDirectShipment($salesId, 1, $internalId, null, $results['subtotal'], $results['core'], $results['shipping'], $results['total'], null, false, true);
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Create Local Pickup Order</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" type="text/css" href="datagrid/styles/x-blue/style.css">
	<style>
		h1
		{
			text-align: left !important;
		}
		body
		{
			padding: 20px;
			font-family: tahoma, verdana;
			font-size: 12px;
		}
	</style>
  </head>
<body>
	<?php include_once("analytics.php") ?>
	<h1 class="x-blue_dg_caption">Local Pickup Submission Results</h1>
	<ul>
<?php
	if ($results['success'] == 1)
		echo "<li>Order accepted for warehouse 1 (" . ImcUtils::$siteIDs[$siteID] . ") under order ID " . $results['message'] . ". Included items:</li>\r\n";
	else
		echo "<li>Order for warehouse 1 (" . ImcUtils::$siteIDs[$siteID] . ") was rejected. Reason: " . $results['message'] . ". Affected items:</li>\r\n";

	echo "<ul>\r\n";
	foreach ($skus as $sku => $qty)
		echo "<li>${qty}x ${sku}</li>\r\n";
	echo "</ul>\r\n";
?>
	</ul>
	<br/>
	<a href="javascript:closeWindow();">Close this window</a>
	<br/>
	<a href='order.php?sales_id=<?=$salesId?>' class='x-blue_dg_label'>Go to Order Details page</a>
	<script>
	function closeWindow()
	{
		window.open('','_self','');
		window.close();
	}
	</script> 
</body>
</html>