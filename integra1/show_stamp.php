<?php

require_once('system/config.php');
require_once('system/acl.php');

$user = Login('shipgrid');
$user = strtolower($user);

$id = $_REQUEST['id'];

if (empty($id))
{
	header('Location: barcode.php');
	return;
}

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$material = @$_REQUEST['material'];
if (strlen($material) > 0)
{
    mysql_query(sprintf("UPDATE stamps SET material = '%s', print_date = NOW(), email = '%s' WHERE id = '%s'", mysql_real_escape_string($material), mysql_real_escape_string($user), mysql_real_escape_string($id)));
    mysql_query(sprintf("UPDATE sales s, stamps st SET s.status = 4 WHERE st.id = '%s' AND st.sales_id = s.id", mysql_real_escape_string($id)));
}

$pounds = @$_REQUEST['pounds'];
if (!empty($pounds))
{
	mysql_query(sprintf("UPDATE stamps SET pounds = '%s' WHERE id = '%s'", mysql_real_escape_string($pounds), mysql_real_escape_string($id)));
}

$ounces = @$_REQUEST['ounces'];
if (!empty($ounces))
{
	mysql_query(sprintf("UPDATE stamps SET ounces = '%s' WHERE id = '%s'", mysql_real_escape_string($ounces), mysql_real_escape_string($id)));
}

mysql_close();

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Integra :: Order Shipment Tool</title>
	<style>
		#stamp
		{
			width: 4in;
		}
		@media print
		{
			input
			{
				display:none;
			}
		}
	</style>
  </head>
<body>
	<input type="button" onclick="window.location='barcode.php';" value="Back to Barcode Input" />
	<br/><br/>
<? if (file_exists(STAMPS_DIR . $id . '.jpg')): ?>
	<img id="stamp" src="stamps/<?=$id?>.jpg" />
<? else: ?>
	<script>alert('The stamp was not properly generated.');</script>
<? endif;?>
</body>
</html>