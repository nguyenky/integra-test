<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login();

/*
 * ============================================================== *
 * 				 Add New logic to upload csv file 				  *
 * ============================================================== *
*/

if ($_REQUEST['upload'] == '1')
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$tempFile = $_FILES['list']['tmp_name'];
	$targetFile =  "/tmp/sku_translation_" . time();
	move_uploaded_file($tempFile, $targetFile);

	system("sed -i '/^\\s*$/d' {$targetFile}");

	$lines = count(explode("\n", file_get_contents($targetFile)));

	if ($lines > 0) {
		mysql_query("TRUNCATE TABLE eoc.sku_translation");

		$sql = "
		LOAD DATA LOCAL INFILE '$targetFile'
		INTO TABLE eoc.sku_translation
		FIELDS TERMINATED BY ',' ENCLOSED BY '\"'
		LINES TERMINATED BY '\r\n'
		IGNORE 1 LINES 
		(orig_sku, new_sku)";

		mysql_query($sql);
	}

	header('Location: translate_sku.php');
	return;
}

// END for uploading


header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'ex_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = <<<EOD
SELECT id, orig_sku, new_sku
FROM sku_translation
EOD;
$default_order = array('id' => 'ASC');
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
		"200" => "200",
		"500" => "500",
		"1000" => "1000",
);

$paging_arrows = array(
		"first" => "|&lt;&lt;",
		"previous" => "&lt;&lt;",
		"next" => "&gt;&gt;",
		"last" => "&gt;&gt;|"
);
$dgrid->SetPagingSettings($paging, $paging, $pages_array, 50, $paging_arrows);
$dgrid->AllowExporting(true, true);
$dgrid->AllowExportingTypes(array('csv'=>'true', 'xls'=>'true', 'pdf'=>'false', 'xml'=>'false'));
$vm_table_properties = array('width'=>'70%');
$dgrid->SetViewModeTableProperties($vm_table_properties);  
$vm_columns = array
(
	'orig_sku'=>array('header'=>'Old SKU', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'new_sku'=>array('header'=>'New SKU', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'sku_translation';
$primary_key = 'id';
$condition   = 'sku_translation.id = ' . $_REQUEST['ex_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'orig_sku'  =>array('header'=>'Old SKU', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'50'),
    'new_sku'  =>array('header'=>'New SKU', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'50'),
);
$dgrid->SetColumnsInEditMode($em_columns);

$filtering_fields = array(
		"Old SKU" => array(
				"type" => "textbox",
				"table" => "sku_translation",
				"field" => "orig_sku",
				"filter_condition" => "",
				"show_operator" => "false",
				"default_operator" => "%like%",
				"case_sensitive" => "false",
				"comparison_type" => "string",
				"width" => "140px",
				"on_js_event" => ""),
		"New SKU" => array(
				"type" => "textbox",
				"table" => "sku_translation",
				"field" => "new_sku",
				"filter_condition" => "",
				"show_operator" => "false",
				"default_operator" => "%like%",
				"case_sensitive" => "false",
				"comparison_type" => "string",
				"width" => "140px",
				"on_js_event" => ""),
);

$dgrid->AllowFiltering(true, false);
$dgrid->SetFieldsFiltering($filtering_fields);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: SKU Translation</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2, p, li { font-family: tahoma, verdana; }
	#instructions
	{
        font-family: tahoma, verdana;
		width: 700px;
		display: block;
		margin-left: auto;
		margin-right: auto;
		text-align: center;
	}
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>SKU Translation</h2>
</center>
<div id="instructions">
    The old SKUs below will be changed automatically to the new SKUs as they arrive.
	<br/>
	<a href="gen_kits.php">Download Kits with Prices</a>
</div>
<br/>
<center>
	<p>
		<form method="POST" action="translate_sku.php?upload=1" enctype="multipart/form-data">
			<p>
				Upload new list (will replace all entries)<br/>
				<input name="list" type="file" accept=".txt" />
				<input type="submit" value="Upload">
			</p>
		</form>
	</p>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
</body>
</html>