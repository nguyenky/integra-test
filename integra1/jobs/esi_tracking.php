<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');

$imap = imap_open(SYSTEM_EMAIL_IMAP_HOST . 'ESI_Tracking', SYSTEM_EMAIL_USERNAME, SYSTEM_EMAIL_PASSWORD);
$emails = imap_search($imap, 'ALL');

$trackList = array();
 
if ($emails)
{
	foreach ($emails as $email_number)
	{
		$message = trim(utf8_encode(quoted_printable_decode(imap_fetchbody($imap, $email_number, '1'))));
		
		if (stristr($message, 'United Parcel Service'))
		{
			preg_match_all(
			'/Tracking Number:\s+(?<tracking>\S+)\s+Reference Number 1:\s+(?<esi_id>\S+)\s+Reference Number 2:\s+(?<eoc_id>\S+)/ims',
			$message, $matches, PREG_SET_ORDER);
		
			foreach ($matches as $match)
			{
				$eocId = trim($match['eoc_id']);
				$trackList[$eocId]['tracking'] = trim($match['tracking']);
				$trackList[$eocId]['carrier'] = 'UPS';
				$trackList[$eocId]['esi_id'] = trim($match['esi_id']);
			}
		}
		else if (stristr($message, 'Jessica'))
		{
			preg_match_all('/PO\s*#\s*(?<eoc_id>[\w\-]+)[\s>]+(?<tracking>#?9[0-9 ]+)/ims', $message, $matches, PREG_SET_ORDER);

			foreach ($matches as $match)
			{
				$eocId = trim($match['eoc_id']);
				$trackList[$eocId]['tracking'] = str_replace('#', '', str_replace(' ', '', $match['tracking']));
				
				if (stristr($tracking, '1Z'))
					$trackList[$eocId]['carrier'] = 'UPS';
				else
					$trackList[$eocId]['carrier'] = 'USPS';
			}
		}
		else
		{
			SendSystemEmail('Possible Manual Tracking Upload Required',
			'The following message was received in the tracking inbox, but the scripts were unable to extract the tracking information:\r\n\r\n' . $message, false);
		}
		
		imap_delete($imap, $email_number);
	}
}

imap_close($imap, CL_EXPUNGE);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

foreach ($trackList as $recordNum => $track)
{
	$q = "SELECT id, tracking_num FROM sales WHERE record_num = '%s'";
	$row = mysql_fetch_row(mysql_query(query($q, $recordNum)));
	if (empty($row))
	{
		SendAdminEmail('Missing W3 order for tracking update', "Record Number: ${recordNum}\r\nTracking: " . $track['tracking'], false);
		continue;
	}
	else
	{
		$salesId = $row[0];
		$existingTracking = $row[1];
	}
	
			
	if (empty($existingTracking)) // temporarily prevent overwriting of tracking numbers
	{
		$q=<<<EOQ
		UPDATE sales SET tracking_num = '%s', carrier = '%s', fulfilled = 1, fake_tracking = 0, status = 4
		WHERE id = %d
EOQ;
		mysql_query(query($q, $track['tracking'], $track['carrier'], $salesId));
	}

	if (!empty($track['esi_id']))
	{
		$q=<<<EOQ
		UPDATE direct_shipments SET tracking_num = '%s'
		WHERE order_id = '%s' AND supplier = 3
EOQ;
		mysql_query(query($q, $track['tracking'], $track['esi_id']));
	}
	else
	{
		$q=<<<EOQ
		UPDATE direct_shipments SET tracking_num = '%s'
		WHERE supplier = 3 AND order_id IN (SELECT order_id FROM direct_shipments WHERE sales_id = '%s')
EOQ;
		mysql_query(query($q, $track['tracking'], $salesId));
	}
	
	if (empty($existingTracking))
	{
		$s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
		$s = file_get_contents("http://integra.eocenterprise.com/tracking_email.php?sales_id=${salesId}");
	}
}
