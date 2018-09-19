<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../system/amazon');

set_time_limit(0);
ini_set('memory_limit', '512M');
ignore_user_abort(TRUE);

file_put_contents(LOGS_DIR . "a_inventory.log", "================ START AMAZON INVENTORY AT: ". date('Y-m-d H:i:s') ." ===================\r\n", FILE_APPEND);


try {

	$serviceUrl = "https://mws.amazonservices.com";

$config = array
(
	'ServiceURL' => $serviceUrl,
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
	
$request = new MarketplaceWebService_Model_RequestReportRequest();
$request->setMerchant(MERCHANT_ID);
$request->setReportType('_GET_FLAT_FILE_OPEN_LISTINGS_DATA_');
$response = $service->requestReport($request);

file_put_contents(LOGS_DIR . "a_inventory.log", "First Response: ".serialize($response) ."\r\n", FILE_APPEND);
$requestReportResult = $response->getRequestReportResult();
$reportRequestInfo = $requestReportResult->getReportRequestInfo();
$reportRequestId = $reportRequestInfo->getReportRequestId();

while (true)
{
	try
	{
		$request = new MarketplaceWebService_Model_GetReportListRequest();
		$request->setMerchant(MERCHANT_ID);
		$idList = new MarketplaceWebService_Model_IdList();
		$request->setReportRequestIdList($idList->withId($reportRequestId));
		$request->setAvailableToDate(new DateTime('now', new DateTimeZone('UTC')));
		$request->setAvailableFromDate(new DateTime('-1 months', new DateTimeZone('UTC')));
		$request->setAcknowledged(false);

		$response = $service->getReportList($request);

		file_put_contents(LOGS_DIR . "a_inventory.log", "RESPONSE: ".serialize($response)."\r\n", FILE_APPEND);

		$getReportListResult = $response->getGetReportListResult();
		$reportInfoList = $getReportListResult->getReportInfoList();

		if (count($reportInfoList) > 0)
		{
			$reportInfo = $reportInfoList[0];
			$reportId = $reportInfo->getReportId();
			if (!empty($reportId))
				break;
		}
	}
	catch (Exception $e)
	{
		file_put_contents(LOGS_DIR . "a_inventory.log", "ERROR: ".$e->getTraceAsString()."\r\n", FILE_APPEND);
		echo $e->getMessage() . "\r\n";
	}

	sleep(60);
}

file_put_contents(LOGS_DIR. "a_inventory.log", "Finish calling amazon \r\n", FILE_APPEND);

$request = new MarketplaceWebService_Model_GetReportRequest();
$request->setMerchant(MERCHANT_ID);
$request->setReport(@fopen('php://temp', 'rw+'));
$request->setReportId($reportId);
$response = $service->getReport($request);
$getReportResult = $response->getGetReportResult();
$stream = $request->getReport();

$conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysqli_select_db($conn, DB_SCHEMA);
mysqli_set_charset($conn, 'utf8');

mysqli_query($conn, "UPDATE amazon_listings SET active = 0");

while (!feof($stream))
{ 
    $line = stream_get_line($stream, 1000000, "\n");
	$fields = explode("\t", trim($line));
	
	if (count($fields) != 4)
		continue;
		
	if ($fields[1] == "asin")
		continue;
		
	$sku = $fields[0];
	$asin = $fields[1];
	$price = $fields[2];
	$quantity = $fields[3];
	
	$q=<<<EOQ
	INSERT INTO amazon_listings (asin, sku, price, quantity, active)
	VALUES ('%s', '%s', '%s', '%s', 1)
	ON DUPLICATE KEY UPDATE sku=VALUES(sku), price=VALUES(price), quantity=VALUES(quantity), active=1, timestamp=NOW()
EOQ;

	file_put_contents(LOGS_DIR. "a_inventory.log", "MYSQL: ".sprintf($q, $asin, $sku, $price, $quantity)." \r\n", FILE_APPEND);

	mysqli_query($conn, sprintf($q, $asin, $sku, $price, $quantity));
}

$merchantId = MERCHANT_ID;

$amazonFeed = <<<EOD
<?xml version="1.0"?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
<Header><DocumentVersion>1.01</DocumentVersion><MerchantIdentifier>${merchantId}</MerchantIdentifier></Header>
<MessageType>Inventory</MessageType>
EOD;

passthru('/bin/php ' . __DIR__ . '/../../integra2/artisan amazon:update_inventory');

$tmp = '/tmp';
$tmp_revise = "${tmp}/kit_amazon";

if (is_file($tmp_revise)) $amazonFeed .= file_get_contents($tmp_revise);

$amazonFeed .= "</AmazonEnvelope>";

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

$feedHandle = @fopen('php://temp', 'rw+');
fwrite($feedHandle, $amazonFeed);
rewind($feedHandle);
$request = new MarketplaceWebService_Model_SubmitFeedRequest();
$request->setMerchant(MERCHANT_ID);
$request->setMarketplaceIdList(array("Id" => array(MARKETPLACE_ID)));
$request->setFeedType('_POST_INVENTORY_AVAILABILITY_DATA_');
$request->setContentMd5(base64_encode(md5(stream_get_contents($feedHandle), true)));
rewind($feedHandle);
$request->setPurgeAndReplace(false);
$request->setFeedContent($feedHandle);
rewind($feedHandle);
$response = $service->submitFeed($request);
@fclose($feedHandle);
$res = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedProcessingStatus();
$feedId = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedSubmissionId();

file_put_contents(__DIR__ . "/../logs/amazon_inv.txt", date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d H:i:s') . "] ${feedId}\r\n", FILE_APPEND);


} catch (Exception $ex) {
	$conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysqli_select_db($conn, DB_SCHEMA);
	
	$q = "
		INSERT INTO cron_job_logs (job_name, status, message, time_occur)
		VALUES ('%s', '%d', '%s', NOW())";

	mysqli_query($conn, sprintf($q, 'a_inventory', 0, "JOB FAILED: ".$ex->getMessage()));

	file_put_contents(LOGS_DIR . "a_inventory.log", "ERROR: ".$ex->getTraceAsString()."\r\n", FILE_APPEND);
}

file_put_contents(LOGS_DIR . "a_inventory.log", "============= JOB DONE AT ". date('Y-m-d H:i:s') ." ============== \r\n", FILE_APPEND);

mysqli_close($conn);

function __autoload($className)
{
	$filePath = __DIR__ . '/../system/amazon/' . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
	if (file_exists($filePath))
	{
		require_once $filePath;
		return;
	}
}

?>