<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');
require_once(__DIR__ . '/../system/ssf_utils.php');

const SUBMITED_STATUS = "_SUBMITTED_";
const DONE_STATUS = "_DONE_";

set_time_limit(0);

set_include_path(get_include_path() . PATH_SEPARATOR . realpath(__DIR__ . '/../system/amazon'));

file_put_contents(LOGS_DIR . "a_tracking.log", "=============== START AMAZON TRACKING AT ". date('Y-m-d H:i:s') ." ===============\r\n", FILE_APPEND);

try {

    $conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysqli_select_db($conn, DB_SCHEMA);
mysqli_set_charset($conn, 'utf8');

    function urgentItems($internalIds = []) {
        $queue = [];
        if(!empty($internalIds)) {
            $strInternalIds = implode("','", $internalIds);

            #$strInternalIds = "'" + $strInternalIds + "'";
            $sql= "
                SELECT id, sales_id, internal_id, tracking_num, carrier
                FROM eoc.amazon_tracking_queue
                WHERE internal_id IN ('%s')";



            $q = sprintf($sql, $strInternalIds);

            file_put_contents(LOGS_DIR . "a_tracking.log", "SQL: ". $q ." \r\n", FILE_APPEND);

            $res = mysqli_query($conn, $q);

            ob_start();
            var_dump($res);
            file_put_contents(LOGS_DIR . "a_tracking.log", "items: ". ob_get_clean() ." \r\n", FILE_APPEND);


            while ($row = mysqli_fetch_row($res))
            {
                $str = $row[0] . '|' . $row[1] . '|' . $row[2] . '|' . $row[3] . '|' . $row[4];
                file_put_contents(LOGS_DIR . "a_tracking.log", "SQL: ". $str ." \r\n", FILE_APPEND);
                $queue[] = $row[0] . '|' . $row[1] . '|' . $row[2] . '|' . $row[3] . '|' . $row[4];
            }

            
        }

        ob_start();
        var_dump($queue);
        file_put_contents(LOGS_DIR . "a_tracking.log", "items: ". ob_get_clean() ." \r\n", FILE_APPEND);

        return $queue;
    }

    

$q=<<<EOQ
    SELECT id, sales_id, internal_id, tracking_num, carrier
    FROM eoc.amazon_tracking_queue
    WHERE status = 0
    LIMIT 200
EOQ;

$queue = [];

$res = mysqli_query($conn, $q);

while ($row = mysqli_fetch_row($res))
{
    $queue[] = $row[0] . '|' . $row[1] . '|' . $row[2] . '|' . $row[3] . '|' . $row[4];
}


file_put_contents(LOGS_DIR . "a_tracking.log", "Number of items: ". count($queue) ." \r\n", FILE_APPEND);

if (empty($queue))
    exit;

$merchantId = MERCHANT_ID;
$feed = <<<EOD
<?xml version="1.0" encoding="utf-8" ?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
    <Header>
        <DocumentVersion>1.01</DocumentVersion>
        <MerchantIdentifier>${merchantId}</MerchantIdentifier>
    </Header>
    <MessageType>OrderFulfillment</MessageType>
EOD;

$ctr = 1;
$date = gmdate(DATE_ATOM);
$ids = [];

foreach ($queue as $q)
{
    $fields = explode('|', $q);
    $id = $fields[0];
    $salesId = $fields[1];
    $internalId = $fields[2];
    $trackingNum = $fields[3];
    $carrier = $fields[4];

    $ids[] = $id;

    file_put_contents(LOGS_DIR . "a_tracking.log", "Process for id: ". $id ." \r\n", FILE_APPEND);

    mysqli_query($conn, "UPDATE eoc.amazon_tracking_queue SET status = 1 WHERE status = 0 AND id = {$id}");

    $feed .= <<<EOD
<Message>
    <MessageID>${ctr}</MessageID>
    <OrderFulfillment>
        <AmazonOrderID>${internalId}</AmazonOrderID>
        <FulfillmentDate>${date}</FulfillmentDate>
        <FulfillmentData>
            <CarrierName>${carrier}</CarrierName>
            <ShipperTrackingNumber>${trackingNum}</ShipperTrackingNumber>
        </FulfillmentData>
    </OrderFulfillment>
</Message>
EOD;

    $ctr++;
}

$feed .= "</AmazonEnvelope>";
$idStr = implode(',', $ids);

file_put_contents(LOGS_DIR . "a_tracking.log", "List Items: ". $idStr ." \r\n", FILE_APPEND);

try
{
    file_put_contents(LOGS_DIR . "a_tracking.log", "=============== Start Calling API ================ \r\n", FILE_APPEND);
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
    file_put_contents(LOGS_DIR . "a_tracking.log", "response: ". serialize($response) ." \r\n", FILE_APPEND);
    @fclose($feedHandle);
    $result = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedProcessingStatus();
    $feedId = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedSubmissionId();

    file_put_contents(LOGS_DIR . "a_tracking.log", "response FeedID: ". $feedId ." \r\n", FILE_APPEND);
    file_put_contents(LOGS_DIR . "a_tracking.log", "response result: ". $result ." \r\n", FILE_APPEND);


    $sql = <<<EOQ
UPDATE eoc.amazon_tracking_queue
SET status = 2,
feed_id = '%s',
result = '%s',
complete_date = NOW()
WHERE status = 1
AND id IN (%s)
EOQ;

    $q = sprintf($sql, $feedId, $result, $idStr);

    file_put_contents(LOGS_DIR . "a_tracking.log", "SQL: ". $q ." \r\n", FILE_APPEND);

    mysqli_query($conn, $q);
}
catch (Exception $e)
{
    mysqli_query($conn, sprintf(<<<EOQ
UPDATE eoc.amazon_tracking_queue
SET status = 99,
feed_id = NULL,
result = '%s',
complete_date = NULL
WHERE status = 1
AND id IN (%s)
EOQ
    , $e->getMessage(), $idStr));
}

mysqli_close($conn);

} catch(Exception $ex) {
    file_put_contents(LOGS_DIR . "a_tracking.log", "ERRORS: ". $ex->getMessage() ." \r\n", FILE_APPEND);
}

file_put_contents(LOGS_DIR . "a_tracking.log", "=========== JOB DONE AT ". date('Y-m-d H:i:s') ." =============== \r\n", FILE_APPEND);

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

