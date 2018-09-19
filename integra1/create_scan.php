<?php

require_once('system/config.php');
require_once('system/item_utils.php');
require_once('system/stamps_utils.php');
require_once('system/acl.php');

$user = Login('sales');

$statusCodes = array(
	0 => 'Unspecified',
	1 => 'Scheduled',
	2 => 'Item Ordered / Waiting',
	3 => 'Ready for Dispatch',
	4 => 'Order Complete',
	90 => 'Cancelled',
    91 => 'Payment Pending',
	92 => 'Return Pending',
	93 => 'Return Complete',
	94 => 'Refund Pending',
	99 => 'Error',
);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$action = $_REQUEST['action'];

// temporary
define('SHIPFROM_ZIP', '33166');

if ($action == 'scan' && !empty($_POST['txid']))
{
	$pounds = ceil(array_sum($_POST['weight']));
	$xm = 0;
	$pm = 0;
	$om = 0;

	foreach ($_POST['service'] as $service)
	{
		if ($service == 'US-XM') $xm++;
		else if ($service == 'US-PM') $pm++;
		else $om++;
	}
		
	// future: support multiple shipfrom sites. now loading only the first one.
	$shipFrom = mysql_fetch_row(mysql_query("SELECT recipient_name, street, city, state, zip, phone FROM pickup_sites WHERE shipping_only = 1"));
	
	$from['name'] = $shipFrom[0];
	$aLines = explode(';', $shipFrom[1]);
	$from['address1'] = (count($aLines) >= 1) ? $aLines[0] : '';
	$from['address2'] = (count($aLines) >= 2) ? $aLines[1] : '';
	$from['address3'] = (count($aLines) >= 3) ? $aLines[2] : '';
	$from['city'] = $shipFrom[2];
	$from['state'] = $shipFrom[3];
	$from['zip'] = $shipFrom[4];
	$from['phone'] = $shipFrom[5];
	
	$from['address'] = $shipFrom[1];
	
	if (strpos($from['zip'], '-') !== FALSE)
	{
		$z = explode('-', $from['zip']);
		$from['zip'] = trim($z[0]);
		$from['zip_ext'] = trim($z[1]);
	}
	else $from['zip_ext'] = '';
	
	$z = explode(' ', $from['name']);
	
	$from['firstname'] = $z[0];
	if (count($z) > 1)
		$from['lastname'] = $z[1];

	$scanId = StampsUtils::CreateScan($_POST['txid'], $from, $pounds, $xm, $pm, $om);
	
	if (empty($scanId))
	{
		echo '<html><head></head><body><input type="button" onclick="window.location=';
		echo "'create_scan.php';";
		echo '" value="Back to Shipments Page" /><script>alert(';
		echo "'The scan form was not properly generated.";
		echo '\n\nThis happens when all the orders selected are already part of another SCAN form generated outside Integra.';
		echo "');</script></body></html>";
		return;
	}

	header("Location: scans/${scanId}.pdf");
	return;
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Integra :: Today's Shipments</title>
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
	<h2>Today's Shipments</h2>
	<p>Below is a list of all stamps created today. Please check which stamps you want to include in the SCAN form and request for pickup. Orders marked completed are included by default.</p>
	<p>Those that are marked "OTHER BATCH" have already been included in another SCAN form generated outside Integra.</p>
	<table id="table_checklist" class="table table-bordered table-condensed">
		<thead>
			<tr>
				<th><input type="checkbox" id="check_all" /></th>
				<th>Date</th>
				<th>Record #</th>
				<th>Name</th>
				<th>Tracking</th>
				<th>Class</th>
				<th>Weight</th>
				<th>Status</th>
				<th>SCAN</th>
				<th>Pickup</th>
				<th>Remarks</th>
			</tr>
		</thead>
		<tbody>
<?
$q = <<<EOQ
SELECT st.id, s.record_num, s.id, s.buyer_name, st.tracking_num, SUBSTR(st.service, 1, 5) as class, pounds + (ounces / 16) as weight, s.status, scan_id, pickup_ref, s.remarks, st.print_date
FROM stamps st, sales s
WHERE st.sales_id = s.id
AND s.status = 4
AND st.scan_id = ''
AND print_date >= CURDATE()
AND s.country IN ('US', 'VI', 'PR', 'GU', 'AK')
ORDER BY st.print_date
EOQ;

$res = mysql_query($q);
$rowCount = 0;

while ($row = mysql_fetch_row($res))
{
	$rowCount++;
	echo '<tr>';
	
	if (empty($row[8]))
		echo '<td><input class="check_row" type="checkbox" name="txid[]" value="'
		. $row[0] . '" ' . ($row[7] == 4 ? ' checked ' : '')
		. '/><input type="hidden" name="weight[]" value="'
		. $row[6] . '" /><input type="hidden" name="service[]" value="'
		. $row[5] . '" /></td>';
	else
		echo '<td></td>';

	echo '<td>' . htmlentities($row[11]) . '</td>';
	echo '<td><a target="_blank" href="order.php?sales_id=' . $row[2] . '">' . htmlentities($row[1]) . '</a></td>';
	echo '<td><a target="_blank" href="order.php?sales_id=' . $row[2] . '">' . htmlentities($row[3]) . '</a></td>';
	echo '<td><a target="_blank" href="show_stamp.php?id=' . $row[0] . '">' . htmlentities($row[4]) . '</a></td>';
	echo '<td style="white-space: nowrap">' . htmlentities(StampsUtils::$serviceTypes[$row[5]]) . '</td>';
	echo '<td>' . $row[6] . '</td>';
	echo '<td>' . htmlentities($statusCodes[$row[7]]) . '</td>';
	
	if ($row[8] == 'OTHER BATCH')
		echo '<td>' . htmlentities($row[8]) . '</td>';
	else if (empty($row[8]))
		echo '<td></td>';
	else
		echo '<td><a target="_blank" href="scans/' . $row[8] . '.pdf">' . htmlentities($row[8]) . '</a></td>';
	
	echo '<td>' . htmlentities($row[9]) . '</td>';

	if (preg_match('/^Waiting for W1 truck order \d+ from [^\.]+\.$/i', $row[10]))
		echo '<td></td>';
	else if (preg_match('/^Waiting for W1 truck order \d+ from [^\.]+\. Waiting for W1 truck order \d+ from [^\.]+\.$/i', $row[10]))
		echo '<td></td>';
	else if (preg_match('/^Waiting for W2 bulk order \d+ from [^\.]+\.$/i', $row[10]))
		echo '<td></td>';
	else
		echo '<td>' . htmlentities($row[10]) . '</td>';

	echo "</tr>\n";
}
?>
		</tbody>
	</table>
	
	<p><span id="check_count">0</span> of <?=$rowCount?> stamp<?=($rowCount > 1) ? 's' : ''?> selected.</p>

	<button id="create_scan" type="submit" class="btn btn-primary">Create SCAN Form & Request Pickup</button>
	<br/>
	<br/>
	
	<h3>All SCAN Forms Generated Today</h3>
	<p>Below is a list of all SCAN forms generated today. Please print them out for USPS staff.</p>
	<table id="scan_list" class="table table-bordered table-condensed">
		<thead>
			<tr>
				<th>SCAN #</th>
				<th># of Stamps</th>
			</tr>
		</thead>
		<tbody>
<?
$q = <<<EOQ
SELECT scan_id, COUNT(*)
FROM stamps
WHERE DATE(create_date) = DATE(CONVERT_TZ(UTC_TIMESTAMP(), 'GMT', 'US/Eastern'))
AND scan_id != 'OTHER BATCH'
AND scan_id > ''
GROUP BY 1
ORDER BY create_date
EOQ;

$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
{
	echo '<tr>';
	echo '<td><a target="_blank" href="scans/' . $row[0] . '.pdf">' . htmlentities($row[0]) . '</a></td>';
	echo '<td>' . htmlentities($row[1]) . '</td>';
	echo "</tr>\n";
}
?>
		</tbody>
	</table>
</form>
<br/>
<br/>
<script src="js/jquery.min.js"></script>
<script>
function createScanClicked()
{
	$("input:checkbox:not(:checked)").each(function(i, v)
	{
		$(this).closest('tr').find('input[type=hidden]').remove();
	});
	
	$('#stamp_form').submit();
}

function checkAllClicked()
{
	$('input[type=checkbox]').prop('checked', $('#check_all').prop('checked'));
	checkRowClicked();
}

function checkRowClicked()
{
	$('#check_count').text($('input[type=checkbox]:checked').length);
}

$(document).ready(function()
{
	$('#create_scan').click(createScanClicked);
	$('#check_all').click(checkAllClicked);
	$('.check_row').click(checkRowClicked);
	checkRowClicked();
});
</script>
</body>
</html>