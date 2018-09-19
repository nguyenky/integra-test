<?php

require_once('system/config.php');
require_once('system/item_utils.php');
require_once('system/endicia_utils.php');
require_once('system/stamps_utils.php');
require_once('system/e_utils.php');
require_once('system/acl.php');

$user = Login('shipgrid');
$user = strtolower($user);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

if (empty($salesId))
{
	header('Location: barcode.php');
	return;
}

$record_num = $_REQUEST['record_num'];
$c_hash = $_REQUEST['c_hash'];
$c_name = $_REQUEST['c_name'];
$c_address1 = $_REQUEST['c_address1'];
$c_address2 = $_REQUEST['c_address2'];
$c_address3 = $_REQUEST['c_address3'];
$c_city = $_REQUEST['c_city'];
$c_state = $_REQUEST['c_state'];
$c_zip = $_REQUEST['c_zip'];
$c_country = $_REQUEST['c_country'];

$e_hash = $_REQUEST['e_hash'];
$e_name = $_REQUEST['e_name'];
$e_address1 = $_REQUEST['e_address1'];
$e_address2 = $_REQUEST['e_address2'];
$e_address3 = $_REQUEST['e_address3'];
$e_city = $_REQUEST['e_city'];
$e_state = $_REQUEST['e_state'];
$e_zip = $_REQUEST['e_zip'];
$e_country = $_REQUEST['e_country'];

$service = $_REQUEST['service'];

$pounds = $_REQUEST['pounds'];
$ounces = $_REQUEST['ounces'];

$length = $_REQUEST['length'];
$width = $_REQUEST['width'];
$height = $_REQUEST['height'];
$material = $_REQUEST['material'];

$action = $_REQUEST['action'];

$validateOnly = $_REQUEST['validate_only'];

$row = mysql_fetch_row(mysql_query(query("SELECT status, record_num, buyer_name, street, city, state, zip, speed, remarks, total, country, tracking_num, weight FROM sales WHERE id = %d", $salesId)));

if (empty($row))
{
	header('Location: barcode.php');
	return;
}

$status = $row[0];
$record_num = $row[1];
$speed = $row[7];
$remarks = $row[8]; // deprecated
$totalPaid = $row[9];
$country = $row[10];
$trackingNum = $row[11];
$savedWeight = $row[12];
$savedPounds = floor($savedWeight);
$savedOunces = ceil(($savedWeight - $savedPounds) * 16);

$stampRow = mysql_fetch_row(mysql_query(query("SELECT id FROM stamps WHERE sales_id = %d ORDER BY create_date DESC LIMIT 1", $salesId)));

if (!empty($stampRow) && !empty($stampRow[0]))
    $existingStamp = $stampRow[0];
else $existingStamp = '';

// temporary
define('SHIPFROM_ZIP', '33166');

if ($action == 'stamp')
{
    // redirect to existing stamp if there is one (don't create a second one)
    if (!empty($existingStamp))
    {
        header("Location: show_stamp.php?id=" . $existingStamp);
        return;
    }

	// future: support multiple shipfrom sites. now loading only the first one.
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

	$to['name'] = $c_name;
	$to['address1'] = $c_address1;
	$to['address2'] = $c_address2;
	$to['address3'] = $c_address3;
	$to['city'] = $c_city;
	$to['state'] = $c_state;
	$to['zip'] = $c_zip;
    $to['country'] = $c_country;

    if ($country != 'US' && $country != 'PR' && $country != 'GU' && $country != 'VI')
    {
        $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ', 'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή');
        $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'α', 'Ε', 'ε', 'Ο', 'ο', 'Ω', 'ω', 'Ι', 'ι', 'ι', 'ι', 'Υ', 'υ', 'υ', 'υ', 'Η', 'η');

        $to['name'] = str_replace($a, $b, $c_name);
        $to['address1'] = str_replace($a, $b, $c_address1);
        $to['address2'] = str_replace($a, $b, $c_address2);
        $to['address3'] = str_replace($a, $b, $c_address3);
        $to['city'] = str_replace($a, $b, $c_city);
        $to['state'] = str_replace($a, $b, $c_state);

        $res = EndiciaUtils::CreateStamp($salesId, $record_num, $from, $to, $service, $pounds, $ounces, $length, $width, $height, $material, $user, $validateOnly);
    }
    else
    {
        StampsUtils::SaveStampsPreset($salesId, $service, $pounds, $ounces, $length, $width, $height, $user, $speed);
        $res = StampsUtils::CreateStamp($salesId, $record_num, $from, $to, $service, $pounds, $ounces, $length, $width, $height, $material, $user, $validateOnly);
    }

	if ($res['success'])
	{
		header("Location: show_stamp.php?id=" . $res['txid']);
		return;
	}
	
	$stampError = $res['error'];
}

date_default_timezone_set('America/New_York');

$curMinute = intval(ltrim(date('i'),'0'));

if ($curMinute < 30)
	$anchor = date('Y-m-d H:00:00');
else $anchor = date('Y-m-d H:30:00');

$progressRow = mysql_fetch_row(mysql_query(query("SELECT IFNULL(COUNT(*), 0) FROM stamps WHERE print_date >= '%s' AND email = '%s'", $anchor, $user)));
$progress = $progressRow[0];

$goalRow = mysql_fetch_row(mysql_query(query("SELECT goal FROM goals WHERE metric = 0 AND (email = '%s' OR email = '') ORDER BY email DESC LIMIT 1", $user)));
$progressGoal = $goalRow[0];

$progressPct = ceil($progress * 100 / $progressGoal);
if ($progressPct > 100) $progressPct = 100;

if ($progressPct >= 100)
    $progressColor = 'success';
else if ($progressPct >= 65)
    $progressColor = 'warning';
else
    $progressColor = 'danger';
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Integra :: Order Shipment Tool</title>
	<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
	<style>
		#stamp_form
		{
			margin-top: 30px;
			margin-left: 30px;
		}
		.limit_div
		{
			width: 700px;
		}
		.table-fixed
		{
			table-layout: fixed;
		}
		#pounds, #ounces, #length, #width, #height
		{
			width: 50px;
		}
		input[readonly]
		{
			cursor: auto !important;
		}
        .expedite
        {
            background-color: salmon !important;
        }
        .margin-top-50 {
        	margin-top: 50px !important;
        }
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>

<form role="form" class="form-horizontal" id="stamp_form" method="POST" action="ship.php?sales_id=<?=$salesId?>">
    <div class="pull-right" style="width: 300px;">
        <strong>Today's Goal</strong>
        <div class="progress">
            <div class="progress-bar progress-bar-<?=$progressColor?>" role="progressbar" aria-valuenow="<?=$progressPct?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$progressPct?>%;">
                <span class="sr-only"><?=$progressPct?>%</span>
            </div>
        </div>
    </div>
	<h2>EOC Order <?=$record_num?></h2>
	<? if ($status == 94): ?>
		<p class="well alert alert-danger">This order is currently marked REFUND PENDING. Please <strong>Do Not Ship Order</strong> and Return to Original Supplier.</p>
	<? endif; ?>

    <? if ($status == 90): ?>
        <p class="well alert alert-danger">This order is currently marked CANCELLED. Please double check before creating a stamp.</p>
    <? endif; ?>

    <? if ($status == 4): ?>
        <p class="well alert alert-success">This order is already marked COMPLETE.</p>
    <? endif; ?>

    <? if (!empty($existingStamp)): ?>
        <div class="well alert alert-warning">
            <p>This order has an existing stamp. Enter the envelope/box type, weight, and click Load Stamp to print the existing stamp and set the order to Complete, or create a new one below.</p>

            <div class="form-group form-inline">
				<div class="col-xs-offset-1 col-xs-10">
					<input id="material2" type="textbox" name="material" placeholder="Envelope/Box Type" style="width: 140px;"/>
					&nbsp;&nbsp;&nbsp;Weight:
					<input id="pounds2" type="textbox" name="pounds" value="<?=$savedPounds?>" style="width: 50px;"> lb.
					<input id="ounces2" type="textbox" name="ounces" value="<?=$savedOunces?>" style="width: 50px;"> oz.
					&nbsp;&nbsp;&nbsp;
					<button id="load_stamp" type="button" class="btn btn-primary">Load Stamp</button>
				</div>
            </div>
        </div>
    <? endif; ?>
	<p class="text-danger"><strong><?=$stampError?></strong></p>

<?
if (empty($e_name))
{
	$e_name = $row[2];
    $origAddress = $row[3];
	$aLines = explode(';', $origAddress);
	$e_city = $row[4];
	$e_state = convert_state($row[5], 'abbrev');
	$e_zip = $row[6];
    $e_country = $row[10];
	$e_address1 = (count($aLines) >= 1) ? trim($aLines[0]) : '';
	$e_address2 = (count($aLines) >= 2) ? trim($aLines[1]) : '';
	$e_address3 = (count($aLines) >= 3) ? trim($aLines[2]) : '';
}

if ($country != 'US' && $country != 'PR' && $country != 'GU' && $country != 'VI')
    $res = EndiciaUtils::CleanseAddress($e_name, $e_address1, $e_address2, $e_address3, $e_city, $e_state, $e_zip, $e_country);
else
    $res = StampsUtils::CleanseAddress($e_name, $e_address1, $e_address2, $e_address3, $e_city, $e_state, $e_zip, $e_country);

if ($res['address_match'] == 'true')
{
    if ($country != 'US' && $country != 'PR' && $country != 'GU' && $country != 'VI')
    {
        $addressClass = '';
        $addressError = '';
    }
    else
    {
        $addressClass = 'text-success';
        $addressError = 'This address is valid.';
    }

	$e_hash = '';
	$c_hash = $res['cleanse_hash'];
	$c_name = $res['name'];
	$c_address1 = $res['address1'];
	$c_address2 = $res['address2'];
	$c_address3 = $res['address3'];
	$c_city = $res['city'];
	$c_state = $res['state'];
	$c_zip = $res['zip'];

    if (isset($res['zip_ext']) && !empty($res['zip_ext']))
        $c_zip .= '-' . $res['zip_ext'];

    $c_country = $res['country'];
}
else
{
	if ($res['city_state_zip_ok'] == 'true')
	{
		$addressClass= 'text-warning';
		$addressError = 'The street address is ambiguous. Please either edit the address or use the address as entered.';
		$e_hash = $res['override_hash'];
		$c_hash = '';
		$c_name = $res['name'];
		$c_address1 = $res['address1'];
		$c_address2 = $res['address2'];
		$c_address3 = $res['address3'];
		$c_city = $res['city'];
		$c_state = $res['state'];
		$c_zip = $res['zip'] . '-' . $res['zip_ext'];
        $c_country = $res['country'];
	}
	else
	{
		$addressClass= 'text-danger';
		$addressError = 'This address is invalid. Please edit the address to proceed.';
		$e_hash = '';
		$c_hash = '';
		$c_name = '';
		$c_address1 = '';
		$c_address2 = '';
		$c_address3 = '';
		$c_city = '';
		$c_state = '';
		$c_zip = '';
        $c_country = '';
	}
}

$parts = GetOrderComponents($salesId);
$missingPart = false;
$totalWeight = 0;
$weightError = '';
$pounds = 0;
$ounces = 0;
$supplierCost = 0;
$lastPackagePreset = '';
?>

	<div class="row">
		<div class="col-xs-12 col-sm-6">
			<h3>Package Checklist</h3>
			<table id="table_checklist" class="table table-bordered table-condensed table-fixed">
				<thead>
					<tr>
						<th>Quantity</th>
						<th>MPN</th>
						<th>Brand</th>
						<th>Description</th>
						<th>Image</th>
					</tr>
				</thead>
				<tbody>
<?
foreach ($parts as $sku => $qty)
{
	if (startsWith($sku, 'PU'))
		$item = ItemUtils::GetPUItem($sku, $salesId);
	else if (startsWith($sku, 'WP'))
		$item = ItemUtils::GetWPItem($sku, $salesId);
	else if (startsWith($sku, 'TR'))
		$item = ItemUtils::GetTRItem($sku, $salesId);
	else if (startsWith($sku, 'EOCE'))
		$item = ItemUtils::GetESIItem($sku);
	else if (startsWith($sku, 'EOCS') || strpos($sku, '.') > 0)
		$item = ItemUtils::GetSSFItem($sku);
	else
		$item = ItemUtils::GetIMCItem($sku);
		
	$totalWeight += ($item['weight'] * $qty);
	$supplierCost += ($item['price'] * $qty);
		
	if (empty($item['weight']) || empty($qty))
	{
		$missingPart = true;

		if (empty($item['weight']) && empty($savedWeight))
			$weightError = 'The weight of one of the items was not found in the system. Please weigh the package manually.';

		if (empty($qty))
			$itemError = 'One of the items was not found in the system, or this order may contain product kits. Please visit the original listing(s) for more details on the contents of this order:';
	}

?>
				<tr>
					<td class="right"><?=$qty?></td>
					<td><?=$item['mpn']?></td>
					<td><?=$item['brand']?></td>
					<td><?=$item['desc']?></td>
					<td><img width='100%' src="<?=$item['image']?>" /></td>
				</tr>
<?
}

if (!empty($savedWeight))
	$totalWeight = $savedWeight;

$pounds = floor($totalWeight);
$ounces = ceil(($totalWeight - $pounds) * 16);

$res = mysql_query("SELECT ebay_item_id, amazon_asin, sku FROM sales_items WHERE sales_id = ${salesId}");
$listingUrls = array();
$listingImages = array();
while ($row = mysql_fetch_row($res))
{
	
	/*
	$idx = stripos($row[2], '$');
	
	if ($idx === FALSE || $idx > (strlen($row[2]) - 3))
		continue; 
	*/

	if (!empty($row[0]))
	{
		$listingUrls[$row[0]] = 'http://www.ebay.com/itm/' . $row[0];
		$item = EbayUtils::GetItem($row[0]);
		if (!empty($item['picture_big']))
			$listingImages['http://www.ebay.com/itm/' . $row[0]] = $item['picture_big'];
	}
	else
		$listingUrls[$row[1]] = 'http://www.amazon.com/gp/offer-listing/' . $row[1];
}

if ($missingPart)
	$totalWeight = 0;

?>
				</tbody>
			</table>

			<p class="text-danger"><strong><?=$itemError?></strong></p>

    <?
        $row = mysql_fetch_row(mysql_query(query("SELECT GROUP_CONCAT(CONCAT(ds.order_id, IF(IFNULL(s.etd, ds.etd) > '', CONCAT(' (ETD ', DATE_FORMAT(IFNULL(s.etd, ds.etd), '%%m/%%d'), ')'), '')) SEPARATOR ', ')
FROM direct_shipments ds, direct_shipments_sales dss, sales s
WHERE ds.order_id = dss.order_id
AND s.id = dss.sales_id
AND dss.sales_id = %d", $salesId)));
        $supplierOrders = $row[0];

        if (empty($supplierOrders))
            $supplierOrders = 'None';
    ?>

	        <label>Warehouse Orders: <strong><?=$supplierOrders?></strong></label><br/>
<?
	if (count($listingUrls) > 0 && !empty($itemError))
	{
		echo "<ul>";
		foreach ($listingUrls as $listing => $url)
		{
			echo "<li><a href='${url}' target='_blank'>${listing}</a></li>\n";
		}
		echo "</ul>";
	}
	
	if (!empty($listingImages))
	{
		echo '<h3>Kit Images in Store</h3>';
		foreach ($listingImages as $url => $img)
			echo "<a href='${url}' target='_blank'><img src='${img}' /></a><br/>\n";
	}
?>

			<div class="form-group" style="margin-top:30px;">
				<label for="c_name" class="col-xs-2 control-label">Tracking #</label>
				<div class="col-xs-7">
					<input id="tracking_num" type="textbox" class="form-control" name="tracking_num" value="<?=htmlentities($trackingNum)?>" /></td>
				</div>
				<div class="col-xs-3 text-right">
					<button id="save_tracking" type="button" class="btn btn-primary" disabled>Save Tracking</button>
				</div>
			</div>
		</div>

		<div class="col-xs-12 col-sm-6">
			<h3>Order History</h3>

			<table class="table table-condensed table-bordered table-fixed">
				<thead>
				<tr>
					<th>Date</th>
					<th>Entered By</th>
					<th>Remarks</th>
				</tr>
				</thead>
				<tbody id="order_history">
<?
$res = mysql_query(query(<<<EOQ
SELECT oh.ts, REPLACE(oh.email, '@eocenterprise.com', '') AS email, oh.remarks
FROM integra_prod.order_history oh, integra_prod.users u
WHERE oh.order_id = '%s'
AND u.email = '%s'
AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
AND oh.remarks > ''
ORDER BY oh.ts
EOQ
	, $salesId, $user));

while ($row = mysql_fetch_row($res)):
?>
				<tr>
					<td><?=$row[0]?></td>
					<td><?=$row[1]?></td>
					<td class="wrap"><?=nl2br($row[2])?></td>
				</tr>
<?
endwhile;
?>
				</tbody>
			</table>

			<div style="margin-bottom:10px">
				<textarea class="form-control" rows="4" placeholder="Type new history entry here." id="remarks"></textarea>
			</div>
			<div class="text-right">
				<input type="checkbox" id="mark_error"> <strong>Mark as Error</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				Remark for: &nbsp;
				<input type="checkbox" id="show_sales" checked> Sales &nbsp;
				<input type="checkbox" id="show_data" checked> Data &nbsp;
				<input type="checkbox" id="show_pricing" checked> Pricing &nbsp;
				<input type="checkbox" id="show_shipping" checked> Shipping &nbsp;&nbsp;&nbsp;
				<button class="btn btn-primary" id="add_history">Submit</button>
			</div>
		</div>
	</div>

	<div class="limit_div">
		<h3>Verify Address</h3>

		<input type="hidden" id="existing_stamp" name="existing_stamp" value="<?=$existingStamp?>" />
		<input type="hidden" id="validate_only" name="validate_only" value="0" />
		<input type="hidden" name="record_num" value="<?=$record_num?>" />
		<input type="hidden" name="c_hash" value="<?=$c_hash?>" />
		<input type="hidden" name="e_hash" value="<?=$e_hash?>" />

		<div class="form-group">
			<p class="text-center <?=$addressClass?>"><strong><?=$addressError?></strong></p>
		</div>

		<div class="form-group">
			<label for="c_name" class="col-xs-3 control-label">Recipient Name</label>
			<div class="col-xs-9">
				<input id="c_name" type="textbox" class="form-control c_address" name="c_name" value="<?=$c_name?>" readonly /></td>
				<input id="e_name" type="textbox" class="form-control e_address" name="e_name" value="<?=$e_name?>" style="display:none;" /></td>
			</div>
		</div>

		<div class="form-group">
			<label for="c_address1" class="col-xs-3 control-label">Address Line 1</label>
			<div class="col-xs-9">
				<input id="c_address1" type="textbox" class="form-control c_address" name="c_address1" value="<?=$c_address1?>" readonly /></td>
				<input id="e_address1" type="textbox" class="form-control e_address" name="e_address1" value="<?=$e_address1?>" style="display:none;" /></td>
			</div>
		</div>

		<div class="form-group">
			<label for="c_address2" class="col-xs-3 control-label">Address Line 2</label>
			<div class="col-xs-9">
				<input id="c_address2" type="textbox" class="form-control c_address" name="c_address2" value="<?=$c_address2?>" readonly /></td>
				<input id="e_address2" type="textbox" class="form-control e_address" name="e_address2" value="<?=$e_address2?>" style="display:none;" /></td>
			</div>
		</div>

		<div class="form-group">
			<label for="c_address3" class="col-xs-3 control-label">Address Line 3</label>
			<div class="col-xs-9">
				<input id="c_address3" type="textbox" class="form-control c_address" name="c_address3" value="<?=$c_address3?>" readonly /></td>
				<input id="e_address3" type="textbox" class="form-control e_address" name="e_address3" value="<?=$e_address3?>" style="display:none;" /></td>
			</div>
		</div>

		<div class="form-group">
			<label for="city" class="col-xs-3 control-label">City</label>
			<div class="col-xs-9">
				<input id="c_city" type="textbox" class="form-control c_address" name="c_city" value="<?=$c_city?>" readonly /></td>
				<input id="e_city" type="textbox" class="form-control e_address" name="e_city" value="<?=$e_city?>" style="display:none;" /></td>
			</div>
		</div>

		<div class="form-group">
			<label for="state" class="col-xs-3 control-label">State</label>
			<div class="col-xs-9">
				<input id="c_state" type="textbox" class="form-control c_address" name="c_state" value="<?=$c_state?>" readonly /></td>
				<input id="e_state" type="textbox" class="form-control e_address" name="e_state" value="<?=$e_state?>" style="display:none;" /></td>
			</div>
		</div>

		<div class="form-group">
			<label for="zip" class="col-xs-3 control-label">ZIP Code</label>
			<div class="col-xs-9">
				<input id="c_zip" type="textbox" class="form-control c_address" name="c_zip" value="<?=$c_zip?>" readonly /></td>
				<input id="e_zip" type="textbox" class="form-control e_address" name="e_zip" value="<?=$e_zip?>" style="display:none;" /></td>
			</div>
		</div>

		<div class="form-group">
			<label for="country" class="col-xs-3 control-label">Country Code</label>
			<div class="col-xs-9">
				<input id="c_country" type="textbox" class="form-control c_address" name="c_country" maxlength="2" value="<?=$c_country?>" readonly /></td>
				<input id="e_country" type="textbox" class="form-control e_address" name="e_country" maxlength="2" value="<?=$e_country?>" style="display:none;" /></td>
			</div>
		</div>

		<div class="form-group">
			<div class="col-xs-offset-3 col-xs-9">
				<button id="use_address_as_entered" class="btn btn-default" style="display:none;">Use Address as Entered</button>
				<button id="edit_address" class="btn btn-default">Edit</button>
				<button id="submit_address" type="submit" class="btn btn-default e_address" style="display:none;">Submit</button>
			</div>
		</div>

		<div id="package_options">
			<h3>Package Options</h3>
<?
if ($country != 'US' && $country != 'PR' && $country != 'GU' && $country != 'VI')
{
    $rates = EndiciaUtils::GetRates(SHIPFROM_ZIP, !empty($c_zip) ? $c_zip : $e_zip, !empty($c_country) ? $c_country : $e_country, $pounds, $ounces, $preset['length'], $preset['width'], $preset['height']);
}
else if (!empty($c_hash) || !empty($e_hash))
{
	$preset = StampsUtils::LoadStampsPreset($salesId);
	if (!empty($preset))
	{
		$pounds = $preset['pounds'];
		$ounces = $preset['ounces'];
		$weightError = '';
		$lastPackagePreset = sprintf(
			"Presets loaded from a <a href='http://integra2.eocenterprise.com/#/orders/view/%d' target='_blank'>similar order</a> processed by %s on %s",
			$preset['last_sales_id'], $preset['email'], $preset['timestamp']);
	}

	$rates = StampsUtils::GetRates(SHIPFROM_ZIP, !empty($c_zip) ? $c_zip : $e_zip, !empty($c_country) ? $c_country : $e_country, $pounds, $ounces, $preset['length'], $preset['width'], $preset['height']);
}
?>
			<div class="form-group">
				<div class="col-xs-9">
					<p class="text-success"><strong><?=$lastPackagePreset?></strong></p>
				</div>
			</div>

			<div class="form-group">
				<label for="speed" class="col-xs-3 control-label">Requested Shipping</label>
				<div class="col-xs-9">
					<input id="speed" type="textbox" class="form-control
<?php
    if (stripos($speed, 'next') !== false ||
        stripos($speed, 'nxt') !== false ||
        stripos($speed, '2nd') !== false ||
        stripos($speed, 'pickup') !== false ||
        stripos($speed, 'over') !== false)
                echo "expedite";
?> " name="speed" value="<?=$speed?>" readonly />
				</div>
			</div>

			<div class="form-group">
				<label for="total" class="col-xs-3 control-label">Order Total</label>
				<div class="col-xs-9">
					<input id="total" type="textbox" class="form-control" name="total" value="$<?=number_format($totalPaid, 2)?>" readonly />
				</div>
			</div>

			<div class="form-group">
				<label for="pounds" class="col-xs-3 control-label">Weight</label>
				<div class="col-xs-9">
					<input id="pounds" type="textbox" name="pounds" value="<?=$pounds?>" /> lb.&nbsp;
					<input id="ounces" type="textbox" name="ounces" value="<?=$ounces?>" /> oz.&nbsp;
					<? if ($country == 'US' || $country == 'PR' || $country == 'GU' || $country == 'VI'): ?>
						<button id="get_rates" class="btn btn-default">Refresh Rates</button>
					<? endif; ?>
					<p class="text-danger"><strong><?=$weightError?></strong></p>
				</div>
			</div>

			<div class="form-group">
				<label for="service" class="col-xs-3 control-label">Mail Class</label>
				<div class="col-xs-9">
					<select id="service" name="service" class="form-control">
<?
if (!empty($c_hash) || !empty($e_hash))
{		
	foreach ($rates as $key => $rate)
	{
		echo "<option value='${key}' measure='" . $rate['measure'] . "' ";
		
		if (!empty($preset) && $key == $preset['service'])
			echo "selected";
        // temporary shortcut for eBay Canada
        else if (strpos($record_num, 'CA-') === 0 && $key == 'CommercialePacket')
            echo "selected";

		echo ">"  . $rate['desc'] . "</option>\n";
	}
}
?>
					</select>
				</div>
			</div>

			<div id="dimensions" class="form-group" style="display:none;">
				<label for="length" class="col-xs-3 control-label">Dimensions (in.)</label>
				<div class="col-xs-9">
					<input id="length" type="text" name="length" placeholder="L" value="<?=(!empty($preset)) ? $preset['length'] : ''?>"/>&nbsp;x&nbsp;
					<input id="width" type="text" name="width" placeholder="W" value="<?=(!empty($preset)) ? $preset['width'] : ''?>"/>&nbsp;x&nbsp;
					<input id="height" type="text" name="height"  placeholder="H" value="<?=(!empty($preset)) ? $preset['height'] : ''?>"/>&nbsp;

					<? if ($country == 'US' || $country == 'PR' || $country != 'GU' || $country != 'VI'): ?>
						<button id="get_rates2" class="btn btn-default">Refresh Rates</button>
					<? endif; ?>
				</div>
			</div>

			<div class="form-group">
				<label for="total" class="col-xs-3 control-label">Envelope/Box Type</label>
				<div class="col-xs-9">
					<input id="material" type="textbox" class="form-control" name="material" />
				</div>
			</div>

			<div class="form-group">
				<div class="col-xs-offset-3 col-xs-9">
					<button id="create_stamp" type="submit" class="btn btn-primary">Create Stamp</button>&nbsp;
					<button id="validate" type="submit" class="btn btn-warning">Validate Only</button>&nbsp;
					Postage Balance: $<?= number_format(($country != 'US' && $country != 'PR' && $country != 'GU' && $country != 'VI') ? EndiciaUtils::GetBalance() : StampsUtils::GetBalance(), 2) ?>
				</div>
			</div>
		</div>
	</div>
</form>

<br/>
<br/>
<script src="js/jquery.min.js"></script>
<script>
function useAddressAsEnteredClicked()
{
	$('input[name="c_hash"]').val($('input[name="e_hash"]').val());
	$('input[name="c_name"]').val($('input[name="e_name"]').val());
	$('input[name="c_address1"]').val($('input[name="e_address1"]').val());
	$('input[name="c_address2"]').val($('input[name="e_address2"]').val());
	$('input[name="c_address3"]').val($('input[name="e_address3"]').val());
	$('input[name="c_city"]').val($('input[name="e_city"]').val());
	$('input[name="c_state"]').val($('input[name="e_state"]').val());
	$('input[name="c_zip"]').val($('input[name="e_zip"]').val());
    $('input[name="c_country"]').val($('input[name="e_country"]').val());
	$('#use_address_as_entered').hide();
	$('#address_error').hide();
	$('#package_options').show();
	return false;
}

function editAddressClicked()
{
	$('.e_address').show();
	$('.c_address').hide();
	$('#edit_address').hide();
	$('#use_address_as_entered').hide();
	$('#submit_address').show();
	return false;
}

function mailClassChanged()
{
	if ($('#service :selected').attr('measure') == 'Y')
		$('#dimensions').show();
	else
	{
		$('input[name="length"]').val('');
		$('input[name="width"]').val('');
		$('input[name="height"]').val('');
		$('#dimensions').hide();
	}
}

function createStampClicked()
{
    if ($('#material').val() == '')
    {
        alert('Please enter the envelope/box type.');
        return false;
    }

    $('#create_stamp').prop('disabled', true);
    $('#validate').prop('disabled', true);
	$('#stamp_form').attr('action', 'ship.php?sales_id=<?=$salesId?>&action=stamp');
	$('#stamp_form').submit();
}

function validateClicked()
{
    $('#validate_only').val('1');
    $('#create_stamp').prop('disabled', true);
    $('#validate').prop('disabled', true);
    $('#stamp_form').attr('action', 'ship.php?sales_id=<?=$salesId?>&action=stamp');
    $('#stamp_form').submit();
}

function loadStampClicked()
{
    if ($('#material2').val() == '')
    {
        alert('Please enter the envelope/box type.');
        return false;
    }

	if ($('#pounds2').val() == '' && $('#ounces2').val() == '')
	{
		alert('Please enter the weight.');
		return false;
	}

    window.location = "show_stamp.php?id=" + $('#existing_stamp').val()
			+ '&material=' + encodeURI($('#material2').val())
			+ '&pounds=' + encodeURI($('#pounds2').val())
			+ '&ounces=' + encodeURI($('#ounces2').val());

}

function getRatesClicked()
{
	var zip = $('#c_zip').val();
    var country = $('#c_country').val();
	var ounces = $('#ounces').val();
	var pounds = $('#pounds').val();
	var length = $('#length').val();
	var width = $('#width').val();
	var height = $('#height').val();

	$.ajax('get_stamp_rates.php?zip=' + zip +
        '&country=' + country +
		'&ounces=' + ounces +
		'&pounds=' + pounds +
		'&length=' + length +
		'&width=' + width +
		'&height=' + height).done(function(data)
		{
			$('#service').html(data);
			mailClassChanged();
		});

	return false;
}

function saveTrackingClicked()
{
    $('#save_tracking').prop('disabled', true);
    $('#save_tracking').text('Saving...');

    $.ajax('change_tracking.php?sales_id=<?=$salesId?>&email=<?=$user?>&tracking=' + encodeURI($('#tracking_num').val())).done(function(data)
    {
        $('#save_tracking').text('Saved');
    }).error(function()
    {
        $('#save_tracking').prop('disabled', false);
        $('#save_tracking').text('Save Tracking');
        alert('There was an error while changing the tracking number.\nPlease check your internet connection.');
    });

    return false;
}

function trackingChanged()
{
    $('#save_tracking').prop('disabled', false);
    $('#save_tracking').text('Save Tracking');
}

function addHistoryClicked()
{
	$.post('add_history.php', {
				sales_id: '<?=$salesId?>',
				email: '<?=$user?>',
				remarks: $('#remarks').val(),
				mark_error: $('#mark_error').is(':checked') ? 1 : 0,
				show_sales: $('#show_sales').is(':checked') ? 1 : 0,
				show_data: $('#show_data').is(':checked') ? 1 : 0,
				show_pricing: $('#show_pricing').is(':checked') ? 1 : 0,
				show_shipping: $('#show_shipping').is(':checked') ? 1 : 0
			}).done(function(data)
	{
		$('#order_history').append(data);
		$('#remarks').val('');
	}).error(function()
	{
		alert('There was an error while submitting the remarks.\nPlease check your internet connection.');
	});

	return false;
}

$(document).ready(function()
{
	$('#edit_address').click(editAddressClicked);
	$('#use_address_as_entered').click(useAddressAsEnteredClicked);
	$('#create_stamp').click(createStampClicked);
    $('#load_stamp').click(loadStampClicked);
    $('#validate').click(validateClicked);
	$('#get_rates').click(getRatesClicked);
	$('#get_rates2').click(getRatesClicked);
    $('#save_tracking').click(saveTrackingClicked);
	$('#add_history').click(addHistoryClicked);
    $('#tracking_num').change(trackingChanged);

	$('#service').change(mailClassChanged);

	if ($('input[name="c_hash"]').val() == '' && $('input[name="e_hash"]').val() == '')
		editAddressClicked();
		
	if ($('input[name="c_hash"]').val() == '' && $('input[name="e_hash"]').val() != '')
		$('#use_address_as_entered').show();
		
	if ($('input[name="c_hash"]').val() == '')
		$('#package_options').hide();
		
	mailClassChanged();
});
</script>
</body>
</html>
