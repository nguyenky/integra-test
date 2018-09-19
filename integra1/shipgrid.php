<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/sphinxapi.php');
require_once('system/acl.php');

$user = Login('shipgrid');

set_time_limit(120);

ob_start();

$statusCodes = array(
    2 => 'Item Ordered / Waiting',
    3 => 'Ready for Dispatch',
    4 => 'Order Complete'
);

$classCodes = array(
    'Standard / Ground' => 'Standard / Ground',
    'Expedited / Express' => 'Expedited / Express',
    'Second Day' => 'Second Day',
    'Next Day / Overnight' => 'Next Day / Overnight',
    'International' => 'International',
    'Local Pick Up' => 'Local Pick Up'
);

$statusTips = array(
    2 => 'The item has been ordered from the supplier, but has not yet arrived.',
    3 => 'The item has arrived from the supplier, and ready for pickup or shipping.',
    4 => 'The item is either on the way or has been received by the customer.'
);

$query = array_key_exists('q', $_GET) ? trim($_GET['q']) : '';
$inFilter = "";

// add 0 prefix for IMC
if ((stripos($query, '2') === 0) && (strlen($query) == 9))
    $query = '0' . $query;

if (!empty($query))
{
    $cl = new SphinxClient();
    $cl->SetServer(SPHINX_HOST, 3312);
    $cl->SetMatchMode(SPH_MATCH_EXTENDED);
    $cl->SetLimits(0, 500);
    $cl->SetRankingMode(SPH_RANK_PROXIMITY_BM25);

    $result = $cl->Query($query, 'sales');

    if ($result === false || empty($result[matches]))
        $inFilter = " AND 0 = 1";
    else $inFilter = " AND id IN (" . implode(array_keys($result[matches]), ',') . ")";
}

$paging = array(
    "results" => true,
    "results_align" => "left",
    "pages" => true,
    "pages_align" => "center",
    "page_size" => true,
    "page_size_align" => "right"
);

$pages_array = array(
    "25" => "25",
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

// TODO: change v_ship_gridremarks

$sql = "SELECT
		id,
		country,
		order_date,
		record_num,
		status,
        ordered,
        supplier,
        etd,
        stamped,
        speed,
		(SELECT oh.remarks
    FROM integra_prod.order_history oh, integra_prod.users u
    WHERE oh.order_id = s.id
    AND u.email = '{$user}'
    AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
    AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
    AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
    AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
    AND oh.remarks > ''
    ORDER BY oh.ts DESC
    LIMIT 1) AS remarks
	FROM eoc.v_shipgrid s
	WHERE 1 = 1 {$inFilter}";

$columns = array(
    "id" => array(
        "type" => "label",
        "visible" => "false"),
    "order_date" => array(
        "header" => "Order Date",
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
    "record_num" => array(
        "header" => "Record #",
        "type" => "link",
        "align" => "left",
        "width" => "",
        "wrap" => "nowrap",
        "text_length" => "-1",
        "case" => "normal",
        "summarize" => "false",
        "sort_by" => "",
        "visible" => "true",
        "field_key" => "id",
        "field_data" => "record_num",
        "target" => "ship",
        "href" => "ship.php?sales_id={0}",
        "on_js_event" => ""),
    "status" => array(
        "header" => "Status",
        "type" => "enum",
        "source" => $statusCodes,
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
    "ordered" => array(
        "header" => "Ordered",
        "type" => "data",
        "align" => "center",
        "width" => "",
        "wrap" => "nowrap",
        "text_length" => "-1",
        "case" => "normal",
        "summarize" => "false",
        "sort_by" => "",
        "visible" => "true",
        "on_js_event" => ""),
    "supplier" => array(
        "header" => "Source",
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
        "on_js_event" => ""),
    "stamped" => array(
        "header" => "Stamp",
        "type" => "data",
        "align" => "center",
        "width" => "",
        "wrap" => "nowrap",
        "text_length" => "-1",
        "case" => "normal",
        "summarize" => "false",
        "sort_by" => "",
        "visible" => "true",
        "on_js_event" => ""),
    "speed" => array(
        "header" => "Class",
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
    "remarks" => array(
        "header" => "Remarks",
        "type" => "link",
        "readonly" => true,
        "align" => "left",
        "width" => "",
        "wrap" => "nowrap",
        "text_length" => "60",
        "case" => "normal",
        "summarize" => "false",
        "sort_by" => "",
        "visible" => "true",
        "field_key" => "id",
        "field_data" => "remarks",
        "href" => "javascript:openHistory({0})",
        "on_js_event" => ""),
);

$dgSales = new DataGrid(false, true, 'sh_');
$dgSales->SetColumnsInViewMode($columns);
$dgSales->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('order_date' => 'DESC'));

$layouts = array(
    "view" => "0",
    "edit" => "0",
    "details" => "1",
    "filter" => "2"
);
$dgSales->setLayouts($layouts);
$dgSales->SetPostBackMethod('GET');
$dgSales->SetModes(array());
$dgSales->SetCssClass("x-blue");
$dgSales->AllowSorting(true);
$dgSales->AllowPrinting(false);
$dgSales->AllowExporting(false, false);
$dgSales->SetPagingSettings($paging, array(), $pages_array, 50, $paging_arrows);
$dgSales->SetHttpGetVars(array("q"));
$dgSales->AllowFiltering(true, false);

$filtering_fields = array(
    "Order From" => array(
        "type" => "calendar",
        "table" => "s",
        "field" => "order_date",
        "field_type" => "from",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => ">=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => "",
        "calendar_type" => "floating"),
    "Order To" => array(
        "type" => "calendar",
        "table" => "s",
        "field" => "order_date",
        "field_type" => "to",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "<=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => "",
        "calendar_type" => "floating"),
    "Status" => array(
        "type" => "dropdownlist",
        "table" => "s",
        "field" => "status",
        "source" => $statusCodes,
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
    "ETD From" => array(
        "type" => "calendar",
        "table" => "s",
        "field" => "etd",
        "field_type" => "from",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => ">=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => "",
        "calendar_type" => "floating"),
    "ETD To" => array(
        "type" => "calendar",
        "table" => "s",
        "field" => "etd",
        "field_type" => "to",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "<=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => "",
        "calendar_type" => "floating"),
    "Country" => array(
        "type" => "dropdownlist",
        "table" => "s",
        "field" => "country",
        "source" => ['US' => 'US'],
        "filter_condition" => "",
        "show_operator" => "true",
        "default_operator" => "",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
    "Class" => array(
        "type" => "dropdownlist",
        "table" => "s",
        "field" => "speed",
        "source" => $classCodes,
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
);

$dgSales->SetFieldsFiltering($filtering_fields);
$dgSales->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <title>Shipment Grid</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" href="css/jquery-ui.css">
    <style>
        input:hover#search
        {
            border: 2px inset !important;
        }
        .default_dg_label, .default_dg_error_message
        {
            font-family: tahoma, verdana;
            font-size: 12px;
        }
        h2
        {
            font-family: tahoma, verdana;
            font-size: 16px;
        }
        #sh_a_hide, #sh_a_unhide
        {
            display: none;
        }
        .x-blue_dg_textbox
        {
            width: 100px !important;
        }
        #history_frame
        {
            border: none;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
    <h2>Shipments Grid</h2>

    <form>
        <div class="x-blue_dg_caption" style="display: inline;">Search Orders:</div>
        <input id="search" name="q" type="text" value="<?=$query?>"/>
        <input class="x-blue_dg_button" type="submit" value="Search" />&nbsp;
        <input id="dispatch" class="x-blue_dg_button" type="button" value="Set Linked Orders for Dispatch" />&nbsp;
        <input id="bulk_etd" class="x-blue_dg_button" type="button" value="Set ETD of Linked Orders" />
    </form>

    <?php
    if (!empty($query))
    {
        if ($result[total] > 500)
            echo '<span class="x-blue_dg_warning_message">' . $result['total'] . ' matching orders found. Only first 500 are displayed. Please refine your search.</span>&nbsp;&nbsp;';

        echo "<a href='shipgrid.php' class='x-blue_dg_label'>Show all orders</a><br/><br/>";
    }

    $dgSales->Show();
    ob_end_flush();
    ?>
</center>
<br/>
<br/>

<div id="history_popup" class="brandPopup" title="Order History">
    <iframe id="history_frame" src=""></iframe>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js" type="text/javascript"></script>
<script src="js/jquery.ui.position.js" type="text/javascript"></script>
<script src="js/jquery.jeditable.js" type="text/javascript"></script>
<script>
    var historySalesId = 0;

    $("#history_popup").dialog({
        autoOpen: false,
        modal: true,
        width: 700,
        height: 500,
        open: function(ev, ui){
            $('#history_frame').attr('src','history.php?sales_id=' + historySalesId);
            $('.ui-dialog :button').blur();
        }
    });

    function openHistory(id)
    {
        historySalesId = id;
        $('#history_frame').contents().find("body").html('');
        $('#history_popup').dialog('open');
    }

    function changeStatus(sales_id)
    {
        $.ajax('change_status.php?code=' + $('#status_' + sales_id).val() + '&sales_id=' + sales_id).fail(function()
        {
            alert('There was an error while changing the order status.\nPlease check your internet connection.');
        });
    }

    $(document).ready(function()
    {
        $('table.x-blue_dg_filter_table tr').append($('table.tblToolBar').contents().contents().contents());
        $('table.tblToolBar').remove();
        $('table.x-blue_dg_filter_table td[align=right]').attr('align','left');

        var operator = $('#sh__ff_s_country_operator').val();

        $("#sh__ff_s_country_operator").empty();
        $("#sh__ff_s_country_operator").append("<option value=''>All</option>");
        $("#sh__ff_s_country_operator").append("<option value='='>US Only</option>");
        $("#sh__ff_s_country_operator").append("<option value='!='>International</option>");
        $('#sh__ff_s_country').hide();

        if ($('#sh__ff_s_country').val() == '')
            $('#sh__ff_s_country_operator').val('');
        else
            $('#sh__ff_s_country_operator').val(operator);

        $('#sh__ff_s_country_operator').change(function()
        {
            if ($('#sh__ff_s_country_operator').val() == '')
                $('#sh__ff_s_country').val('');
            else
                $('#sh__ff_s_country').val('US');
        });

        if ($('#sh__ff_s_country_operator').val() == '')
            $('#sh__ff_s_country').val('');
        else
            $('#sh__ff_s_country').val('US');

        $('#dispatch').click(function()
        {
            var isnum = /^\d+$/.test($('#search').val().trim());

            if (!isnum)
            {
                alert('Please enter a valid order number in the search box.');
                return false;
            }

            $.get('dispatch.php?id=' + $('#search').val()).done(function(data)
            {
                alert(data + ' linked orders set to "Ready for Dispatch"');
            });

            return false;
        });

        $('#bulk_etd').click(function()
        {
            var isnum = /^\d+$/.test($('#search').val().trim());

            if (!isnum)
            {
                alert('Please enter a valid order number in the search box.');
                return false;
            }

            var etd = prompt('Enter ETD (yyyy-mm-dd)', '');
            if (etd == '') return false;

            $.get('bulketd.php?id=' + $('#search').val() + '&etd=' + etd).done(function(data)
            {
                alert(data + ' linked orders updated');
            });

            return false;
        });

        $('tr.dg_tr').each(function(i, v)
        {
            var temp = $(this).find('td').eq(1).find('a').attr('href');
            if (temp === undefined)
                return true;
            temp = temp.split('=');
            sales_id = temp[temp.length-1];

            status = $(this).find('td').eq(2).text();

            $(this).find('td').eq(2).html('<select onchange="changeStatus(' + sales_id + ')" id="status_' + sales_id + '">'
            <?
                foreach ($statusCodes as $code => $text)
                            echo "+ '<option value=" . '"' . $code . '" title="' . $statusTips[$code] . '">' . $text . "</option>'";
            ?>
            + '</select>');

            $('#status_' + sales_id).find('option:contains("' + status + '")').attr('selected', true);
        });

        $('#sh__contentTable tr td:nth-child(6)').each(function()
        {
            var temp = $(this).closest('tr').find('td').eq(1).find('a').attr('href');
            if (temp === undefined)
                return true;
            temp = temp.split('=');
            var id = temp[temp.length-1];
            $(this).text($(this).text());
            $(this).attr('id', 'etd_' + id);
            $(this).editable("edit_etd.php",
                {
                    indicator : "<img src='img/ajax.gif'>",
                    event     : "click",
                    style	  : "inherit",
                    width     : "300"
                });
        });
    });
</script>
</body>
</html>