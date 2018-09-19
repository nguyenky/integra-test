<?php

require_once('config.php');
require_once('imc_utils.php');
require_once('ssf_utils.php');

class ItemUtils
{
	public static function GetSupplier($sku)
	{
		if (startsWith($sku, "EOCE"))
			return 3;	// ESI
		else if (startsWith($sku, "EOCS") || strpos($sku, '.') > 0)
			return 2;	// SSF
		else
			return 1;	// IMC
	}

	public static function GetESIItem($sku)
	{
		$item['mpn'] = '';
		$item['desc'] = '';
		$item['brand'] = '';
		$item['weight'] = 0;
		$item['price'] = 0;
		$item['image'] = '';
		$item['supplier'] = 'ESI';

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
		
		$item['mpn'] = startsWith(sku, 'EOCE') ? substr($sku, 4) : $sku;

		$row = mysql_fetch_row(mysql_query(query("SELECT name, unit_price FROM esi_items WHERE mpn = '%s' ORDER BY obsolete ASC LIMIT 1", $item['mpn'])));
		
		if (!empty($row))
		{
			$item['desc'] = $row[0];
			$item['price'] = $row[1];
		}
				
		return $item;
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
			$mpn = substr($sku, 0, $dotIdx);
			$brandId = substr($sku, $dotIdx+1);
		}

		if (!empty($brandId))
        {
            $res = mysql_query(query(
                "SELECT name, brand, weight, unit_price FROM ssf_items WHERE mpn = '%s' AND brand_id = '%s'",
                $mpn, $brandId));
        }
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

	public static function GetPUItem($sku, $salesId)
	{
		$item['mpn'] = '';
		$item['desc'] = '';
		$item['brand'] = '';
		$item['weight'] = 0;
		$item['price'] = 0;
		$item['image'] = '';
		$item['supplier'] = 'pu';

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$res = mysql_query(query("SELECT description, unit_price FROM sales_items WHERE sku = '%s' AND sales_id = '%s'", $sku, $salesId));
		$row = mysql_fetch_row($res);

		if ($row)
		{
			$item['desc'] = $row[0];
			$item['price'] = $row[1];
			$item['mpn'] = trim(startsWith($sku, 'EOC') ? substr($sku, 3) : $sku);
			$item['image'] = 'http://catalog.eocenterprise.com/img/' . str_replace('-', '', $item['mpn']);
		}

		return $item;
	}

	public static function GetWPItem($sku, $salesId)
	{
		$item['mpn'] = '';
		$item['desc'] = '';
		$item['brand'] = '';
		$item['weight'] = 0;
		$item['price'] = 0;
		$item['image'] = '';
		$item['supplier'] = 'wps';

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$res = mysql_query(query("SELECT description, unit_price FROM sales_items WHERE sku = '%s' AND sales_id = '%s'", $sku, $salesId));
		$row = mysql_fetch_row($res);

		if ($row)
		{
			$item['desc'] = $row[0];
			$item['price'] = $row[1];
			$item['mpn'] = trim(startsWith($sku, 'EOC') ? substr($sku, 3) : $sku);
			$item['image'] = 'http://catalog.eocenterprise.com/img/' . str_replace('-', '', $item['mpn']);
		}

		return $item;
	}

	public static function GetTRItem($sku, $salesId)
	{
		$item['mpn'] = '';
		$item['desc'] = '';
		$item['brand'] = '';
		$item['weight'] = 0;
		$item['price'] = 0;
		$item['image'] = '';
		$item['supplier'] = 'tr';

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$res = mysql_query(query("SELECT description, unit_price FROM sales_items WHERE sku = '%s' AND sales_id = '%s'", $sku, $salesId));
		$row = mysql_fetch_row($res);

		if ($row)
		{
			$item['desc'] = $row[0];
			$item['price'] = $row[1];
			$item['mpn'] = trim(startsWith($sku, 'EOC') ? substr($sku, 3) : $sku);
			$item['image'] = 'http://catalog.eocenterprise.com/img/' . str_replace('-', '', $item['mpn']);
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

	public static function GetOrderFromBarcode($barcode, $orderId)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$barcode = preg_replace("/[^A-Z0-9.]/", '', strtoupper(str_replace('/', '.', $barcode)));

		$q=<<<EOQ
			SELECT s.id
			FROM direct_shipments_sales dss INNER JOIN sales s ON dss.sales_id = s.id
			WHERE dss.normalized_order_id = '%s'
			ORDER BY status ASC, order_date ASC
EOQ;
		$res = mysql_query(query($q, ltrim(str_replace(' ', '', $orderId), '0')));
		
		while ($row = mysql_fetch_row($res))
			$salesIds[] = $row[0];
		
		if (empty($salesIds))
			return null;
			
		foreach ($salesIds as $salesId)
		{
			$parts = GetOrderComponents($salesId);
			
			foreach ($parts as $sku => $qty)
			{
				if ($sku == $barcode)
					return $salesId;

                if (startsWith($sku, 'EOCE') || startsWith($sku, 'EOCS'))
                    $mpn = substr($sku, 4);
				else if (startsWith($sku, 'WP') || startsWith($sku, 'PU') || startsWith($sku, 'TR'))
					$mpn = str_replace('-', '', substr($sku, 2));
                else
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

    public static function FindOrderItems($orderId, $keyword)
    {
        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_SCHEMA);

        $keyword = trim(strtolower($keyword));

        $q = <<<EOQ
			SELECT s.id, si.sku, si.description
			FROM direct_shipments_sales dss, sales s, sales_items si
			WHERE dss.sales_id = s.id
			AND s.id = si.sales_id
			AND s.status != 4
			AND dss.normalized_order_id = '%s'
			AND (LCASE(si.description) LIKE '%%%s%%' OR LCASE(si.sku) LIKE '%%%s%%')
EOQ;
        $res = mysql_query(query($q, ltrim(str_replace(' ', '', $orderId), '0'), $keyword, $keyword));

        $ret = [];

        while ($row = mysql_fetch_row($res))
        {
            $sku = $row[1];

            // SSF
            if (stripos($sku, '.') || stripos($sku, '/'))
            {
                if (stripos($sku, 'EOCS') === 0)
                    $sku = substr($sku, 4);
            }
            // ESI
            else if (stripos($sku, 'EOCE') === 0)
                $sku = substr($sku, 4);
            // IMC
            else if (stripos($sku, 'EOC') === 0)
                $sku = substr($sku, 3);
			else if (stripos($sku, 'EOC') === 0)
				$sku = substr($sku, 3);

            $ret[] = ['id' => $row[0], 'sku' => str_replace('-', '', $sku), 'desc' => $row[2]];
        }

        return $ret;
    }
}

?>