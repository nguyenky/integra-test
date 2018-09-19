<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/imc_utils.php');
require_once('system/acl.php');

$user = Login();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT id, name, supplier_site
FROM pickup_sites
WHERE active = 1
EOD;

$rows = mysql_query($q);
while ($row = mysql_fetch_row($rows))
{
	$siteId = $row[0];
	$siteName = $row[1];
	$source = $row[2];
	
	$pickupSites[$siteId] = $siteName;
	$pickupSources[$siteId] = $source;
}

// IMC
unset($supplier);
$supplier['code'] = 'imc';
$supplier['num'] = '1';
$supplier['sites'] = ImcUtils::$siteIDs;
$supplier['data'] = $imcData;
$supplier['hasBrand'] = false;
$supplier['skuPrefix'] = 'EOC';
$supplier['tableWidth'] = '80%';
$suppliers[] = $supplier;

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Create Local Pickup Order</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" type="text/css" href="datagrid/styles/x-blue/style.css">
	<link rel="stylesheet" href="css/jquery-ui.css">
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
	<h1 class="x-blue_dg_caption">Create Local Pickup Order</h1>
	<form id="orderForm" method="POST" action="">
		<table id="headTbl" width="440px">
			<tr class="vert_row">
				<td><b>Record Number:</b></td>
				<td><input id="recordNum" name="recordNum" maxlength="20" type="text" value=""/></td>
			</tr>
			<tr class="vert_row">
				<td><b>Name:</b></td>
				<td><input id="name" name="name" maxlength="50" type="text" value=""/></td>
			</tr>
			<tr class="vert_row">
				<td><b>E-mail:</b></td>
				<td><input id="email" name="email" maxlength="50" type="text" value=""/></td>
			</tr>
			<tr class="vert_row">
				<td><b>Phone:</b></td>
				<td><input id="phone" name="phone" type="text" value=""/></td>
			</tr>
			<tr class="vert_row">
				<td><b>Pickup Location:</b></td>
				<td>
					<select id="site_id" name="site_id">
					<?php
						foreach ($pickupSites as $siteId => $siteName)
							echo '<option value="' . $siteId . '">' . htmlentities($siteName) . "</option>\n";
					?>
					</select>
					<?php
						foreach ($pickupSources as $siteId => $sourceId)
							echo '<input type="hidden" id="source_' . $siteId . '" value="' . $sourceId . '"/>' . "\n";
					?>
				</td>
			</tr>
			<tr class="vert_row">
				<td><b>Sales Agent:</b></td>
				<td><input id="agent" name="agent" type="text" value=""/></td>
			</tr>
		</table>

<?php foreach ($suppliers as $s): ?>
		<br/>
		<hr/>
		<table id="w<?=$s['num']?>itemTbl" width="<?=$s['tableWidth']?>">
			<thead>
				<tr class="th_row">
					<th></th>
					<th>Quantity</th>
					<th>SKU</th>
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
			</tbody>
		</table>
		<br/>
		<button id="w<?=$s['num']?>_add" type="button" onclick="w<?=$s['num']?>_addRow();">Add SKU</button>
		<input id="w<?=$s['num']?>_submit" class="submitButton" type="submit" onclick="return validateOrder('<?=$s['num']?>', 'create_pickup_submit.php');" value="Submit Order"/>
		<br/>
<?php endforeach; ?>
	</form>
	
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
	
	row += "</td><td id='w<?=$s['num']?>qty_" + sku + "' class='qtycell'>" + qty + "</td><td id='w<?=$s['num']?>sku_" + sku + "'>" + sku + "</td>";
	
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
	});
}

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
	$('#agent').val(getCookie('agent'));
	$('table').addClass('x-blue_dg_table');
	$('td').addClass('x-blue_dg_td dg_left dg_nowrap');
	$('th').addClass('x-blue_dg_th dg_left dg_nowrap');
	$('tr').addClass('dg_tr');
	$('.th_row').css('background-color', '#fcfaf6');
	$('.vert_row').css('background-color', '#F7F9FB');
	
	$('#site_id').change(function()
	{
		source = $('#source_' + $('#site_id :selected').val()).val();
		$('input.qtybox').not('[name^="w1order_' + source + '_"]').each(function(index)
		{
			$(this).val('');
			$(this).attr('disabled', 'disabled');
			$(this).change();
		});
	});

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
	$('#site_id').trigger('change');
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
		
	if (!check('name', 'name'))
		return false;
		
	if (!check('email', 'name'))
		return false;
		
	if (!check('phone', 'phone'))
		return false;

	if (!check('agent', 'name of the sales agent'))
		return false;
	else
		setCookie('agent', $('#agent').val(), 365);

	var sum = 0;

	$('input.qtybox').each(function(index)
	{
		sum += parseInt($(this).val()) || 0;
	});
	
	if (sum == 0)
	{
		alert('Please enter SKUs and the quantities you want to order.');
		return false;
	}
	
	$('#orderForm').attr('action', action);

	return confirm('Are you sure you want to submit this order?');
}

</script>
</body>
</html>