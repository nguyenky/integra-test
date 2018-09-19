<?php

require_once('system/config.php');
require_once('system/utils.php');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

$state = $_REQUEST['state'];
settype($state, 'integer');

if ($state != 0 && $state != 1)
	return;
	
if (empty($salesId))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

if ($state == 0)
{
	mysql_query("UPDATE sales SET auto_order = 0 WHERE id = ${salesId} AND auto_order = 1 AND fulfilled = 0");
	echo $state;
	return;
}
else
{
	$result = CheckOrderSuppliers($salesId);
	
	if ($result == -2)
	{
		echo 'Auto-processing is not supported for this order because it has items coming from different warehouses.';
		return;
	}
	else if ($result == 0)
	{
		echo 'Auto-processing cannot be turned on for this order because some or all of the SKUs are not mapped to any warehouse. Please check the SKU-MPN mappings.';
		return;
	}
	else if ($result == -1)
	{
		echo 'Auto-processing is not yet supported for this warehouse.';
		return;
	}

	mysql_query("UPDATE sales SET auto_order = 1 WHERE id = ${salesId} AND auto_order = 0 AND fulfilled = 0");
	
	if (mysql_affected_rows() == 1)
	{
		echo $state;
		return;
	}
	else
	{
		echo 'Auto-processing cannot be turned on for this order. The order might have been fulfilled already. Please try refreshing the page.';
		return;
	}
}