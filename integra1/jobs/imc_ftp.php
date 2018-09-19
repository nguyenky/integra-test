<?php

require_once(__DIR__ . '/../system/ebay/lms/ServiceEndpointsAndTokens.php');
require_once(__DIR__ . '/../system/ebay/lms/LargeMerchantServiceSession.php');
require_once(__DIR__ . '/../system/ebay/lms/DOMUtils.php');
require_once(__DIR__ . '/../system/ebay/lms/PrintUtils.php');

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');

const IMC_SIDE_ID_ADDITION = 100;

set_time_limit(0);
ini_set('memory_limit', '768M');
ignore_user_abort(TRUE);

file_put_contents(LOGS_DIR . "imc_ftp.log", "================= START IMC_FTP JOB =============== \r\n", FILE_APPEND);

try {

	$conn_id = ftp_connect(IMC_FTP_SERVER);
$login_result = ftp_login($conn_id, IMC_FTP_USERNAME, IMC_FTP_PASSWORD);

ftp_chdir($conn_id, 'Inventory2');
$contents = ftp_nlist($conn_id, '.');

$exceptions = ['INV_0104.csv', 'INV_0110.csv', 'INV_0118.csv', 'INV_0127.csv'];

array_map('unlink', glob(__DIR__ . '/tmp/ftp/*.csv'));
foreach ($contents as $file)
{
	file_put_contents(LOGS_DIR . "imc_ftp.log", " FILE: ". $file ." \r\n", FILE_APPEND);
	if(in_array($file, $exceptions))
	{
		file_put_contents(LOGS_DIR . "imc_ftp.log", " Ingore FILE: ". $file ." \r\n", FILE_APPEND);
		continue;
	}

	ftp_get($conn_id, __DIR__ . "/tmp/ftp/${file}", $file, FTP_BINARY);
}

ftp_close($conn_id);

if (empty($contents))
{
	error_log('Unable to connect to W1 FTP');
	return;
}

$conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysqli_select_db($conn, DB_SCHEMA);

mysqli_query($conn, "TRUNCATE TABLE imc_ftp");

$files = glob(__DIR__ . '/tmp/ftp/*.csv');

$actions =[101,103,105,106,107,108,109,111,112,115,123,125];

foreach ($files as $file)
{
	// get warehouse number from filename
	$wh = intval(substr(basename($file), 4, 4), 10);
	if($wh < 100) {
		$wh += IMC_SIDE_ID_ADDITION;
	}
	
	// only load warehouses registered in our system (those supporting dropshipping or truck delivery)
	if (!array_key_exists($wh, ImcUtils::$siteIDs))
		continue;
	
	if(!in_array($wh,$actions))
		continue;

	// load csv to imc_ftp table
	$abs = realpath($file);
	$res = mysqli_query($conn, "LOAD DATA LOCAL INFILE '${abs}' INTO TABLE imc_ftp FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\\n' (mpn_spaced, jpn_spaced, qty) SET wh = ${wh}");
	if (!$res) {
		echo mysqli_error($conn);
		file_put_contents(LOGS_DIR . "imc_ftp.log", "ERROR: ". mysqli_error($conn) ." \r\n", FILE_APPEND);
	} 
}

// update mpn_unspaced and jpn_unspaced fields
$res = mysqli_query($conn, "UPDATE imc_ftp SET mpn_unspaced = REPLACE(mpn_spaced, ' ', ''), jpn_unspaced = REPLACE(jpn_spaced, ' ', '')");
if (!$res) echo mysqli_error($conn);

// sum up total quantity available from all warehouses
mysqli_query($conn, "TRUNCATE TABLE imc_qty");
$res = mysqli_query($conn, "INSERT INTO imc_qty (mpn, qty) (SELECT mpn_unspaced, SUM(qty) FROM imc_ftp GROUP BY mpn_unspaced)");
if (!$res) echo mysqli_error($conn);

/*
// update need4autoparts inventory (only miami and pompano). disable products not on stock
mysql_query("TRUNCATE TABLE eoc.n4ap_avail");

$q = <<<EOD
INSERT INTO eoc.n4ap_avail (entity_id, avail)
(
	SELECT	cpe.entity_id, IF (SUM(f.qty) > 0, 1, 2)
	FROM	magento.catalog_product_entity cpe,
			magento.catalog_product_website cpw,
			magento.core_website cw,
			eoc.sku_mpn sm,
			imc_ftp f
	WHERE	cpe.entity_id = cpw.product_id
	AND	cw.website_id = cpw.website_id
	AND	cw.code = 'need4autoparts'
	AND	cpe.sku = sm.sku
	AND	f.wh IN (8, 15)
	AND	sm.mpn = f.mpn_unspaced
	GROUP BY cpe.entity_id
)
EOD;
$res = mysql_query($q);
if (!$res) echo mysql_error();

$q = <<<EOD
DELETE FROM magento.catalog_product_entity_int
WHERE store_id = (SELECT store_id FROM magento.core_store WHERE code = 'need4autoparts')
AND attribute_id = (SELECT attribute_id FROM magento.eav_attribute WHERE attribute_code = 'status')
EOD;
$res = mysql_query($q);
if (!$res) echo mysql_error();

$q = <<<EOD
INSERT INTO magento.catalog_product_entity_int (entity_type_id, attribute_id, store_id, entity_id, value)
(
	SELECT a.entity_type_id, a.attribute_id, s.store_id, n.entity_id, n.avail
	FROM magento.eav_attribute a, magento.core_store s, magento.catalog_product_website cpw, eoc.n4ap_avail n
	WHERE a.attribute_code = 'status'
	AND s.code = 'need4autoparts'
	AND s.website_id = cpw.website_id
	AND n.entity_id = cpw.product_id
)
EOD;
$res = mysql_query($q);
if (!$res) echo mysql_error();
*/
mysqli_select_db($conn, DB_SCHEMA);


$environment = ENV_PRODUCTION;
$siteId = SITE_ID;

$row = mysqli_fetch_row(mysqli_query($conn, "SELECT TIMESTAMPDIFF(MINUTE, MAX(timestamp), NOW()) FROM ebay_listings"));
$diff = $row[0];

if (!empty($diff) && $diff > 60)
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

	if(!empty($xml) && 'Success' == (string)$xml->ack)
	{
		$jobId = (string)$xml->jobId;
		file_put_contents(__DIR__ . '/../logs/ebay_inv.txt', date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d H:i:s') . "] ActiveInventoryReport ${jobId}\r\n", FILE_APPEND);
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
	mysqli_select_db($conn, DB_SCHEMA);
	mysqli_query($conn, "UPDATE ebay_listings SET active = 0");

	foreach($xml->ActiveInventoryReport->SKUDetails as $item)
	{
		$q=<<<EOQ
INSERT INTO ebay_listings (item_id, sku, price, quantity, active)
VALUES ('%s', '%s', '%s', '%s', 1)
ON DUPLICATE KEY UPDATE sku=VALUES(sku), price=VALUES(price), quantity=VALUES(quantity), active=1, timestamp=NOW()
EOQ;
		mysqli_query($conn, sprintf($q, $item->ItemID, $item->SKU, $item->Price, $item->Quantity));
	}
}

mysqli_select_db($conn, DB_SCHEMA);
mysqli_query($conn, "TRUNCATE integra_prod.ebay_listings");

$q=<<<EOQ
INSERT INTO integra_prod.ebay_listings (item_id, sku, price, quantity, active, always_list)
(SELECT item_id, sku, price, quantity, active, always_list FROM eoc.ebay_listings)
EOQ;
mysqli_query($conn, $q);


// combine all quantities from imc, ssf, esi, pu, and wp
mysqli_query($conn, "TRUNCATE TABLE eoc.supplier_qty");

$q = <<<EOD
INSERT INTO supplier_qty (mpn, qty, supplier, updated)
SELECT i.mpn, i.qty, 1, i.timestamp
FROM imc_qty i
UNION ALL
SELECT s.mpn, s.qty, 2, s.timestamp
FROM ssf_qty s
UNION ALL
SELECT e.mpn, e.qty, 3, e.timestamp
FROM esi_qty e
UNION ALL
SELECT e.sku, e.quantity, 7, MAX(e.timestamp)
FROM ebay_listings e
WHERE e.sku LIKE 'PU%'
AND e.active = 1
GROUP BY 1, 2, 3
UNION ALL
SELECT e.sku, e.quantity, 8, MAX(e.timestamp)
FROM ebay_listings e
WHERE e.sku LIKE 'WP%'
AND e.active = 1
GROUP BY 1, 2, 3
UNION ALL
SELECT e.sku, e.quantity, 9, MAX(e.timestamp)
FROM ebay_listings e
WHERE e.sku LIKE 'TR%'
AND e.active = 1
GROUP BY 1, 2, 3
EOD;
$res = mysqli_query($conn, $q);
if (!$res) echo mysqli_error($conn);

// map mpns into skus and lookup those skus that should not be relisted
mysqli_query($conn, "TRUNCATE TABLE inventory_map");

$q = <<<EOD
INSERT IGNORE INTO inventory_map (mpn, supplier, sku, qty, no_indiv_relist)
SELECT m.mpn, m.supplier, m.sku, s.qty, m.no_indiv_relist
FROM sku_mpn m, supplier_qty s
WHERE m.mpn = s.mpn AND m.supplier = s.supplier
UNION
SELECT s.mpn, s.supplier, s.mpn AS sku, s.qty, (SELECT IFNULL(MAX(no_indiv_relist), 0) FROM sku_mpn m WHERE m.mpn = s.mpn AND m.supplier = s.supplier) AS no_indiv_relist
FROM supplier_qty s
UNION
SELECT s.mpn, s.supplier, CONCAT('EOC', s.mpn) AS sku, s.qty, (SELECT IFNULL(MAX(no_indiv_relist), 0) FROM sku_mpn m WHERE m.mpn = s.mpn AND m.supplier = s.supplier) AS no_indiv_relist
FROM supplier_qty s
WHERE s.supplier = 1
UNION
SELECT s.mpn, s.supplier, CONCAT('EOCS', s.mpn) AS sku, s.qty, (SELECT IFNULL(MAX(no_indiv_relist), 0) FROM sku_mpn m WHERE m.mpn = s.mpn AND m.supplier = s.supplier) AS no_indiv_relist
FROM supplier_qty s
WHERE s.supplier = 2
EOD;
$res = mysqli_query($conn, $q);
if (!$res) echo mysqli_error($conn);
try{
	mysqli_query($conn, "CALL magento_update_inventory()");

	$date = date_create("now", new DateTimeZone('America/New_York'));
	$fn = date_format($date, 'Y-m-d_H-i') . '_inventory_source';
	$csv = "/tmp/mysql/${fn}.csv";
	$zip = __DIR__ . "/../inventory_history/${fn}.zip";


$q = <<<EOQ
SELECT 'MPN', 'Quantity', 'SKUs', 'Supplier', 'Last Updated (EST)'
UNION ALL
SELECT * INTO OUTFILE '%s'
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\\r\\n'
FROM
(SELECT s.mpn, s.qty, IFNULL(GROUP_CONCAT(DISTINCT m.sku ORDER BY m.sku), ''), s.supplier, CONVERT_TZ(s.updated, '+08:00', 'EST')
FROM supplier_qty s LEFT JOIN sku_mpn m ON s.mpn = m.mpn AND s.supplier = m.supplier
GROUP BY s.mpn, s.qty, s.supplier, s.updated
ORDER BY 3, 1) x
EOQ;
	$res = mysqli_query($conn, sprintf($q, $csv));
	if (!$res) echo mysqli_error($conn);

	for ($i = 0; $i < 60; $i++) {
		sleep(1);
		if (is_file($csv)) break;
	}

	exec("zip -9j ${zip} ${csv}");

	unlink("/var/lib/mysql/" . $csv);

	$fn = date_format($date, 'Y-m-d_H-i') . '_disconnected_listings';
	$csv = "/tmp/mysql/${fn}.csv";
	$zip = __DIR__ . "/../inventory_history/${fn}.zip";

$q = <<<EOQ
SELECT 'Store', 'Item ID', 'SKU'
UNION ALL
SELECT 'eBay', e.item_id, e.sku
FROM ebay_listings e
WHERE e.sku NOT LIKE '%%-%%'
AND e.sku NOT LIKE '%%$%%'
AND e.active = 1
AND NOT EXISTS
(SELECT 1 FROM inventory_map m WHERE m.sku = e.sku)
UNION ALL
SELECT 'Amazon', a.asin, a.sku INTO OUTFILE '%s'
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
FROM amazon_listings a
WHERE a.sku NOT LIKE '%%-%%'
AND a.sku NOT LIKE '%%$%%'
AND a.active = 1
AND NOT EXISTS
(SELECT 1 FROM inventory_map m WHERE m.sku = a.sku)
EOQ;
	$res = mysqli_query($conn, sprintf($q, $csv));
	exec("zip -9j ${zip} ${csv}");

	unlink($csv);
}
catch(Exception $e)
{
	file_put_contents(LOGS_DIR . "imc_ftp.log", $e->getMessage()."\r\n", FILE_APPEND);
}
if (!$res) echo mysqli_error($conn);

for ($i = 0; $i < 60; $i++) {
	sleep(1);
	if (is_file($csv)) break;
}

$q = <<<EOQ
UPDATE
	integra_prod.google_feed gf,
	magento.catalog_product_entity cpe,
	magento.catalog_product_entity_decimal cped,
	magento.core_store cs,
	magento.cataloginventory_stock_status css
SET gf.price = cped.value, gf.availability = IF(css.stock_status = 1, 'in stock', 'out of stock')
WHERE gf.mpn = cpe.sku
AND cpe.entity_id = cped.entity_id
AND cped.attribute_id = 75
AND cped.store_id = cs.store_id
AND cs.code = 'europortparts'
AND cpe.entity_id = css.product_id
AND css.website_id = cs.website_id
EOQ;
$res = mysqli_query($conn, $q);
if (!$res) {
	echo mysqli_error($conn);
	file_put_contents(LOGS_DIR . "imc_ftp.log", "======= ERROR: ". $mysqli_error($conn) ." ======= \r\n", FILE_APPEND);
} 

file_put_contents(LOGS_DIR . "imc_ftp.log", "================= ENDED IMC_FTP JOB =============== \r\n", FILE_APPEND);

mysqli_close($conn);

} catch(Exception $ex) {
	file_put_contents(LOGS_DIR . "imc_ftp.log", $ex->getMessage()."\r\n", FILE_APPEND);
}



?>