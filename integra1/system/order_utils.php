<?php

require_once('config.php');
require_once('item_utils.php');

class OrderUtils
{
	public static function GetSKUParts($items)
	{
		$parts = array();
		
		if (!empty($items))
		{
			foreach ($items as $sku => $qty)
			{
				if (empty($sku))
					continue;
					
				$sku = str_replace('/', '.', strtoupper($sku));
				
				$components = explode('$', $sku);
				
				foreach ($components as $component)
				{
                    if ($component == 'D' || $component == 'W')
                        continue;

					$totalQty = 0;
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
						
					$existingQty = 0;
					if (array_key_exists($sku, $parts))
						$existingQty = $parts[$sku];
						
					$parts[$sku] = $existingQty + $totalQty;
				}
			}
		}

		return $parts;
	}

	public static function GetOrder($salesId)
	{
		$order = array();

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
		
		$fields = array('store', 'internal_id', 'record_num', 'order_date', 'total', 'buyer_id', 'email', 'buyer_name', 'street', 'city', 'state', 'country', 'zip', 'phone', 'speed', 'tracking_num', 'carrier', 'agent', 'fulfilment', 'status', 'fake_tracking', 'supplier_cost', 'weight', 'supplier', 'intl_street', 'intl_city', 'intl_state', 'intl_country', 'intl_zip', 'related_sales_id', 'related_record_num', 'sold_price');
		$row = mysql_fetch_row(mysql_query(query("SELECT " . implode(',', $fields) . " FROM sales WHERE id = %d", $salesId)));
		
		for ($i = 0; $i < count($fields); $i++)
			$order[$fields[$i]] = $row[$i];
		
		$fields = array('ebay_item_id', 'amazon_asin', 'sku', 'description', 'quantity', 'unit_price', 'total');
		$rows = mysql_query(query("SELECT " . implode(',', $fields) . " FROM sales_items WHERE sales_id = %d", $salesId));
		
		while ($row = mysql_fetch_row($rows))
		{
			for ($i = 0; $i < count($fields); $i++)
				$orderItem[$fields[$i]] = $row[$i];
			
			$idx = stripos('$', $orderItem['sku']);
			
			if ($idx >= 0 && $idx <= (strlen($orderItem['sku']) - 3))
			{
				$order['has_kit'] = true;
				$orderItem['is_kit'] = true;
			}
			else
				$orderItem['is_kit'] = false;
				
			$order['items'][] = $orderItem;
			
			$order['sku_qty'][$orderItem['sku']] += $orderItem['qty'];
		}
		
		$order['components'] = self::GetSKUParts($order['sku_qty']);
		
		foreach ($order['components'] as $sku => $qty)
		{
			$supplier = ItemUtils::GetSupplier($sku);
			$suppliers[$supplier] = 1;
			
			$component['supplier'] = $supplier;
			
			if ($supplier == 1)
			{
				$row = mysql_fetch_row(mysql_query("SELECT weight, name, brand FROM imc_items WHERE mpn IN (SELECT mpn FROM sku_mpn WHERE sku = '$sku' AND supplier = 1) LIMIT 1"));
				if (empty($row))
				{
					$weightStr = "No W1 weight data in database";
					$weight = 0;
					break;
				}
				
				$component['weight'] = $row[0];
				$component['name'] = $row[1];
				$component['brand'] = $row[2];
				
				$weight += ($row[0] * $qty);
				$imcParts[$mpn] = $qty;
				$names[$mpn] = $row[1];
				$brands[$mpn] = $row[2];
			}
			
			$q=<<<EOQ
			SELECT supplier
			FROM sku_mpn WHERE sku = '${sku}'
			ORDER BY id
			LIMIT 1
EOQ;

			unset($supplier);
			$res = mysql_query($q);
			while ($row = mysql_fetch_row($res))
				$supplier = $row[0];
			
			
		}
		
		$order['suppliers'] = array_keys($suppliers);
		
		if (empty($suppliers))
			$order['prime_supplier'] = 0;	// Unknown / SKU Error
		else if (count($suppliers) > 1)
			$order['prime_supplier'] = -2;	// More than 1 supplier
		else
			$order['prime_supplier'] = $order['suppliers'][0];
				
		return $order;
	}
	
	public static function GetSSFItem($sku)
	{
		$item['mpn'] = '';
		$item['desc'] = '';
		$item['brand'] = '';
		$item['weight'] = 0;
		$item['price'] = 0;
		$item['image'] = '';
		$item['supplier'] = 'SSF';

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
		
		$item['mpn'] = $mpn = startsWith($sku, 'EOCS') ? substr($sku, 4) : $sku;
		$item['image'] = "http://catalog.eocenterprise.com/img/" . str_replace('-', '', $mpn);

		$brandId = '';
		$dotIdx = strpos($sku, '.');
		if ($dotIdx)
		{
			$mpn = substr($sku, 4, $dotIdx-4);
			$brandId = substr($sku, $dotIdx+1);
		}

		if (!empty($brandId))
			$res = mysql_query(query(
				"SELECT name, brand, weight, unit_price FROM ssf_items WHERE mpn = '%s' AND brand_id = '%s'",
				$mpn, $brandId));
		else
			$res = mysql_query(query(
				"SELECT name, brand, weight, unit_price FROM ssf_items WHERE mpn = '%s' LIMIT 1", $mpn));

		$row = mysql_fetch_row($res);
		
		if (!empty($row))
		{
			$item['desc'] = $row[0];
			$item['brand'] = $row[1];
			$item['weight'] = $row[2];
			$item['price'] = $row[3];
			
			if (!empty($item['weight']))
				return $item;
		}

		$temp = SsfUtils::QueryItems(array($mpn));
		if (count($temp) == 1)
		{
			$item['desc'] = $temp[0]['desc'];
			$item['brand'] = $temp[0]['brand'];
			$item['weight'] = $temp[0]['weight'];
			$item['price'] = $temp[0]['price'];
		}

		return $item;
	}
	
	public static function GetIMCItem($sku)
	{
		$item['mpn'] = '';
		$item['desc'] = '';
		$item['brand'] = '';
		$item['weight'] = 0;
		$item['price'] = 0;
		$item['image'] = '';
		$item['supplier'] = 'IMC';

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
		
		$noPrefix = trim(startsWith($sku, 'EOC') ? substr($sku, 3) : $sku);
		$tryMPN[] = $noPrefix;
		
		$res = mysql_query(query("SELECT mpn FROM sku_mpn WHERE sku = '%s'", $sku));
			
		while ($row = mysql_fetch_row($res))
			$tryMPN[] = trim($row[0]);
			
		$res = mysql_query(query("SELECT imc_unspaced, jpn_unspaced FROM imc_lookup WHERE imc_unspaced = '%s' OR jpn_unspaced = '%s'", $noPrefix, $noPrefix));
		
		while ($row = mysql_fetch_row($res))
		{
			$tryMPN[] = trim($row[0]);
			$tryMPN[] = trim($row[1]);
		}
		
		$tryMPN = array_unique($tryMPN);
		
		foreach ($tryMPN as $mpn)
		{
			$row = mysql_fetch_row(mysql_query(query(
				"SELECT name, brand, weight, unit_price FROM imc_items WHERE mpn = '%s' LIMIT 1", $mpn)));

			if (!empty($row))
			{
				$item['mpn'] = $mpn;
				$item['desc'] = $row[0];
				$item['brand'] = $row[1];
				$item['weight'] = $row[2];
				$item['price'] = $row[3];
				$item['image'] = "http://catalog.eocenterprise.com/img/" . str_replace('-', '', $mpn);
				
				if (!empty($item['weight']))
					return $item;
			}

			$temp = ImcUtils::QueryItems(array($mpn));
			if (count($temp) == 1)
			{
				$item['mpn'] = $mpn;
				$item['desc'] = $temp[0]['desc'];
				$item['brand'] = $temp[0]['brand'];
				$item['weight'] = $temp[0]['weight'];
				$item['price'] = $temp[0]['price'];
				$item['image'] = "http://catalog.eocenterprise.com/img/" . str_replace('-', '', $mpn);

				return $item;
			}
		}
	}
	
	public static function GetOrderFromSSFBarcode($barcode, $orderId)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$q=<<<EOQ
			SELECT s.id
			FROM sales_items si, sales s, direct_shipments_sales dss
			WHERE s.id = si.sales_id
			AND si.sku LIKE '%%%s%%'
			AND dss.sales_id = s.id
			AND dss.order_id = '%s'
			ORDER BY status ASC, order_date ASC
			LIMIT 1
EOQ;
		$rows = mysql_fetch_row(mysql_query(query($q,
			str_replace('EOCS', '', str_replace('/', '.', $barcode)), $orderId)));

		if (!empty($rows) && !empty($rows[0]))
			return $rows[0];
		else
			return null;
	}

	public static function GetOrderFromIMCBarcode($barcode, $orderId)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$barcode = preg_replace("/[^A-Z0-9]/", '', strtoupper($barcode));

		$q=<<<EOQ
			SELECT s.id
			FROM direct_shipments_sales dss INNER JOIN sales s ON dss.sales_id = s.id
			WHERE TRIM(LEADING '0' FROM dss.order_id) = TRIM(LEADING '0' FROM '%s')
			ORDER BY status
			ASC, order_date ASC
EOQ;
		$res = mysql_query(query($q, $orderId));
		
		while ($row = mysql_fetch_row($res))
			$salesIds[] = $row[0];
		
		if (empty($salesIds))
			return null;
			
		foreach ($salesIds as $salesId)
		{
			$parts = GetOrderComponents($salesId);
			
			foreach ($parts as $sku => $qty)
			{
				if (startsWith($sku, 'EOCE') || startsWith($sku, 'EOCS') || strpos($sku, '.') > 0)
					continue;
					
				if ($sku == $barcode)
					return $salesId;
				
				$mpn = trim(startsWith($sku, 'EOC') ? substr($sku, 3) : $sku);
				
				if ($mpn == $barcode)
					return $salesId;
					
				$res = mysql_query(query("SELECT mpn FROM sku_mpn WHERE sku = '%s'", $sku));

				while ($row = mysql_fetch_row($res))
					if (trim($row[0]) == $barcode)
						return $salesId;
						
				$q=<<<EOQ
					SELECT imc_unspaced, jpn_unspaced, imc_upc, jpn_upc, custom
					FROM imc_lookup
					WHERE imc_unspaced = '%s'
					OR jpn_unspaced = '%s'
					OR imc_upc = '%s'
					OR jpn_upc = '%s'
					OR custom = '%s'
EOQ;
				$res = mysql_query(query($q, $mpn, $mpn, $mpn, $mpn, $mpn));
		
				while ($row = mysql_fetch_row($res))
					for ($i = 0; $i < 5; $i++)
						if (trim($row[$i]) == $barcode)
							return $salesId;
			}
		}
		
		return null;
	}
}

?>