<?php

require_once('ebay/lms/DOMUtils.php');
require_once('ebay/lms/PrintUtils.php');

//require_once('Mail.php');
//require_once('Mail/mime.php');

require_once('config.php');

function query()
{
	$args = func_get_args();
	if (count($args) < 2)
		return false;
	$q = array_shift($args);
	$args = array_map('mysql_real_escape_string', $args);
	array_unshift($args, $q);
	$q = call_user_func_array('sprintf', $args);
	return $q;
}

function CreateManualOrder($skus, $prices, $descs, $internalId, $recordNum, $email, $name, $street, $city, $state, $zip, $phone, $speed, $agent, $fulfilment, $status, $relatedRecordNum, $soldPrice, $supplier)
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$total = 0;
	foreach ($skus as $sku => $qty)
		$total += $prices[$sku] * $qty;
		
	if ($soldPrice == 0)
		$soldPrice = $total;
		
	$relatedSalesId = 0;
	
	if (!empty($relatedRecordNum))
	{
		$row = mysql_fetch_row(mysql_query(query("SELECT id FROM sales WHERE record_num = '%s' ORDER BY id DESC LIMIT 1", $relatedRecordNum)));
		$relatedSalesId = $row[0];
		
		if (empty($relatedSalesId))
			$relatedSalesId = 0;
	}

	$q=<<<EOQ
INSERT IGNORE INTO sales (
store,
	internal_id,
		record_num,
			order_date,
				total,
					buyer_id,
						email,
							buyer_name,
								street,
									city,
										state,
											zip,
												phone,
													speed,
														agent,
															fulfilment,
																status,
																	related_record_num,
																		related_sales_id,
																			sold_price,
																				supplier,
																					supplier_cost)
VALUES (
'Manual',
	'%s',
		NULLIF('%s', ''),
			'%s',
				'%s',
					'',
						'%s',
							'%s',
								'%s',
									'%s',
										'%s',
											'%s',
												'%s',
													'%s',
														'%s',
															%d,
																%d,
																	NULLIF('%s', ''),
																		NULLIF('%s', 0),
																			'%s',
																				%d,
																					'%s')
EOQ;
	mysql_query(query($q,
	$internalId,
		$recordNum,
			gmdate('Y-m-d H:i:s'),
				$total,
						$email,
							$name,
								$street,
									$city,
										$state,
											$zip,
												$phone,
													$speed,
														$agent,
															$fulfilment,
																$status,
																	$relatedRecordNum,
																		$relatedSalesId,
																			(float) $soldPrice,
																				$supplier,
																					$total));

	$salesId = mysql_insert_id();
		
	foreach ($skus as $sku => $qty)
	{
		$q=<<<EOQ
		INSERT IGNORE INTO sales_items (sales_id, sku, description, quantity, unit_price, total)
		VALUES (%d, '%s', '%s', '%s', '%s', '%s')
EOQ;
		mysql_query(query($q,
			$salesId,
			$sku,
			$descs[$sku],
			$qty,
			$prices[$sku],
			$prices[$sku] * $qty));
	}
	
	$result = GetTotalWeight($salesId);
	$weight = $result['weight'];
	if (!empty($weight))
		mysql_query("UPDATE sales SET weight = '${weight}' WHERE id = ${salesId}");
	
	return $salesId;
}

function CheckAutoProcess($salesId)
{
	$supplierId = CheckOrderSuppliers($salesId);
	
	if ($supplierId < 1)
		return;
		
	if ($supplierId == 1)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
	//mysql_query("UPDATE sales SET auto_order = 1 WHERE id = ${salesId} AND auto_order = 0 AND fulfilled = 0");
	}
}

function GetTotalWeight($salesId)
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$res = mysql_query("SELECT sku, quantity FROM sales_items WHERE sales_id = ${salesId}");
	while ($row = mysql_fetch_row($res))
		$items[$row[0]] = $row[1];

    $parts = GetSKUParts($items);
    $weight = 0;
    $weightStr = '';
    $imcParts = array();
    $ssfParts = array();
    $esiParts = array();

    foreach ($parts as $sku => $qty)
    {
        $q=<<<EOQ
        SELECT supplier, mpn
        FROM sku_mpn WHERE sku = '${sku}'
        ORDER BY id
        LIMIT 1
EOQ;

        $row = mysql_fetch_row(mysql_query($q));
        if (empty($row))
        {
            if (stripos($sku, '.') !== false)
            {
                $supplier = 2;
                $mpn = str_replace("EOCS", "", $sku);
            }
            else
            {
                $supplier = 1;
                $mpn = str_replace("EOC", "", $sku);
            }
        }
        else
        {
            $supplier = $row[0];
            $mpn = $row[1];
        }

        if ($supplier == 1)
        {
            $row = mysql_fetch_row(mysql_query("SELECT weight, name, brand FROM imc_items WHERE mpn IN (SELECT mpn FROM sku_mpn WHERE sku = '$sku' AND supplier = 1) LIMIT 1"));
            if (empty($row))
            {
                $row = mysql_fetch_row(mysql_query("SELECT weight, name, brand FROM imc_items WHERE mpn = '$mpn'"));
                if (empty($row))
                {
                    $weightStr = "No W1 weight data in database";
                    $weight = 0;
                    break;
                }
            }

            $weight += ($row[0] * $qty);
            $existingQty = 0;
            if (array_key_exists($mpn, $imcParts)) $existingQty = $imcParts[$mpn];
            $imcParts[$mpn] = $existingQty + $qty;
            $names[$mpn] = $row[1];
            $brands[$mpn] = $row[2];
        }
        else if ($supplier == 2)
        {
            $origMpn = $mpn;
            $fields = explode('.', $mpn);
            $mpn = $fields[0];
            if (count($fields) > 1) $brand = $fields[1];
            else $brand = '';

            $row = mysql_fetch_row(mysql_query("SELECT weight, name, brand FROM ssf_items WHERE mpn = '$mpn' AND brand_id = '$brand'"));
            if (empty($row))
            {
                $weightStr = "No W2 weight data in database";
                $weight = 0;
                break;
            }

            $weight += ($row[0] * $qty);
            $existingQty = 0;
            if (array_key_exists($origMpn, $ssfParts)) $existingQty = $ssfParts[$origMpn];
            $ssfParts[$origMpn] = $existingQty + $qty;
            $names[$origMpn] = $row[1];
            $brands[$origMpn] = $row[2];
        }
        else
        {
            $weightStr = "No W3 weight data in database";
            $weight = 0;
            $existingQty = 0;
            if (array_key_exists($mpn, $esiParts)) $existingQty = $esiParts[$mpn];
            $esiParts[$mpn] = $existingQty + $qty;
            $names[$mpn] = '';
            $brands[$mpn] = '';
            break;
        }
    }

	if (!empty($weight))
	{
		$pounds = floor($weight);
		$ounces = ($weight - $pounds) * 16;
		if (!empty($ounces) && !empty($pounds))
			$weightStr = "$pounds lb $ounces oz";
		else if (empty($ounces) && !empty($pounds))
			$weightStr = "$pounds lb";
		else if (!empty($ounces) && empty($pounds))
			$weightStr = "$ounces oz";
	}
	
	$result['weight'] = $weight;
	$result['weight_str'] = $weightStr;
	
	return $result;
}

function GetOrderComponents($salesId)
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
	
	$res = mysql_query("SELECT sku, quantity FROM sales_items WHERE sales_id = ${salesId}");
	while ($row = mysql_fetch_row($res))
    {
        $sku = $row[0];

        if (endsWith($sku, '$D') || endsWith($sku, '$W'))
            $sku = substr($sku, 0, strlen($sku) - 2);

		$items[$sku] = $row[1];
    }
		
	$parts = GetSKUParts($items);
	return $parts;
}

function CheckOrderSuppliers($salesId, $skipWarehouse = false)
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

    $res = mysql_query('SELECT COUNT(*) FROM eoc.sales_items WHERE sku LIKE \'%$D\' AND sales_id = ' . $salesId);
    $row = mysql_fetch_row($res);

    if (!empty($row) && !empty($row[0]))
    {
        $res = mysql_query("SELECT COUNT(*) FROM eoc.sales WHERE speed = 'Local Pick Up' AND id = {$salesId}");
        $row = mysql_fetch_row($res);

        if (!empty($row) && !empty($row[0]))
            $forceDropship = false;
        else
            $forceDropship = true;

    }
    else
        $forceDropship = false;
	
	$parts = GetOrderComponents($salesId);
	$suppliers = array();
	
	foreach ($parts as $sku => $qty)
	{
        if (!$forceDropship && !$skipWarehouse)
        {
            $eocStock = GetEOCStock($sku);
            if ($eocStock >= $qty)
            {
                $suppliers[5] = 1; // eoc;
                continue;
            }
        }

		if (strpos($sku, 'PU') === 0)
			$suppliers[7] = 1; // pu
		else if (strpos($sku, 'WP') === 0)
			$suppliers[8] = 1; // wps
        else if (strpos($sku, 'TR') === 0)
            $suppliers[9] = 1; // tr
        else if (strpos($sku, '.') === false) // check for dot
            $suppliers[1] = 1;  // imc
        else $suppliers[2] = 1; // ssf
	}

	// multiple suppliers
	if (count($suppliers) > 1)
		return -2;
	
	reset($suppliers);
	return key($suppliers);
}

function GetEOCStock($sku)
{
    mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
    mysql_select_db("integra_prod");

    // ssf with prefix
    if (strpos($sku, 'EOCS') === 0 && strpos($sku, '.') !== false)
        $sku = str_replace('EOCS', '', $sku);
    else if (strpos($sku, 'EOC') === 0)
        $sku = str_replace('EOC', '', $sku);

    // Temporary -- move to Integra 2 with more flexible supplier mechanism

    $query = "
SELECT quantity
FROM warehouses w, product_warehouse pw, products p LEFT JOIN product_codes pc ON pc.product_id = p.id
WHERE pw.warehouse_id = w.id
AND w.supplier_id = 5
AND pw.product_id = p.id
AND (p.sku = '%s' OR pc.code = '%s')";

    $res = mysql_fetch_row(mysql_query(sprintf($query, cleanup(str_replace('-', '', $sku)), cleanup(str_replace('-', '', $sku)))));

    mysql_select_db(DB_SCHEMA);

    return $res[0];
}

function UseEOCStock($sku, $quantity)
{
    mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
    mysql_select_db("integra_prod");

    // ssf with prefix
    if (strpos($sku, 'EOCS') == 0 && strpos($sku, '.') !== false)
        $sku = str_replace('EOCS', '', $sku);
    else if (strpos($sku, 'EOC') == 0)
        $sku = str_replace('EOC', '', $sku);

    // Temporary -- move to Integra 2 with more flexible supplier mechanism

    $query = "
UPDATE product_warehouse pw, products p, warehouses w
SET pw.quantity = pw.quantity - %d
WHERE pw.warehouse_id = w.id
AND w.supplier_id = 5
AND pw.product_id = p.id
AND p.sku = '%s'";

    mysql_query(sprintf($query, cleanup($quantity), cleanup(str_replace('-', '', $sku))));
}

function SaveManualOrder($skus, $prices, $descs, $internalId, $recordNum, $email, $name, $street, $city, $state, $zip, $phone, $speed, $agent)
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$total = 0;
	foreach ($skus as $sku => $qty)
		$total += $prices[$sku] * $qty;

	$q=<<<EOQ
	INSERT IGNORE INTO sales (store, internal_id, record_num, order_date, total, supplier_cost, buyer_id, email, buyer_name, street, city, state, zip, phone, speed, fulfilled, auto_order, agent)
	VALUES ('Manual', '%s', NULLIF('%s', ''), '%s', '%s', '', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 1, 0, '%s')
EOQ;
	mysql_query(sprintf($q,
		cleanup($internalId),
		cleanup($recordNum),
		cleanup(gmdate('Y-m-d H:i:s')),
		cleanup($total),
		cleanup($total),
		cleanup($email),
		cleanup($name),
		cleanup($street),
		cleanup($city),
		cleanup($state),
		cleanup($zip),
		cleanup($phone),
		cleanup($speed),
		cleanup($agent)));
	
	$salesId = mysql_insert_id();
		
	foreach ($skus as $sku => $qty)
	{
		$q=<<<EOQ
		INSERT IGNORE INTO sales_items (sales_id, sku, description, quantity, unit_price, total)
		VALUES (%d, '%s', '%s', '%s', '%s', '%s')
EOQ;
		mysql_query(sprintf($q,
			$salesId,
			cleanup($sku),
			cleanup($descs[$sku]),
			$qty,
			$prices[$sku],
			$prices[$sku] * $qty));
	}
	
	return $salesId;
}

function SaveDirectShipment($salesId, $supplier, $orderId, $refId = null, $subtotal, $core, $shipping, $total, $tracking = null, $isBulk = false, $noTracking = false)
{
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$q=<<<EOQ
	INSERT IGNORE INTO direct_shipments (sales_id, supplier, order_id, order_id2, subtotal, core, shipping, total, tracking_num, is_bulk, no_tracking)
	VALUES ('%s', '%s', '%s', %s, '%s', '%s', '%s', '%s', %s, %d, %d)
EOQ;
	mysql_query(sprintf($q, $salesId, $supplier, $orderId, empty($refId) ? 'NULL' : "'${refId}'", $subtotal, $core, $shipping, $total, empty($tracking) ? 'NULL' : "'${tracking}'", empty($isBulk) ? 0 : 1, empty($noTracking) ? 0 : 1));

    if (!empty($salesId))
    {
        $q = <<<EOQ
	    INSERT IGNORE INTO direct_shipments_sales (sales_id, order_id)
	    VALUES ('%s', '%s')
EOQ;
        mysql_query(sprintf($q, $salesId, $orderId));
    }

	mysql_query("UPDATE sales_items SET shipment_order_id = '${orderId}' WHERE sales_id = ${salesId}");
	mysql_query("UPDATE sales SET status = 2, fulfilment = 1 WHERE id = ${salesId}");
	mysql_query("UPDATE sales SET fulfilled = 1 WHERE id = ${salesId}");
	mysql_query("UPDATE sales SET auto_order = 0 WHERE fulfilled = 1 AND auto_order = 1 AND id = ${salesId}");
	mysql_query("UPDATE sales SET shipping_cost = '${shipping}' WHERE id = ${salesId}");

    date_default_timezone_set('America/New_York');

    if ($supplier == 1 && $noTracking)
    {
        $dow = date('D');

        if ($dow == 'Sun')
            $etd = date('Y-m-d', strtotime('tomorrow'));
        else if ($dow == 'Sat')
        {
            if (intval(date('G')) > 12)
                $etd = date('Y-m-d', strtotime('next Monday'));
            else $etd = date('Y-m-d');
        }
        else
        {
            if (intval(date('G')) > 15)
                $etd = date('Y-m-d', strtotime('tomorrow'));
            else $etd = date('Y-m-d');
        }

        mysql_query("UPDATE direct_shipments SET etd = '{$etd}' WHERE supplier = 1 AND no_tracking = 1 AND order_id = '{$orderId}'");
    }
}

function TrimSKUPrefix($parts, $prefix)
{
	$results = array();
	
	if (!empty($parts))
	{
		foreach ($parts as $sku => $qty)
		{
			if (empty($sku))
				continue;
					
			$sku = strtoupper($sku);
				
			if (startsWith($sku, $prefix))
				$sku = substr($sku, strlen($prefix));
			
			//if (startsWith($sku, 'EOC'))
				//$sku = substr($sku, 3);
					
			if (empty($sku))
				continue;
				
			$results[$sku] = $qty;
		}
	}
		
	return $results;
}

function GetKitParts($sku)
{
    mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
    mysql_select_db(DB_SCHEMA);
    $items = [];

    $res = mysql_query(
"SELECT c.sku, k.quantity
FROM integra_prod.products p, integra_prod.products c, integra_prod.kit_components k
WHERE p.sku = '${sku}'
AND p.is_kit = 1
AND p.id = k.product_id
AND k.component_product_id = c.id");

    while ($row = mysql_fetch_row($res))
        $items[$row[0]] = $row[1];

    return $items;
}

function GetSKUParts($items)
{
	$parts = array();
	
	if (!empty($items))
	{
		foreach ($items as $sku => $qty)
		{
			if (empty($sku))
				continue;

            if (startsWith($sku, 'EK'))
            {
                if (endsWith($sku, '$D') || endsWith($sku, '$W'))
                    $kitSku = substr($sku, 0, strlen($sku) - 2);
                else $kitSku = $sku;

                $kitParts = GetKitParts($kitSku);

                foreach ($kitParts as $compSku => $compQty)
                {
                    $existingQty = 0;
                    if (array_key_exists($compSku, $parts))
                        $existingQty = $parts[$compSku];

                    $parts[$compSku] = $existingQty + ($compQty * $qty);
                }

                if (count($kitParts) > 0)
                    continue;
            }
				
			$sku = str_replace('/', '.', strtoupper($sku));
			
			$components = explode('$', $sku);
			
			foreach ($components as $component)
			{
                if ($component == 'D' || $component == 'W')
                    continue;

				$totalQty = 0;

				// ignore dash for pu and wp
				if (strpos($component, 'PU') === 0
                    || strpos($component, 'WP') === 0
                    || strpos($component, 'TR') === 0)
					$pair = [$component];
				else
					$pair = explode('-', $component);
				
				if (count($pair) == 2)
				{
					$sku = $pair[0];
					if (is_numeric($pair[1]) && $pair[1] > 0)
						$totalQty = $qty * $pair[1];
					else
						$totalQty = $qty;
				}
				else
				{
					$sku = $component;
					$totalQty = $qty;
				}

                if (strpos($sku, '.') === false)
                {
                    if (startsWith($sku, 'EOC'))
                        $sku = substr($sku, 3);
                }
                else
                {
                    if (startsWith($sku, 'EOCS'))
                        $sku = substr($sku, 4);
                }

				$existingQty = 0;
				if (array_key_exists($sku, $parts))
					$existingQty = $parts[$sku];
					
				$parts[$sku] = $existingQty + $totalQty;
			}
		}
	}

	return $parts;
}

function SendSystemEmail($subject, $body, $useHtml=true)
{
	/*
	$sender = SYSTEM_EMAIL_FROM;
	$recipient = SYSTEM_EMAIL_TO;

	$headers = array('From' => $sender, 'Subject' => $subject);

	if ($useHtml)
	{
		$mime = new Mail_mime("\n");
		$mime->setHTMLBody($body);
		$body = $mime->get();
		$headers = $mime->headers($headers);
	}

	$smtpinfo["host"] = SYSTEM_EMAIL_HOST;
	$smtpinfo["port"] = SYSTEM_EMAIL_PORT;
	$smtpinfo["auth"] = true;
	$smtpinfo["username"] = SYSTEM_EMAIL_USERNAME;
	$smtpinfo["password"] = SYSTEM_EMAIL_PASSWORD;
	
	$mail = @Mail::factory("smtp", $smtpinfo);
	@$mail->send($recipient, $headers, $body);
	*/
}

function SendAdminEmail($subject, $body, $useHtml=true)
{
	/*
	$sender = SYSTEM_EMAIL_FROM;
	$recipient = ADMIN_EMAIL_TO;

	$headers = array('From' => $sender, 'Subject' => $subject);

	if ($useHtml)
	{
		$mime = new Mail_mime("\n");
		$mime->setHTMLBody($body);
		$body = $mime->get();
		$headers = $mime->headers($headers);
	}

	$smtpinfo["host"] = SYSTEM_EMAIL_HOST;
	$smtpinfo["port"] = SYSTEM_EMAIL_PORT;
	$smtpinfo["auth"] = true;
	$smtpinfo["username"] = SYSTEM_EMAIL_USERNAME;
	$smtpinfo["password"] = SYSTEM_EMAIL_PASSWORD;
	
	$mail = @Mail::factory("smtp", $smtpinfo);
	@$mail->send($recipient, $headers, $body);
	*/
}

function SendTrackingEmail($to, $subject, $body, $useHtml=true)
{
	/*
	$sender = TRACKING_EMAIL_FROM;

	$headers = array('From' => $sender, 'Subject' => $subject, 'To' => $to);

	if ($useHtml)
	{
		$mime = new Mail_mime("\n");
		$mime->setHTMLBody($body);
		$body = $mime->get();
		$headers = $mime->headers($headers);
	}

	$smtpinfo["host"] = TRACKING_EMAIL_HOST;
	$smtpinfo["port"] = TRACKING_EMAIL_PORT;
	$smtpinfo["auth"] = true;
	$smtpinfo["username"] = TRACKING_EMAIL_USERNAME;
	$smtpinfo["password"] = TRACKING_EMAIL_PASSWORD;
	
	$mail = @Mail::factory("smtp", $smtpinfo);
	@$mail->send($to, $headers, $body);
	*/
}

function XMLtoArray($XML)
{
    $xml_array = null;
    $xml_parser = xml_parser_create();
    xml_parse_into_struct($xml_parser, $XML, $vals);
    xml_parser_free($xml_parser);
    // wyznaczamy tablice z powtarzajacymi sie tagami na tym samym poziomie
    $_tmp='';
    foreach ($vals as $xml_elem) {
        $x_tag=$xml_elem['tag'];
        $x_level=$xml_elem['level'];
        $x_type=$xml_elem['type'];
        if ($x_level!=1 && $x_type == 'close') {
            if (isset($multi_key[$x_tag][$x_level]))
                $multi_key[$x_tag][$x_level]=1;
            else
                $multi_key[$x_tag][$x_level]=0;
        }
        if ($x_level!=1 && $x_type == 'complete') {
            if ($_tmp==$x_tag)
                $multi_key[$x_tag][$x_level]=1;
            $_tmp=$x_tag;
        }
    }
    // jedziemy po tablicy
    foreach ($vals as $xml_elem) {
        $x_tag=$xml_elem['tag'];
        $x_level=$xml_elem['level'];
        $x_type=$xml_elem['type'];
        if ($x_type == 'open')
            $level[$x_level] = $x_tag;
        $start_level = 1;
        $php_stmt = '$xml_array';
        if ($x_type=='close' && $x_level!=1)
            $multi_key[$x_tag][$x_level]++;
        while ($start_level < $x_level) {
            $php_stmt .= '[$level['.$start_level.']]';
            if (isset($multi_key[$level[$start_level]][$start_level]) && $multi_key[$level[$start_level]][$start_level])
                $php_stmt .= '['.($multi_key[$level[$start_level]][$start_level]-1).']';
            $start_level++;
        }
        $add='';
        if (isset($multi_key[$x_tag][$x_level]) && $multi_key[$x_tag][$x_level] && ($x_type=='open' || $x_type=='complete')) {
            if (!isset($multi_key2[$x_tag][$x_level]))
                $multi_key2[$x_tag][$x_level]=0;
            else
                $multi_key2[$x_tag][$x_level]++;
            $add='['.$multi_key2[$x_tag][$x_level].']';
        }
        if (isset($xml_elem['value']) && trim($xml_elem['value'])!='' && !array_key_exists('attributes', $xml_elem)) {
            if ($x_type == 'open')
                $php_stmt_main=$php_stmt.'[$x_type]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
            else
                $php_stmt_main=$php_stmt.'[$x_tag]'.$add.' = $xml_elem[\'value\'];';
            eval($php_stmt_main);
        }
        if (array_key_exists('attributes', $xml_elem)) {
            if (isset($xml_elem['value'])) {
                $php_stmt_main=$php_stmt.'[$x_tag]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
                eval($php_stmt_main);
            }
            foreach ($xml_elem['attributes'] as $key=>$value) {
                $php_stmt_att=$php_stmt.'[$x_tag]'.$add.'[$key] = $value;';
                eval($php_stmt_att);
            }
        }
    }
    return $xml_array;
}

function parseForResponseXML($response)
{
	$beginResponseXML = strpos($response, '<?xml');
	$endResponseXML = strpos($response, '</downloadFileResponse>', $beginResponseXML);

	if($endResponseXML === FALSE)
	{
		$errorXML = parseForErrorMessage($response);
		PrintUtils::printXML($errorXML);
		die();
	}

	$endResponseXML += strlen('</downloadFileResponse>');

	return substr($response, $beginResponseXML,	$endResponseXML - $beginResponseXML);
}

function parseForErrorMessage($response)
{
	$beginErrorMessage = strpos($response, '<?xml');
	$endErrorMessage = strpos($response, '</errorMessage>', $beginErrorMessage);
	$endErrorMessage += strlen('</errorMessage>');

	return substr($response, $beginErrorMessage, $endErrorMessage - $beginErrorMessage);
}

function parseForFileBytes($uuid, $response)
{
	$contentId = 'Content-ID: <' . $uuid . '>';
	$mimeBoundaryPart = strpos($response,'--MIMEBoundaryurn_uuid_');
	$beginFile = strpos($response, $contentId, $mimeBoundaryPart);
	$beginFile += strlen($contentId);
	$beginFile += 4;
	$endFile = strpos($response,'--MIMEBoundaryurn_uuid_',$beginFile);
	$endFile -= 2;
	$fileBytes = substr($response, $beginFile, $endFile - $beginFile);
	return $fileBytes;
}

function parseForXopIncludeUUID($responseDOM)
{
	$xopInclude = $responseDOM->getElementsByTagName('Include')->item(0);
	$uuid = $xopInclude->getAttributeNode('href')->nodeValue;
	$uuid = substr($uuid, strpos($uuid,'urn:uuid:'));
	return $uuid;
}

function writeZipFile($bytes, $zipFilename)
{
	$handler = fopen($zipFilename, 'wb');
	fwrite($handler, $bytes);
	fclose($handler);
}

function cleanup($data, $write=true)
{
	if (isset($data))
	{
        if (get_magic_quotes_gpc())
            $data = stripslashes($data);
        if ($write)
            $data = mysql_real_escape_string($data);
    }
    return $data;
}

function standardize_shipping($speed)
{
    $map =
    [
        'Expedited' => 'Expedited / Express',
        'ShippingMethodExpress' => 'Expedited / Express',
        'UPS3rdDay'=> 'Expedited / Express',
        'USPSFirstClassMailInternational' => 'International',
        'InternationalPriorityShipping' => 'International',
        'USPSPriorityMailInternational' => 'International',
        'Shipping Option - USPS First-Class Mail International' => 'International',
        'Pickup' => 'Local Pick Up',
        'NextDay' => 'Next Day / Overnight',
        'NXTDAYSAVR' => 'Next Day / Overnight',
        'UPSNextDay' => 'Next Day / Overnight',
        'FedExStandardOvernight' => 'Next Day / Overnight',
        'ShippingMethodOvernight' => 'Next Day / Overnight',
        'Shipping Option - Next Day' => 'Next Day / Overnight',
        'SecondDay' => 'Second Day',
        '2ND DAYAIR' => 'Second Day',
        'UPS2ndDay' => 'Second Day',
        'Shipping Option - 2nd Day' => 'Second Day',
        'FedEx2Day'=>'Second Day',
        'UPSGround' => 'Standard / Ground',
        'Standard' => 'Standard / Ground',
        'GROUND' => 'Standard / Ground',
        'ShippingMethodStandard' => 'Standard / Ground',
        'Free Shipping - Free' => 'Standard / Ground',
        'Other' => 'Standard / Ground',
        'FreeEconomy' => 'Standard / Ground',
        'Shipping Option - Ground' => 'Standard / Ground',
        'Shipping Option - Free Shipping' => 'Standard / Ground',
        'USPSFirstClass' => 'Standard / Ground',       
    ];

    if (array_key_exists($speed, $map))
        return $map[$speed];

    if (stripos($speed, 'pickup') !== false)
        return $map['Pickup'];
}

function convert_state($name, $to='name')
{
	$states = array(
	array('name'=>'Alabama', 'abbrev'=>'AL'),
	array('name'=>'Alaska', 'abbrev'=>'AK'),
	array('name'=>'Arizona', 'abbrev'=>'AZ'),
	array('name'=>'Arkansas', 'abbrev'=>'AR'),
	array('name'=>'California', 'abbrev'=>'CA'),
	array('name'=>'Colorado', 'abbrev'=>'CO'),
	array('name'=>'Connecticut', 'abbrev'=>'CT'),
	array('name'=>'Delaware', 'abbrev'=>'DE'),
	array('name'=>'DC', 'abbrev'=>'DC'),
	array('name'=>'Florida', 'abbrev'=>'FL'),
	array('name'=>'Georgia', 'abbrev'=>'GA'),
	array('name'=>'Hawaii', 'abbrev'=>'HI'),
	array('name'=>'Idaho', 'abbrev'=>'ID'),
	array('name'=>'Illinois', 'abbrev'=>'IL'),
	array('name'=>'Indiana', 'abbrev'=>'IN'),
	array('name'=>'Iowa', 'abbrev'=>'IA'),
	array('name'=>'Kansas', 'abbrev'=>'KS'),
	array('name'=>'Kentucky', 'abbrev'=>'KY'),
	array('name'=>'Louisiana', 'abbrev'=>'LA'),
	array('name'=>'Maine', 'abbrev'=>'ME'),
	array('name'=>'Maryland', 'abbrev'=>'MD'),
	array('name'=>'Massachusetts', 'abbrev'=>'MA'),
	array('name'=>'Michigan', 'abbrev'=>'MI'),
	array('name'=>'Minnesota', 'abbrev'=>'MN'),
	array('name'=>'Mississippi', 'abbrev'=>'MS'),
	array('name'=>'Missouri', 'abbrev'=>'MO'),
	array('name'=>'Montana', 'abbrev'=>'MT'),
	array('name'=>'Nebraska', 'abbrev'=>'NE'),
	array('name'=>'Nevada', 'abbrev'=>'NV'),
	array('name'=>'New Hampshire', 'abbrev'=>'NH'),
	array('name'=>'New Jersey', 'abbrev'=>'NJ'),
	array('name'=>'New Mexico', 'abbrev'=>'NM'),
	array('name'=>'New York', 'abbrev'=>'NY'),
	array('name'=>'North Carolina', 'abbrev'=>'NC'),
	array('name'=>'North Dakota', 'abbrev'=>'ND'),
	array('name'=>'Ohio', 'abbrev'=>'OH'),
	array('name'=>'Oklahoma', 'abbrev'=>'OK'),
	array('name'=>'Oregon', 'abbrev'=>'OR'),
	array('name'=>'Pennsylvania', 'abbrev'=>'PA'),
	array('name'=>'Rhode Island', 'abbrev'=>'RI'),
	array('name'=>'South Carolina', 'abbrev'=>'SC'),
	array('name'=>'South Dakota', 'abbrev'=>'SD'),
	array('name'=>'Tennessee', 'abbrev'=>'TN'),
	array('name'=>'Texas', 'abbrev'=>'TX'),
	array('name'=>'Utah', 'abbrev'=>'UT'),
	array('name'=>'Vermont', 'abbrev'=>'VT'),
	array('name'=>'Virginia', 'abbrev'=>'VA'),
	array('name'=>'Washington', 'abbrev'=>'WA'),
	array('name'=>'West Virginia', 'abbrev'=>'WV'),
	array('name'=>'Wisconsin', 'abbrev'=>'WI'),
	array('name'=>'Wyoming', 'abbrev'=>'WY')
	);

	$return = strtoupper($name);

	foreach ($states as $state)
	{
		if ($to == 'name')
		{
			if (strtolower($state['abbrev']) == strtolower($name))
			{
				$return = $state['name'];
				break;
			}
			else if (strtolower($state['name']) == strtolower($name))
			{
				$return = $state['name'];
				break;
			}
		}
		else if ($to == 'abbrev')
		{
			if (strtolower($state['name']) == strtolower($name))
			{
				$return = strtoupper($state['abbrev']);
				break;
			}
			else if (strtoupper($state['abbrev']) == strtoupper($name))
			{
				$return = strtoupper($state['abbrev']);
				break;
			}
		}
	}
	
	return $return;
}

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function alast($array)
{
	if (!is_array($array))
		return $array;
	
	return alast(reset($array));
}

function asearch($array, $key, $avoid = '', $last = true)
{
	$res = search_nested_arrays($array, $key, $avoid);
	
	if ($last)
		return alast($res);
	else
		return reset($res);
}

function search_name_value($line, $name)
{
	$nvs = search_nested_arrays($line, 'OA:NAMEVALUE', 'AAIA:SUBLINE');
	
	if (empty($nvs))
		return null;
		
	if (array_key_exists('NAME', $nvs))
		$nvs = array($nvs);

	foreach ($nvs as $key => $pair)
	{
		if (!array_key_exists('NAME', $pair))
			continue;

		if ($pair['NAME'] == $name)
			if (array_key_exists('content', $pair))
				return $pair['content'];
	}
}

function search_nested_arrays($array, $key, $avoid = '')
{
    if (is_object($array))
        $array = (array)$array;
		
	if (!is_array($array))
		return null;
    
    $result = array();
    foreach ($array as $k => $value)
	{ 
        if (is_array($value) || is_object($value))
		{
			if (is_array($avoid) && !empty($avoid))
			{
				$skip = false;

				foreach ($avoid as $a)
				{
					if (!empty($a) && $k === $a)
					{
						$skip = true;
						break;
					}
				}
				
				if ($skip)
					continue;
			}
			else
			{
				if (!empty($avoid) && $k === $avoid)
					continue;
			}
            $r = search_nested_arrays($value, $key, $avoid);
            if (!is_null($r))
				array_push($result,$r);
        }
    }
    
    if (array_key_exists($key, $array))
        array_push($result,$array[$key]);
    
    if (count($result) > 0)
	{
        $result_plain = array();
        foreach ($result as $k => $value)
		{ 
            if(is_array($value))
                $result_plain = array_merge($result_plain,$value);
            else
                array_push($result_plain,$value);
        }
        return $result_plain;
    }
    return NULL;
}

function TranslateSKU($sku)
{
    mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
    mysql_select_db(DB_SCHEMA);

    $res = mysql_query(query("SELECT new_sku FROM sku_translation WHERE orig_sku = '%s'", $sku));
    $row = mysql_fetch_row($res);

    if (!empty($row) && !empty($row[0]))
        return $row[0];

    return $sku;
}

?>
