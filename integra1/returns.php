<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');
require_once('system/sphinxapi.php');

$user = Login();

$suppliers = array(
	1 => 'W1',
	2 => 'W2',
    3 => 'W3',
    4 => 'W4',
    5 => 'W5',
    6 => 'W6',
    7 => 'W7',
    8 => 'W8',
    9 => 'W9',
);

$statuses = array(
    1 => 'Return Pending',
    2 => 'Partial Return Pending',
    3 => 'Return Complete',
    4 => 'Partial Return Complete',
    5 => 'Refunded',
    6 => 'Refund Complete'
);

$reasons = array(
    1 => 'Customer Cancellation',
    2 => 'Customer Exchange',
    3 => 'Customer Refuse',
    4 => 'Customer Return',
    5 => 'Non-Fitment',
    6 => 'Undelivered',
    7 => "EOC WH Can't Ship",
    8 => 'Damaged In Transit'
);

$query = $_GET[q];
$inFilter = "";

if (!empty($query))
{
    $cl = new SphinxClient();
    $cl->SetServer(SPHINX_HOST, 3312);
    $cl->SetMatchMode(SPH_MATCH_EXTENDED);
    $cl->SetLimits(0, 500);
    $cl->SetRankingMode(SPH_RANK_PROXIMITY_BM25);

    $result = $cl->Query($query, 'returns');

    if ($result === false || empty($result[matches]))
        $inFilter = " WHERE 0 = 1";
    else $inFilter = " WHERE id IN (" . implode(array_keys($result[matches]), ',') . ")";
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

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'ex_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = <<<EOD
SELECT id, date_created, customer_ra, po_num, name, customer_tracking, supplier, order_num, sku, qty, status, reason, supplier_ra, refund_amount
FROM returns {$inFilter}
EOD;
$default_order = array('id' => 'DESC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, $default_order);
$layouts = array('view'=>'0', 'edit'=>'1', 'details'=>'0', 'filter'=>'2');
$dgrid->SetLayouts($layouts);
$modes = array
(
	'add'	  =>array('view'=>true, 'edit'=>false, 'type'=>'link',  'show_button'=>true, 'show_add_button'=>'inside'),
	'edit'	  =>array('view'=>true, 'edit'=>true,  'type'=>'link',  'show_button'=>true, 'byFieldValue'=>''),
	'details' =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true),
	'delete'  =>array('view'=>true, 'edit'=>true,  'type'=>'image', 'show_button'=>true)
);
$dgrid->SetModes($modes);
$css_class = 'x-blue';
$dgrid->SetCssClass($css_class);
$dgrid->AllowPrinting(false);
$dgrid->SetPagingSettings($paging, array(), $pages_array, 50, $paging_arrows);
$vm_table_properties = array('width'=>'70%');
$dgrid->SetViewModeTableProperties($vm_table_properties);  
$vm_columns = array
(
    'date_created'=>array('header'=>'Date Created', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'customer_ra'=>array('header'=>'Customer RA', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'po_num'=>array('header'=>'PO #', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'name'=>array('header'=>'Name', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'customer_tracking'=>array('header'=>'Tracking #', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'supplier'=>array('header'=>'Warehouse', 'type'=>'enum', 'align'=>'center', 'wrap'=>'nowrap', 'source' => $suppliers),
    'order_num'=>array('header'=>'Supplier Order #', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'sku'=>array('header'=>'SKU', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'qty'=>array('header'=>'Quantity', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'status'=>array('header'=>'Status', 'type'=>'enum', 'align'=>'center', 'wrap'=>'nowrap', 'source' => $statuses),
    'reason'=>array('header'=>'Reason', 'type'=>'enum', 'align'=>'center', 'wrap'=>'nowrap', 'source' => $reasons),
    'supplier_ra'=>array('header'=>'Supplier RA', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'refund_amount'=>array('header'=>'Refund Amount', 'type'=>'label', 'align'=>'right', 'wrap'=>'nowrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'returns';
$primary_key = 'id';
$condition   = 'returns.id = ' . $_REQUEST['ex_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
    'customer_ra'  =>array('header'=>'Customer RA', 'type'=>'textbox', 'req_type'=>'st', 'width'=>'210px', 'maxlength' =>'50'),
    'po_num'  =>array('header'=>'PO #', 'type'=>'textbox', 'req_type'=>'st', 'width'=>'210px', 'maxlength' =>'50'),
    'name'  =>array('header'=>'Name', 'type'=>'textbox', 'req_type'=>'st', 'width'=>'210px', 'maxlength' =>'100'),
    'customer_tracking'  =>array('header'=>'Tracking #', 'type'=>'textbox', 'req_type'=>'st', 'width'=>'210px', 'maxlength' =>'100'),
	'supplier'  =>array('header'=>'Warehouse', 'type'=>'enum', 'req_type'=>'st', 'source' => $suppliers),
    'order_num'  =>array('header'=>'Order #', 'type'=>'textbox', 'req_type'=>'st', 'width'=>'210px', 'maxlength' =>'50'),
    'sku'  =>array('header'=>'SKU', 'type'=>'textbox', 'req_type'=>'st', 'width'=>'210px', 'maxlength'=>'50'),
    'qty'  =>array('header'=>'Quantity', 'type'=>'text', 'req_type'=>'sn', 'width'=>'210px'),
    'status'  =>array('header'=>'Status', 'type'=>'enum', 'req_type'=>'st', 'source' => $statuses),
    'reason'  =>array('header'=>'Reason', 'type'=>'enum', 'req_type'=>'st', 'source' => $reasons),
    'supplier_ra'  =>array('header'=>'Supplier RA', 'type'=>'textbox', 'req_type'=>'st', 'width'=>'210px', 'maxlength' =>'50'),
    'refund_amount'  =>array('header'=>'Refund Amount', 'type'=>'money', 'req_type'=>'sn', 'width'=>'210px'),
);
$dgrid->SetColumnsInEditMode($em_columns);
$dgrid->AllowFiltering(true, false);

$filtering_fields = array(
    "From" => array(
        "type" => "calendar",
        "table" => "returns",
        "field" => "date_created",
        "field_type" => "from",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => ">=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => "",
        "calendar_type" => "floating"),
    "To" => array(
        "type" => "calendar",
        "table" => "returns",
        "field" => "date_created",
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
        "table" => "returns",
        "field" => "status",
        "source" => $statuses,
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
    "Reason" => array(
        "type" => "dropdownlist",
        "table" => "returns",
        "field" => "reason",
        "source" => $reasons,
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
    "Warehouse" => array(
        "type" => "dropdownlist",
        "table" => "returns",
        "field" => "supplier",
        "source" => $suppliers,
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
);

$dgrid->SetFieldsFiltering($filtering_fields);
$dgrid->Bind(false);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Returns</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2, p, li { font-family: tahoma, verdana; }
    .default_dg_label, .default_dg_error_message
    {
        font-family: tahoma, verdana;
        font-size: 12px;
    }
    .tblToolBar
    {
        display: none;
    }
    #ex__ff_returns_date_created_fo_from.x-blue_dg_textbox, #ex__ff_returns_date_created_fo_to.x-blue_dg_textbox
    {
        width: 80px !important;
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
<h2>Returns</h2>
    <?php
    if ($_REQUEST['ex_export'] != 'true')
    {
        ?>

        <form>
            <div class="x-blue_dg_caption" style="display: inline;">Search Returns:</div>
            <input id="search" name="q" type="text" value="<?=$query?>"/>
            <input class="x-blue_dg_button" type="submit" value="Search" />
        </form>

        <?php
        if (!empty($query))
        {
            if ($result[total] > 500)
                echo '<span class="x-blue_dg_warning_message">' . $result['total'] . ' matching returns found. Only first 500 are displayed. Please refine your search.</span>&nbsp;&nbsp;';

            echo "<a href='returns.php' class='x-blue_dg_label'>Show all returns</a><br/><br/>";
        }
    }

    $dgrid->Show();
    ob_end_flush();
    ?>
<br/>
</center>
<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js" type="text/javascript"></script>
<script src="js/jquery.jeditable.js" type="text/javascript"></script>
<script>
    $(document).ready(function()
    {
        $("#ex__contentTable tr td:nth-child(11)").each(function()
        {
            var id = $(this).closest('tr').find('a[href*="edit"]').first().attr('href').split(',')[1].replace(/"/g, '').trim();
            var status = $(this).text();

            $(this).html('<select onchange="changeStatus(' + id + ')" id="status_' + id + '">'
            <?
                foreach ($statuses as $code => $text)
                    echo "+ '<option value=" . '"' . $code . '">' . $text . "</option>'";
            ?>
            + '</select>');

            $('#status_' + id + ' option').each(function()
            {
                if ($(this).text() == status)
                {
                    $(this).attr('selected', 'selected');
                }
            });
        });

        $("#ex__contentTable tr td:nth-child(13)").each(function()
        {
            var id = $(this).closest('tr').find('a[href*="edit"]').first().attr('href').split(',')[1].replace(/"/g, '').trim();
            $(this).text($(this).text());
            $(this).attr('id', 'ra_' + id);

            $(this).editable("edit_return.php?field=supplier_ra",
                {
                    indicator : "<img src='img/ajax.gif'>",
                    event     : "click",
                    style	  : "inherit",
                    placeholder   : '      '
                });
        });

        $("#ex__contentTable tr td:nth-child(14)").each(function()
        {
            var id = $(this).closest('tr').find('a[href*="edit"]').first().attr('href').split(',')[1].replace(/"/g, '').trim();
            $(this).text($(this).text());
            $(this).attr('id', 'refund_' + id);

            $(this).editable("edit_return.php?field=refund_amount",
                {
                    indicator : "<img src='img/ajax.gif'>",
                    event     : "click",
                    style	  : "inherit",
                    placeholder   : '      '
                });
        });
    });

    function changeStatus(id)
    {
        $.ajax('change_status_return.php?code=' + $('#status_' + id).val() + '&id=' + id).fail(function()
        {
            alert('There was an error while changing the status.\nPlease check your internet connection.');
        });
    }
</script>
</body>
</html>