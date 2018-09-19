<?php

require_once('system/config.php');
require_once('system/acl.php');

//$user = Login('sales');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$q = <<<EOQ
SELECT order_date, order_id, total
FROM direct_shipments
WHERE supplier = 1
AND is_bulk = 1
ORDER BY id DESC LIMIT 20
EOQ;

$res = mysql_query($q);

$imcOrders = [];

while ($row = mysql_fetch_row($res))
{
	$order = [];
	$order['order_date'] = $row[0];
	$order['order_id'] = $row[1];
	$order['total'] = $row[2];
	$imcOrders[] = $order;
}

$url = IMC_WEB_EXPORT_HOST;
$username = IMC_WEB_EXPORT_USERNAME;
$password = IMC_WEB_EXPORT_PASSWORD;
$store = IMC_WEB_EXPORT_STORE;

$cookie = tempnam("/tmp", "imcweb");

$ch = curl_init();
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);

curl_setopt($ch, CURLOPT_URL, "${url}Logon");
curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId=${store}&catalogId=${store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId=${username}&logonPassword=${password}&logonImg.x=32&logonImg.y=15");
$output = curl_exec($ch);

curl_setopt($ch, CURLOPT_POSTFIELDS, '');
curl_setopt($ch, CURLOPT_POST, false);

foreach ($imcOrders as &$order)
{
	curl_setopt($ch, CURLOPT_URL, "${url}OrderDetailCmd?orderId=" . $order['order_id']);
	$output = curl_exec($ch);
	
	$re = "/PO #.+?width[^>]+>(?<po>[^<]+)</is"; 
	preg_match($re, $output, $match);
	$order['po'] = trim($match['po']);
	
	$re = "/Invoice #.+?InvoiceDetailCmd[^>]+>(?<invoice>[^<]+)</is"; 
	preg_match($re, $output, $match);
	$order['invoice'] = trim($match['invoice']);

	$re = '/productId=(?<productId>\d+)&orderedAsPartNumber=(?<keyedAs>[^"]+)">(?<partNum>[^<]+)<.+?nameCol"><span>(?<desc>[^<]+)<.+?unitCol"><span>\$(?<unitPrice>[^<]+)<.+?qtyCol"><span>(?<qty>\d+)<.+?center"><span>(?<status>[^<]+)</is'; 
	preg_match_all($re, $output, $matches, PREG_SET_ORDER);
	
	$numProcessing = 0;
	$numCompleted = 0;
	$numMisc = 0;
	$unshipped = [];

	foreach ($matches as $match)
	{
		$partNum = trim($match['partNum']);
		$status = trim($match['status']);
		$desc = trim($match['desc']);
		$qty = trim($match['qty']);
		
		if ($status == 'Completed')
			$numProcessing+=$qty;
		else if ($status == 'Processing')
			$numCompleted+=$qty;
		else
		{
			$numMisc+=$qty;
			$unshipped[] = "${qty}x ${partNum} (${desc})";
		}
	}

	$order['num_completed'] = $numCompleted;
	$order['num_processing'] = $numProcessing;
	$order['num_misc'] = $numMisc;
	$order['unshipped'] = implode(", ", $unshipped);
}

curl_setopt($ch, CURLOPT_URL, "${url}Logoff?langId=-1&storeId=${store}&catalogId=${store}&URL=LogonForm&isLogOff=0");
curl_exec($ch);
curl_close($ch);

unlink($cookie);


/*


$q = <<<EOQ
SELECT order_date, order_id, total
FROM direct_shipments
WHERE supplier = 2
AND is_bulk = 1
ORDER BY id DESC LIMIT 10
EOQ;

$res = mysql_query($q);

$ssfOrders = [];

while ($row = mysql_fetch_row($res))
{
	$order = [];
	$order['order_date'] = $row[0];
	$order['order_id'] = $row[1];
	$ssfOrders[] = $order;
}



$cookie = tempnam("/tmp", "ssfweb");

$ch = curl_init();
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

curl_setopt($ch, CURLOPT_POST, false);

curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/");
$output = curl_exec($ch);

$re = "/\"__VIEWSTATE\" value=\"(?<vs>[^\"]+)/i"; 
preg_match($re, $output, $matches);
$vs = urlencode($matches['vs']);

$re = "/\"__EVENTVALIDATION\" value=\"(?<ev>[^\"]+)/i"; 
preg_match($re, $output, $matches);
$ev = urlencode($matches['ev']);

$username = "2833599d";
$password = "eduardo";

$post = "ctl00_ScriptManager1_HiddenField=&__EVENTTARGET=ctl00%24HTMLcontent%24LoginButton_clickctl00%24myshop&__EVENTARGUMENT=${username}%7C%21%7C${password}&__VIEWSTATE=${vs}&__EVENTVALIDATION=${ev}";
print_r($post);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/");
$output = curl_exec($ch);

print_r($output);

curl_setopt($ch, CURLOPT_POSTFIELDS, '');
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/storefront/AbandonSession.aspx?act=lf&lid=0&t=" . time());
$output = curl_exec($ch);

unlink($cookie);
exit;
/*


curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId=${store}&catalogId=${store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId=${username}&logonPassword=${password}&logonImg.x=32&logonImg.y=15");
$output = curl_exec($ch);

curl_setopt($ch, CURLOPT_POSTFIELDS, '');
curl_setopt($ch, CURLOPT_POST, false);

foreach ($imcOrders as &$order)
{
	curl_setopt($ch, CURLOPT_URL, "${url}OrderDetailCmd?orderId=" . $order['order_id']);
	$output = curl_exec($ch);
	
	$re = "/PO #.+?width[^>]+>(?<po>[^<]+)</is"; 
	preg_match($re, $output, $match);
	$order['po'] = trim($match['po']);
	
	$re = "/Invoice #.+?InvoiceDetailCmd[^>]+>(?<invoice>[^<]+)</is"; 
	preg_match($re, $output, $match);
	$order['invoice'] = trim($match['invoice']);

	$re = '/productId=(?<productId>\d+)&orderedAsPartNumber=(?<keyedAs>[^"]+)">(?<partNum>[^<]+)<.+?nameCol"><span>(?<desc>[^<]+)<.+?unitCol"><span>\$(?<unitPrice>[^<]+)<.+?qtyCol"><span>(?<qty>\d+)<.+?center"><span>(?<status>[^<]+)</is'; 
	preg_match_all($re, $output, $matches, PREG_SET_ORDER);
	
	$numProcessing = 0;
	$numCompleted = 0;
	$numMisc = 0;
	$unshipped = [];

	foreach ($matches as $match)
	{
		$partNum = trim($match['partNum']);
		$status = trim($match['status']);
		$desc = trim($match['desc']);
		$qty = trim($match['qty']);
		
		if ($status == 'Completed')
			$numProcessing+=$qty;
		else if ($status == 'Processing')
			$numCompleted+=$qty;
		else
		{
			$numMisc+=$qty;
			$unshipped[] = "${qty}x ${partNum} (${desc})";
		}
	}

	$order['num_completed'] = $numCompleted;
	$order['num_processing'] = $numProcessing;
	$order['num_misc'] = $numMisc;
	$order['unshipped'] = implode(", ", $unshipped);
}

curl_setopt($ch, CURLOPT_URL, "${url}Logoff?langId=-1&storeId=${store}&catalogId=${store}&URL=LogonForm&isLogOff=0");
curl_exec($ch);
curl_close($ch);
*/
//unlink($cookie);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Integra :: Supplier Shipments</title>
	<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" /> 
	<style>
		#shipment_list
		{
			margin-top: 30px;
			margin-left: 30px;
			margin-right: 30px;
		}
		#scan_list
		{
			width: 400px;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>

<form role="form" class="form-horizontal" id="shipment_list" method="POST" action="create_scan.php?action=scan">
	<h2>Last 20 W1 Truck Orders</h2>
	<table id="table_checklist" class="table table-bordered table-condensed">
		<thead>
			<tr>
				<th>Order Date</th>
				<th>Order #</th>
				<th>PO #</th>
				<th>Invoice #</th>
				<th>Order Total</th>
				<th># of Items Processing</th>
				<th># of Items Shipped</th>
				<th># of Items Unshipped</th>
				<th>Unshipped Items</th>
			</tr>
		</thead>
		<tbody>
<?
foreach ($imcOrders as $order)
{
	echo '<tr>';
	
	echo '<td>' . htmlentities($order['order_date']) . '</td>';
	echo '<td>' . htmlentities($order['order_id']) . '</td>';
	echo '<td>' . htmlentities($order['po']) . '</td>';
	echo '<td>' . htmlentities($order['invoice']) . '</td>';
	echo '<td>' . htmlentities($order['total']) . '</td>';
	echo '<td>' . htmlentities($order['num_processing']) . '</td>';
	echo '<td>' . htmlentities($order['num_completed']) . '</td>';
	echo '<td>' . htmlentities($order['num_misc']) . '</td>';
	echo '<td>' . htmlentities($order['unshipped']) . '</td>';
	
	echo '</tr>';
}
?>
		</tbody>
	</table>
<br/>
<script src="js/jquery.min.js"></script>
</body>
</html>