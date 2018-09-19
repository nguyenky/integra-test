<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');

if ($_REQUEST['upload'] == '1')
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$tempFile = $_FILES['list']['tmp_name'];
	$targetFile =  "/tmp/ipo_mpn_" . time();
	move_uploaded_file($tempFile, $targetFile);

	system("sed -i '/^\\s*$/d' {$targetFile}");

	$lines = count(explode("\n", file_get_contents($targetFile)));

	if ($lines > 0) {
		mysql_query("TRUNCATE TABLE eoc.imc_use_ipo");

		$sql = "
	LOAD DATA LOCAL INFILE '$targetFile'
	INTO TABLE eoc.imc_use_ipo
	FIELDS TERMINATED BY ','
	OPTIONALLY ENCLOSED BY '\"'
	LINES TERMINATED BY '\\r\\n'
	(mpn)";

		mysql_query($sql);
	}

	header('Location: ipo_mpn.php');
	return;
}

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'ex_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = <<<EOD
SELECT id, mpn
FROM imc_use_ipo
EOD;
$default_order = array('mpn' => 'ASC');
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
		"200" => "200"
);

$paging_arrows = array(
		"first" => "|&lt;&lt;",
		"previous" => "&lt;&lt;",
		"next" => "&gt;&gt;",
		"last" => "&gt;&gt;|"
);

$dgrid->SetPagingSettings($paging, array(), $pages_array, 100, $paging_arrows);

$vm_table_properties = array('width'=>'70%');
$dgrid->SetViewModeTableProperties($vm_table_properties);  
$vm_columns = array
(
	'mpn'=>array('header'=>'MPN', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'imc_use_ipo';
$primary_key = 'id';
$condition   = 'imc_use_ipo.id = ' . $_REQUEST['ex_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'mpn'  =>array('header'=>'MPN', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'50'),
);
$dgrid->SetColumnsInEditMode($em_columns);

$filtering_fields = array(
	"MPN" => array(
			"type" => "textbox",
			"table" => "imc_use_ipo",
			"field" => "mpn",
			"default_operator" => "like%",
			"show_operator" => "false",
			"case_sensitive" => "false",
			"comparison_type" => "string",
			"width" => "100px",
			"on_js_event" => ""),
);

$dgrid->AllowFiltering(true, false);
$dgrid->SetFieldsFiltering($filtering_fields);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: IPO MPNs</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2, p, li { font-family: tahoma, verdana; }
	#instructions
	{
		width: 700px;
		display: block;
		margin-left: auto;
		margin-right: auto;
	}
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>IPO MPNs</h2>
<p>
	<a href="gen_imc_ipo.php">Download List</a>
</p>
<p>
	<form method="POST" action="ipo_mpn.php?upload=1" enctype="multipart/form-data">
		<p>
			Upload new list (will replace all entries)<br/>
			<input name="list" type="file" accept=".txt" />
			<input type="submit" value="Upload">
		</p>
	</form>
</p>
</center>
<center>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
<script src="js/jquery.min.js" type="text/javascript"></script>
</body>
</html>