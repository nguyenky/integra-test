<?php

require_once('system/acl.php');

$user = Login('sales');
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Integra :: Override Supplier Ordering</title>
        <link rel="stylesheet" href="css/jquery-ui.css"/>
        <style>
            body
            {
                font: 15px arial;
            }
        </style>
	</head>
<body>
    <h2>Override Supplier Ordering</h2>
    <br/>
    <p>To process all orders currently marked "Scheduled" with fulfillment "Direct" for W1, select a date range below then click Process:</p>
    <form>
        From Date: <input class="datepicker" type="text" id="from_w1" placeholder="YYYY-MM-DD" required />
        <br/>
        To Date: <input class="datepicker" type="text" id="to_w1" placeholder="YYYY-MM-DD" required />
        <br/><br/>
        <button id="dropship_w1" type="button">Process</button>
    </form>
    <br/>
    <br/>
    <p>To process all orders currently marked "Scheduled" with fulfillment "Direct" for W2,
        <a target="_blank" href="http://integra.eocenterprise.com/jobs/dropship_ssf.php">Click here</a></p>
    <br/>
    <p>To process all orders currently marked "Scheduled" with fulfillment "EOC" from supplier W1,
        <a target="_blank" href="http://integra.eocenterprise.com/jobs/auto_order_eoc.php">Click here</a></p>
    <br/>
    <p>To process all orders currently marked "Scheduled" with fulfillment "EOC" from supplier W2,
        <a target="_blank" href="http://integra.eocenterprise.com/jobs/auto_order_ssf4.php">Click here</a></p>
    <br/>
    <p><b>IMPORTANT NOTES:</b></p>
    <p>- Do not click any of the links above more than once. Wait for the process to finish. It might take a few minutes depending on how many orders are included.</p>
    <p>- Orders will be duplicated if you click the links above while an order is in process.</p>
    <p>- Avoid overriding the ordering process during the following scheduled automatic processing times: 12-1AM, 5:30AM, 12-1PM, and 5:30PM.</p>
    <p>- If there are any errors, double check with the supplier first before clicking the links again.</p>
    <br/>

    <script type="application/javascript" src="js/jquery.min.js"></script>
    <script type="application/javascript" src="js/jquery-ui.min.js"></script>

    <script>
        $(document).ready(function()
        {
            $( ".datepicker").datepicker({"dateFormat": "yy-mm-dd"});

            $('#dropship_w1').click(function()
            {
                var from = $('#from_w1').val();
                var to = $('#to_w1').val();

                if (from.length == 0)
                {
                    alert('Please select a from date');
                    return;
                }

                if (to.length == 0)
                {
                    alert('Please select a to date');
                    return;
                }

                window.open("http://integra.eocenterprise.com/jobs/dropship_imc.php?from=" + from + "&to=" + to, "_blank");
            });
        });
    </script>
</body>
</html>