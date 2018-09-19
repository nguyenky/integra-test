<?php

require_once('config.php');
require_once('utils.php');

class EsiUtils
{
	public static $siteIDs = array(
		'SU' => 'Sunrise, FL',
		);

	public static function QueryItems($skus, $markNotFound = false)
	{
        return []; // old ESI site already deprecated

		if (empty($skus))
			return;

		$line = 1;
		$post = '';
		$results = array();
		
		$dt = new DateTime();
		$dt->setTimeZone(new DateTimeZone('America/New_York'));
		$date = urlencode($dt->format('m/d/Y'));

		foreach ($skus as $sku)
		{
			if (empty($sku))
				continue;

			$sku = strtoupper($sku);
						
			if (startsWith($sku, 'EOCE'))
				$sku = substr($sku, 4);
							
			if (empty($sku))
				continue;

			$post .= "part_${line}=${sku}&qty_${line}=1&itemdue_${line}=${date}&";
			$line++;
			$validSkus[] = $sku;
		}
		
		if (empty($validSkus))
			return;
		
		$post .= "QuickEntry=Quick+Entry";
		
		$url = ESI_HOST;
		$username = ESI_USERNAME;
		$password = ESI_PASSWORD;

		$cookie = tempnam("/tmp", "esi");

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_POST, true);

		curl_setopt($ch, CURLOPT_URL, $url . 'index.php');
		curl_setopt($ch, CURLOPT_POSTFIELDS, "CompanyNameField=esi1_bms&UserNameEntryField=${username}&Password=${password}&SubmitUser=Login");
		$output = curl_exec($ch);

		curl_setopt($ch, CURLOPT_URL, $url . 'SelectOrderItems.php?NewOrder=Yes');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$output = curl_exec($ch);
		
		curl_setopt($ch, CURLOPT_URL, $url . 'Logout.php');
		curl_setopt($ch, CURLOPT_POSTFIELDS, '');
		curl_setopt($ch, CURLOPT_POST, false);
		curl_exec($ch);

		curl_close($ch);

		preg_match_all(
		'/StockStatus.+?StockID=(?<sku>[^&]+)&.+?<td>(?<desc>[^<]+)<\/td>.+?<\/td>\s*<td>(?<qty>[^<]+)<\/td>.+?right>(?<price>[^<]+)/is',
		$output, $matches, PREG_SET_ORDER);
		
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
		
		reset(self::$siteIDs);
		$siteID = key(self::$siteIDs);

		foreach ($matches as $match)
		{
			unset($item);
			setlocale(LC_MONETARY, 'en_US');
			$item['sku'] = trim($match['sku']);
			$item['desc'] = trim($match['desc']);
			$item['price'] = money_format('%^!i', trim($match['price']));
			$item["site_${siteID}"] = intval(trim($match['qty']));
			$results[] = $item;
			
			$q=<<<EOQ
			UPDATE esi_items SET name = '%s', unit_price = '%s', qty_avail = '%s', `timestamp` = NOW()
			WHERE mpn = '%s'
EOQ;
			mysql_query(sprintf($q,
				cleanup($item['desc']),
				cleanup($item['price']),
				cleanup($item["site_${siteID}"]),
				cleanup($item['sku'])));
		}
		
		unset($matches);
		
		preg_match_all(
		'/There is only (?<qty>\d+) units of\s+(?<sku>\S+)\s+(?<desc>.+?)\s+available in/is',
		$output, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $match)
		{
			unset($item);
			$item['sku'] = trim($match['sku']);
			$item['desc'] = trim($match['desc']);
			$item['price'] = '?';
			$item["site_${siteID}"] = intval(trim($match['qty']));
			$results[] = $item;
			
			$q=<<<EOQ
			UPDATE esi_items SET name = '%s', qty_avail = '%s', `timestamp` = NOW()
			WHERE mpn = '%s'
EOQ;
			mysql_query(sprintf($q,
				cleanup($item['desc']),
				cleanup($item["site_${siteID}"]),
				cleanup($item['sku'])));
		}
		
		unset($matches);
		
		preg_match_all(
		'/The item\s+(?<sku>\S+)\s+could not be added to the order because it has been flagged as obsolete/is',
		$output, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $match)
		{
			$q=<<<EOQ
			UPDATE esi_items SET obsolete = 1, qty_avail = 0, `timestamp` = NOW()
			WHERE mpn = '%s'
EOQ;
			mysql_query(sprintf($q, cleanup(trim($match['sku']))));
		}
		
		foreach ($validSkus as $vsku)
		{
			$found = false;
			
			foreach ($results as $result)
			{
				if ($result['sku'] == $vsku)
				{
					$found = true;
					break;
				}
			}
			
			if (!$found)
			{
				unset($item);
				$item['sku'] = $vsku;
				$item['desc'] = 'Not found';
				$item["site_${siteID}"] = 0;
				$item['price'] = '?';
				$results[] = $item;
				
				if ($markNotFound)
				{
					$q=<<<EOQ
					UPDATE esi_items SET obsolete = 1, qty_avail = 0, `timestamp` = NOW()
					WHERE mpn = '%s'
EOQ;
					mysql_query(sprintf($q, cleanup($item['sku'])));
				}
			}
		}
		
		unlink($cookie);
		
		return $results;
	}

	public static function OrderItems($skus, $name, $address, $city, $state, $zip, $phone, $recordNum, $shipping)
	{
		$results['success'] = false;
		$results['message'] = '';
		$results['items'] = array();
		
		try
		{
			$line = 1;
			$query = '';
			
			$dt = new DateTime();
			$dt->setTimeZone(new DateTimeZone('America/New_York'));
			$date = urlencode($dt->format('m/d/Y'));

			foreach ($skus as $sku => $qty)
			{
				if (empty($sku))
					continue;
					
				if (empty($qty))
					continue;

				$sku = strtoupper($sku);
							
				if (startsWith($sku, 'EOCE'))
					$sku = substr($sku, 4);
								
				if (empty($sku))
					continue;

				$query .= "part_${line}=${sku}&qty_${line}=${qty}&itemdue_${line}=${date}&";
				$line++;
				$validSkus[$sku] = $qty;
			}
			
			if (empty($validSkus))
			{
				$results['message'] = 'No valid SKUs found.';
				return $results;
			}
			
			$query .= "QuickEntry=Quick+Entry";
			
			$url = ESI_HOST;
			$username = ESI_USERNAME;
			$password = ESI_PASSWORD;

			$cookie = tempnam("/tmp", "esi");

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_POST, true);

			curl_setopt($ch, CURLOPT_URL, $url . 'index.php');
			curl_setopt($ch, CURLOPT_POSTFIELDS, "CompanyNameField=esi1_bms&UserNameEntryField=${username}&Password=${password}&SubmitUser=Login");
			$output = curl_exec($ch);

			curl_setopt($ch, CURLOPT_URL, $url . 'SelectOrderItems.php?NewOrder=Yes');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			$output = curl_exec($ch);
			
			preg_match_all(
			'/StockStatus.+?StockID=(?<sku>[^&]+)&.+?<td>(?<desc>[^<]+)<\/td>.+?<\/td>\s*<td>(?<qty>[^<]+)<\/td>.+?right>(?<price>[^<]+)/is',
			$output, $matches, PREG_SET_ORDER);
			
			$line = 0;
			$okSkus = array();
			$order = '';

			foreach ($matches as $match)
			{
				$sku = trim($match['sku']);
				$price = trim($match['price']);
				
				$order .= "POLine_${line}=&Quantity_${line}=" . $validSkus[$sku] . "&Price_${line}=${price}&ItemDue_${line}=${date}&Narrative_${line}=&";
				$line++;
				$okSkus[] = $sku;
			}
			
			$order .= "DeliveryDetails=Enter+Delivery+Details+and+Confirm+Order";

			$diff = array_diff(array_keys($validSkus), $okSkus);
			
			//print_r(func_get_args());
			if (!empty($diff))
			{
				$results['message'] = 'Some of the items are not available.';
				$results['items'] = $diff;
				return $results;
			}
			
			curl_setopt($ch, CURLOPT_URL, $url . 'SelectOrderItems.php');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $order);
			$output = curl_exec($ch);
			
			$deliver = "DeliverTo=" . urlencode($name) . "&Location=SUN&DeliveryDate=${date}&QuoteDate=${date}&ConfirmedDate=${date}"
			. "&BrAdd1=" . urlencode($address) . "&BrAdd2=" . urlencode($city) . "&BrAdd3=" . urlencode($state)
			. "&BrAdd4=" . urlencode($zip) . "&BrAdd5=&BrAdd6=&PhoneNo=" . urlencode($phone)
			. "&Email=" . urlencode(ESI_TRACKING_EMAIL) . "&CustRef=" . urlencode($recordNum)
			. "&Comments=&DeliverBlind=2&ReprintPackingSlip=0&FreightCost=0&ShipVia=${shipping}&Quotation=0&ProcessOrder=Place+Order";
			
			curl_setopt($ch, CURLOPT_URL, $url . 'DeliveryDetails.php?identifier=');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $deliver);
			$output = curl_exec($ch);
			
			preg_match('/Order Number (?<id>\S+) has been entered/i', $output, $matches);
			
			if (!empty($matches['id']))
			{
				$results['success'] = true;
				$results['message'] = trim($matches['id']);
				$results['items'] = $okSkus;
			}
			else
			{
				$results['message'] = 'There was an error while submitting the order.';
				$results['items'] = array_keys($validSkus);
			}
				
			curl_setopt($ch, CURLOPT_URL, $url . 'Logout.php');
			curl_setopt($ch, CURLOPT_POSTFIELDS, '');
			curl_setopt($ch, CURLOPT_POST, false);
			curl_exec($ch);

			curl_close($ch);
		}
		catch (Exception $e)
		{
			$results['message'] = $e->getMessage();
		}
		
		unlink($cookie);

		return $results;
	}

	public static function PreSelectItems($parts)
	{
		reset(self::$siteIDs);
		$siteID = key(self::$siteIDs);

		$results = array();
		
		$esiParts = TrimSKUPrefix($parts, 'EOCE');

		if (empty($esiParts))
			return $results;

		$esiAvails = self::QueryItems(array_keys($esiParts));
			
		foreach ($esiAvails as $esiAvail)
		{
			$sku = $esiAvail['sku'];
			$results['desc'][$sku] = $esiAvail['desc'];
			$results['price'][$sku] = $esiAvail['price'];
			$results['avail'][$siteID][$sku] = $esiAvail["site_${siteID}"];
			$results['cart'][$siteID][$sku] = min($esiAvail["site_${siteID}"], $esiParts[$sku]);
		}
		
		$results['parts'] = $esiParts;
		
		return $results;
	}
	
	public static function ConvertShipping($speed)
	{
		if ($speed == 'GROUND')
			return ESI_SHIPPING_GROUND;
		else if ($speed == '2ND DAYAIR')
			return ESI_SHIPPING_2DAY;
		else if ($speed == 'NXTDAYSAVR')
			return ESI_SHIPPING_1DAY;
		else if (stristr('ground', $speed) !== false)
			return ESI_SHIPPING_GROUND;
		else if (stristr($speed, 'standard') !== false)
			return ESI_SHIPPING_GROUND;
		else if (stristr($speed, 'expedited') !== false)
			return ESI_SHIPPING_GROUND;
		else if (stristr($speed, 'second') !== false)
			return ESI_SHIPPING_2DAY;
		else if (stristr($speed, '2nd') !== false)
			return ESI_SHIPPING_2DAY;
		else if (stristr($speed, 'next') !== false)
			return ESI_SHIPPING_1DAY;
		else
			return ESI_SHIPPING_GROUND;
	}
}
?>