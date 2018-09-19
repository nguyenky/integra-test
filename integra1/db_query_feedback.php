<?php
require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

session_start();
ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Get database report feedback</title>
<style>
	h2 { font-family: tahoma, verdana; }
</style>
</head>
<body>

<center>
<h2>Get database report feedback</h2>
<form action="db_query_feedback.php" method="POST" enctype="multipart/form-data">
	<div class="row">
		Upload negative feedback file in csv exention which store records that you want to get report
	</div>
	<div class="row">
		<div class="col-md-4">File</div>
		<div class="col-md-8">
			<input class="form-control" type="file" name="feedback" />
		</div>
	</div>
	<!--<div class="row">
		<div class="col-md-4">Send to Email: </div>
		<div class="col-md-8">
			<input class="form-control" type="text" name="email" />
		</div>
	</div>
	<div class="row">
		<div class="col-md-4">Subject: </div>
		<div class="col-md-8">
			<input class="form-control" type="text" name="subject" />
		</div>
	</div> -->
	<div class="row">
		<div class="col-md-8"></div>
		<div class="col-md-4">
			<input class="form-control btn btn-standard btn-success" type="submit" value="Submit"/>
		</div>
	</div>
</form>

<?php

if($_SERVER['REQUEST_METHOD'] === 'POST') {
	
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
	
	$tempFile = $_FILES['feedback']['tmp_name'];
	$targetFile =  "/tmp/feedbacks/negative_feedback_" . time();
	move_uploaded_file($tempFile, $targetFile);

	system("sed -i '/^\\s*$/d' {$targetFile}");

	$lines = count(explode("\n", file_get_contents($targetFile)));

	if ($lines > 0) {
		mysql_query("TRUNCATE TABLE eoc.nagative_feedback_record");

		$sql = "
	LOAD DATA LOCAL INFILE '$targetFile'
	INTO TABLE eoc.nagative_feedback_record
	FIELDS TERMINATED BY ','
	OPTIONALLY ENCLOSED BY '\"'
	LINES TERMINATED BY '\\r\\n'
	(record_num)";

		mysql_query($sql);

		@unlink($targetFile);
	}

	header('Location: db_query_feedback.php');
	return;
} else {

	$paging = array(
			"results" => true,
			"results_align" => "left",
			"pages" => true,
			"pages_align" => "center",
			"page_size" => true,
			"page_size_align" => "right"
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
		"record_num" => array(
			"header" => "Record #",
			"align" => "right"
		),
		"order_date" => array(
			"header" => "Date Created",
			"align" => "center"),
		"sold_price" => array(
			"header" => "Sales Price",
			"type" => "label",
			"align" => "right",
			"wrap" => "nowrap"),
		"supplier_cost" => array(
			"header" => "Cost",
			"type" => "label",
			"align" => "right"),
		"etd" => array(
			"header" => "ETD",
			"type" => "label",
			"align" => "right",),
		"supplier_name" => array(
			"header" => "Warehouse Supplier",
			"type" => "label",
			"align" => "center",
			"wrap" => "nowrap"),
		"related_sale_id" => array(
			"header" => "Order ID",
			"type" => "label",
			"align" => "right"),
		"fulfilment" => array(
			"header" => "Fulfilment",
			"type" => "label",
			"align" => "right"),
		"email" => array(
			"header" => "Email",
			"type" => "label",
			"align" => "right"),
		"address" => array(
			"header" => "Address",
			"type" => "label",
			"align" => "right"),
		"speed" => array(
			"header" => "Shipping Type",
			"type" => "label",
			"align" => "right"),
		"carrier" => array(
			"header" => "Shipping Agent",
			"type" => "label",
			"align" => "right"),
		"status" => array(
			"header" => "Status",
			"type" => "label",
			"align" => "right"),
		"tracking_num" => array(
			"header" => "Tracking Number",
			"type" => "label",
			"align" => "right"),
		"remarks" => array(
			"header" => "Remarks",
			"type" => "label",
			"align" => "right"),
		);

	$sql = "
		SELECT s.*,
			  CONCAT(s.street, ', ', s.city, ', ', s.state, ', ', s.zip, ', ', s.country) as address,
			  sup.name as supplier_name
		FROM eoc.nagative_feedback_record na 
		INNER JOIN eoc.sales s ON (na.record_num = s.record_num)
		LEFT JOIN suppliers sup ON (s.supplier = sup.id)
	";

	$dg = new DataGrid(false, false, 'sh_');
	
	$dg->SetColumnsInViewMode($columns);
	$dg->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array());
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
	//$dg->AllowPrinting(false);
	//$dg->AllowExporting(true, true);
	
	$dg->AllowExporting(true, true);
	$exporting_types = array(
	    'csv'=>'true', 'xls'=>'true', 'pdf'=>'false', 'xml'=>'false'
	);
	$dg->AllowExportingTypes($exporting_types);

	$dg->SetPagingSettings($paging, array(), $pages_array, 100, $paging_arrows);

	$filtering_fields = array(
		"Record #" => array(
				"type" => "textbox",
				"table" => "na",
				"field" => "record_num",
				"default_operator" => "like%",
				"show_operator" => "false",
				"case_sensitive" => "false",
				"comparison_type" => "string",
				"width" => "100px",
				"on_js_event" => ""),
	);

	$dg->AllowFiltering(true, false);
	$dg->SetFieldsFiltering($filtering_fields);

	$dg->Bind(false);

}
?>

<div class="apphp_datagrid">
<?php
	$dg->Show();
    ob_end_flush();
?>
</div>

</center>
</body>
</html>