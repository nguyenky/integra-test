<?php
require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');
ini_set('max_execution_time', 123456);
ini_set('memory_limit', '2048M');
$user = Login('sales');

function connectDatabase() {
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
    mysql_select_db(DB_SCHEMA);
}

function getInvoices($fromDate) {
	connectDatabase();
	$sql = "
		SELECT si.*, sit.supplier_invoice_id, sit.sku, sit.quantity, sit.quantity_shipped, sit.unit_price
		FROM integra_prod.supplier_invoices  si
		LEFT JOIN integra_prod.supplier_invoice_items sit ON (si.id = sit.supplier_invoice_id)
		WHERE si.order_date >= '${fromDate}'
	";

	error_log($sql);

	$results = mysql_query($sql);


	$invoices = array();
	while($result = mysql_fetch_assoc($results)) {

		$invoices[$result['id']]['id'] = $result['id'];
		$invoices[$result['id']]['supplier_id'] = $result['supplier_id'];
		$invoices[$result['id']]['invoice_num'] = $result['invoice_num'];
		$invoices[$result['id']]['order_num'] = $result['order_num'];
		$invoices[$result['id']]['po_num'] = $result['po_num'];
		$invoices[$result['id']]['order_date'] = $result['order_date'];
		$invoices[$result['id']]['tracking_num'] = $result['tracking_num'];
		$invoices[$result['id']]['total'] = $result['total'];
		$invoices[$result['id']]['invoice_items'][] = $result;
	}
	mysql_close();

	return $invoices;
}
function writeToTotalInvoicesSheet($sheet, $invoices, $fromDate) {

	$sheet->setCellValue("A1", "Invoices from ".$fromDate->format('Y-m-d'));
	$sheet->mergeCells("A1:H1");
	$sheet->getStyle("A1:H1")->getAlignment()->setVertical('center')->setHorizontal('center');

	$sheet->getStyle("A1:H1")->getFont()->setBold(true)->setSize(18);

	$headers = array('id', 'supplier_id', 'invoice_num', 'order_num', 'po_num',
					 'order_date', 'tracking_num', 'total');

	$colums = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H');

	$rowIndex = 3;
	// BUILD headers
	foreach ($colums as $colum) {
		$sheet->getColumnDimension($colum)->setAutoSize(true);
	}

	$sheet->getStyle('A'.$rowIndex.':H'.$rowIndex)->getFont()->setBold(true)->setSize(14);

	$sheet->getStyle('A'.$rowIndex.':H'.$rowIndex)
								  ->getFill()
								  ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								  ->getStartColor()
								  ->setRGB('0066CC');

	$sheet->getStyle('A'.$rowIndex.':H'.$rowIndex)->getAlignment()->setVertical('center')
					          	  ->setHorizontal('center');

	$sheet->getStyle('A'.$rowIndex.':H'.$rowIndex)->getBorders()->getBottom()
												  ->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);

	$sheet->fromArray($headers, NULL, 'A'.$rowIndex);

	$rowIndex += 1;
	$items = array();
	foreach($invoices as $invoice) {

		$sheet->getStyle("A".$rowIndex.":H".$rowIndex)
						  			  ->getAlignment()->setVertical('center')
						          	  ->setHorizontal('right');

		$sheet->getStyle("A".$rowIndex.":H".$rowIndex)->getFont()->setBold(true)->setSize(14);

		$fieldValues = array();
		foreach($invoice as $k => $v) {

			if(in_array($k, $headers)) {

				array_push($fieldValues, $v);
			}

		}
		array_push($items, $fieldValues);

	}
	$sheet->fromArray($items, NULL, "A".$rowIndex);
}

function writeToInvoiceItemsSheet($sheet, $invoices) {
	$rowIndex = 1;
	$headers = array('supplier_invoice_id', 'sku', 'invoice_num', 'order_num', 'quantity',
						'quantity_shipped', 'unit_price');

	$sheet->getStyle("A".$rowIndex.":H".$rowIndex)->getFill()
								  ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								  ->getStartColor()
								  ->setRGB('99CCFF');

	$sheet->getStyle("A".$rowIndex.":H".$rowIndex)
					  			  ->getAlignment()->setVertical('center')
					          	  ->setHorizontal('center');

	$sheet->fromArray($headers, NULL, 'A'.$rowIndex);
	$rowIndex++;
	$items = array();
	foreach($invoices as $invoice) {
		foreach($invoice['invoice_items'] as $invoiceItem) {
			$subInvoices = array();
			foreach($invoiceItem as $k1 => $v1) {
				if(in_array($k1, $headers)) {
					array_push($subInvoices, $v1);
				}
			}
			array_push($items, $subInvoices);
		}
	}

	$sheet->fromArray($items, NULL, 'A'.$rowIndex);
}

function writeToXlsFile($invoices, $fromDate) {
	$d = new DateTime();
	error_log("START writing excel: ".$d->format('Y-m-d h:i:s'));

	$objPHPExcel = new PHPExcel();

	$cm = PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
	PHPExcel_Settings::setCacheStorageMethod($cm);

	$objPHPExcel->getProperties()->setTitle("Invoices from ".$fromDate->format('Y-m-d'));

	$objPHPExcel->setActiveSheetIndex(0);
	$sheet = $objPHPExcel->getActiveSheet();
	writeToTotalInvoicesSheet($sheet, $invoices, $fromDate);

	$objPHPExcel->createSheet(1);
	$objPHPExcel->setActiveSheetIndex(1);
	$sheet = $objPHPExcel->getActiveSheet();
	writeToInvoiceItemsSheet($sheet, $invoices);

	$d = new DateTime();
	error_log("End writing excel: ".$d->format('Y-m-d h:i:s'));

	return $objPHPExcel;
}

$error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST') {

	require_once 'system/PHPExcel/PHPExcel.php';

	$fromDate = $_REQUEST['from_date'];
	$fromDate = DateTime::createFromFormat('Y-m-d', $fromDate);

	// $filename = "invoices_".time();

	// ob_end_clean();

 //    header('Content-Type: application/vnd.ms-excel');
 //    header('Content-Disposition: attachment;filename="' . $filename.'.xls"');
 //    header('Cache-Control: max-age=0');
 //    // If you're serving to IE 9, then the following may be needed
 //    header('Cache-Control: max-age=1');

 //    // If you're serving to IE over SSL, then the following may be needed
 //    header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
 //    header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
 //    header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
 //    header ('Pragma: public'); // HTTP/1.0




    if($fromDate) {
    	$invoices = getInvoices($fromDate->format('Y-m-d'));

    	$filename1 = 'Total Invoices'.time($fromDate).'.csv';
    	$filename2 = 'Invoice Item'.time($fromDate).'.csv';

    	$objPHPExcel = writeToXlsFile($invoices, $fromDate);
    	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');

    	$objWriter->setSheetIndex(0);
		$objWriter->save($filename1);

		$objWriter->setSheetIndex(1);
		$objWriter->save($filename2);

		$zipname = time($fromdate).'.zip';
		$zip = new ZipArchive;
		$zip->open($zipname, ZipArchive::CREATE);

		 $zip->addFile($filename1);
		 $zip->addFile($filename2);

		$zip->close();
        unlink($filename1);
        unlink($filename2);
        ob_end_clean();
		header('Content-Type: application/zip');
		header('Content-disposition: attachment; filename='.$zipname);
		header('Content-Length: ' . filesize($zipname));
		header("Pragma: no-cache");
        header("Expires: 0");
        readfile($zipname);
    	exit();
    } else {
    	$error = "Invalid Date.";
    	exit();
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <title>Download Sales Data</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" href="css/jquery-ui.css"/>
    <style>
        body
        {
            font-family: tahoma, verdana;
        }
        .show {
        	display: block;
        }
        .hide {
        	display: none;
        }
        #getReport {
        	background-color: #008CBA;
    	    border: none;
    	    color: white;
    	    padding: 15px 32px;
    	    text-align: center;
    	    text-decoration: none;
    	    display: inline-block;
    	    font-size: 16px;
    	    border-radius: 4px;
        }
        .error {
        	color: red;
        }
    </style>
</head>
<body>
<div>
		<center>
			<form action="/download_invoices.php" method="POST" id="frmReport" >
			    <h2>Download Invoices Data</h2>
			    <br/>
			    <b style="color: red;"><?=$error?></b>

			    <p><b>From Date:</b>
			    	<input placeholder="yyyy-mm-dd" type="text"  style="" value="" name="from_date" id="datepicker" >
			    </p>
			    <p><b>To Date:</b> Today ()</p>
			    <p class="loading hide">Generating ... <img src="img/ajax.gif" /></p>
			    <p class="action">

			    	<input id="getReport" type="submit" value="Download"/>
			    </p>

		    	<!-- <input type="hidden" name="data" id="reportData" value="" /> -->
		    </form>
		</center>

</div>
<script type="application/javascript" src="js/jquery.min.js"></script>
<script type="application/javascript" src="js/jquery-ui.min.js"></script>
<script type="text/javascript">
	$(document).ready(function() {
		$("#datepicker").datepicker({ dateFormat: 'yy-mm-dd' });

		$('#frmReport').on('submit', function() {
			$('#getReport').fadeOut('fast');
			return true;
		});


	});
</script>
</body>
</html>
