<?php
require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

session_start();

/*
* - BUILD SQL QUERY
* - QUERY TO DATABASE TO GET DATA
*/
function getSalesByFilters($filters, $from, $end, $states) {
	$from = $from->format('Y-m-d 00:00:00');
	$end = $end->format('Y-m-d H:i:s');

	$sql = "
		SELECT id, store, agent, record_num, order_date, total_paid, email, city, state, country, zip, speed, fulfilment_desc, status_desc, supplier_cost, weight, supplier, shipping_cost, listing_fee, stamps_cost, stamps_material, stamps_service, stamps_weight, direct_supplier, direct_order_id, direct_subtotal, direct_core, direct_shipping, direct_total, invoice_num, invoice_total, store_item_id, sku, quantity, unit_price, line_total
    	FROM v_sales
    	WHERE order_date >= '${from}' AND order_date <= '${end}'
    		AND fulfilment_desc = '%s' AND status_desc = '%s' AND speed = '%s' AND country = '%s' 
    		AND weight > %f AND state NOT IN ('%s') AND (stamps_cost is NULL or stamps_cost = '') 
	";


	/*error_log(sprintf($sql, $filters['fulfilment'], $filters['status'], $filters['speed'], $filters['country'], 
				$filters['weight'], implode("', '", $states)));*/

	return mysql_query(sprintf($sql, $filters['fulfilment'], $filters['status'], $filters['speed'], $filters['country'], 
				$filters['weight'], implode("', '", $states)));
}

function connectDatabase() {
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
    mysql_select_db(DB_SCHEMA);
}

function getLast5BusinessDays($fromDate) {
	$last5BusinessDay = NULL;
	$dayInWeek = $fromDate->format('w');
	$weekInYear = $fromDate->format('W');
	if($weekInYear >= 1) {
		if($dayInWeek < 5) {
			$last5BusinessDay = $fromDate->modify('-6 day');
		} else {
			$last5BusinessDay = $fromDate->modify('-'.($dayInWeek - 1).' day');
		}
		return $last5BusinessDay;
	}
}

$user = Login('sales');

if(isset($_REQUEST['report']) && $_REQUEST['report']) {

	
	try {
		// HARDCODE filters 
		$speed = $_REQUEST['speed'];
		switch ($speed) {
			case 'standard_ground':
				$speed = "Standard / Ground";
				break;
			
			case 'expedited_express':
				$speed = "Expedited / Express";
				break;
		}

		$weight = (float)$_REQUEST['weight'];

		$filters = array('fulfilment' => 'EOC', 'status' => 'Scheduled', 'speed' => $speed, 
						'weight' => $weight, 'country' => "US");
		$exceptStates = ["HI", "PR", "AK", "GU", "MP", "VI", "Hawaii", "Puerto Rico", "Alaska", "Guam", "Marshall Island", "Virgin Island"];

		// Prepare Date Range for report
		$today = new DateTime();
		$last5Days = clone $today;
		$last5Days = $last5Days->modify('-5 day');

	    if($_SERVER['REQUEST_METHOD'] === 'POST') {
    		$filename = "sales_".$today->format('Y-m-d').".csv";
    		
    	    header("Content-type: text/csv");
    	    header("Content-Disposition: attachment; filename=${filename}");
    	    header("Pragma: no-cache");
    	    header("Expires: 0");
    	    print $_POST['data'];
    	    exit();
	    } else {

	    	connectDatabase();

	    	$rows = getSalesByFilters($filters, $last5Days, $today, $exceptStates);

	    	if (empty($rows) || mysql_num_rows($rows) == 0) {
	    	    $error = 'No sales data for date range provided.';

	    	} else {
	    	    $output = fopen('php://output', 'w');

	    	    fputcsv($output, array("id", "store", "agent", "record_num", "order_date", "total_paid", "email", "city", "state", "country", 
	    	    				"zip", "speed", "fulfilment_desc", "status_desc", "supplier_cost", "weight", "supplier", "shipping_cost", 
	    	    				"listing_fee", "stamps_cost", "stamps_material", "stamps_service", "stamps_weight", "direct_supplier", 
	    	    				"direct_order_id", "direct_subtotal", "direct_core", "direct_shipping", "direct_total", "invoice_num", 
	    	    				"invoice_total", "store_item_id", "sku", "quantity", "unit_price", "line_total"));

	    	    //echo "id, store, agent, record_num, order_date, total_paid, email, city, state, country, zip, speed, fulfilment_desc, status_desc, supplier_cost, weight, supplier, shipping_cost, listing_fee, stamps_cost, stamps_material, stamps_service, stamps_weight, direct_supplier, direct_order_id, direct_subtotal, direct_core, direct_shipping, direct_total, invoice_num, invoice_total, store_item_id, sku, quantity, unit_price, line_total\r\n";
	    	    while ($row = mysql_fetch_row($rows))
	    	    {
	    	        $cols = [];
	    	        foreach ($row as $r)
	    	            $cols[] = '"' . trim(str_replace("\n", '', str_replace("\r", '', str_replace("\t", '', $r)))) . '"';
	    	        //echo implode(',', $cols) . "\r\n";
	    	        fputcsv($output, $cols);
	    	    }
	    	    mysql_close();

	    	    print $output;
	    	    exit();
	    	}
	    }

	} catch(Exception $ex) {
		$error = $ex->getMessage();
		//echo $ex->getTraceAsString();
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
	<!-- <form method="get" action="/gen_w1orders.php" id="frmReport"> -->
		<center>
		    <h2>Download Sales Data</h2>
		    <br/>
		    <b style="color: red;"><?=$error?></b>

		    <p><b>From Date:</b> 5 business days prior.</p>
		    <p><b>To Date:</b> Today ()</p>
		    <p><b>Fulfilment:</b> EOC</p>
		    <p><b>Status:</b> Scheduled</p>
		    <p><b>Speed:</b> 
		    	<select name="speed" id="speed">
		    		<option value="standard_ground">Standard / Ground</option>
		    		<option value="expedited_express">Expedited / Express</option>
		    	</select>
		    </p>
		    <p><b>Weight:</b> is greater than: <input type="number" id="weight" name="weight" step="0.01" min="0.01" value="0.59" /></p>
		    <p class="error hide"></p>
		    <p><b>Country:</b> US</p>
		    <p><b>State:</b> All states except: HI, PR, AK, GU, MP, VI, Hawaii, Puerto Rico, Alaska, Guam, Marshall Island, Virgin Island</p> 
		    <p><b>Stamps_cost:</b> is blank/empty</p>
		    <p class="loading hide">Generating ... <img src="img/ajax.gif" /></p>
		    <p class="action">

		    	<!-- <input type="hidden" name="report" value="1" /> -->
		    	<input id="getReport" type="button" value="Download"/>
		    </p>
		    <form action="/gen_w1orders.php?report=1" method="POST" id="frmReport" >
		    	<input type="hidden" name="data" id="reportData" value="" />
		    </form>
		</center>

</div>
<script type="application/javascript" src="js/jquery.min.js"></script>
<script type="application/javascript" src="js/jquery-ui.min.js"></script>
<script type="text/javascript">
	$(document).ready(function() {
		function isNumber(n) {
		  return !isNaN(parseFloat(n)) && isFinite(n);
		}
		$('#getReport').on('click', function() {
			$(this).attr("disabled", "disabled");
			$(".loading").fadeIn();
			$(".action").fadeOut();
			var speed = $('#speed').val();
			var weight = $('#weight').val();
			if(!isNumber(weight)) {
				$('.error').text("Invalid number");
				$('.error').removeClass('hide').addClass('show');
				return false;
			} else {
				$('.error').removeClass('show').addClass('hide');
			}

			$.get("/gen_w1orders.php", {report: 1, speed: speed, weight: weight}, function(filedata) {
				$("#reportData").val(filedata);
				$("#frmReport").submit();
		        $(".loading").fadeOut();
		        $(".action").fadeIn();
		        $(this).removeAttr("disabled");
			});


			//document.location.href = '/gen_w1orders.php?report=1';
			
		});
	});
</script>
</body>
</html>





