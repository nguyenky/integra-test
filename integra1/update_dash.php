<?php
if(!$_POST["data"]){
	echo "Invalid data";
	exit;
}
include('system/config.php');
mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
$data=json_decode($_POST["data"]);
foreach($data->items as $item)
{
	$col_id=preg_replace('/[^\d\s]/', '', $item->column);
	$widget_id=preg_replace('/[^\d\s]/', '', $item->id);
	$height=preg_replace('/[^\d\s]/', '', $item->height);
	$sql = <<<EOD
UPDATE user_dash
SET column_id = '%d',
	sort_no = '%d',
	collapsed = '%d',
	height = '%d'
WHERE id = '%d'
EOD;
	mysql_query(sprintf($sql, $col_id, $item->order, $item->collapsed, $height, $widget_id)) or die('Error updating widget DB');
}
echo "success";

?>