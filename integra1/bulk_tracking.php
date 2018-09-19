<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

set_include_path(get_include_path() . PATH_SEPARATOR . realpath('system/amazon'));

$user = Login();

function __autoload($className)
{
	$filePath = 'system/amazon/' . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
	if (file_exists($filePath))
	{
		require_once $filePath;
		return;
	}
}

if ($_FILES['userfile']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['userfile']['tmp_name']))
{
	$file = file_get_contents($_FILES['userfile']['tmp_name']);
	$lines = explode("\n", $file);
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Bulk Tracking Upload Tool</title>
	<style>
		body
		{
			margin: 50px;
			font: 16px arial;
		}
		b
		{
			color: red;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>

<h1>Bulk Tracking Upload Tool</h1>

<?php
	if (!empty($lines))
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
		
		$config = array
		(
			'ServiceURL' => 'https://mws.amazonservices.com/',
			'ProxyHost' => null,
			'ProxyPort' => -1,
			'MaxErrorRetry' => 3,
		);

		$service = new MarketplaceWebService_Client(
			AWS_ACCESS_KEY_ID,
			AWS_SECRET_ACCESS_KEY, 
			$config,
			APPLICATION_NAME,
			APPLICATION_VERSION);

		$merchantId = MERCHANT_ID;
		$date = gmdate("c");
		$mid = 1;

$feed = <<<EOD
<?xml version="1.0"?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
<Header><DocumentVersion>1.01</DocumentVersion><MerchantIdentifier>${merchantId}</MerchantIdentifier></Header>
<MessageType>OrderFulfillment</MessageType>
EOD;

		foreach ($lines as $line)
		{
			$line = trim($line);
			if (empty($line))
				continue;
				
			if (stristr($line, 'sales') !== FALSE || stristr($line, 'record') !== FALSE || stristr($line, 'tracking') !== FALSE)
				continue;
			
			if (strstr($line, ';') !== FALSE)
				$fields = explode(';', $line);
			else
				$fields = explode(',', $line);

			if (count($fields) < 3)
			{
				echo "<b>[DATA ERROR] ${line}</b><br/>\r\n";
				continue;
			}
			
			$recordNum = strtoupper(trim($fields[0]));
			$tracking = $fields[1];
			$carrier = trim($fields[2]);
			$tracking = str_replace(' ', '', $tracking);
			$tracking = trim(str_replace('-', '', $tracking));
			
			if ($carrier == "ups")
				$carrier = "UPS";
			
			if (empty($recordNum) || empty($tracking) || empty($carrier))
			{
				echo "<b>[DATA ERROR] ${line}</b><br/>\r\n";
				continue;
			}

			$q = "SELECT id, store, internal_id, tracking_num FROM sales WHERE record_num = '%s'";
			$row = mysql_fetch_row(mysql_query(sprintf($q, $recordNum)));
			if (empty($row) || empty($row[0]) || empty($row[1]) || empty($row[2]))
			{
				echo "<b>[NOT FOUND] ${line}</b><br/>\r\n";
				continue;
			}
			
			$salesId = $row[0];
			$orderId = $row[2];
			$existingTracking = $row[3];
			
			if (!empty($existingTracking))
			{
				echo "<b>[ALREADY HAS TRACKING NUMBER] ${line}</b><br/>\r\n";
				continue;
			}

			$q = "UPDATE sales SET tracking_num = '%s', carrier = '%s' WHERE id = %d";
			mysql_query(sprintf($q, mysql_real_escape_string($tracking), mysql_real_escape_string($carrier), $salesId));

			if ($row[1] == 'Amazon')
			{
$feed .= <<<EOD
<Message><MessageID>${mid}</MessageID><OrderFulfillment>
<AmazonOrderID>${orderId}</AmazonOrderID><FulfillmentDate>${date}</FulfillmentDate>
<FulfillmentData><CarrierName>${carrier}</CarrierName><ShipperTrackingNumber>${tracking}</ShipperTrackingNumber>
</FulfillmentData></OrderFulfillment></Message>
EOD;
				$mid++;
				$amazon[] = $line;
				continue;
			}
			else
			{				
				$s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
				
				if (stristr($s, 'success') !== FALSE)
				{
					echo "[OK] ${line}<br/>\r\n";
					continue;
				}
				else
				{
					echo "<b>[EBAY ERROR] ${line}</b><br/>\r\n";
					continue;
				}
			}
		}
		
		$feed .= "</AmazonEnvelope>";
		
		if (!empty($amazon))
		{
			$feedHandle = @fopen('php://temp', 'rw+');
			fwrite($feedHandle, $feed);
			rewind($feedHandle);
			$request = new MarketplaceWebService_Model_SubmitFeedRequest();
			$request->setMerchant(MERCHANT_ID);
			$request->setMarketplaceIdList(array("Id" => array(MARKETPLACE_ID)));
			$request->setFeedType('_POST_ORDER_FULFILLMENT_DATA_');
			$request->setContentMd5(base64_encode(md5(stream_get_contents($feedHandle), true)));
			rewind($feedHandle);
			$request->setPurgeAndReplace(false);
			$request->setFeedContent($feedHandle);
			rewind($feedHandle);
			$response = $service->submitFeed($request);
			@fclose($feedHandle);
			$res = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedProcessingStatus();
			
			foreach ($amazon as $a)
			{
				if ($res == '_SUBMITTED_')
					echo "[OK] ${a}<br/>\r\n";
				else
					echo "<b>[AMAZON ERROR] ${a}</b><br/>\r\n";
			}
		}
		
		echo '<br/><a href="bulk_tracking.php">Upload another file</a>';
	}
	else
	{
?>

<form enctype="multipart/form-data" action="bulk_tracking.php" method="POST">
	<h4>Upload CSV file:</h4>
	<input type="hidden" name="MAX_FILE_SIZE" value="4000000" />
	<input type="file" name="userfile" /><br/>
	<input type="submit" value="Upload File" />
</form>

<br/>
<h4>File Format:</h4>
Record#,Tracking#,Carrier
<br/><br/>
<i>Note: Only eBay record numbers are supported at this time.</i>
<?	} ?>

</body>
</html>