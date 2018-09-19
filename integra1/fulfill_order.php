<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/imc_utils.php');
require_once('system/ssf_utils.php');
require_once('system/esi_utils.php');
require_once('system/acl.php');

$user = Login('sales');

$salesId = $_GET['sales_id'];
settype($salesId, 'integer');

if (!empty($salesId))
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$q = "SELECT record_num, buyer_name, street, city, state, zip, speed, email, phone, store FROM sales WHERE id = ${salesId}";
	$row = mysql_fetch_row(mysql_query($q));
	if (empty($row))
	{
		echo "Not found";
		exit;
	}

	$recordNum = $row[0];
	$shipToName = $row[1];
	$address = $row[2];
	$city = $row[3];
	$state = convert_state(preg_replace('/[^a-zA-Z0-9 ]/s', '', $row[4]), 'abbrev');
	$country = "US";
	$zip = $row[5];
	$speed = $row[6];
	$email = $row[7];
	$phone = $row[8];
	$store = $row[9];

	if (stristr('ground', $speed) !== false)
		$sGround = "selected";
	else if (stristr($speed, 'standard') !== false)
		$sGround = "selected";
	else if (stristr($speed, 'expedited') !== false)
		$sGround = "selected";
	else if (stristr($speed, 'second') !== false)
		$s2nd = "selected";
	else if (stristr($speed, '2nd') !== false)
		$s2nd = "selected";
	else if (stristr($speed, 'next') !== false)
		$sNext = "selected";
	else
		$sGround = "selected";
		
	$items = array();

	$res = mysql_query("SELECT sku, quantity FROM sales_items WHERE sales_id = ${salesId}");
	while ($row = mysql_fetch_row($res))
	{
		if (array_key_exists($row[0], $items))
			$items[$row[0]] += $row[1];
		else
			$items[$row[0]] = $row[1];
	}
	
	$parts = GetSKUParts($items);
	$imcData = ImcUtils::PreSelectItems($parts, $state, $zip);
	$ssfData = SsfUtils::PreSelectItems($parts);
	$esiData = EsiUtils::PreSelectItems($parts);
}

// IMC
unset($supplier);
$supplier['code'] = 'imc';
$supplier['num'] = '1';
$supplier['sites'] = ImcUtils::$siteIDs;
$supplier['data'] = $imcData;
$supplier['hasBrand'] = false;
$supplier['hasBulkShip'] = true;
$supplier['hasTruck'] = true;
$supplier['skuPrefix'] = 'EOC';
$supplier['tableWidth'] = '80%';
$suppliers[] = $supplier;

// SSF
unset($supplier);
$supplier['code'] = 'ssf';
$supplier['num'] = '2';
$supplier['sites'] = SsfUtils::$siteIDs;
$supplier['data'] = $ssfData;
$supplier['hasBrand'] = true;
$supplier['hasBulkShip'] = true;
$supplier['hasTruck'] = false;
$supplier['skuPrefix'] = 'EOCS';
$supplier['tableWidth'] = '80%';
$supplier['popupWidth'] = '800';
$suppliers[] = $supplier;

// ESI
unset($supplier);
$supplier['code'] = 'esi';
$supplier['num'] = '3';
$supplier['sites'] = EsiUtils::$siteIDs;
$supplier['data'] = $esiData;
$supplier['hasBrand'] = false;
$supplier['hasBulkShip'] = false;
$supplier['hasTruck'] = false;
$supplier['skuPrefix'] = 'EOCE';
$supplier['tableWidth'] = '45%';
$suppliers[] = $supplier;

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Order Fulfilment</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" type="text/css" href="datagrid/styles/x-blue/style.css">
	<link rel="stylesheet" href="jquery-ui.css">
	<style>
		.submitButton
		{
			border: 2px outset buttonface !important;
		}
		input[readonly="readonly"]
		{
			background-color: #EBEBE4;
			color: #545454;
		}
		input[type=text]
		{
			border: 2px inset !important;
			width: 196px;
		}
		input.qtybox
		{
			text-align: right;
			width: 20px;
		}
		select
		{
			width: 200px;
		}
		input.qtywarn
		{
			background-color: #FF0000;
		}
		input.qtyok
		{
			background-color: #00FF00;
		}
		h1
		{
			text-align: left !important;
		}
		body
		{
			padding: 0px 10px;
		}
		div.brandPopup .ui-dialog-titlebar-close
		{
			display: none;
		}
		div.brandPopup table
		{
			margin: 10px auto;
		}
	</style>
  </head>
<body>
	<?php include_once("analytics.php") ?>
	<h1 class="x-blue_dg_caption">Order Fulfilment</h1>
	<form id="orderForm" method="POST" action="">
		<input name="salesId" type="hidden" value="<?=$salesId?>"/>
		<table id="headTbl" width="440px">
			<tr class="vert_row">
				<td><b>Record Number:</b></td>
				<td><input id="recordNum" name="recordNum" maxlength="20" type="text" <?=empty($recordNum)?'':'readonly="readonly"'?> value="<?=htmlspecialchars($recordNum)?>"/></td>
			</tr>
<?php if (empty($salesId)): ?>
			<tr class="vert_row">
				<td><b>Related to Order (optional):</b></td>
				<td><input id="relatedRecordNum" name="relatedRecordNum" maxlength="20" type="text"/></td>
			</tr>
<?php endif; ?>
			<tr class="vert_row">
				<td><b>Ship To:</b></td>
				<td><input id="name" name="name" maxlength="50" type="text" value="<?=htmlspecialchars($shipToName)?>"/></td>
			</tr>
			<tr class="vert_row">
				<td><b>E-mail:</b></td>
				<td><input id="email" name="email" maxlength="50" type="text" <?=empty($email)?'':'readonly="readonly"'?> value="<?=htmlspecialchars($email)?>"/></td>
			</tr>
			<tr class="vert_row">
				<td><b>Address:</b></td>
				<td><input id="address" name="address" maxlength="60" type="text" value="<?=htmlspecialchars($address)?>"/></td>
			</tr>
			<tr class="vert_row">
				<td><b>City:</b></td>
				<td><input id="city" name="city" maxlength="50" type="text" value="<?=htmlspecialchars($city)?>"/></td>
			</tr>
			<tr class="vert_row">
				<td><b>State:</b></td>
				<td><input id="state" name="state" type="text" value="<?=htmlspecialchars($state)?>"/></td>
			</tr>
			<tr class="vert_row">
				<td><b>Zip Code:</b></td>
				<td><input id="zip" name="zip" type="text" value="<?=htmlspecialchars($zip)?>"/></td>
			</tr>
			<tr class="vert_row">
				<td><b>Phone:</b></td>
				<td><input id="phone" name="phone" type="text" <?=empty($phone)?'':'readonly="readonly"'?> value="<?=htmlspecialchars($phone)?>"/></td>
			</tr>
<?php if (!empty($salesId)): ?>
			<tr class="vert_row">
				<td><b>Requested Shipping:</b></td>
				<td><input type="text" readonly="readonly" value="<?=htmlspecialchars($speed)?>"/></td>
			</tr>
<?php endif; ?>
			<tr class="vert_row">
				<td><b>Speed (if Drop Shipping):</b></td>
				<td>
					<select id="speed" name="speed">
						<option <?=$sGround?> value="GROUND">Ground</option>
						<option <?=$s2nd?> value="2ND DAYAIR">2nd Day Air</option>
						<option <?=$sNext?> value="NXTDAYSAVR">Next Day Air Saver</option>
					</select>
				</td>
			</tr>
			<tr class="vert_row">
				<td><b>Sales Agent:</b></td>
				<td><input id="agent" name="agent" type="text" <?=($store == 'Manual' || empty($store))?'':'readonly="readonly"'?> value="<?=($store == 'Manual' || empty($store)) ? $user : $store?>"/></td>
			</tr>
<?php if (empty($salesId)): ?>
			<tr class="vert_row">
				<td><b>Sold at Total Price (optional):</b></td>
				<td><input id="soldPrice" name="soldPrice" maxlength="10" type="text"/></td>
			</tr>
<?php endif; ?>
		</table>

<?php foreach ($suppliers as $s): ?>
		<br/>
		<hr/>
		<h1 class="x-blue_dg_caption">Warehouse <?=$s['num']?></h1>
		<table id="w<?=$s['num']?>itemTbl" width="<?=$s['tableWidth']?>">
			<thead>
				<tr class="th_row">
					<th></th>
					<th>Quantity</th>
					<th>SKU</th>
					<?=$s['hasBrand'] ? '<th>Brand</th>' : ''?>
					<th>Description</th>
					<th>Unit Price</th>
	<?php
	foreach ($s['sites'] as $siteID => $siteName)
	{
		if (empty($s['data']['transit']) || !array_key_exists($siteID, $s['data']['transit']))
			echo "<th id=w${s['num']}site_${siteID}>${siteName}</th>\r\n";
		else
		{
			$days = trim($s['data']['transit'][$siteID]);
			$trans = ($days == '1' ? "(1 day)" : "(${days} days)");
			echo "<th id=w${s['num']}site_${siteID}>${siteName}<br/>${trans}</th>\r\n";
		}
	}
	?>
				</tr>
			</thead>
			<tbody>
	<?php
	if (!empty($salesId) && !empty($ssfData['parts']))
	{
		foreach ($s['data']['parts'] as $sku => $qty)
		{
			if (!empty($s['data']['desc'][$sku]))
				$dsc = $s['data']['desc'][$sku];
			else
				$dsc = 'Not found';

			echo "<tr id='w${s['num']}row_${sku}'><td><button type='button' onclick=" . '"' .
				"removeRow('${sku}', 'w${s['num']}');" . '"' . ">Remove</button><button type='button' onclick=" . '"' .
				"w${s['num']}_editRow('${sku}');" . '"' . ">Change</button>";
			
			if ($s['hasBrand'])
				echo "<button type='button' onclick=" . '"' . "w${s['num']}_getBrands('${sku}');" . '"' . ">View Brands</button>";
			
			echo "</td><td id='w${s['num']}qty_${sku}' class='qtycell'>${qty}</td><td id='w${s['num']}sku_${sku}'>${sku}</td>";
			
			if ($s['hasBrand'])
				echo "<td id='w${s['num']}brand_${sku}'>" . $s['data']['brand'][$sku] . "</td>";
				
			echo "<td id='w${s['num']}desc_${sku}'>" . $dsc . "</td><td id='w${s['num']}price_${sku}'>" . $s['data']['price'][$sku] . "</td>\r\n";
			echo "<input name='w${s['num']}desc_" . str_replace('.', '-', $sku) . "' type='hidden' value='${dsc}'/>\r\n";
			echo "<input name='w${s['num']}price_" . str_replace('.', '-', $sku) . "' type='hidden' value='" . $s['data']['price'][$sku] . "'/>\r\n";
			
			foreach ($s['sites'] as $siteID => $siteName)
			{
				$order = $s['data']['cart'][$siteID][$sku];
				$avl = $s['data']['avail'][$siteID][$sku];
				echo "<td id='avail_${siteID}_${sku}'><input class='qtybox' ";
				if (empty($avl))
				{
					echo "readonly='readonly' ";
					if ($avl === '')
						$avl = '?';
				}
				echo "type='text' name='w${s['num']}order_${siteID}_" . str_replace('.', '-', $sku) . "' value='${order}'>&nbsp;/&nbsp;${avl}</td>";
			}

			echo "</tr>\r\n";
		}
	}
	?>
			</tbody>
		</table>
		<br/>
		<button id="w<?=$s['num']?>_add" type="button" onclick="w<?=$s['num']?>_addRow();">Add SKU</button>
		<input id="w<?=$s['num']?>_dropship" class="submitButton" type="submit" onclick="return validateOrder('<?=$s['num']?>', '<?=$s['code']?>_submit.php');" value="Order Now for Drop Shipping"/>
		<?php if ($s['hasBulkShip'] && empty($salesId)): ?>
			<input id="w<?=$s['num']?>_bulkship" class="submitButton" type="submit" onclick="return validateOrder('<?=$s['num']?>', 'eoc_submit.php?w=<?=$s['num']?>');" value="Schedule for EOC Shipping"/>
		<?php endif; ?>
		<?php if ($s['hasTruck']): ?>
			<input id="w<?=$s['num']?>_truck" class="submitButton" type="submit" onclick="return validateOrder('<?=$s['num']?>', '<?=$s['code']?>_truck_submit.php');" value="Order Now for Truck Delivery / Pickup"/>
		<?php endif; ?>
		<br/>
<?php endforeach; ?>
	</form>
	
	<br/>
	<p class='x-blue_dg_label'>
	Note:
	<br/>
	If you click "Schedule for EOC shipping", the items will be included in the next bulk order. The actual warehouse where the items will come from will vary depending on availability and shipment batch optimization.
	<br/>
	The selected shipping speed is only used by the system if you click "Order Now for Drop Shipping".
	<br/>
	If you click "Order Now for Truck Delivery / Pickup", the items will be ordered immediately and will be delivered by truck to the EOC warehouse / pickup center.
	<br/>
	For manual orders, use the "Sold at Total Price" field to track how much total price was charged to the customer. Use the "Related to Order" field if this order is an extension of another order. A link to the original order will show up in the Order Details page.
	</p>
	
<?php if (!empty($store) && $store != 'Manual'): ?>
	<br/>
	<a href='http://integra2.eocenterprise.com/#/orders/view/<?=$salesId?>' class='x-blue_dg_label'>Back to Order Details page</a>
<?php endif; ?>

<?php foreach ($suppliers as $s): ?>
	<?php if ($s['hasBrand']): ?>
<div id="w<?=$s['num']?>brandTbl" class="brandPopup" title="Select Brand">
	<table>
		<thead>
			<tr class="th_row">
				<th>Brand</th>
				<th>Unit Price</th>
		<?php foreach ($s['sites'] as $siteID => $siteName) echo "<th>${siteName}</th>\r\n"; ?>
			</tr>
		</thead>
		<tbody>
		</tbody>
	</table>
</div>
	<?php endif; ?>
<?php endforeach; ?>

<script src="js/jquery.min.js" type="text/javascript"></script>
<script src="js/jquery-ui.min.js" type="text/javascript"></script>
<script type="text/javascript">

<?php foreach ($suppliers as $s): ?>
function w<?=$s['num']?>_editRow(sku)
{
	w<?=$s['num']?>_addRow(sku, true, $('#w<?=$s['num']?>qty_' + e(sku)).html());
}

function w<?=$s['num']?>_addRow(startSku, removeFirst, startQty)
{
	startSku = startSku || '';
	startQty = startQty || '1';

	if (typeof removeFirst == 'undefined')
		removeFirst = removeFirst || false;

	var sku = $.trim(prompt('Please enter the SKU:', startSku)).toUpperCase();
	if (sku == '')
		return;
		
	if (sku.indexOf('<?=$s['skuPrefix']?>') == 0)
		sku = sku.substr(<?=strlen($s['skuPrefix'])?>);
		
	if (sku == '')
		return;

	if (!removeFirst && $('td#w<?=$s['num']?>sku_' + e(sku)).length != 0)
	{
		alert('This SKU is already on the table.');
		return;
	}
	
	if (removeFirst)
	{
		if (startSku != sku)
		{
			if ($('td#w<?=$s['num']?>sku_' + e(sku)).length != 0)
			{
				alert('This SKU is already on the table.');
				return;
			}
		}
	}
	
	var qty = parseInt(prompt('Please enter the quantity:', startQty)) || 0;
	if (qty == 0)
		return;
	
	if (removeFirst)
		removeRow(startSku, 'w<?=$s['num']?>', false);
		
	w<?=$s['num']?>_renderRow(sku, qty);
}

function w<?=$s['num']?>_renderRow(sku, qty)
{
	var row = "<tr id='w<?=$s['num']?>row_" + sku + "'><td><button type='button' onclick=" + '"' + "removeRow('" + sku + "', 'w<?=$s['num']?>');" + '"' +
	">Remove</button><button type='button' onclick=" + '"' + "w<?=$s['num']?>_editRow('" + sku + "');" + '"' + ">Change</button>";
	
	<?php if ($s['hasBrand']): ?>
	row += "<button type='button' onclick=" + '"' + "w<?=$s['num']?>_getBrands('" + sku + "');" + '"' + ">View Brands</button>";
	<?php endif; ?>
	
	row += "</td><td id='w<?=$s['num']?>qty_" + sku + "' class='qtycell'>" + qty + "</td><td id='w<?=$s['num']?>sku_" + sku + "'>" + sku + "</td>";
	
	<?php if ($s['hasBrand']): ?>
	row += "<td id='w<?=$s['num']?>brand_" + sku + "'></td>";
	<?php endif; ?>
	
	row += "<td id='w<?=$s['num']?>desc_" + sku + "'>Requesting data...</td>" + "<td id='w<?=$s['num']?>price_" + sku + "'>?</td>\r\n";
	
	row += "<input name='w<?=$s['num']?>desc_" + en(sku) + "' type='hidden' value=''/>\r\n";
	row += "<input name='w<?=$s['num']?>price_" + en(sku) + "' type='hidden' value=''/>\r\n";
	
	$('th[id^="w<?=$s['num']?>site_"]').each(function(index)
	{
		var id = $(this).attr('id').split('_');
		row += "<td id='avail_" + id[1] + "_" + sku + "'><input class='qtybox' readonly='readonly' type='text' name='w<?=$s['num']?>order_" + id[1] + "_" + en(sku) + "' value=''>&nbsp;/&nbsp;?</td>";
	});

	row += "</tr>\r\n";
	
	$('#w<?=$s['num']?>itemTbl > tbody:last').append(row);
	
	$('tr#w<?=$s['num']?>row_' + e(sku) + ' button').each(function(index) { $(this).attr('disabled', 'disabled'); });
	$('#w<?=$s['num']?>_add').attr('disabled', 'disabled');
	$('#w<?=$s['num']?>_dropship').attr('disabled', 'disabled');
	<?php if ($s['hasBulkShip']): ?>
		$('#w<?=$s['num']?>_bulkship').attr('disabled', 'disabled');
	<?php endif; ?>
	<?php if ($s['hasTruck']): ?>
		$('#w<?=$s['num']?>_truck').attr('disabled', 'disabled');
	<?php endif; ?>
	
	init();

	$.getJSON('<?=$s['code']?>_ajax.php?sku=' + sku, function(data)
	{
		$('tr#w<?=$s['num']?>row_' + e(sku) + ' button').each(function(index) { $(this).removeAttr('disabled'); });
		$('#w<?=$s['num']?>_add').removeAttr('disabled');
		$('#w<?=$s['num']?>_dropship').removeAttr('disabled');
		<?php if ($s['hasBulkShip']): ?>
			$('#w<?=$s['num']?>_bulkship').removeAttr('disabled');
		<?php endif; ?>
		<?php if ($s['hasTruck']): ?>
			$('#w<?=$s['num']?>_truck').removeAttr('disabled');
		<?php endif; ?>

		$('#w<?=$s['num']?>brand_' + e(sku)).html(data.brand);
		$('#w<?=$s['num']?>desc_' + e(sku)).html(data.desc);
		$('#w<?=$s['num']?>price_' + e(sku)).html(data.price);
		
		$('input[name="w<?=$s['num']?>desc_' + en(sku) + '"]').val(data.desc);
		$('input[name="w<?=$s['num']?>price_' + en(sku) + '"]').val(data.price);
				
		$('th[id^="w<?=$s['num']?>site_"]').each(function(index)
		{
			var id = $(this).attr('id').split('_');
			var cnt = $('td#avail_' + id[1] + '_' + e(sku)).html();
			var idx = cnt.lastIndexOf('&nbsp;');
			var avl = data[$(this).attr('id').substring(2)];
			if (typeof avl == 'undefined')
				avl = '?';
			$('td#avail_' + id[1] + '_' + e(sku)).html(cnt.substring(0, idx+6) + avl);
			if (avl > 0)
			{
				$('input.qtybox[name="w<?=$s['num']?>order_' + id[1] + '_' + en(sku) + '"]').removeAttr('readonly');
				<?php if (count($s['sites']) == 1): ?>
				var needed = parseInt($('#w<?=$s['num']?>qty_' + e(data.sku)).html());
				if (parseInt(avl) >= needed)
					$('input.qtybox[name="w<?=$s['num']?>order_' + id[1] + '_' + en(data.sku) + '"]').val(needed);
				<?php endif ?>
			}
		});

		init();
	
		<?php if ($s['hasBrand']): ?>
		if (data.desc == 'Select brand')
		{
			$('#w<?=$s['num']?>brandTbl tbody tr').remove();
			
			for (i = 0; i < data.options.length; i++)
			{
				var row = '<tr><td class="x-blue_dg_td dg_left dg_nowrap">';
				
				row += '<a href="javascript:w<?=$s['num']?>_chooseBrand(' + "'" + sku + "', '"
				+ data.options[i].sku + "', '"
				+ data.options[i].brand + "', '"
				+ data.options[i].desc + "', '"
				+ data.options[i].price + "', '" +
				data.options[i].site_<?=implode(" + ',' + data.options[i].site_", array_keys($s['sites']))?>
				+ "');" + '">'
				+ data.options[i].brand + '</a></td><td class="x-blue_dg_td dg_right dg_nowrap">' + data.options[i].price
				+ '</td>' + 

				<?php
				foreach ($s['sites'] as $siteID => $siteName)
					echo "'<td class=" . '"x-blue_dg_td dg_right dg_nowrap">' . "' + data.options[i].site_${siteID} + '</td>' + ";
				?>

				'</tr>';
				$('#w<?=$s['num']?>brandTbl tbody').append(row);
			}

			$("#w<?=$s['num']?>brandTbl").dialog("open");
		}
		<?php endif; ?>
	});
}

<?php if ($s['hasBrand']): ?>
function w<?=$s['num']?>_getBrands(sku)
{
	var idx = sku.indexOf('.');
		
	var qty = $('#w<?=$s['num']?>qty_' + e(sku)).html();

	removeRow(sku, 'w<?=$s['num']?>', false);
	
	if (idx != -1)
		sku = sku.substring(0, idx);
	
	w<?=$s['num']?>_renderRow(sku, qty);
}

function w<?=$s['num']?>_chooseBrand(oldSku, newSku, brand, desc, price, sites)
{
	$('tr#w<?=$s['num']?>row_' + e(oldSku) + ' button:contains("Remove")').attr('onclick', "removeRow('" + newSku + "', 'w<?=$s['num']?>');");
	$('tr#w<?=$s['num']?>row_' + e(oldSku) + ' button:contains("Change")').attr('onclick', "w<?=$s['num']?>_editRow('" + newSku + "');");
	$('tr#w<?=$s['num']?>row_' + e(oldSku) + ' button:contains("Brands")').attr('onclick', "w<?=$s['num']?>_getBrands('" + newSku + "');");

	$('tr#w<?=$s['num']?>row_' + e(oldSku)).attr('id', 'w<?=$s['num']?>row_' + newSku);

	$('td#w<?=$s['num']?>qty_' + e(oldSku)).attr('id', 'w<?=$s['num']?>qty_' + newSku);

	$('td#w<?=$s['num']?>sku_' + e(oldSku)).text(newSku);
	$('td#w<?=$s['num']?>sku_' + e(oldSku)).attr('id', 'w<?=$s['num']?>sku_' + newSku);
	
	$('td#w<?=$s['num']?>brand_' + e(oldSku)).text(brand);
	$('td#w<?=$s['num']?>brand_' + e(oldSku)).attr('id', 'w<?=$s['num']?>brand_' + newSku);
	
	$('td#w<?=$s['num']?>desc_' + e(oldSku)).text(desc);
	$('td#w<?=$s['num']?>desc_' + e(oldSku)).attr('id', 'w<?=$s['num']?>desc_' + newSku);
	
	$('td#w<?=$s['num']?>price_' + e(oldSku)).text(price);
	$('td#w<?=$s['num']?>price_' + e(oldSku)).attr('id', 'w<?=$s['num']?>price_' + newSku);
	
	$('input[name="w<?=$s['num']?>desc_' + en(oldSku) + '"]').attr('value', desc);
	$('input[name="w<?=$s['num']?>desc_' + en(oldSku) + '"]').attr('name', 'w<?=$s['num']?>desc_' + en(newSku));
	
	$('input[name="w<?=$s['num']?>price_' + en(oldSku) + '"]').attr('value', price);
	$('input[name="w<?=$s['num']?>price_' + en(oldSku) + '"]').attr('name', 'w<?=$s['num']?>price_' + en(newSku));
	
	var qtys = sites.split(',');

	<?php
	foreach ($s['sites'] as $siteID => $siteName)
	{
		echo "$('input[name=" . '"w' . $s['num'] . 'order_' . $siteID . "_' + en(oldSku) + '" . '"]' . "').attr('name', 'w${s['num']}order_${siteID}" . "_' + en(newSku));";
		echo "var cnt = $('td#avail_${siteID}_' + e(oldSku)).html();";
		echo "var idx = cnt.lastIndexOf('&nbsp;');";
		echo "var avl = qtys.shift();";
		echo "$('td#avail_${siteID}_' + e(oldSku)).html(cnt.substring(0, idx+6) + avl);";
		echo "$('td#avail_${siteID}_' + e(oldSku)).attr('id', 'avail_${siteID}_' + newSku);";
		echo "if (avl > 0) ";
		echo "$('input.qtybox[name=" . '"w' . $s['num'] . 'order_' . $siteID . "_' + en(newSku) + '" . '"]' . "').removeAttr('readonly');";
	}
	?>

	$("#w<?=$s['num']?>brandTbl").dialog("close");
	
	init();
}
<?php endif; ?>

<?php endforeach; ?>

$(document).ready(init);

function e(sku)
{
	return sku.replace('.', '\\.');
}

function en(sku)
{
	return sku.replace('.', '-');
}

function enr(sku)
{
	return sku.replace('-', '.');
}

function check(name, label)
{
	if ($('#' + name).val() == '')
	{
		alert('Please enter the ' + label + '.');
		$('#' + name).focus();
		return false;
	}
	
	return true;
}

function init()
{
	$('table').addClass('x-blue_dg_table');
	$('td').addClass('x-blue_dg_td dg_left dg_nowrap');
	$('th').addClass('x-blue_dg_th dg_left dg_nowrap');
	$('tr').addClass('dg_tr');
	$('.th_row').css('background-color', '#fcfaf6');
	$('.vert_row').css('background-color', '#F7F9FB');

	$('input.qtybox').keydown(function(event)
	{
        if ( event.keyCode == 46 || event.keyCode == 8 || event.keyCode == 9 || event.keyCode == 27 || event.keyCode == 13 || 
            (event.keyCode == 65 && event.ctrlKey === true) || 
            (event.keyCode >= 35 && event.keyCode <= 39))
		{
			 return;
		}
        else
		{
            if (event.shiftKey || (event.keyCode < 48 || event.keyCode > 57) && (event.keyCode < 96 || event.keyCode > 105 ))
			{
                event.preventDefault();
            }
        }
    });
	
	$('input.qtybox').change(function()
	{
		var name = $(this).attr('name').split('_');
		var wh = $(this).attr('name').substring(0, 2);
		var site = name[1];
		var sku = enr(name[2]);
		var needed = parseInt($('#' + wh + 'qty_' + e(sku)).html());
		var avail = parseInt($('#avail_' + site + '_' + e(sku)).text().replace(/\s/g, '').substring(1) || 0);
		var val = parseInt($(this).val()) || 0;
		
		if (val > avail)
		{
			$(this).addClass('qtywarn');
			$(this).removeClass('qtyok');
		}
		else
		{
			$(this).removeClass('qtywarn');
			
			if (val != 0)
				$(this).addClass('qtyok');
			else
				$(this).removeClass('qtyok');
		}
		
		
		var sum = 0;
		
		$('.qtybox[name$="_' + en(sku) + '"]').each(function(index)
		{
			if ($(this).attr('name').substring(0, 2) == wh)
				sum += parseInt($(this).val(), 10) || 0;
		});

		if (sum == needed)
			$('#' + wh + 'qty_' + e(sku)).css('background-color', '#00FF00');
		else
			$('#' + wh + 'qty_' + e(sku)).css('background-color', '#FF0000');
    });
	
	$('input.qtybox').trigger('change');
	
	<?php foreach ($suppliers as $s): ?>
	<?php if ($s['hasBrand']): ?>
	$("#w<?=$s['num']?>brandTbl" ).dialog(
	{
		autoOpen: false,
		modal: true,
		width: <?=$s['popupWidth']?>,
		closeOnEscape: false,
		resizable: false,
		dialogClass: "no-close",
	});
	<?php endif; ?>
	<?php endforeach; ?>
}

function getCookie(c_name)
{
	var i,x,y,ARRcookies=document.cookie.split(";");
	for (i=0;i<ARRcookies.length;i++)
	  {
	  x=ARRcookies[i].substr(0,ARRcookies[i].indexOf("="));
	  y=ARRcookies[i].substr(ARRcookies[i].indexOf("=")+1);
	  x=x.replace(/^\s+|\s+$/g,"");
	  if (x==c_name)
		{
		return unescape(y);
		}
	  }
}

function setCookie(c_name,value,exdays)
{
	var exdate=new Date();
	exdate.setDate(exdate.getDate() + exdays);
	var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
	document.cookie=c_name + "=" + c_value;
}

function removeRow(sku, wh, doConfirm)
{
	if (typeof doConfirm == 'undefined')
		doConfirm = doConfirm || true;

	if (doConfirm)
		if (!confirm('Are you sure you want to remove this SKU?'))
			return;

	$('#' + wh + 'row_' + e(sku)).remove();
}

function validateOrder(whn, action)
{
	if (!check('recordNum', 'record number'))
		return false;
		
	if (!check('name', 'ship-to name'))
		return false;
		
	if (!check('address', 'address'))
		return false;
	
	if (!check('city', 'city'))
		return false;
		
	if (!check('state', 'state'))
		return false;

	if (!check('zip', 'zip code'))
		return false;

<?php if ($store == 'Manual' || empty($store)): ?>
	if (!check('agent', 'name of the sales agent'))
		return false;
	else
		setCookie('agent', $('#agent').val(), 365);
<?php endif; ?>

	var sum = 0;

	$('input.qtybox').each(function(index)
	{
		sum += parseInt($(this).val()) || 0;
	});
	
	if (sum == 0)
	{
		alert('Please enter SKUs and the quantities you want to order from each warehouse.');
		return false;
	}
	
	$('#orderForm').attr('action', action);

	return confirm('Are you sure you want to submit this order?');
}

</script>
</body>
</html>