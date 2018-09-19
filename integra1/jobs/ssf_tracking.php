<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');

$username = SSF_USERNAME;
$password = SSF_PASSWORD;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = "SELECT id, order_id, order_id2 FROM direct_shipments WHERE supplier = 2 AND order_date > DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND no_tracking = 0 AND (tracking_num IS NULL OR LENGTH(tracking_num) = 0) ORDER BY id DESC";
$rows = mysql_query($q);

$carriers = array();

while ($row = mysql_fetch_row($rows))
{
	$dsList[] = $row[0];
	$orderIds[$row[0]] = $row[1];
	$refIds[$row[0]] = $row[2];
}

foreach ($dsList as $id)
{
	$orderId = $orderIds[$id];
	$refId = $refIds[$id];
	
	if (empty($refId))
		continue;
	
	$url = "https://www.ssfparts.com/SSFConnect-v1.0/tracking/freightTracking.asp?refID="
	. urlencode($refId) . "&cuID=" . urlencode($username) . "&cuPass=" . urlencode($password);	
	$xml = file_get_contents($url);
	
	$res = XMLtoArray($xml);
	$tracking = trim(asearch($res, 'TRACKNUM1'));
	$carrier = trim(asearch($res, 'CARRIER'));
	
	if (!empty($tracking) && strlen($tracking) > 10)
	{
		$q=<<<EOQ
		UPDATE direct_shipments SET tracking_num = '%s'
		WHERE id = %d AND supplier = 2
EOQ;
		mysql_query(query($q, $tracking, $id));
		
        $rows = mysql_query(query("SELECT dss.sales_id FROM direct_shipments ds, direct_shipments_sales dss WHERE ds.order_id = dss.order_id AND dss.order_id = '%s' AND ds.is_bulk = 0", $orderId));

		while ($row = mysql_fetch_row($rows))
		{
			$salesId = $row[0];
			$sync[] = $salesId;
			$carriers[$salesId] = $carrier;
			
			file_put_contents("../logs/ssf_track.txt", date('Y-m-d H:i:s') . "] sales_id: ${salesId}, DS id: ${id}\n${xml}\n\n", FILE_APPEND);
		}
	}
}

if (!empty($sync))
{
	$sync = array_unique($sync);

	foreach ($sync as $salesId)
	{
		unset($trackList);
		$trackList = array();

		$q = "SELECT tracking_num FROM sales WHERE id = ${salesId} AND fake_tracking = 0";
		$row = mysql_fetch_row(mysql_query($q));
		if (!empty($row))
		{
			if (!empty($row[0]))
				continue; // temporarily prevent overwriting of tracking numbers
				//$trackList[] = $row[0];
		}

		$q = "SELECT DISTINCT ds.tracking_num FROM direct_shipments ds, direct_shipments_sales dss WHERE ds.order_id = dss.order_id AND dss.sales_id = ${salesId} AND ds.tracking_num > '' AND ds.is_bulk = 0";
		$rows = mysql_query($q);
		while ($row = mysql_fetch_row($rows))
		{
			$trackList[] = $row[0];
		}
		
		$trackList = array_unique($trackList);
		sort($trackList);
		
		if (!empty($trackList))
		{
			$q=<<<EOQ
			UPDATE sales SET tracking_num = '%s', carrier = '%s', fulfilled = 1, fake_tracking = 0, status = 4
			WHERE id = %d
EOQ;

			$newTracking = implode(',', $trackList);
			$carrier = $carriers[$salesId];
			mysql_query(query($q, implode(',', $trackList), $carrier, $salesId));

			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 0, 1, 1, 1)", $salesId, "Status set to: Order Complete"));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 0, 1, 1, 1)", $salesId, "Tracking set to: " . $newTracking . " - " . $carrier));
			
			$s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
			$s = file_get_contents("http://integra.eocenterprise.com/tracking_email.php?sales_id=${salesId}");
		}
	}
}

mysql_close();

?>
