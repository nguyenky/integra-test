<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');
require_once(__DIR__ . '/../system/ssf_utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$rows = mysql_query("
	SELECT id
	FROM sales
	WHERE status = 0
	AND fulfilment = 0
	AND store NOT IN ('eBay', 'Amazon', 'Manual')
	AND order_date > DATE_SUB(NOW(), INTERVAL 3 DAY)");

while ($row = mysql_fetch_row($rows))
	$ids[] = $row[0];
	
if (empty($ids))
	return;

foreach ($ids as $id)
{
	$supplierId = CheckOrderSuppliers($id);
	mysql_query("UPDATE sales SET supplier = '${supplierId}' WHERE id = ${id}");
	
	$result = GetTotalWeight($id);
	$weight = $result['weight'];
	if (!empty($weight))
		mysql_query("UPDATE sales SET weight = '${weight}' WHERE id = ${id}");

	$row = mysql_fetch_row(mysql_query("SELECT SUM(total) FROM sales_items WHERE sales_id = ${id}"));
	$itemTotal = $row[0];

    $row = mysql_fetch_row(mysql_query("SELECT country FROM sales WHERE id = ${id}"));
    $country = $row[0];

    $res = mysql_query("SELECT COUNT(*) FROM eoc.sales WHERE speed = 'Local Pick Up' AND id = {$id}");
    $row = mysql_fetch_row($res);

    if (!empty($row) && !empty($row[0]))
        $pickup = true;
    else $pickup = false;
	
	if ($supplierId == 1)
	{
		$parts = GetOrderComponents($id);
		$items = ImcUtils::QueryItems(array_keys($parts));
		$total = 0;
		
		foreach ($items as $item)
		{
			if (empty($item['price']))
			{
				$total = 0;
				break;
			}
			else $total += ($item['price'] * $parts[$item['sku']]);
		}

        /*
        echo "id: $id \n";
        echo "total: $total \n";

        echo "IMC parts:\n";
        print_r($parts);
        echo "IMC items:\n";
        print_r($items);
		*/

		mysql_query("UPDATE sales SET supplier_cost = '${total}' WHERE id = ${id}");

		if ($itemTotal < $total && $total > 0)
		{
			mysql_query("UPDATE sales SET status = 99 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
            if (mysql_affected_rows() > 0)
                mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 0, 1)", $id,
                    'Selling price is below item cost!'));
		}
        else if ($pickup || $country != 'US')
        {
            mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
        }
		else if ($total >= IMC_AUTODIRECT)
		{
			mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
		}
		else if ($total < IMC_AUTOEOC && $total > 0)
		{
			mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
		}
        else if ($total >= IMC_AUTOEOC && $total < IMC_AUTODIRECT) // gray area
        {
            $fillerCost = IMC_AUTODIRECT - $total;

            if (!empty($weight))
            {
                $res = mysql_query(sprintf(<<<EOQ
SELECT MAX(rate)
FROM e_shipping_rate
WHERE weight_from <= %1\$s
AND weight_to >= %1\$s
EOQ
                    , $weight));
                $row = mysql_fetch_row($res);
                $stampsCost = $row[0];

                if ($fillerCost > $stampsCost) // cheaper to ship via EOC
                    mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
                else // cheaper to dropship with filler
                    mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
            }
            else // unknown weight. default to dropship.
                mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
        }
	}
	else if ($supplierId == 2)
	{
		$parts = GetOrderComponents($id);
		$items = SsfUtils::QueryItems(array_keys($parts));
		$total = 0;
		
		foreach ($items as $item)
		{
			if (empty($item['price']))
			{
				$total = 0;
				break;
			}
			else $total += ($item['price'] * $parts[$item['sku']]);
		}

        /*
        echo "id: $id \n";
        echo "total: $total \n";

        echo "SSF parts:\n";
        print_r($parts);
        echo "SSF items:\n";
        print_r($items);
        */
		
		mysql_query("UPDATE sales SET supplier_cost = '${total}' WHERE id = ${id}");
		
		if ($itemTotal < $total && $total > 0)
		{
			mysql_query("UPDATE sales SET status = 99 WHERE id = ${id}");
            if (mysql_affected_rows() > 0)
                mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 0, 1)", $id,
                    'Selling price is below item cost'));
		}
        else if ($pickup || $country != 'US')
        {
            mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
        }
		else if ($total >= SSF_AUTODIRECT)
		{
			mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
		}
		else if ($total < SSF_AUTOEOC && $total > 0)
		{
			mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
		}
        /*else if ($total >= SSF_AUTOEOC && $total < SSF_AUTODIRECT) // gray area
        {
            $fillerCost = SSF_AUTODIRECT - $total;

            if (!empty($weight))
            {
                $res = mysql_query(sprintf(<<<EOQ
SELECT MAX(rate)
FROM e_shipping_rate
WHERE weight_from <= %1\$s
AND weight_to >= %1\$s
EOQ
                    , $weight));
                $row = mysql_fetch_row($res);
                $stampsCost = $row[0];

                if ($fillerCost > $stampsCost) // cheaper to ship via EOC
                    mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
                else // cheaper to dropship with filler
                    mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
            }
            else // unknown weight. default to dropship.
                mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
        }*/
	}
    else if ($supplierId == 5)
    {
        $parts = GetOrderComponents($id);
        $partial = false;
        $total = 0;
        $fromImc = true;

        foreach ($parts as $sku => $qty)
        {
            if (strpos($sku, '.'))
            {
                $i = SsfUtils::QueryItems([$sku]);
                $fromImc = false;
            }
            else $i = ImcUtils::QueryItems([$sku]);

            if (!empty($i) && count($i) > 0 && !empty($i[0]['price']))
                $total += ($i[0]['price'] * $qty);

            $eocStock = GetEOCStock($sku);
            if ($eocStock < $qty)
            {
                $partial = true;
                break;
            }
        }

        if ($partial)
        {
            mysql_query("UPDATE sales SET supplier = -2, status = 99 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
            if (mysql_affected_rows() > 0)
                mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $id, 'Multiple warehouse order'));
        }
        else
        {
            mysql_query("UPDATE sales SET supplier_cost = '${total}' WHERE id = ${id}");

            if ($itemTotal < $total && $total > 0)
            {
                mysql_query("UPDATE sales SET status = 99 WHERE id = ${id}");
                if (mysql_affected_rows() > 0)
                    mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 0, 1)", $id,
                        'Selling price is below item cost!'));
            }
            else if ($pickup || $country != 'US')
            {
                mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
            }
            else if ($fromImc && $total >= IMC_AUTODIRECT) // dropship from IMC
            {
                mysql_query("UPDATE sales SET fulfilment = 1, status = 1, supplier = 1 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
            }
            else if (!$fromImc && $total >= SSF_AUTODIRECT) // dropship from SSF
            {
                mysql_query("UPDATE sales SET fulfilment = 1, status = 1, supplier = 2 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
            }
            else
            {
                mysql_query("UPDATE sales SET supplier = 5, status = 1, fulfilment = 3, site_id = 3 WHERE id = ${id} AND status = 0 AND fulfilment = 0");
                if (mysql_affected_rows() > 0)
                    mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 0)", $id,
                        'On stock in EOC WH'));
            }
        }
    }
}

mysql_close();

?>