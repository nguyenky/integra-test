<?php
require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login('egrid');
set_time_limit(0);
$query = $_REQUEST['q'];
$action = $_REQUEST['action'];
$seller = EBAY_SELLER;

$strategyCodes = array(
		1 => 'Match',
		2 => 'Go Under',
		99 => 'Matrix'
);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$rows = mysql_query("SELECT id FROM integra_users WHERE email = '${user}'");
$row = mysql_fetch_row($rows);
$userId = $row[0];
settype($userId, 'integer');

$file = __DIR__ . DIRECTORY_SEPARATOR . 'jobs' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'egrid.' . $userId;

if ($action == 'clear')
{
	mysql_query("DELETE FROM ebay_research WHERE user_id = '$userId'");
}
else if ($action == 'search')
{
	if ($_FILES['file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['file']['tmp_name']))
	{
		if (move_uploaded_file($_FILES['file']['tmp_name'], $file))
			exec('nohup php ' . __DIR__ . DIRECTORY_SEPARATOR . 'jobs' . DIRECTORY_SEPARATOR . 'e_research_file.php ' . $userId . ' '. 1 .' >/dev/null 2>/dev/null &');
	}
	else
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "http://integra.eocenterprise.com/e_research.php?u=${userId}&q=" . urlencode($query));
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);
		curl_exec($ch);
	}
}
else if ($action == 'download')
{
	$sql = <<<EOD
	SELECT keywords, item_id, title, image_url, price, shipping, seller, num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, brand
	FROM ebay_research
	WHERE user_id = '${userId}'
EOD;

	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=egrid.csv");
	header("Pragma: no-cache");
	header("Expires: 0");

	$output = fopen('php://output', 'w');
	fputcsv($output, ['Keywords', 'Item ID', 'Title', 'Image', 'Price', 'Shipping', 'Seller', 'Hits', 'Qty Sold', 'Compatibility', 'Qty Available', 'Category', 'MPN', 'IPN', 'OPN', 'Brand']);

	$rows = mysql_query($sql);
	while ($row = mysql_fetch_row($rows))
		fputcsv($output, $row);

	return;
}

$keywords = [];
$rows = mysql_query("SELECT DISTINCT keywords FROM eoc.ebay_research WHERE user_id = '${userId}' ORDER BY keywords");
while ($row = mysql_fetch_row($rows))
	$keywords[$row[0]] = $row[0];

session_start();
ob_start();

$paging = array(
		"results" => true,
		"results_align" => "left",
		"pages" => true,
		"pages_align" => "center",
		"page_size" => true,
		"page_size_align" => "right"
);

$pages_array = array(
		"50" => "50",
		"100" => "100",
		"200" => "200"
);

$paging_arrows = array(
		"first" => "|&lt;&lt;",
		"previous" => "&lt;&lt;",
		"next" => "&gt;&gt;",
		"last" => "&gt;&gt;|"
);

$columns = array(
	"monitor" => array(
		"header" => "Monitor",
		"type" => "checkbox",
		"align" => "center",
		"true_value" => "1",
		"false_value" => "0"),
	"keywords" => array(
		"header" => "Keywords",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap"),
	"image" => array(
		"header" => "Image",
		"type" => "data",
		"align" => "center"),
	"item_id" => array(
		"header" => "Item ID",
		"type" => "link",
		"align" => "left",
		"wrap" => "nowrap",
		"sort_by" => "item_id",
		"field_key" => "item_id",
		"field_data" => "item_id",
        "target" => "_blank",
		"href" => "http://www.ebay.com/itm/{0}"),
	"title" => array(
		"header" => "Title",
		"type" => "link",
		"align" => "left",
		"wrap" => "nowrap",
		"sort_by" => "title",
		"field_key" => "item_id",
		"field_data" => "title",
		"target" => "_blank",
		"href" => "http://www.ebay.com/itm/{0}"),
	"vs_ours" => array(
		"header" => "Reprice With",
		"type" => "label",
		"align" => "center",
		"wrap" => "nowrap"),
	"strategy" => array(
		"header" => "Strategy",
		"type" => "enum",
		"source" => $strategyCodes,
		"readonly" => true,
		"align" => "center",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"brand" => array(
		"header" => "Brand",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap"),
	"seller" => array(
		"header" => "Seller",
		"type" => "label",
		"align" => "left"),
	"price" => array(
		"header" => "Price",
		"type" => "money",
		"align" => "right",
		"sort_by" => "price",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ","),
	"min_price" => array(
		"header" => "Min Price",
		"type" => "money",
		"align" => "right",
		"sort_by" => "price",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ","),
	"shipping" => array(
		"header" => "Shipping",
		"type" => "money",
		"align" => "right",
		"sort_by" => "shipping",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ","),
	"num_hit" => array(
		"header" => "Hits",
		"type" => "label",
		"align" => "right",
		"sort_by" => "num_hit",
		"sort_type" => "numeric"),
	"num_sold" => array(
		"header" => "Qty Sold",
		"type" => "label",
		"align" => "right",
		"sort_by" => "num_sold",
		"sort_type" => "numeric"),
	"num_compat" => array(
		"header" => "Compatible Vehicles",
		"type" => "label",
		"align" => "right",
		"sort_by" => "num_compat",
		"sort_type" => "numeric"),
	"mpn" => array(
		"header" => "MPN",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap"),
	"opn" => array(
		"header" => "OPN",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap"),
	"ipn" => array(
		"header" => "IPN",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap"),
	"sku" => array(
		"header" => "SKU",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap"),
	"category" => array(
		"header" => "Category",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap"),
	);

$brandFilter = '';
$kwFilter = '';

if (isset($_REQUEST['sh__ff_ebay_research_brand']))
	$brandFilter = trim(str_replace("'", '', $_REQUEST['sh__ff_ebay_research_brand']));

if (isset($_REQUEST['sh__ff_ebay_research_keywords']))
	$kwFilter = trim(str_replace("'", '', $_REQUEST['sh__ff_ebay_research_keywords']));

$sql = <<<EOD
SELECT user_id,
IFNULL((SELECT 1 FROM ebay_monitor em WHERE disable = 0 AND em.item_id = ebay_research.item_id LIMIT 1), 0) AS monitor,
keywords, item_id, title,
CONCAT('<a target="_blank" class="preview" href="', image_url, '"><img class="preview" src="', image_url, '"/></a>') AS image,
price, shipping, seller, num_hit, num_sold, num_compat, num_avail,
mpn, ipn, opn, brand, sku, category,
(SELECT vs_ours FROM ebay_monitor em WHERE em.item_id = ebay_research.item_id LIMIT 1) AS vs_ours,
(SELECT strategy FROM ebay_monitor em WHERE em.item_id = ebay_research.item_id LIMIT 1) AS strategy,
(SELECT min_price FROM ebay_listings el WHERE el.item_id = ebay_research.item_id LIMIT 1) AS min_price
FROM ebay_research
WHERE (user_id = '${userId}' AND seller = 'qeautoparts1' AND keywords LIKE '%{$kwFilter}%' AND brand LIKE '%{$brandFilter}%') OR
user_id = '${userId}'
EOD;
$dg = new DataGrid(false, false, 'sh_');
$dg->SetColumnsInViewMode($columns);
$dg->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('num_sold' => 'DESC'));
$layouts = array(
	"view" => "0",
	"edit" => "0", 
	"details" => "1", 
	"filter" => "2"
	);
$dg->SetLayouts($layouts);
$dg->SetPostBackMethod('GET');
$dg->SetModes(array());
$dg->SetCssClass("x-blue");
$dg->AllowSorting(true);
$dg->AllowPrinting(false);
$dg->AllowExporting(false, false);
$dg->SetPagingSettings($paging, array(), $pages_array, 100, $paging_arrows);

$filtering_fields = array(
		"Keywords" => array(
				"type" => "dropdownlist",
				"table" => "ebay_research",
				"field" => "keywords",
				"source" => $keywords,
				"filter_condition" => "",
				"show_operator" => "false",
				"default_operator" => "=",
				"case_sensitive" => "false",
				"comparison_type" => "string",
				"width" => "",
				"on_js_event" => ""),
		"Brand" => array(
				"type" => "textbox",
				"table" => "ebay_research",
				"field" => "brand",
				"default_operator" => "%like%",
				"show_operator" => "false",
				"case_sensitive" => "false",
				"comparison_type" => "string",
				"width" => "100px",
				"on_js_event" => ""),
		"Seller" => array(
				"type" => "dropdownlist",
				"table" => "ebay_research",
				"field" => "seller",
				"filter_condition" => "",
				"show_operator" => "false",
				"default_operator" => "=",
				"case_sensitive" => "false",
				"comparison_type" => "string",
				"multiple" => true,
				"multiple_size" => 8,
				"width" => "",
				"on_js_event" => ""),
);

$dg->AllowFiltering(true, false);
$dg->SetFieldsFiltering($filtering_fields);
$dg->Bind(false);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>eBay Keyword Research</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		img.preview
		{
			height: 50px;
		}
		#preview
		{
			position:absolute;
			border:1px solid #ccc;
			background:#333;
			padding:5px;
			display:none;
			color:#fff;
		}
		.ourListing
		{
			background-color: lightgreen !important;
		}
		input:hover#search
		{
			border: 2px inset !important;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<br/>
<?php
	if ($_REQUEST['sh_export'] != 'true'):
?>
		<form id="searchForm" method="GET">
			<div class="x-blue_dg_caption" style="display: inline;">  Keyword Search:</div>
			<input id="action" name="action" type="hidden" value="search"/>
			<input id="search" name="q" type="text" value="<?=$query?>"/>
			<?php
			if (!is_file($file))
			{
				echo ' or <input id="file" accept=".txt" onchange="selectFile();" type="file" name="file" /><br/><br/>';
			}
			?>
			<input class="x-blue_dg_button" type="submit" value="Search & Add to Grid" /> &nbsp;
			<input class="x-blue_dg_button" type="button" onclick="download();" value="Download Results" /> &nbsp;
			<input class="x-blue_dg_button" type="button" onclick="clearResults();" value="Start Over" /> <sup> current user: <?=$userId;?> </sup>

			<?php
				if (is_file($file))
				{
					echo '<br/><p><div class="x-blue_dg_caption" style="display: inline;"><i>Integra is currently processing your last uploaded file</i></div></p>';
				}
			?>
		</form>
<?php
	endif;
?>
</center>
<div class="apphp_datagrid">
<?php
	$dg->Show();
    ob_end_flush();
?>
</div>
<br/><br/><br/><br/><br/><br/>
<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js" type="text/javascript"></script>
<script src="js/jquery.jeditable.js" type="text/javascript"></script>
<script>
function monitorChanged()
{
	var monitor = $(this).prop('checked');
	var id = $(this).attr('item_id');
	var cb = $(this);
	var img = $('.monitor_ajax[item_id=' + id + ']');
	cb.hide();
	img.show();

	$.get('ebay_monitor_switch.php?id=' + id + '&on=' + (monitor ? '1' : '0'))
			.done(function(data)
			{
				$(this).prop('checked', (data == '1'));
			})
			.fail(function()
			{
				alert('Unable to set item monitoring status. Try to refresh your browser.');
				$(this).prop('checked', (monitor ? false : true));
			})
			.always(function()
			{
				img.hide();
				cb.show();
			});
}

this.imagePreview = function()
{
	xOffset = 10;
	yOffset = 30;
	$("a.preview").hover(function(e)
	{
		var href = $(this).find('img').first().attr('src');
		this.t = this.title;
		this.title = "";	
		var c = (this.t != "") ? "<br/>" + this.t : "";
		$("body").append("<p id='preview'><img src='"+ href +"' alt='Image preview' />"+ c +"</p>");
		$("#preview")
			.css("top",(e.pageY - xOffset) + "px")
			.css("left",(e.pageX + yOffset) + "px")
			.fadeIn("fast");						
    },
	function()
	{
		this.title = this.t;	
		$("#preview").remove();
    });	
	$("a.preview").mousemove(function(e)
	{
		$("#preview")
			.css("top",(e.pageY - xOffset) + "px")
			.css("left",(e.pageX + yOffset) + "px");
	});			
};
$(document).ready(function()
{
	imagePreview();

	$('#sh__contentTable tbody td:contains("<?=$seller?>")').closest('tr').addClass('ourListing');

    $('#sh__contentTable tr:contains("<?=$seller?>") td:nth-child(5)').each(function()
    {
        var id = $(this).closest('tr').find('td:nth-child(4)').text().trim();
        $(this).text($(this).text());
        $(this).attr('id', 'title_' + id);
        $(this).attr('prev', $(this).text());
        $(this).editable("revise_node.php?field=title",
            {
                indicator : "<img src='img/ajax.gif'>",
                event     : "click",
                style	  : "inherit",
                width     : "300",
                callback: function(value, settings)
                {
                    $(this).attr('prev', $(this).text());
                    settings.submitdata = {prev: $(this).attr('prev')};
                },
                submitdata : {prev: $(this).attr('prev')}
            });
    });

    $('#sh__contentTable tr:contains("<?=$seller?>") td:nth-child(10)').each(function()
    {
        var id = $(this).closest('tr').find('td:nth-child(4)').text().trim();
        $(this).text($(this).text());
        $(this).attr('id', 'price_' + id);
        $(this).attr('prev', $(this).text());
        $(this).editable("revise_node.php?field=price",
            {
                indicator : "<img src='img/ajax.gif'>",
                event     : "click",
                style	  : "inherit",
                width     : "100",
                callback: function(value, settings)
                {
                    $(this).attr('prev', $(this).text());
                    settings.submitdata = {prev: $(this).attr('prev')};
                },
                submitdata : {prev: $(this).attr('prev')}
            });
    });

	$('#sh__contentTable tr:contains("<?=$seller?>") td:nth-child(11)').each(function()
	{
		var id = $(this).closest('tr').find('td:nth-child(4)').text().trim();
		$(this).text($(this).text());
		$(this).attr('id', 'minprice_' + id);
		$(this).attr('prev', $(this).text());
		$(this).editable("revise_node.php?field=minprice",
			{
				indicator : "<img src='img/ajax.gif'>",
				event     : "click",
				style	  : "inherit",
				width     : "100",
				callback: function(value, settings)
				{
					$(this).attr('prev', $(this).text());
					settings.submitdata = {prev: $(this).attr('prev')};
				},
				submitdata : {prev: $(this).attr('prev')}
			});
	});

	$('#sh__contentTable tr:not(:contains("<?=$seller?>")) td:nth-child(6)').each(function()
	{
		var id = $(this).closest('tr').find('td:nth-child(4)').text().trim();
		$(this).text($(this).text());
		$(this).attr('id', 'vsours_' + id);
		$(this).attr('prev', $(this).text());
		$(this).editable("revise_node.php?field=vsours",
			{
				indicator : "<img src='img/ajax.gif'>",
				event     : "click",
				style	  : "inherit",
				width     : "300",
				callback: function(value, settings)
				{
					$(this).attr('prev', $(this).text());
					settings.submitdata = {prev: $(this).attr('prev')};

					var monitorbox = $("input[name^=symonitor][item_id='" + id + "']");

					if (value && value.length > 0 && !monitorbox.prop('checked'))
						monitorbox.trigger('click');
				},
				submitdata : {prev: $(this).attr('prev')}
			});
	});

	$('#sh__contentTable tr:not(:contains("<?=$seller?>")) td:nth-child(7)').each(function()
	{
		var id = $(this).closest('tr').find('td:nth-child(4)').text().trim();
		var strategy = $(this).text();
		$(this).html('<select onchange="changeStrategy(' + id + ')" id="strategy_' + id + '">'
			<?
				foreach ($strategyCodes as $code => $text)
					echo "+ '<option value=" . '"' . $code . '">' . $text . "</option>'";
			?>
			+ '</select>');

		if (!strategy || strategy.length == 0) strategy = 'Match'; // default
		$('#strategy_' + id).find('option:contains("' + strategy + '")').attr('selected', true);
	});

	$('#sh__contentTable tr:contains("<?=$seller?>") td:nth-child(3) a').each(function()
	{
		var id = $(this).closest('tr').find('td:nth-child(4)').text().trim();
		$(this).attr('id', 'picture_' + id);
		$(this).attr('href', 'javascript:void(0)');
		$(this).removeAttr('target');
		$(this).click(function(event)
		{
			event.stopPropagation();

			var new_pic = prompt('Enter new listing image URL', '');
			if (!new_pic || new_pic.length == 0) return;
			if (new_pic.indexOf('http') != 0)
			{
				alert('Image URL must start with http. Check if the URL is valid.');
				return false;
			}

			var img = $(this).find('img').first();
			var prev = img.attr('src');

			var data = {
				id: $(this).attr('id'),
				prev: prev,
				value: new_pic
			};

			img.attr('src', 'img/ajax.gif');

			$.post("revise_node.php?field=picture", data)
				.success(function(res)
				{
					img.attr('src', res);
				})
				.error(function()
				{
					img.attr('src', prev);
				});

			return false;
		});
	});

	$('input[value=Reset]').click(function(e) {
		$('#sh__ff_ebay_research_brand').val('');
		$('#sh__ff_ebay_research_seller').val(null);
		$('input[value=Search]').trigger('click');
	});
});

function changeStrategy(sales_id)
{
	$.post('revise_node.php?field=strategy', {value: $('#strategy_' + sales_id).val(), id: 'strategy_' + sales_id}).fail(function()
	{
		alert('There was an error while changing the strategy.\nPlease check your internet connection.');
	});
}

function clearResults()
{
	$('#action').val('clear');
	$('#searchForm').submit();
}

function download()
{
	$('#action').val('download');
	$('#searchForm').submit();
}

function selectFile()
{
	$('#searchForm').attr('method', $('#file').val() ? 'POST' : 'GET');
	$('#searchForm').attr('enctype', 'multipart/form-data');
}

$('input[name^=symonitor]').each(function()
{
	var id = $(this).closest('tr').find('td:nth-child(4)').text().trim();
	$(this).after('<img src="img/ajax.gif" style="display:none" class="monitor_ajax" item_id="' + id + '">');
	$(this).attr('item_id', id);
	$(this).removeAttr('onclick');
	$(this).click(monitorChanged);
});

$('table.tblToolBar').remove();
$('.x-blue_dg_filter_table td[align=right]').attr('align', 'left');
$('#sh_searchset').css('width', 'auto');
$('#sh_searchset .x-blue_dg_fieldset').css('width', 'auto');
</script>
</body>
</html>