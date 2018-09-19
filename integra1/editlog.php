<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');

//require_once('system/acl.php');
//$user = Login();

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');
session_start();
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'dg_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$dgrid->SetPostBackMethod('GET');
$sql = 'SELECT item_id, source_id, is_new, created_by, created_on, edited_field, before_value, after_value FROM ebay_edit_log';
$default_order = array('created_on' => 'DESC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, $default_order);
$layouts = array('view'=>'0', 'edit'=>'0', 'details'=>'0', 'filter'=>'2');
$dgrid->SetLayouts($layouts);
$modes = array
(
    'add'	  =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true, 'show_add_button'=>'inside'),
    'edit'	  =>array('view'=>false, 'edit'=>false,  'type'=>'link',  'show_button'=>true, 'byFieldValue'=>''),
    'details' =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true),
    'delete'  =>array('view'=>false, 'edit'=>false,  'type'=>'image', 'show_button'=>true)
);
$dgrid->SetModes($modes);
$css_class = 'x-blue';
$dgrid->SetCssClass($css_class);
$dgrid->AllowPrinting(false);
$paging_option = false;
$rows_numeration = false;
$numeration_sign = '';
$dropdown_paging = false;

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

$actions = [0 => 'Revise', 1 => 'New'];

$dgrid->SetPagingSettings($paging, array(), $pages_array, 100, $paging_arrows);
$dgrid->SetViewModeTableProperties($vm_table_properties);
$vm_columns = array
(
    'created_on'=>array('header'=>'Date', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'created_by'=>array('header'=>'User', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'is_new'=>array('header'=>'Action', 'type'=>'enum', 'source' => $actions, 'align'=>'center', 'wrap'=>'nowrap'),
    'item_id'=>array('header'=>'Our Listing', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'source_id'=>array('header'=>'Source Listing', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'edited_field'=>array('header'=>'Field', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'before_value'=>array('header'=>'Before', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
    'after_value'=>array('header'=>'After', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);

$dgrid->AllowExporting(true, true);
$dgrid->AllowExportingTypes(array('csv'=>'true', 'xls'=>'true', 'pdf'=>'false', 'xml'=>'false'));

$dgrid->AllowFiltering(true, false);

$filtering_fields = array(
    "From" => array(
        "type" => "calendar",
        "table" => "ebay_edit_log",
        "field" => "created_on",
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
        "table" => "ebay_edit_log",
        "field" => "created_on",
        "field_type" => "to",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "<=",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => "",
        "calendar_type" => "floating"),
    "User" => array(
        "type" => "dropdownlist",
        "table" => "ebay_edit_log",
        "field" => "created_by",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "like%",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
    "Action" => array(
        "type" => "dropdownlist",
        "table" => "ebay_edit_log",
        "field" => "is_new",
        "source" => $actions),
    "Item ID" => array(
        "type" => "textbox",
        "table" => "ebay_edit_log",
        "field" => "item_id",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "=",
        "case_sensitive" => "true",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
    "Field" => array(
        "type" => "dropdownlist",
        "table" => "ebay_edit_log",
        "field" => "edited_field",
        "filter_condition" => "",
        "show_operator" => "false",
        "default_operator" => "like%",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "",
        "on_js_event" => ""),
);

$dgrid->SetFieldsFiltering($filtering_fields);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Integra :: eBay Edit Log</title>
    <?php $dgrid->WriteCssClass(); ?>
    <style>
        h2 { font-family: tahoma, verdana; }
        .x-blue_dg_textbox { width: auto !important; }
        img.preview { width: 225px !important; }
    </style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
    <h2>eBay Edit Log</h2>
    <?php
    $dgrid->Bind();
    ob_end_flush();
    ?>
</center>
<script src="js/jquery.min.js"></script>
<script>
    $(document).ready(function()
    {
        $('tr.dg_tr').each(function(i, v)
        {
            if ($(this).find('td').eq(5).find('label').text() != 'Picture') return;

            var pics = $(this).find('td').eq(6).find('label').text().split('http');
            var pic = '';
            var i;
            var img = '';

            for (i = 0; i < pics.length; i++)
            {
                pic = pics[i].trim();
                if (pic.length == 0) continue;
                img += '<img class="preview" src="http' + pic + '"/>';
            }


            $(this).find('td').eq(6).find('label').parent().html(img);

            pics = $(this).find('td').eq(7).find('label').text().split('http');
            img = '';

            for (i = 0; i < pics.length; i++)
            {
                pic = pics[i].trim();
                if (pic.length == 0) continue;
                img += '<img class="preview" src="http' + pic + '"/>';
            }

            $(this).find('td').eq(7).find('label').parent().html(img);
        });
    });
</script>
</body>
</html>