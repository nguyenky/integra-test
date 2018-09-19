<?php require_once('AbstractEbayServiceRequest.php') ?>
<?php

/**
 * Implementation for the File Transfer Service Upload Request.
 */
class FileTransferServiceUploadRequest extends AbstractEbayServiceRequest
{
	
	public function __construct($dataFormat, $responseDataFormat, $securityToken,
		$operationName, $endpoint, $uuid)
	{
		parent::__construct($dataFormat, $responseDataFormat, $securityToken,
			$operationName, $endpoint);
			
		$this->uuid = $uuid;
	}
	
	protected function getHeaders()
	{	
		$headers = parent::getHeadersBaseline();
		
		$contentType = 'multipart/related;'
			. ' boundary=MIME_boundary;'
			. ' type="application/xop+xml";'
			. ' start="' . $this->uuid . '";'
			. ' start-info="text/xml"';
		
		array_push($headers, 'Content-Type: ' . $contentType)	;
		array_push($headers, 'X-EBAY-SOA-SERVICE-NAME: FileTransferService');
		
		return $headers;
	}
	
	/**
	 * Increases the timeout to account for the uploading
	 * of large files. Increase if needed.
	 * @param mixed $connection cURL Handle
	 */
	protected function setAdditionalOptions($connection)
	{
		curl_setopt($connection, CURLOPT_TIMEOUT, 30 );
	}
}

?>