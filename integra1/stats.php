<?php

require_once('system/config.php');
require_once('system/acl.php');

$user = Login();

// CROSSTAB QUERIES

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(SUM(total), 2) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Total sales value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT( SUM( total ) * DAYOFMONTH( LAST_DAY( MAX( order_date ) ) ) / DAYOFMONTH( MAX( order_date ) ) , 2 ) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Sales forecast (USD)'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(SUM(total), 2) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'eBay'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['eBay sales value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT( SUM( total ) * DAYOFMONTH( LAST_DAY( MAX( order_date ) ) ) / DAYOFMONTH( MAX( order_date ) ) , 2 ) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'eBay'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['eBay sales forecast'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(SUM(total), 2) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'Amazon'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Amazon sales value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT( SUM( total ) * DAYOFMONTH( LAST_DAY( MAX( order_date ) ) ) / DAYOFMONTH( MAX( order_date ) ) , 2 ) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'Amazon'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Amazon sales forecast'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(SUM(total), 2) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'qeautoparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['QE Auto Parts sales value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT( SUM( total ) * DAYOFMONTH( LAST_DAY( MAX( order_date ) ) ) / DAYOFMONTH( MAX( order_date ) ) , 2 ) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'qeautoparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['QE Auto Parts sales forecast'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(SUM(total), 2) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'need4autoparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Need 4 Auto Parts sales value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT( SUM( total ) * DAYOFMONTH( LAST_DAY( MAX( order_date ) ) ) / DAYOFMONTH( MAX( order_date ) ) , 2 ) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'need4autoparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Need 4 Auto Parts sales forecast'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(SUM(total), 2) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'europortparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Euro Port Parts sales value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT( SUM( total ) * DAYOFMONTH( LAST_DAY( MAX( order_date ) ) ) / DAYOFMONTH( MAX( order_date ) ) , 2 ) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'europortparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Euro Port Parts sales forecast'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(SUM(total), 2) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'eocparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['EOC Parts sales value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT( SUM( total ) * DAYOFMONTH( LAST_DAY( MAX( order_date ) ) ) / DAYOFMONTH( MAX( order_date ) ) , 2 ) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'eocparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['EOC Parts sales forecast'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(SUM(total), 2) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'Manual'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Manual sales value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT( SUM( total ) * DAYOFMONTH( LAST_DAY( MAX( order_date ) ) ) / DAYOFMONTH( MAX( order_date ) ) , 2 ) AS SALES
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'Manual'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Manual sales forecast'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Total order count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'eBay'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['eBay order count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'Amazon'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Amazon order count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'qeautoparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['QE Auto Parts order count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'need4autoparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Need 4 Auto Parts order count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'europortparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Euro Port Parts order count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'eocparts'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['EOC Parts order count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND store = 'Manual'
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Manual order count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(DISTINCT buyer_id), 0) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Customer count'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(COUNT(*)/COUNT(DISTINCT buyer_id), 4) AS CNT
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Average orders per customer'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%Y-%m' ) AS MONTH , FORMAT(AVG(total), 2) AS AVE
	FROM sales
	WHERE order_date >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 6 MONTH ) 
	AND total > 0
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$queries['Average order value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// SCALAR QUERIES

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT FORMAT(COUNT(1), 0) AS LISTINGS
	FROM ebay_listings
	WHERE active = 1
EOQ;
$squeries['Active eBay listings'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT FORMAT(COUNT(1), 0) AS LISTINGS
	FROM amazon_listings
	WHERE active = 1
EOQ;
$squeries['Active Amazon listings'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT FORMAT(COUNT(1), 0) AS LISTINGS
	FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_int cpei, magento.eav_attribute a
	WHERE cpe.entity_id = cpei.entity_id
	AND cpei.attribute_id = a.attribute_id
	AND a.entity_type_id = 4
	AND a.attribute_code = 'status'
	AND cpei.value = 1
	AND cpei.store_id = 0
EOQ;
$squeries['Active Magento listings'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT FORMAT(COUNT(DISTINCT buyer_id), 0) AS TOTAL_CUSTOMERS
	FROM sales
	WHERE total > 0
	AND buyer_id > ''
EOQ;
$squeries['Total number of customers'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT FORMAT(COUNT(*), 0) AS TOTAL_ORDERS
	FROM sales
	WHERE total > 0
EOQ;
$squeries['Total number of recorded orders'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT FORMAT(SUM(total), 2) AS TOTAL_VALUE
	FROM sales
EOQ;
$squeries['Total value of recorded orders'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// TABLE QUERIES

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT SKU, DESCRIPTION, QUANTITY
	FROM (
		SELECT sku, description, SUM(quantity) AS QUANTITY
		FROM sales_items
		WHERE sku > ''
		GROUP BY sku, description
		) x
	ORDER BY QUANTITY DESC 
	LIMIT 100
EOQ;
$tqueries['Top 100 Best-Selling SKUs by Quantity'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT SKU, DESCRIPTION, QUANTITY, FORMAT(AVE_PRICE, 2) AS AVG_PRICE, FORMAT(VAL, 2) AS VALUE
	FROM (
		SELECT sku, description, SUM(quantity) AS QUANTITY, AVG(unit_price) AS AVE_PRICE, SUM(total) AS VAL
		FROM sales_items
		WHERE sku > ''
		GROUP BY sku, description
		) x
	ORDER BY VAL DESC 
	LIMIT 10
EOQ;
$tqueries['Top 10 Best-Selling SKUs by Value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT BUYER_ID, QUANTITY, FORMAT(AVE_VALUE, 2) AS AVG_VALUE, FORMAT(TOT_VALUE, 2) AS TOTAL_VALUE
	FROM (
		SELECT BUYER_ID, COUNT(*) AS QUANTITY, AVG(total) AS AVE_VALUE, SUM(total) AS TOT_VALUE
		FROM sales
		WHERE buyer_id > ''
		AND total > 0
		GROUP BY BUYER_ID
		) x
	ORDER BY TOT_VALUE DESC 
	LIMIT 10
EOQ;
$tqueries['Top 10 Customers by Order Value'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT BUYER_ID, QUANTITY
	FROM (
		SELECT BUYER_ID, COUNT(*) AS QUANTITY
		FROM sales
		WHERE buyer_id > ''
		AND total > 0
		GROUP BY BUYER_ID
		) x
	ORDER BY QUANTITY DESC 
	LIMIT 10
EOQ;
$tqueries['Top 10 Customers by Order Quantity'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT STATE, QUANTITY
	FROM (
		SELECT state, COUNT(*) AS QUANTITY
		FROM sales
		WHERE state > ''
		AND total > 0
		GROUP BY state
		) x
	ORDER BY QUANTITY DESC 
	LIMIT 10
EOQ;
$tqueries['Top 10 Ordering States'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$q=<<<EOQ
	SELECT CITY, QUANTITY
	FROM (
		SELECT UPPER(city) AS CITY, COUNT(*) AS QUANTITY
		FROM sales
		WHERE city > ''
		AND total > 0
		GROUP BY 1
		) x
	ORDER BY QUANTITY DESC 
	LIMIT 10
EOQ;
$tqueries['Top 10 Ordering Cities'] = $q;
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

foreach ($queries as $n => $q)
{
	$result = mysql_query($q);
	
	unset($results);

	while ($row = mysql_fetch_row($result))
	{
		$results[$row[0]] = $row[1];
		$months[$row[0]] = 1;
	}

	$allres[$n] = $results;
}

unset($n);
unset($q);

foreach ($squeries as $n => $q)
{
	$row = mysql_fetch_row(mysql_query($q));
	$sallres[$n] = $row[0];
}

unset($n);
unset($q);

foreach ($tqueries as $n => $q)
{
	$result = mysql_query($q);
	
	unset($results);

	while ($row = mysql_fetch_row($result))
	{
		$results[] = $row;
	}

	$tallres[$n] = $results;
	
	unset($fields);
	
	$i = 0;
	while ($i < mysql_num_fields($result))
	{
		$meta = mysql_fetch_field($result, $i);
		$fields[] = $meta->name;
		$i++;
	}

	$tfields[$n] = $fields;
}

mysql_close();

ksort($months);
unset($results);

?>

<html>
<head>
	<title>Statistics</title>
	<link rel="stylesheet" type="text/css" href="css/stats.css">
</head>
<body>
	<?php include_once("analytics.php") ?>
	<br/>
	<center>
		<h1>STATISTICS</h1>
	</center>
	<br/>
	<center>
		<table>

<?php

unset($n);
unset($d);

foreach ($sallres as $n => $d)
	echo "<tr><td>$n</td><td>$d</td></tr>";

?>

		</table>
	</center>
	<br/>
	<center>
		<table>
			<tr>
				<td></td>

<?php

foreach ($months as $m => $u)
	echo "<td>$m</td>";

echo "</tr>";

unset($results);
unset($m);
unset($d);

foreach ($allres as $n => $results)
{
	echo "<tr><td>$n</td>";
	
	foreach ($months as $m => $u)
	{
		echo "<td>";
		$found = 0;
		
		unset($m2);
		unset($d);
		
		if ($results)
		{
			foreach ($results as $m2 => $d)
			{
				if ($m == $m2)
				{
					$found = $d;
					break;
				}
			}
		}
		
		echo "$found</td>";
	}
	
	echo "</tr>";
}

echo "</table></center><br/><br/>";

foreach ($tqueries as $n => $q)
{
	echo "<h2><center>$n</center></h2><br/>";
	
	echo "<center><table>";
	
	echo "<tr>";
	
	unset($field);
	
	foreach ($tfields[$n] as $field)
	{
		echo "<td>" . htmlspecialchars($field) . "</td>";
	}
	
	echo "</tr>";
	
	unset($results);
	$results = $tallres[$n];
	
	foreach ($results as $row)
	{
		echo "<tr>";
		
		foreach ($row as $cell)
		{
			echo "<td>" . htmlspecialchars($cell) . "</td>";
		}
		
		echo "</tr>";
	}

	echo "</table></center><br/><br/>";
}

?>

</body>
</html>
