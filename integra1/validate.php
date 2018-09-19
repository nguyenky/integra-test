<?php

require_once('system/config.php');
require_once('system/stamps_utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

if (empty($salesId)) return;

$result = [
	'skip' => true,
	'tracking_num' => null,
	'carrier' => null,
	'status' => null,
	'id' => $salesId
];

$row = mysql_fetch_row(mysql_query(query("SELECT status, record_num, buyer_name, street, city, state, zip, country, speed, tracking_num, fulfilment, weight FROM sales WHERE id = %d", $salesId)));
if (empty($row))
{
	echo json_encode($result);
	return;
}

$status = $row[0];
$recordNum = $row[1];
$buyerName = $row[2];
$street = $row[3];
$city = $row[4];
$state = convert_state($row[5], 'abbrev');
$zip = $row[6];
$country = $row[7];
$speed = $row[8];
$trackingNum = $row[9];
$fulfilment = $row[10];
$weight = $row[11];

if (!empty($trackingNum)
		|| $status == 90
		|| $status == 4
		|| $fulfilment != 3
		|| $speed == 'Next Day / Overnight'
		|| $speed == 'Second Day'
		|| $speed == 'Local Pick Up'
		|| $speed == 'International'
		|| $speed == 'ePacket'
		|| ($country != 'US' && $country != 'PR' && $country != 'GU' && $country != 'VI'))
{
	echo json_encode($result);
	return;
}

$preset = StampsUtils::LoadStampsPreset($salesId);
if (empty($preset))
{
    if (empty($weight) || $weight > 0.9375)
    {
        echo json_encode($result);
        return;
    }
    // default to first class mail, thick envelope if > 0 and <= 15 ounces (0.9375 pounds)
    else
    {
        $preset['service'] = 'US-FC|Thick Envelope';
        $preset['pounds'] = floor($weight);
        $preset['ounces'] = ceil(($weight - $preset['pounds']) * 16);
        $preset['length'] = 0;
        $preset['width'] = 0;
        $preset['height'] = 0;
    }
}

$aLines = explode(';', $street);
$address1 = (count($aLines) >= 1) ? trim($aLines[0]) : '';
$address2 = (count($aLines) >= 2) ? trim($aLines[1]) : '';
$address3 = (count($aLines) >= 3) ? trim($aLines[2]) : '';

$cleanRes = StampsUtils::CleanseAddress($buyerName, $address1, $address2, $address3, $city, $state, $zip, $country);
if ($cleanRes['address_match'] != 'true')
{
	echo json_encode($result);
	return;
}

$to['name'] = $cleanRes['name'];
$to['address1'] = $cleanRes['address1'];
$to['address2'] = $cleanRes['address2'];
$to['address3'] = $cleanRes['address3'];
$to['city'] = $cleanRes['city'];
$to['state'] = $cleanRes['state'];
$to['zip'] = $cleanRes['zip'];
$to['country'] = $cleanRes['country'];

if (isset($cleanRes['zip_ext']) && !empty($cleanRes['zip_ext']))
	$to['zip'] .= '-' . $cleanRes['zip_ext'];

$service = $preset['service'];
$pounds = $preset['pounds'];
$ounces = $preset['ounces'];
$length = $preset['length'];
$width = $preset['width'];
$height = $preset['height'];
$material = '';
$user = $_REQUEST['user'];
$validateOnly = true;

$shipFrom = mysql_fetch_row(mysql_query("SELECT recipient_name, street, city, state, zip, phone FROM pickup_sites WHERE shipping_only = 1"));
$from['name'] = $shipFrom[0];
$aLines = explode(';', $shipFrom[1]);
$from['address1'] = (count($aLines) >= 1) ? trim($aLines[0]) : '';
$from['address2'] = (count($aLines) >= 2) ? trim($aLines[1]) : '';
$from['address3'] = (count($aLines) >= 3) ? trim($aLines[2]) : '';
$from['city'] = $shipFrom[2];
$from['state'] = $shipFrom[3];
$from['zip'] = $shipFrom[4];
$from['phone'] = str_replace('-', '', $shipFrom[5]);

$res = StampsUtils::CreateStamp($salesId, $recordNum, $from, $to, $service, $pounds, $ounces, $length, $width, $height, $material, $user, $validateOnly);

if ($res['success'])
	$result['skip'] = false;

$row = mysql_fetch_row(mysql_query(query("SELECT status, tracking_num, carrier FROM sales WHERE id = %d", $salesId)));
$result['status'] = $row[0];
$result['tracking_num'] = $row[1];
$result['carrier'] = $row[2];

echo json_encode($result);
return;
