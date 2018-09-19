<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/imc_utils.php');
require_once('system/ssf_utils.php');
require_once('system/esi_utils.php');
require_once('system/acl.php');

$user = Login();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Create Order</title>
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
	</style>
  </head>
<body>
	<?php include_once("analytics.php") ?>
	<h1 class="x-blue_dg_caption">Create Order</h1>

	<form id="orderForm" method="POST" action="">	
		<table id="itemTable" width="80%" class="x-blue_dg_table">
			<thead>
				<tr class="th_row dg_tr" style="background-color: rgb(252, 250, 246);">
					<th class="x-blue_dg_th dg_left dg_nowrap"></th>
					<th class="x-blue_dg_th dg_left dg_nowrap">SKU</th>
					<th class="x-blue_dg_th dg_left dg_nowrap">Quantity</th>
					<th class="x-blue_dg_th dg_left dg_nowrap">Description</th>
					<th class="x-blue_dg_th dg_left dg_nowrap">Unit Price</th>
					<th class="x-blue_dg_th dg_left dg_nowrap">Total Price</th>
					<th class="x-blue_dg_th dg_left dg_nowrap">Availability</th>
				</tr>
			</thead>
			<tbody>
				<tr id="w1row_S9132937.61" class="dg_tr">
					<td class="x-blue_dg_td dg_left dg_nowrap">
						<button type="button" onclick="removeRow('S9132937.61', 'w1');">Remove</button>
					</td>
					<td id="w1sku_S9132937.61" class="x-blue_dg_td dg_left dg_nowrap">S9132937.61</td>
					<td id="w1qty_S9132937.61" class="qtycell x-blue_dg_td dg_left dg_nowrap" style="background-color: rgb(255, 0, 0);">
						<input type="number" name="" min="1" max="999" step="any" />
					</td>
					<td id="w1desc_S9132937.61" class="x-blue_dg_td dg_left dg_nowrap">Description</td>
					<td id="w1price_S9132937.61" class="x-blue_dg_td dg_left dg_nowrap">Unit Price</td>
					<td id="w1price_S9132937.61" class="x-blue_dg_td dg_left dg_nowrap">Total Price</td>
					<td id="w1price_S9132937.61" class="x-blue_dg_td dg_left dg_nowrap">Availability</td>
				</tr>
			</tbody>
		</table>
		
		<button id="addSku" type="button">Add SKU</button>
		
		<br/>

		<table id="headTbl" width="440px">
			<tr class="vert_row">
				<td><b>Record Number:</b></td>
				<td><input id="recordNum" name="recordNum" maxlength="20" type="text" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>Name:</b></td>
				<td><input id="name" name="name" maxlength="50" type="text" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>E-mail:</b></td>
				<td><input id="email" name="email" maxlength="50" type="text" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>Address:</b></td>
				<td><input id="address" name="address" maxlength="60" type="text" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>City:</b></td>
				<td><input id="city" name="city" maxlength="50" type="text" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>State:</b></td>
				<td><input id="state" name="state" maxlength="20" type="text" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>Country:</b></td>
				<td><input id="country" name="country" maxlength="2" type="text" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>Zip Code:</b></td>
				<td><input id="zip" name="zip" type="text" maxlength="20" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>Phone:</b></td>
				<td><input id="phone" name="phone" type="text" maxlength="20" /></td>
			</tr>
			<tr class="vert_row">
				<td><b>Shipping:</b></td>
				<td>
					<select id="speed" name="speed">
						<option selected value="GROUND">Ground</option>
						<option value="2ND DAYAIR">2nd Day Air</option>
						<option value="NXTDAYSAVR">Next Day Air</option>
					</select>
				</td>
			</tr>
			<tr class="vert_row">
				<td><b>Sales Agent:</b></td>
				<td><input id="agent" name="agent" type="text" value="<?=$user?>"/></td>
			</tr>
			<tr class="vert_row">
				<td><b>Total Retail Price:</b></td>
				<td><input id="total" name="total" type="text" maxlength="20" /></td>
			</tr>
		</table>
		
		<input id="saveOrder" class="submitButton" type="submit" onclick="return validateOrder('<?=$s['num']?>', '<?=$s['code']?>_submit.php');" value="Save Order"/>
<?php endforeach; ?>
	</form>

<script src="js/jquery.min.js" type="text/javascript"></script>
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
	$('#w<?=$s['num']?>_submit').attr('disabled', 'disabled');
	
	init();

	$.getJSON('<?=$s['code']?>_ajax.php?sku=' + sku, function(data)
	{
		$('tr#w<?=$s['num']?>row_' + e(sku) + ' button').each(function(index) { $(this).removeAttr('disabled'); });
		$('#w<?=$s['num']?>_add').removeAttr('disabled');
		$('#w<?=$s['num']?>_submit').removeAttr('disabled');

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

	return confirm('Are you sure you want to submit this order to warehouse ' + whn + '?');
}

</script>
</body>
</html>