<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Aws\Sqs\SqsClient;
 
require_once(__DIR__ . '/../libraries/MWSSubscriptionsService/Client.php');
require_once(__DIR__ . '/../libraries/MWSSubscriptionsService/Model/DeregisterDestinationInput.php');
require_once(__DIR__ .'/../libraries/MWSSubscriptionsService/Model/Destination.php');
require_once(__DIR__ .'/../libraries/MWSSubscriptionsService/Model/AttributeKeyValueList.php');
require_once(__DIR__ .'/../libraries/MWSSubscriptionsService/Model/AttributeKeyValue.php');
require_once(__DIR__ . '/../libraries/MWSSubscriptionsService/Interface.php');
require_once(__DIR__ . '/../libraries/MWSSubscriptionsService/Exception.php');


class AmazonDeregisterDestination extends Command
{
	protected $name = 'amazon:deregisterDestination';
    protected $description = 'DeRegister Destination data';

    public function __construct()
    {
    	parent::__construct();
    }

    public function fire() {
    	Log::info("================ DeRegister Destination ". date('Y-m-d H:i:s') ." ==============");

    	$serviceUrl = "https://mws.amazonservices.com/Subscriptions/2013-07-01";

    	$config = array (
    	  'ServiceURL' => $serviceUrl,
    	  'ProxyHost' => null,
    	  'ProxyPort' => -1,
    	  'ProxyUsername' => null,
    	  'ProxyPassword' => null,
    	  'MaxErrorRetry' => 3,
    	);

    	
    	$service = new MWSSubscriptionsService_Client(
			        Config::get('integra.amazon_mws.access_key_id'),
			        Config::get('integra.amazon_mws.secret_access_key'),
    				#Config::get('queue.connections.sqs.credentials.key'),
    				#Config::get('queue.connections.sqs.credentials.secret'),
			        Config::get('integra.amazon_mws.application_name'),
			        Config::get('integra.amazon_mws.application_version'),
			        $config);

    	$request = new MWSSubscriptionsService_Model_DeregisterDestinationInput();
    	$request->setSellerId(Config::get('integra.amazon_mws.merchant_id'));
    	$request->setMarketplaceId(Config::get('integra.amazon_mws.marketplace_id'));

    	$destination = new MWSSubscriptionsService_Model_Destination();
    	$destination->setDeliveryChannel('SQS');

    	$attributesList = new MWSSubscriptionsService_Model_AttributeKeyValueList();

    	$members = [];
    	
    	$attribute = new MWSSubscriptionsService_Model_AttributeKeyValue();
    	$attribute->setKey('sqsQueueUrl');
    	$attribute->setValue(Config::get('queue.connections.sqs.queue'));

    	$members[] = $attribute;

    	$attributesList->setmember($members);

    	$destination->setAttributeList($attributesList);

    	$request->setDestination($destination);

    	 // object or array of parameters
    	$this->invokeDeregisterDestination($service, $request);

    }
    	/**
    	  * Get Create Subscription Action Sample
    	  * Gets competitive pricing and related information for a product identified by
    	  * the MarketplaceId and ASIN.
    	  *
    	  * @param MWSSubscriptionsService_Interface $service instance of MWSSubscriptionsService_Interface
    	  * @param mixed $request MWSSubscriptionsService_Model_CreateSubscription or array of parameters
    	  */
	function invokeDeregisterDestination(MWSSubscriptionsService_Interface $service, $request)
    {
        try {
          $response = $service->DeregisterDestination($request);
          echo ("Service Response\n");
          echo ("=============================================================================\n");
          $dom = new DOMDocument();
          $dom->loadXML($response->toXML());
          $dom->preserveWhiteSpace = false;
          $dom->formatOutput = true;
          echo $dom->saveXML();
          echo("ResponseHeaderMetadata: " . $response->getResponseHeaderMetadata() . "\n");
       } catch (MWSSubscriptionsService_Exception $ex) {
          echo("Caught Exception: " . $ex->getMessage() . "\n");
          echo("Response Status Code: " . $ex->getStatusCode() . "\n");
          echo("Error Code: " . $ex->getErrorCode() . "\n");
          echo("Error Type: " . $ex->getErrorType() . "\n");
          echo("Request ID: " . $ex->getRequestId() . "\n");
          echo("XML: " . $ex->getXML() . "\n");
          echo("ResponseHeaderMetadata: " . $ex->getResponseHeaderMetadata() . "\n");
       }
     }
}
?>