<?php

require_once('system/config.php');
require_once('system/item_utils.php');
require_once('system/acl.php');

$user = Login('shipgrid');

$barcode = trim($_REQUEST['barcode']);
$orderId = trim($_REQUEST['order_id']);
$keyword = $_REQUEST['keyword'];
$targetSalesId = $_REQUEST['target_sales_id'];
$targetSku = $_REQUEST['target_sku'];
$message = '';
$showKeywords = false;
$matches = [];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

if (!empty($targetSalesId))
{
    if (!empty($targetSku) && !empty($barcode))
    {
        $rows = mysql_fetch_row(mysql_query(query("SELECT 1 FROM imc_lookup WHERE imc_upc = '%s'", $barcode)));

        if (!empty($rows) && !empty($rows[0]))
            mysql_query(query("UPDATE imc_lookup SET custom = '%s' WHERE imc_upc = '%s'", $targetSku, $barcode));
        else
            mysql_query(query("INSERT INTO imc_lookup (custom, imc_upc) VALUES ('%s', '%s')", $targetSku, $barcode));
    }

    header("Location: ship.php?sales_id=${targetSalesId}");
    return;
}

if (!empty($keyword))
{
    $matches = ItemUtils::FindOrderItems($orderId, $keyword);
    $showKeywords = true;

    if (empty($matches))
    {
        $message = 'Still no matching orders. Please try a different keyword or check the order ID.';
    }
    else
    {
        $message = 'Please select a matching item:';
    }
}
else if (!empty($barcode))
{
    $message = 'No matching orders. Please enter the brand or name of the item.';
    $showKeywords = true;

    $salesId = ItemUtils::GetOrderFromBarcode($barcode, $orderId);

	if (!empty($salesId))
	{
		header("Location: ship.php?sales_id=${salesId}");
		return;
	}
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Integra :: Barcode Order Lookup</title>
		<style>
			body
			{
				font-family: Tahoma, Verdana;
				font-size: 50px;
			}
			input[type=text]
			{
				font-size: 50px;
				width: 600px;
				padding: 11px 19px;
			}
			input[type=submit]
			{
				height: 81px;
				width: 200px;
				font-size: 40px;
			}
			form
			{
				margin-top: 100px;
				margin-bottom: 50px;
			}
			td
			{
				padding-bottom: 20px;
				padding-right: 30px;
			}
			td.right
			{
				text-align: right;
			}
			#error
			{
				font-weight: bold;
				font-size: 24px;
				color: red;
			}
			#footer
			{
				font-size: 24px;
				display: none;
			}
            .matches
            {
                font-size: 20px;
            }
            .matches td
            {
                padding: 10px;
                text-align: left;
            }
            .matches img
            {
                width: 350px;
            }
		</style>
	</head>
<body>
<?php include_once("analytics.php") ?>
<center>
	<form method="POST" id="search_form">
		<table>
			<tr>
				<td class="right">Supplier Order ID</td>
				<td><input id="order_id" type="text" name="order_id" maxlength="20" /></td>
			</tr>

			<tr>
				<td class="right">Barcode</td>
				<td>
                    <input id="barcode" type="text" placeholder="Scan item barcode here" name="barcode" value="<?=$barcode?>" maxlength="80" <?= $showKeywords ? '' : 'autofocus' ?> />
                    <input id="target_sku" type="hidden" name="target_sku" />
                    <input id="target_sales_id" type="hidden" name="target_sales_id" />
                </td>
			</tr>
			
			<?php if ($showKeywords): ?>
			<tr>
				<td class="right">Keyword</td>
				<td><input id="keyword" type="text" placeholder="Enter item keyword" name="keyword" value="<?=$keyword?>" autofocus /></td>
			</tr>
			<?php endif; ?>

			<tr>
				<td></td>
				<td><input type="submit" value="Search" /></td>
			</tr>
		</table>
	</form>
	<p id="error"><?=htmlentities($message)?></p>
<? if (!empty($matches)): ?>
    <table class="matches">
    <? foreach ($matches as $m): ?>
        <tr>
            <td><a href="javascript:selectMatch('<?=$m['id']?>', '<?=$m['sku']?>')"><img src="http://catalog.eocenterprise.com/img/<?=str_replace('-', '', $m['sku'])?>"/></a></td>
            <td><a href="javascript:selectMatch('<?=$m['id']?>', '<?=$m['sku']?>')"><?= htmlentities($m['sku']) ?></a></td>
            <td><a href="javascript:selectMatch('<?=$m['id']?>', '<?=$m['sku']?>')"><?= htmlentities($m['desc']) ?></a></td>
        </tr>
    <? endforeach; ?>
    </table>
<? endif; ?>
	<p id="footer">Done for the day? <a href="show_scan.php" target="_blank">Print SCAN Form</a></p>
</center>
<script src="js/jquery.min.js"></script>
<script type="text/javascript">
$(document).ready(function()
{
	$('#<?= $showKeywords ? 'keyword' : 'barcode' ?>').focus();
	$('#order_id').val(getCookie('last_order_id'));
	$('input[type="submit"]').click(function()
	{
		var order_id = $('#order_id').val();
		if (order_id == '')
		{
			alert('Please enter the supplier order number.');
			$('#order_id').focus();
			return false;
		}

		var barcode = $('#barcode').val();
		if (barcode == '')
		{
			alert('Please scan or enter the item barcode.');
			$('#barcode').focus();
			return false;
		}

<?php if ($showKeywords): ?>
		var keyword = $('#keyword').val();
		if (keyword == '')
		{
			alert('Please enter an item keyword.');
			$('#keyword').focus();
			return false;
		}
<?php endif; ?>
		
		setCookie('last_order_id', order_id, 3);
	});
});

function getCookie(c_name)
{
	var i, x, y, ARRcookies = document.cookie.split(";");
	for (i = 0; i < ARRcookies.length; i++)
	{
		x = ARRcookies[i].substr(0,ARRcookies[i].indexOf("="));
		y = ARRcookies[i].substr(ARRcookies[i].indexOf("=") + 1);
		x = x.replace(/^\s+|\s+$/g,"");
		if (x == c_name)
			return unescape(y);
	}
}

function setCookie(c_name, value, exdays)
{
	var exdate = new Date();
	exdate.setDate(exdate.getDate() + exdays);
	var c_value = escape(value) + ((exdays == null) ? "" : "; expires=" + exdate.toUTCString());
	document.cookie = c_name + "=" + c_value;
}

function selectMatch(salesId, sku)
{
    $('#target_sales_id').val(salesId);
    $('#target_sku').val(sku);
    $('#search_form').submit();
}
</script>
</body>
</html>