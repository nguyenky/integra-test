<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login('sales');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

if (empty($salesId))
{
	header('Location: sales.php');
	return;
}

$fulfilmentCodes = array(
		0 => 'Unspecified',
		1 => 'Direct',
		2 => 'Pickup',
		3 => 'EOC',
);

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

/////////////////////////////////////////////////////
mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$row = mysql_fetch_row(mysql_query("SELECT eoc.get_order_link(related_sales_id) AS parent_order,
eoc.get_sub_order_links(id) AS sub_orders FROM eoc.sales WHERE id = '{$salesId}'"));
if (empty($row))
{
	$parentOrder = '';
	$subOrders = '';
}
else
{
	$parentOrder = $row[0];
	$subOrders = $row[1];
}

$res = mysql_query("SELECT sku, quantity FROM sales_items WHERE sales_id = ${salesId}");
while ($row = mysql_fetch_row($res))
	$items[$row[0]] = $row[1];

$parts = GetSKUParts($items);
$weight = 0;
$weightStr = '';
$imcParts = array();
$ssfParts = array();
$esiParts = array();

foreach ($parts as $sku => $qty)
{
	$q=<<<EOQ
	SELECT supplier, mpn
	FROM sku_mpn WHERE sku = '${sku}'
	ORDER BY id
	LIMIT 1
EOQ;

	$row = mysql_fetch_row(mysql_query($q));
	if (empty($row))
	{
		if (stripos($sku, '.') !== false)
		{
			$supplier = 2;
			$mpn = str_replace("EOCS", "", $sku);
		}
		else
		{
			$supplier = 1;
			$mpn = str_replace("EOC", "", $sku);
		}
	}
	else
	{
		$supplier = $row[0];
		$mpn = $row[1];
	}

	if ($supplier == 1)
	{
		$row = mysql_fetch_row(mysql_query("SELECT weight, name, brand FROM imc_items WHERE mpn IN (SELECT mpn FROM sku_mpn WHERE sku = '$sku' AND supplier = 1) LIMIT 1"));
		if (empty($row))
		{
			$row = mysql_fetch_row(mysql_query("SELECT weight, name, brand FROM imc_items WHERE mpn = '$mpn'"));
			if (empty($row))
			{
				$weightStr = "No W1 weight data in database";
				$weight = 0;
				//break;
			}
		}

		$weight += ($row[0] * $qty);
		$existingQty = 0;
		if (array_key_exists($mpn, $imcParts)) $existingQty = $imcParts[$mpn];
		$imcParts[$mpn] = $existingQty + $qty;
		$names[$mpn] = $row[1];
		$brands[$mpn] = $row[2];
	}
	else if ($supplier == 2)
	{
		$origMpn = $mpn;
		$fields = explode('.', $mpn);
		$mpn = $fields[0];
		if (count($fields) > 1) $brand = $fields[1];
		else $brand = '';

		$row = mysql_fetch_row(mysql_query("SELECT weight, name, brand FROM ssf_items WHERE mpn = '$mpn' AND brand_id = '$brand'"));
		if (empty($row))
		{
			$weightStr = "No W2 weight data in database";
			$weight = 0;
			//break;
		}

		$weight += ($row[0] * $qty);
		$existingQty = 0;
		if (array_key_exists($origMpn, $ssfParts)) $existingQty = $ssfParts[$origMpn];
		$ssfParts[$origMpn] = $existingQty + $qty;
		$names[$origMpn] = $row[1];
		$brands[$origMpn] = $row[2];
	}
	else
	{
		$weightStr = "No W3 weight data in database";
		$weight = 0;
		$existingQty = 0;
		if (array_key_exists($mpn, $esiParts)) $existingQty = $esiParts[$mpn];
		$esiParts[$mpn] = $existingQty + $qty;
		$names[$mpn] = '';
		$brands[$mpn] = '';
		//break;
	}
}

if (!empty($weight) && $weightStr == '')
{
	$pounds = floor($weight);
	$ounces = ($weight - $pounds) * 16;
	if (!empty($ounces) && !empty($pounds))
		$weightStr = "$pounds lb $ounces oz";
	else if (empty($ounces) && !empty($pounds))
		$weightStr = "$pounds lb";
	else if (!empty($ounces) && empty($pounds))
		$weightStr = "$ounces oz";
}

$row = mysql_fetch_row(mysql_query("SELECT status FROM sales WHERE id = '$salesId'"));
$status = $row[0];

$sql = "SELECT ds.id, ds.supplier, ds.order_id, ds.tracking_num, IFNULL(s.etd, ds.etd) AS etd
FROM direct_shipments ds, direct_shipments_sales dss, sales s
WHERE ds.order_id = dss.order_id
AND s.id = dss.sales_id
AND dss.sales_id = ${salesId}";

$columns = array(
		"id" => array(
				"type" => "label",
				"visible" => "false"),
		"supplier" => array(
				"header" => "Warehouse",
				"type" => "label",
				"align" => "center",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "false",
				"sort_by" => "",
				"visible" => "true",
				"on_js_event" => ""),
		"order_id" => array(
				"header" => "Order ID",
				"type" => "label",
				"align" => "center",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "false",
				"sort_by" => "",
				"visible" => "true",
				"on_js_event" => ""),
		"tracking_num" => array(
				"header" => "Tracking #",
				"type" => "label",
				"align" => "center",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "false",
				"sort_by" => "",
				"visible" => "true",
				"on_js_event" => ""),
		"etd" => array(
				"header" => "ETD",
				"type" => "label",
				"align" => "center",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "false",
				"sort_by" => "",
				"visible" => "true",
				"on_js_event" => "")
);

$dgDS = new DataGrid(false, true, 'sm_');
$dgDS->SetColumnsInViewMode($columns);
$dgDS->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql);
$dsCount = $dgDS->SelectSqlItem("SELECT COUNT(*) FROM direct_shipments_sales WHERE sales_id = ${salesId}");
$dgDS->SetCssClass("x-blue");
$dgDS->SetViewModeTableProperties(array("width" => "35%"));
$dgDS->SetModes(array());
$dgDS->AllowPrinting(false);
$dgDS->AllowSorting(false);
$dgDS->AllowPaging(false, false);
$dgDS->Bind(false);


$sql = "SELECT
	id,
	sku,
	IF (ebay_item_id > '', ebay_item_id, amazon_asin) as store_item_id,
	description,
	quantity,
	unit_price,
	total
FROM sales_items
WHERE sales_id = ${salesId}";

$columns = array(
		"id" => array(
				"type" => "label",
				"visible" => "false"),
		"sku" => array(
				"header" => "SKU",
				"type" => "label",
				"align" => "center",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "false",
				"sort_by" => "",
				"visible" => "true",
				"on_js_event" => ""),
		"description" => array(
				"header" => "Description",
				"type" => "label",
				"align" => "left",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "false",
				"sort_by" => "",
				"visible" => "true",
				"on_js_event" => ""),
		"store_item_id" => array(
				"header" => "Store Item ID",
				"type" => "label",
				"align" => "center",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "false",
				"sort_by" => "",
				"visible" => "true",
				"on_js_event" => ""),
		"quantity" => array(
				"header" => "Quantity",
				"type" => "label",
				"align" => "right",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "true",
				"sort_by" => "",
				"visible" => "true",
				"sort_type" => "numeric",
				"on_js_event" => ""),
		"unit_price" => array(
				"header" => "Unit Price",
				"type" => "money",
				"align" => "right",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "false",
				"sort_by" => "",
				"visible" => "true",
				"sort_type" => "numeric",
				"sign" => "$",
				"sign_place" => "before",
				"decimal_places" => "2",
				"dec_separator" => ".",
				"thousands_separator" => ",",
				"on_js_event" => ""),
		"total" => array(
				"header" => "Total",
				"type" => "money",
				"align" => "right",
				"width" => "",
				"wrap" => "nowrap",
				"text_length" => "-1",
				"case" => "normal",
				"summarize" => "true",
				"sort_by" => "",
				"visible" => "true",
				"sort_type" => "numeric",
				"sign" => "$",
				"sign_place" => "before",
				"decimal_places" => "2",
				"dec_separator" => ".",
				"thousands_separator" => ",",
				"on_js_event" => ""),
);

$dgItems = new DataGrid(false, true, 'si_');
$dgItems->SetColumnsInViewMode($columns);
$dgItems->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql);
$dgItems->SetCssClass("x-blue");
$dgItems->SetModes(array());
$dgItems->AllowPrinting(false);
$dgItems->AllowSorting(false);
$dgItems->AllowPaging(false, false);
$dgItems->Bind(false);

$sql = "SELECT * FROM sales";

$columns = array(
		"id" => array(
				"type" => "label",
				"visible" => "false"),
		"store" => array(
				"header" => "Store",
				"type" => "label"),
		"record_num" => array(
				"header" => "Record #",
				"type" => "label"),
		"internal_id" => array(
				"header" => "Order #",
				"type" => "label"),
		"order_date" => array(
				"header" => "Order Date",
				"type" => "label"),
		"total" => array(
				"header" => "Total",
				"type" => "label"),
		"buyer_id" => array(
				"header" => "Buyer",
				"type" => "label"),
		"buyer_name" => array(
				"header" => "Ship To",
				"type" => "label"),
		"email" => array(
				"header" => "E-mail",
				"type" => "label"),
		"street" => array(
				"header" => "Street",
				"type" => "label"),
		"city" => array(
				"header" => "City",
				"type" => "label"),
		"state" => array(
				"header" => "State",
				"type" => "label"),
		"country" => array(
				"header" => "Country",
				"type" => "label"),
		"zip" => array(
				"header" => "Zip",
				"type" => "label"),
		"phone" => array(
				"header" => "Phone",
				"type" => "label"),
		"speed" => array(
				"header" => "Requested Shipping",
				"type" => "label"),
		"agent" => array(
				"header" => "Sales Agent",
				"type" => "label"),
		"fulfilment" => array(
				"header" => "Fulfilment",
				"type" => "enum",
				"source" => $fulfilmentCodes,
				"req_type" => "rt"),
		"status" => array(
				"header" => "Status",
				"type" => "enum",
				"source" => $statusCodes,
				"req_type" => "rt"),
		"tracking_num" => array(
				"header" => "Tracking #",
				"type" => "textbox",
				"req_type" => "sty",
				"on_js_event" => ""),
		"carrier" => array(
				"header" => "Carrier",
				"type" => "textbox",
				"req_type" => "sty",
				"on_js_event" => ""),
		"fake_tracking" => array(
				"header" => "Fake Tracking",
				"type" => "checkbox",
				"req_type" => "st",
				"on_js_event" => "",
				"true_value" => 1,
				"false_value" => 0),
		"remarks" => array(
				"header" => "Remarks",
				"type" => "label")
);

if ($_REQUEST['sd_mode'] != 'update')
	$_REQUEST['sd_mode'] = 'edit';

$_REQUEST['sd_rid'] = $salesId;

$dgDetails = new DataGrid(false, true, 'sd_');
$dgDetails->SetHttpGetVars(array("sales_id"));
$dgDetails->SetColumnsInEditMode($columns);
$dgDetails->SetEditModeTableProperties(array("width" => "35%"));
$dgDetails->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql);
$dgDetails->SetCssClass("x-blue");
$dgDetails->SetCaption("Order Details");
$dgDetails->AllowPrinting(false);
$dgDetails->AllowSorting(false);
$dgDetails->AllowPaging(false, false);
$dgDetails->SetTableEdit('sales', 'id', '');
$dgDetails->modeAfterUpdate = 'edit';

$modes = array(
		"add" => array(
				"view" => false,
				"edit" => false),
		"edit" => array(
				"view" => true,
				"edit" => true,
				"type" => "link",
				"byFieldValue" => ""),
		"details" => array(
				"view" => false,
				"edit" => false),
		"delete" => array(
				"view" => false,
				"edit" => false)
);

$dgDetails->SetModes($modes);

if ($_REQUEST['sd_mode'] == 'update')
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$row = mysql_fetch_row(mysql_query("SELECT tracking_num, carrier FROM sales WHERE id = ${salesId}"));
	if (!empty($row))
	{
		$oldTracking = $row[0];
		$oldCarrier = $row[1];
	}
}

$dgDetails->Bind(false);

if ($_REQUEST['sd_mode'] == 'update')
{
	$row = mysql_fetch_row(mysql_query("SELECT tracking_num, carrier FROM sales WHERE id = ${salesId}"));
	if (!empty($row))
	{
		$newTracking = $row[0];
		$newCarrier = $row[1];
	}

	//if (!empty($newTracking) && ($newTracking != $oldTracking || $newCarrier != $oldCarrier))
	if (empty($oldTracking) && !empty($newTracking)) // only post tracking number if this is the first time
	{
		$s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
	}
	//else if (!empty($oldTracking) && !empty($newTracking)) // notify customer about change in tracking number without changing tracking number in ebay
		//$s = file_get_contents("http://integra.eocenterprise.com/emsg/eoc_api/public/ebay_auto_response/${salesId}/edit");
}

?>

	<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
	<html>
	<head>
		<title>Order Details</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<?php
		if ($_REQUEST['sd_mode'] == 'update')
		{
			echo "<script>window.opener.location.reload(false);</script>\r\n";
			echo '<meta HTTP-EQUIV="REFRESH" content="0; url=order.php?sales_id=' . ${salesId} . '">' . "\r\n";
		}
		?>
		<style>
			#printTbl
			{
				display: none;
			}

			#sd__contentTable_bottom
			{
				border-top-width: 0;
			}

			#sd__contentTable
			{
				border-bottom-width: 0;
			}

			#sd__contentTable_bottom a[title="Cancel"], table.tblToolBar
			{
				display: none;
			}

			#sd__contentTable_bottom img
			{
				display: none;
			}

			#sd__contentTable_bottom div
			{
				float: none !important;
				text-align: center;
			}

			#sd_frmEditRow br
			{
				display: none;
			}
		</style>
	</head>
	<body>
	<?php include_once("analytics.php") ?>
	<br/>
	<center>
		<div style="float:left">
			<?php
			ob_start();

			$dgDetails->Show();
			?>

			<br/><br/>
		</div>
		<div class="x-blue_dg_caption">Total Weight: <input type="text" value="<?=$weightStr?>"/></div>
		<br/>
		<div class="x-blue_dg_caption">Shipping Components</div>
		<table class="x-blue_dg_table" align="center">
			<thead>
			<tr>
				<th class="x-blue_dg_th dg_center dg_nowrap">Image</th>
				<th class="x-blue_dg_th dg_center dg_nowrap">MPN</th>
				<th class="x-blue_dg_th dg_center dg_nowrap">Brand</th>
				<th class="x-blue_dg_th dg_center">Description</th>
				<th class="x-blue_dg_th dg_center dg_nowrap">Quantity</th>
			</tr>
			</thead>
			<tbody>
			<?
			foreach ($imcParts as $mpn => $qty)
			{
				echo "<tr class='dg_tr'><td class='x-blue_dg_td dg_left dg_nowrap'>";
				echo "<img width='300px' src='http://catalog.eocenterprise.com/img/" . str_replace('-', '', $mpn) . "/cl1-tneb'/>";
				echo "</td><td class='x-blue_dg_td dg_left dg_nowrap'>${mpn}</td><td class='x-blue_dg_td dg_left dg_nowrap'>" . htmlentities($brands[$mpn]) . "</td><td class='x-blue_dg_td dg_left'>" . htmlentities($names[$mpn]) . "</td><td class='x-blue_dg_td dg_right dg_nowrap'>${qty}</td></tr>\n";
			}
			foreach ($ssfParts as $mpn => $qty)
			{
				echo "<tr class='dg_tr'><td class='x-blue_dg_td dg_left dg_nowrap'>";
				echo "<img width='300px' src='http://catalog.eocenterprise.com/img/" . str_replace('-', '', $mpn) . "/cl1-tneb'/>";

				$fields = explode('.', $mpn);
				$realMpn = $fields[0];

				echo "</td><td class='x-blue_dg_td dg_left dg_nowrap'>${realMpn}</td><td class='x-blue_dg_td dg_left dg_nowrap'>" . htmlentities($brands[$mpn]) . "</td><td class='x-blue_dg_td dg_left'>" . htmlentities($names[$mpn]) . "</td><td class='x-blue_dg_td dg_right dg_nowrap'>${qty}</td></tr>\n";
			}
			foreach ($esiParts as $mpn => $qty)
			{
				echo "<tr class='dg_tr'><td class='x-blue_dg_td dg_center dg_nowrap'></td><td class='x-blue_dg_td dg_left dg_nowrap'>${mpn}</td><td></td><td></td><td class='x-blue_dg_td dg_right dg_nowrap'>${qty}</td></tr>\n";
			}
			?>
			</tbody>
		</table>


		</div>

		<?php
		echo "<br>";
		$dgItems->Show();

		if (!empty($dsCount))
		{
			echo "<br>";
			$dgDS->Show();
		}

		ob_end_flush();
		?>

		<br/>
		<input class="x-blue_dg_button" type="button"
				<? if ($status == 4): ?>
					onclick="javascript:alert('Order is already complete. Please create a new order instead.');"
				<? else: ?>
					onclick="javascript:window.location='direct_shipment.php?sales_id=<?=$salesId?>';"
				<? endif; ?>  value="Direct Shipment" />
		<input class="x-blue_dg_button" type="button" onclick="javascript:linkSupplierOrder();" value="Link Supplier Order" />
		<input class="x-blue_dg_button" type="button" onclick="javascript:window.location='ship.php?sales_id=<?=$salesId?>';" value="Create Stamp" />
		<input class="x-blue_dg_button" type="button" onclick="javascript:sendEmail();" value="Email Tracking Info" />

	</center>
	<script src="js/jquery.min.js" type="text/javascript"></script>
	<script type="text/javascript">
		function sendEmail()
		{
			$.get('tracking_email.php?sales_id=<?=$salesId?>', function(data)
			{
				$('.result').html(data);
				alert(data);
			});

			return false;
		}

		function linkSupplierOrder()
		{
			var order_id = prompt('Please enter the supplier order ID to link to this order:', '');

			if (order_id == '' || order_id == null)
				return false;

			var supplier = '';

			while (true)
			{
				supplier = prompt('Please enter "1" for W1, "2" for W2, etc.:', '');

				if (supplier == '' || supplier == null)
					return false;

				if (supplier == '1'
					|| supplier == '2'
					|| supplier == '3'
					|| supplier == '4'
					|| supplier == '5'
					|| supplier == '6'
					|| supplier == '7'
					|| supplier == '8'
					|| supplier == '9')
					break;
			}

			$.get('link_supplier_order.php?sales_id=<?=$salesId?>&order_id=' + order_id + '&supplier=' + supplier, function(data)
			{
				location.reload();
			});

			return false;
		}

		$(function() {
			<? if (!empty($parentOrder)): ?>
			$('#sd_row_02').after('<tr class="dg_tr" style="background-color:#F7F9FB;"><td class="x-blue_dg_td dg_left dg_nowrap" style="padding-top:5px;vertical-align:top;"><label><b>Parent Order</b></label></td><td class="x-blue_dg_td dg_left dg_nowrap"><label class="x-blue_dg_label"><?=$parentOrder?></label></td></tr>');
			<? endif; ?>
			<? if (!empty($subOrders)): ?>
			$('#sd_row_02').after('<tr class="dg_tr" style="background-color:#F7F9FB;"><td class="x-blue_dg_td dg_left dg_nowrap" style="padding-top:5px;vertical-align:top;"><label><b>Sub Orders</b></label></td><td class="x-blue_dg_td dg_left dg_nowrap"><label class="x-blue_dg_label"><?=$subOrders?></label></td></tr>');
			<? endif; ?>
		});
	</script>
	</body>
	</html>

<?
function GetKitParts($sku)
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
	$items = [];

	$res = mysql_query(
			"SELECT c.sku, k.quantity
FROM integra_prod.products p, integra_prod.products c, integra_prod.kit_components k
WHERE p.sku = '${sku}'
AND p.is_kit = 1
AND p.id = k.product_id
AND k.component_product_id = c.id");

	while ($row = mysql_fetch_row($res))
		$items[$row[0]] = $row[1];

	return $items;
}

function GetSKUParts($items)
{
	$parts = array();

	if (!empty($items))
	{
		foreach ($items as $sku => $qty)
		{
			if (empty($sku))
				continue;

			if (startsWith($sku, 'EK'))
			{
				if (endsWith($sku, '$D') || endsWith($sku, '$W'))
					$kitSku = substr($sku, 0, strlen($sku) - 2);
				else $kitSku = $sku;

				$kitParts = GetKitParts($kitSku);

				foreach ($kitParts as $compSku => $compQty)
				{
					$existingQty = 0;
					if (array_key_exists($compSku, $parts))
						$existingQty = $parts[$compSku];

					$parts[$compSku] = $existingQty + ($compQty * $qty);
				}

				if (count($kitParts) > 0)
					continue;
			}

			$sku = str_replace('/', '.', strtoupper($sku));

			$components = explode('$', $sku);

			foreach ($components as $component)
			{
				if ($component == 'D' || $component == 'W')
					continue;

				$totalQty = 0;
				$pair = explode('-', $component);

				if (count($pair) == 2)
				{
					$sku = $pair[0];
					if (is_numeric($pair[1]) && $pair[1] > 0)
						$totalQty = $qty * $pair[1];
					else
						$totalQty = $qty;
				}
				else
				{
					$sku = $component;
					$totalQty = $qty;
				}

				$existingQty = 0;
				if (array_key_exists($sku, $parts))
					$existingQty = $parts[$sku];

				$parts[$sku] = $existingQty + $totalQty;
			}
		}
	}

	return $parts;
}

function convert_state($name, $to='name')
{
	$states = array(
			array('name'=>'Alabama', 'abbrev'=>'AL'),
			array('name'=>'Alaska', 'abbrev'=>'AK'),
			array('name'=>'Arizona', 'abbrev'=>'AZ'),
			array('name'=>'Arkansas', 'abbrev'=>'AR'),
			array('name'=>'California', 'abbrev'=>'CA'),
			array('name'=>'Colorado', 'abbrev'=>'CO'),
			array('name'=>'Connecticut', 'abbrev'=>'CT'),
			array('name'=>'Delaware', 'abbrev'=>'DE'),
			array('name'=>'DC', 'abbrev'=>'DC'),
			array('name'=>'Florida', 'abbrev'=>'FL'),
			array('name'=>'Georgia', 'abbrev'=>'GA'),
			array('name'=>'Hawaii', 'abbrev'=>'HI'),
			array('name'=>'Idaho', 'abbrev'=>'ID'),
			array('name'=>'Illinois', 'abbrev'=>'IL'),
			array('name'=>'Indiana', 'abbrev'=>'IN'),
			array('name'=>'Iowa', 'abbrev'=>'IA'),
			array('name'=>'Kansas', 'abbrev'=>'KS'),
			array('name'=>'Kentucky', 'abbrev'=>'KY'),
			array('name'=>'Louisiana', 'abbrev'=>'LA'),
			array('name'=>'Maine', 'abbrev'=>'ME'),
			array('name'=>'Maryland', 'abbrev'=>'MD'),
			array('name'=>'Massachusetts', 'abbrev'=>'MA'),
			array('name'=>'Michigan', 'abbrev'=>'MI'),
			array('name'=>'Minnesota', 'abbrev'=>'MN'),
			array('name'=>'Mississippi', 'abbrev'=>'MS'),
			array('name'=>'Missouri', 'abbrev'=>'MO'),
			array('name'=>'Montana', 'abbrev'=>'MT'),
			array('name'=>'Nebraska', 'abbrev'=>'NE'),
			array('name'=>'Nevada', 'abbrev'=>'NV'),
			array('name'=>'New Hampshire', 'abbrev'=>'NH'),
			array('name'=>'New Jersey', 'abbrev'=>'NJ'),
			array('name'=>'New Mexico', 'abbrev'=>'NM'),
			array('name'=>'New York', 'abbrev'=>'NY'),
			array('name'=>'North Carolina', 'abbrev'=>'NC'),
			array('name'=>'North Dakota', 'abbrev'=>'ND'),
			array('name'=>'Ohio', 'abbrev'=>'OH'),
			array('name'=>'Oklahoma', 'abbrev'=>'OK'),
			array('name'=>'Oregon', 'abbrev'=>'OR'),
			array('name'=>'Pennsylvania', 'abbrev'=>'PA'),
			array('name'=>'Rhode Island', 'abbrev'=>'RI'),
			array('name'=>'South Carolina', 'abbrev'=>'SC'),
			array('name'=>'South Dakota', 'abbrev'=>'SD'),
			array('name'=>'Tennessee', 'abbrev'=>'TN'),
			array('name'=>'Texas', 'abbrev'=>'TX'),
			array('name'=>'Utah', 'abbrev'=>'UT'),
			array('name'=>'Vermont', 'abbrev'=>'VT'),
			array('name'=>'Virginia', 'abbrev'=>'VA'),
			array('name'=>'Washington', 'abbrev'=>'WA'),
			array('name'=>'West Virginia', 'abbrev'=>'WV'),
			array('name'=>'Wisconsin', 'abbrev'=>'WI'),
			array('name'=>'Wyoming', 'abbrev'=>'WY')
	);

	$return = strtoupper($name);

	foreach ($states as $state)
	{
		if ($to == 'name')
		{
			if (strtolower($state['abbrev']) == strtolower($name))
			{
				$return = $state['name'];
				break;
			}
			else if (strtolower($state['name']) == strtolower($name))
			{
				$return = $state['name'];
				break;
			}
		}
		else if ($to == 'abbrev')
		{
			if (strtolower($state['name']) == strtolower($name))
			{
				$return = strtoupper($state['abbrev']);
				break;
			}
			else if (strtoupper($state['abbrev']) == strtoupper($name))
			{
				$return = strtoupper($state['abbrev']);
				break;
			}
		}
	}

	return $return;
}

function startsWith($haystack, $needle)
{
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
	$length = strlen($needle);
	if ($length == 0) {
		return true;
	}

	return (substr($haystack, -$length) === $needle);
}
?>