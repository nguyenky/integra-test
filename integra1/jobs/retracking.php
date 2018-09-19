<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
	SELECT DISTINCT s.id
	FROM direct_shipments ims, sales s
	WHERE ims.sales_id = s.id
	AND ims.tracking_num > ''
	AND ims.is_bulk = 0
	AND ims.no_tracking = 0
	AND (s.tracking_num IS NULL OR LENGTH(s.tracking_num) = 0)
EOD;
$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$sync[] = $row[0];
}

if (!empty($sync))
{
	$sync = array_unique($sync);
	
	foreach ($sync as $salesId)
	{
		unset($trackList);
		$trackList = array();
		
		$sendEmail = true;

		$q = "SELECT tracking_num, fulfilled FROM sales WHERE id=${salesId}";
		$row = mysql_fetch_row(mysql_query($q));
		if (!empty($row))
		{
			if (!empty($row[0]))
				continue; //  temporarily prevent overwriting of tracking numbers
				//$trackList[] = $row[0];
				
			if (!empty($row[0]))
				$sendEmail = false;
		}

        $carrier = '';

		$q = "SELECT DISTINCT tracking_num FROM direct_shipments WHERE sales_id=${salesId} AND tracking_num > '' AND is_bulk = 0";
		$rows = mysql_query($q);
		while ($row = mysql_fetch_row($rows))
		{
			$trackList[] = $row[0];
            $prefix = substr($row[0], 0, 2);

            if (startsWith($trackList[0], '1Z'))
                $carrier = 'UPS';
            else if (startsWith($trackList[0], '9'))
                $carrier = 'USPS';
            else if (startsWith($trackList[0], 'D'))
                $carrier = 'OnTrac';
            else $carrier = 'FedEx';
		}
		
		$trackList = array_unique($trackList);
		sort($trackList);
		
		if (!empty($trackList))
		{
			$q=<<<EOQ
			UPDATE sales SET tracking_num = '%s', carrier = '%s', fulfilled = 1
			WHERE id = %d
EOQ;

			$newTracking = implode(',', $trackList);
			mysql_query(query($q, implode(',', $trackList), $carrier, $salesId));

			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 0, 1, 1, 1)", $salesId, "Status set to: Order Complete"));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 0, 1, 1, 1)", $salesId, "Tracking set to: " . $newTracking . " - " . $carrier));
			
			$s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
			if ($sendEmail)
				$s = file_get_contents("http://integra.eocenterprise.com/tracking_email.php?sales_id=${salesId}");
		}
	}
}

mysql_close();

?>
