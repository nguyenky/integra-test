<?php

require_once(__DIR__ . '/../system/ebay/lms/ServiceEndpointsAndTokens.php');
require_once(__DIR__ . '/../system/ebay/lms/LargeMerchantServiceSession.php');
require_once(__DIR__ . '/../system/ebay/lms/DOMUtils.php');
require_once(__DIR__ . '/../system/ebay/lms/PrintUtils.php');

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/counter_utils.php');


ignore_user_abort(TRUE);

$environment = ENV_PRODUCTION;
$siteId = SITE_ID;

///////////////////////////////////////////////////////////////////////////////////////////////////////////


$row = mysql_fetch_row(mysql_query("SELECT TIMESTAMPDIFF(MINUTE, MAX(timestamp), NOW()) FROM ebay_listings"));
$diff = $row[0];

if (true) // (!empty($diff) && $diff > 60)
{
	$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
	$uuid = uniqid("", true);
	$request = <<<EOD
<startDownloadJobRequest xmlns="http://www.ebay.com/marketplace/services">
<downloadJobType>ActiveInventoryReport</downloadJobType>
<UUID>${uuid}</UUID>
</startDownloadJobRequest>
EOD;
	$responseXML = $session->sendBulkDataExchangeRequest('startDownloadJob', $request);
	$xml = simplexml_load_string($responseXML);
	CountersUtils::insertCounter('ActiveInventoryReport','Ebay Inventory',APP_ID);

	if(!empty($xml) && 'Success' == (string)$xml->ack)
	{
		$jobId = (string)$xml->jobId;
		file_put_contents(__DIR__ . "/../logs/ebay_inv.txt", date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d H:i:s') . "] ActiveInventoryReport ${jobId}\r\n", FILE_APPEND);
	}
	else
	{
		error_log("Error while updating eBay inventory (download): ${responseXML}");
		SendAdminEmail('eBay Inventory Download Error', $responseXML);
		exit;
	}

	while (true)
	{
		try
		{
			$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
			$request = <<<EOD
<getJobStatusRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
<jobId>${jobId}</jobId>
</getJobStatusRequest>
EOD;
			$responseXML = $session->sendBulkDataExchangeRequest('getJobStatus', $request);
			$xml = simplexml_load_string($responseXML);

			/*  start insert counter */
			CountersUtils::insertCounter('getJobStatus','Ebay Inventory',APP_ID);
			/*  end insert counter */

			if(!empty($xml) && 'Success' == (string)$xml->ack)
				$fileId = (string)$xml->jobProfile->fileReferenceId;
			else
			{
				error_log("Error while updating eBay inventory (download): ${responseXML}");
				SendAdminEmail('eBay Inventory Download Error', $responseXML);
				exit;
			}
			
			if (!empty($fileId))
				break;
		}
		catch (Exception $e)
		{
			error_log("Error while updating eBay inventory (download): " . $e->getMessage() . "\r\n");
			SendAdminEmail('eBay Inventory Download Error', $responseXML);
		}

		sleep(60);
	}

	$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
	$request = <<<EOD
<downloadFileRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
<taskReferenceId>${jobId}</taskReferenceId>
<fileReferenceId>${fileId}</fileReferenceId>
</downloadFileRequest>
EOD;
	/*  start insert counter */
	CountersUtils::insertCounter('downloadFileRequest','Ebay Inventory',APP_ID);
	/*  end insert counter */
	$response = $session->sendFileTransferServiceDownloadRequest($request);
	$responseXML = parseForResponseXML($response);
	$responseDOM = DOMUtils::createDOM($responseXML);
	$uuid = parseForXopIncludeUUID($responseDOM);
	$fileBytes = parseForFileBytes($uuid, $response);
	$temp = 'ebay_' . time();
	$tempFile = "/tmp/${temp}.zip";
	writeZipFile($fileBytes, $tempFile);
	exec("unzip -d /tmp/${temp} /tmp/${temp}.zip");
	unlink("/tmp/${temp}.zip");
	$files = glob("/tmp/${temp}/*.xml");
	if (count($files) > 0)
	{
		$reportFile = $files[0];
		$xml = simplexml_load_file($reportFile);
		//unlink($reportFile);
	}

	//rmdir("/tmp/${temp}");

	mysql_query("UPDATE ebay_listings SET active = 0");

	foreach($xml->ActiveInventoryReport->SKUDetails as $item)
	{
		$q=<<<EOQ
INSERT INTO ebay_listings (item_id, sku, price, quantity, active)
VALUES ('%s', '%s', '%s', '%s', 1)
ON DUPLICATE KEY UPDATE sku=VALUES(sku), price=VALUES(price), quantity=VALUES(quantity), active=1, timestamp=NOW()
EOQ;
		mysql_query(sprintf($q, $item->ItemID, $item->SKU, $item->Price, $item->Quantity));
	}
}

mysql_query("TRUNCATE integra_prod.ebay_listings");

$q=<<<EOQ
INSERT INTO integra_prod.ebay_listings (item_id, sku, price, quantity, active, always_list)
(SELECT item_id, sku, price, quantity, active, always_list FROM eoc.ebay_listings WHERE suspended = 0)
EOQ;
mysql_query($q);

///////////////////////////////////////////////////////////////////////////////////////////////////////////

passthru('/bin/php ' . __DIR__ . '/../../integra2/artisan ebay:update_inventory');

$tmp = '/tmp';
$tmp_endlist = "${tmp}/kit_endlist";
$tmp_revise = "${tmp}/kit_revise";
$tmp_relist = "${tmp}/kit_relist";

$eBayFeed = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<BulkDataExchangeRequests>
  <Header>
    <SiteID>${siteId}</SiteID>
    <Version>801</Version>
  </Header>
EOD;

if (is_file($tmp_endlist)) $eBayFeed .= file_get_contents($tmp_endlist);

$q = <<<EOD
SELECT e.item_id, m.qty AS new_qty, e.quantity AS old_qty, e.sku
FROM ebay_listings e, inventory_map m
WHERE e.active = 1
AND e.sku = m.sku
AND e.quantity <> m.qty
AND m.qty = 0
AND e.always_list = 0
AND e.sku NOT LIKE 'PU%'
AND e.sku NOT LIKE 'WP%'
AND e.sku NOT LIKE 'TR%'
AND e.suspended = 0
EOD;
$rows = mysql_query($q);
$submit = false;
while ($row = mysql_fetch_row($rows))
{
	$submit = true;
	$itemId = $row[0];
	$qty = $row[1];
	$oldQty = $row[2];
	$sku = $row[3];
	
	$eBayFeed .= <<<EOD
  <EndFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ItemID>${itemId}</ItemID>
	<EndingReason>NotAvailable</EndingReason>
    <Version>801</Version>
  </EndFixedPriceItemRequest>
EOD;

	unset($n);
	$n['item'] = $itemId;
	$n['new'] = $qty;
	$n['old'] = $oldQty;
	$n['sku'] = $sku;
	$n['action'] = 'End List';
	$notifs[] = $n;
}

// KITS
$q = <<<EOD
SELECT e.item_id, m.qty AS avail_qty, e.quantity AS old_qty, e.sku
FROM ebay_listings e, inventory_map m
WHERE e.active = 1
AND e.kit_type = 1
AND e.kit_base = m.sku
AND m.qty < e.kit_qty
AND e.always_list = 0
AND e.sku NOT LIKE 'PU%'
AND e.sku NOT LIKE 'WP%'
AND e.sku NOT LIKE 'TR%'
AND e.suspended = 0
EOD;
$rows = mysql_query($q);
while ($row = mysql_fetch_row($rows))
{
	$submit = true;
	$itemId = $row[0];
	$qty = $row[1];
	$oldQty = $row[2];
	$sku = $row[3];
	
	$eBayFeed .= <<<EOD
  <EndFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <ItemID>${itemId}</ItemID>
	<EndingReason>NotAvailable</EndingReason>
    <Version>801</Version>
  </EndFixedPriceItemRequest>
EOD;

	unset($n);
	$n['item'] = $itemId;
	$n['new'] = $qty;
	$n['old'] = $oldQty;
	$n['sku'] = $sku;
	$n['action'] = 'End List';
	$notifs[] = $n;
}

$eBayFeed .= "</BulkDataExchangeRequests>";

if ($submit)
{
	$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
	$uuid = uniqid("", true);
	$request = <<<EOD
<createUploadJobRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<uploadJobType>EndFixedPriceItem</uploadJobType>
	<UUID>${uuid}</UUID>
</createUploadJobRequest>
EOD;
	$responseXML = $session->sendBulkDataExchangeRequest('createUploadJob', $request);
	$xml = simplexml_load_string($responseXML);
	/*  start insert counter */
	CountersUtils::insertCounter('createUploadJobRequest','Ebay Inventory',APP_ID);
	/*  end insert counter */

	if (!empty($xml) && 'Success' == (string)$xml->ack)
	{
		$jobId = (string)$xml->jobId;
		file_put_contents(__DIR__ . "/../logs/ebay_inv.txt", date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d H:i:s') . "] EndFixedPriceItem ${jobId}\r\n", FILE_APPEND);
		$fileId = (string)$xml->fileReferenceId;
		
		$fileRaw = '/tmp/ebay_' . time() . '.xml';
		$fileGz = $fileRaw . '.gz';

		file_put_contents($fileRaw, $eBayFeed);
		exec("gzip ${fileRaw}");

		$handle = fopen($fileGz, 'r');
		$file = fread($handle, filesize($fileGz));
		fclose($handle);
		$fileSize = strlen($file);

		$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
		$uuid = uniqid();
		$uuid2 = uniqid();
		$request = <<<EOD
--MIME_boundary
Content-Type: application/xop+xml; charset=UTF-8; type="text/xml; charset=UTF-8"
Content-Transfer-Encoding: binary
Content-ID: <0.urn:uuid:${uuid}>

<uploadFileRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<taskReferenceId>${jobId}</taskReferenceId>
	<fileReferenceId>${fileId}</fileReferenceId>
	<fileFormat>gzip</fileFormat>
	<fileAttachment>
		<Size>${fileSize}</Size>
			<Data><xop:Include xmlns:xop="http://www.w3.org/2004/08/xop/include" href="cid:urn:uuid:${uuid2}"/></Data>
	</fileAttachment>
</uploadFileRequest>
--MIME_boundary
Content-Type: application/octet-stream
Content-Transfer-Encoding: binary
Content-ID: <urn:uuid:${uuid2}>

${file}
--MIME_boundary--
EOD;

	$responseXML = $session->sendFileTransferServiceUploadRequest($request, "<0.urn:uuid:${uuid}>");
	$xml = simplexml_load_string($responseXML);
	/*  start insert counter */
	CountersUtils::insertCounter('uploadFileRequest','Ebay Inventory',APP_ID);
	/*  end insert counter */

	if (empty($xml) || 'Success' != (string)$xml->ack)
	{
		error_log("Error while updating eBay inventory (end list): ${responseXML}");
		SendAdminEmail('eBay Inventory End List Error', $responseXML);
	}
	else
	{
		$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
		$request = <<<EOD
<startUploadJobRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<jobId>${jobId}</jobId>
</startUploadJobRequest>
EOD;

			$responseXML = $session->sendBulkDataExchangeRequest('startUploadJob', $request);
			$xml = simplexml_load_string($responseXML);
			/*  start insert counter */
			CountersUtils::insertCounter('startUploadJob','Ebay Inventory',APP_ID);
			/*  end insert counter */

			if (empty($xml) || 'Success' != (string)$xml->ack)
			{
				error_log("Error while updating eBay inventory (end list): ${responseXML}");
				SendAdminEmail('eBay Inventory End List Error', $responseXML);
			}
		}

		//unlink($fileGz);
	}
	else
	{
		error_log("Error while updating eBay inventory (end list): ${responseXML}");
		SendAdminEmail('eBay Inventory End List Error', $responseXML);
	}
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////

$eBayFeed = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<BulkDataExchangeRequests>
  <Header>
    <SiteID>${siteId}</SiteID>
    <Version>739</Version>
  </Header>
EOD;

if (is_file($tmp_revise)) $eBayFeed .= file_get_contents($tmp_revise);

$q = <<<EOD
SELECT item_id, new_qty, old_qty, sku FROM
(
SELECT e.item_id AS item_id, LEAST(m.qty, 20) as new_qty, e.quantity as old_qty, e.sku as sku
FROM ebay_listings e, inventory_map m
WHERE e.active = 1
AND e.sku = m.sku
AND e.quantity <> m.qty
AND (m.qty > 0 OR e.always_list = 1)
AND e.sku NOT LIKE 'PU%'
AND e.sku NOT LIKE 'WP%'
AND e.sku NOT LIKE 'TR%'
AND e.suspended = 0
UNION ALL
SELECT e.item_id AS item_id, LEAST(FLOOR(m.qty / e.kit_qty), 20) as new_qty, e.quantity as old_qty, e.sku as sku
FROM ebay_listings e, inventory_map m
WHERE e.active = 1
AND e.kit_type = 1
AND e.kit_base = m.sku
AND (m.qty >= e.kit_qty OR e.always_list = 1)
AND e.quantity <> FLOOR(m.qty / e.kit_qty)
AND e.sku NOT LIKE 'PU%'
AND e.sku NOT LIKE 'WP%'
AND e.sku NOT LIKE 'TR%'
AND e.suspended = 0
) x
ORDER BY new_qty ASC
EOD;
$rows = mysql_query($q);
$submit = false;
while ($row = mysql_fetch_row($rows))
{
	$submit = true;
	$itemId = $row[0];
	$qty = $row[1];
	$oldQty = $row[2];
	$sku = $row[3];
	
	$eBayFeed .= <<<EOD
  <ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <Version>739</Version>
    <InventoryStatus>
	  <ItemID>${itemId}</ItemID>
      <Quantity>${qty}</Quantity>
    </InventoryStatus>
  </ReviseInventoryStatusRequest>
EOD;

	unset($n);
	$n['item'] = $itemId;
	$n['new'] = $qty;
	$n['old'] = $oldQty;
	$n['sku'] = $sku;
	$n['action'] = 'Update';
	$notifs[] = $n;
}

$eBayFeed .= "</BulkDataExchangeRequests>";

if ($submit)
{
	$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
	$uuid = uniqid("", true);
	$request = <<<EOD
<createUploadJobRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<uploadJobType>ReviseInventoryStatus</uploadJobType>
	<UUID>${uuid}</UUID>
</createUploadJobRequest>
EOD;
	$responseXML = $session->sendBulkDataExchangeRequest('createUploadJob', $request);
	$xml = simplexml_load_string($responseXML);
	/*  start insert counter */
	CountersUtils::insertCounter('createUploadJobRequest','Ebay Inventory',APP_ID);
	/*  end insert counter */
	
	echo $responseXML;

	if (!empty($xml) && 'Success' == (string)$xml->ack)
	{
		$jobId = (string)$xml->jobId;
		file_put_contents(__DIR__ . "/../logs/ebay_inv.txt", date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d H:i:s') . "] ReviseInventoryStatus ${jobId}\r\n", FILE_APPEND);
		$fileId = (string)$xml->fileReferenceId;
		
		$fileRaw = '/tmp/ebay_' . time() . '.xml';
		$fileGz = $fileRaw . '.gz';

		file_put_contents($fileRaw, $eBayFeed);
		exec("gzip ${fileRaw}");

		$handle = fopen($fileGz, 'r');
		$file = fread($handle, filesize($fileGz));
		fclose($handle);
		$fileSize = strlen($file);

		$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
		$uuid = uniqid();
		$uuid2 = uniqid();
		$request = <<<EOD
--MIME_boundary
Content-Type: application/xop+xml; charset=UTF-8; type="text/xml; charset=UTF-8"
Content-Transfer-Encoding: binary
Content-ID: <0.urn:uuid:${uuid}>

<uploadFileRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<taskReferenceId>${jobId}</taskReferenceId>
	<fileReferenceId>${fileId}</fileReferenceId>
	<fileFormat>gzip</fileFormat>
	<fileAttachment>
		<Size>${fileSize}</Size>
			<Data><xop:Include xmlns:xop="http://www.w3.org/2004/08/xop/include" href="cid:urn:uuid:${uuid2}"/></Data>
	</fileAttachment>
</uploadFileRequest>
--MIME_boundary
Content-Type: application/octet-stream
Content-Transfer-Encoding: binary
Content-ID: <urn:uuid:${uuid2}>

${file}
--MIME_boundary--
EOD;

		$responseXML = $session->sendFileTransferServiceUploadRequest($request, "<0.urn:uuid:${uuid}>");
		$xml = simplexml_load_string($responseXML);
		/*  start insert counter */
		CountersUtils::insertCounter('uploadFileRequest','Ebay Inventory',APP_ID);
		/*  end insert counter */

		if (empty($xml) || 'Success' != (string)$xml->ack)
		{
			error_log("Error while updating eBay inventory (revise): ${responseXML}");
			SendAdminEmail('eBay Inventory Revise Error', $responseXML);
		}
		else
		{
			$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
			$request = <<<EOD
<startUploadJobRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<jobId>${jobId}</jobId>
</startUploadJobRequest>
EOD;

			$responseXML = $session->sendBulkDataExchangeRequest('startUploadJob', $request);
			$xml = simplexml_load_string($responseXML);
			/*  start insert counter */
			CountersUtils::insertCounter('startUploadJobRequest','Ebay Inventory',APP_ID);
			/*  end insert counter */

			if (empty($xml) || 'Success' != (string)$xml->ack)
			{
				error_log("Error while updating eBay inventory (revise): ${responseXML}");
				SendAdminEmail('eBay Inventory Revise Error', $responseXML);
			}
		}

		//unlink($fileGz);
	}
	else
	{
		error_log("Error while updating eBay inventory (revise): ${responseXML}");
		SendAdminEmail('eBay Inventory Revise Error', $responseXML);
	}
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////

$eBayFeed = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<BulkDataExchangeRequests>
  <Header>
    <SiteID>${siteId}</SiteID>
    <Version>801</Version>
  </Header>
EOD;

if (is_file($tmp_relist)) $eBayFeed .= file_get_contents($tmp_relist);

$q = <<<EOD
SELECT DISTINCT e.sku,
(
	SELECT eb.item_id
	FROM ebay_listings eb
	WHERE eb.sku = e.sku
	AND e.active = 0
	AND e.suspended = 0
	ORDER BY eb.timestamp DESC
	LIMIT 1
) AS item_id, LEAST(m.qty, 20) AS new_qty
FROM ebay_listings e, inventory_map m
WHERE e.active = 0
AND e.sku = m.sku
AND (m.qty > 0 OR e.always_list = 1)
AND m.no_indiv_relist = 0
AND NOT EXISTS (SELECT 1 FROM ebay_listings el WHERE el.sku = e.sku AND active = 1)
AND e.sku NOT LIKE 'PU%'
AND e.sku NOT LIKE 'WP%'
AND e.sku NOT LIKE 'TR%'
AND e.suspended = 0
EOD;
$rows = mysql_query($q);
$submit = false;
while ($row = mysql_fetch_row($rows))
{
	$submit = true;
	$sku = $row[0];
	$itemId = $row[1];
	$qty = $row[2];
	
	$eBayFeed .= <<<EOD
  <RelistFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
	<Item>
		<ItemID>${itemId}</ItemID>
		<SKU>${sku}</SKU>
		<Quantity>${qty}</Quantity>
		<InventoryTrackingMethod>SKU</InventoryTrackingMethod>
		<CategoryMappingAllowed>true</CategoryMappingAllowed>
		<PrivateListing>true</PrivateListing>
	</Item>
    <Version>801</Version>
  </RelistFixedPriceItemRequest>
EOD;

	unset($n);
	$n['item'] = $itemId;
	$n['new'] = $qty;
	$n['old'] = '-';
	$n['sku'] = $sku;
	$n['action'] = 'Relist';
	$notifs[] = $n;
}

// KITS
$q = <<<EOD
SELECT DISTINCT e.sku,
(
	SELECT eb.item_id
	FROM ebay_listings eb
	WHERE eb.sku = e.sku
	AND e.active = 0
	ORDER BY eb.timestamp DESC
	LIMIT 1
) AS item_id, LEAST(FLOOR(m.qty / e.kit_qty), 20) AS new_qty
FROM ebay_listings e, inventory_map m
WHERE e.active = 0
AND e.suspended = 0
AND e.kit_type = 1
AND e.kit_base = m.sku
AND (m.qty >= e.kit_qty OR e.always_list = 1)
AND NOT EXISTS (SELECT 1 FROM ebay_listings el WHERE el.sku = e.sku AND active = 1)
EOD;
$rows = mysql_query($q);
while ($row = mysql_fetch_row($rows))
{
	$submit = true;
	$sku = $row[0];
	$itemId = $row[1];
	$qty = $row[2];
	
	$eBayFeed .= <<<EOD
  <RelistFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
	<Item>
		<ItemID>${itemId}</ItemID>
		<SKU>${sku}</SKU>
		<Quantity>${qty}</Quantity>
		<InventoryTrackingMethod>SKU</InventoryTrackingMethod>
		<CategoryMappingAllowed>true</CategoryMappingAllowed>
		<PrivateListing>true</PrivateListing>
	</Item>
    <Version>801</Version>
  </RelistFixedPriceItemRequest>
EOD;

	unset($n);
	$n['item'] = $itemId;
	$n['new'] = $qty;
	$n['old'] = '-';
	$n['sku'] = $sku;
	$n['action'] = 'Relist';
	$notifs[] = $n;
}

$eBayFeed .= "</BulkDataExchangeRequests>";

if ($submit)
{
	$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
	$uuid = uniqid("", true);
	$request = <<<EOD
<createUploadJobRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<uploadJobType>RelistFixedPriceItem</uploadJobType>
	<UUID>${uuid}</UUID>
</createUploadJobRequest>
EOD;
	$responseXML = $session->sendBulkDataExchangeRequest('createUploadJob', $request);
	$xml = simplexml_load_string($responseXML);
	/*  start insert counter */
	CountersUtils::insertCounter('createUploadJobRequest','Ebay Inventory',APP_ID);
	/*  end insert counter */

	if (!empty($xml) && 'Success' == (string)$xml->ack)
	{
		$jobId = (string)$xml->jobId;
		file_put_contents(__DIR__ . "/../logs/ebay_inv.txt", date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d H:i:s') . "] RelistFixedPriceItem ${jobId}\r\n", FILE_APPEND);
		$fileId = (string)$xml->fileReferenceId;
		
		$fileRaw = '/tmp/ebay_' . time() . '.xml';
		$fileGz = $fileRaw . '.gz';

		file_put_contents($fileRaw, $eBayFeed);
		exec("gzip ${fileRaw}");

		$handle = fopen($fileGz, 'r');
		$file = fread($handle, filesize($fileGz));
		fclose($handle);
		$fileSize = strlen($file);

		$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
		$uuid = uniqid();
		$uuid2 = uniqid();
		$request = <<<EOD
--MIME_boundary
Content-Type: application/xop+xml; charset=UTF-8; type="text/xml; charset=UTF-8"
Content-Transfer-Encoding: binary
Content-ID: <0.urn:uuid:${uuid}>

<uploadFileRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<taskReferenceId>${jobId}</taskReferenceId>
	<fileReferenceId>${fileId}</fileReferenceId>
	<fileFormat>gzip</fileFormat>
	<fileAttachment>
		<Size>${fileSize}</Size>
			<Data><xop:Include xmlns:xop="http://www.w3.org/2004/08/xop/include" href="cid:urn:uuid:${uuid2}"/></Data>
	</fileAttachment>
</uploadFileRequest>
--MIME_boundary
Content-Type: application/octet-stream
Content-Transfer-Encoding: binary
Content-ID: <urn:uuid:${uuid2}>

${file}
--MIME_boundary--
EOD;

	$responseXML = $session->sendFileTransferServiceUploadRequest($request, "<0.urn:uuid:${uuid}>");
	$xml = simplexml_load_string($responseXML);
	/*  start insert counter */
	CountersUtils::insertCounter('uploadFileRequest','Ebay Inventory',APP_ID);
	/*  end insert counter */
	
	if (empty($xml) || 'Success' != (string)$xml->ack)
	{
		error_log("Error while updating eBay inventory (relist): ${responseXML}");
		SendAdminEmail('eBay Inventory Relist Error', $responseXML);
	}
	else
	{
		$session = new LargeMerchantServiceSession('XML', 'XML', $environment);
		$request = <<<EOD
<startUploadJobRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
	<jobId>${jobId}</jobId>
</startUploadJobRequest>
EOD;

			$responseXML = $session->sendBulkDataExchangeRequest('startUploadJob', $request);
			$xml = simplexml_load_string($responseXML);
			/*  start insert counter */
			CountersUtils::insertCounter('startUploadJobRequest','Ebay Inventory',APP_ID);
			/*  end insert counter */
					
			if (empty($xml) || 'Success' != (string)$xml->ack)
			{
				error_log("Error while updating eBay inventory (relist): ${responseXML}");
				SendAdminEmail('eBay Inventory Relist Error', $responseXML);
			}
		}

		//unlink($fileGz);
	}
	else
	{
		error_log("Error while updating eBay inventory (relist): ${responseXML}");
		SendAdminEmail('eBay Inventory Relist Error', $responseXML);
	}
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////

$html = "<html><body>";

if (!empty($notifs))
{
	$html .= "<table><tr><td>Action</td><td>Item ID</td><td>SKU</td><td>Old Quantity</td><td>Avail Quantity</td><tr>\r\n";
	foreach ($notifs as $n)
	{
		$action = $n['action'];
		$item = $n['item'];
		$sku = $n['sku'];
		$new = $n['new'];
		$old = $n['old'];
		$html .= "<tr><td>${action}</td><td>${item}</td><td>${sku}</td><td>${old}</td><td>${new}</td></tr>\r\n";
	}

	$html .= "</tr></table><br/><br/>The above actions were sent to eBay. It may take up to 30 minutes for the results to reflect online.";
}
else
{
	$html .= "Inventory is up to date. No changes necessary.\r\n";
}

$html .= "</body></html>";

//SendAdminEmail('Inventory Updated (eBay)', $html);

///////////////////////////////////////////////////////////////////////////////////////////////////////////
		
mysql_close();

?>