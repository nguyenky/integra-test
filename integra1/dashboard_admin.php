<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login();

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'dash_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = 'SELECT id, email, widget FROM user_dash';
$default_order = array('email' => 'ASC', 'widget' => 'ASC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, $default_order);
$layouts = array('view'=>'0', 'edit'=>'0', 'details'=>'0', 'filter'=>'2');
$dgrid->SetLayouts($layouts);
$modes = array
(
	'add'	  =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true, 'show_add_button'=>'inside'),
	'edit'	  =>array('view'=>false, 'edit'=>false,  'type'=>'link',  'show_button'=>true, 'byFieldValue'=>''),
	'details' =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true),
	'delete'  =>array('view'=>true, 'edit'=>true,  'type'=>'image', 'show_button'=>true)
);
$dgrid->SetModes($modes);
$css_class = 'x-blue';
$dgrid->SetCssClass($css_class);
$dgrid->AllowPrinting(false);
$paging_option = false;
$rows_numeration = false;
$numeration_sign = '';
$dropdown_paging = false;
$dgrid->AllowPaging($paging_option, $rows_numeration, $numeration_sign, $dropdown_paging);
$dgrid->SetPagingSettings(null, null, null, 10000);
$vm_table_properties = array('width'=>'50%');
$dgrid->SetViewModeTableProperties($vm_table_properties);

$dSet = $dgrid->ExecuteSQL('SELECT widget, title FROM dash_widgets ORDER BY title');
while ($row = $dSet->fetch())
	$widgets[$row[0]] = $row[1];
	
$dSet = $dgrid->ExecuteSQL('SELECT email, first_name, last_name FROM integra_users ORDER BY first_name, last_name');
while ($row = $dSet->fetch())
	$emails[$row[0]] = $row[1] . ' ' . $row[2];

$vm_columns = array
(
	'email'=>array('header'=>'User', 'type'=>'enum', 'align'=>'left', 'wrap'=>'nowrap', 'source'=>$emails),
	'widget'=>array('header'=>'Widget', 'type'=>'enum', 'align'=>'left', 'wrap'=>'nowrap', 'source'=>$widgets),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$table_name  = 'user_dash';
$primary_key = 'id';
$condition   = 'user_dash.id = ' . $_REQUEST['dash_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);

if ($_REQUEST['add'] == '1')
{
	$widget = $_REQUEST['widget'];
	$email = $_REQUEST['email'];

	if (!empty($widget) && !empty($email))
	{
		$q = "INSERT IGNORE INTO user_dash (widget, email) VALUES ('%s', '%s')";
		$dgrid->ExecuteSQL(sprintf($q, str_replace("'", '', $widget), str_replace("'", '', $email)));
	}
}
else if ($_REQUEST['copy'] == '1')
{
	$from = $_REQUEST['from_user'];
	$to = $_REQUEST['to_user'];

	if (!empty($from) && !empty($to) && ($from != $to))
	{
		$q = "INSERT IGNORE INTO user_dash (email, column_id, sort_no, collapsed, height, widget) (SELECT '%s', column_id, sort_no, collapsed, height, widget FROM user_dash WHERE email = '%s')";
		$dgrid->ExecuteSQL(sprintf($q, str_replace("'", '', $to), str_replace("'", '', $from)));
	}
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Dashboard Admin</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2, h4, p { font-family: tahoma, verdana; }
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Dashboard Admin</h2>
<?php
$dgrid->Bind();
ob_end_flush();
?>
<br/><hr/>
<h4>Add Dashboard Widget</h4>
<form action="dashboard_admin.php?add=1" method="POST">
	<p>
	<select name="widget">
<?php
foreach ($widgets as $widget => $title)
	echo '<option value="' . $widget . '">' . htmlentities($title) . "</option>\n";
?>
	</select> to user
	<select name="email">
<?php
foreach ($emails as $email => $name)
	echo '<option value="' . $email . '">' . htmlentities($name) . "</option>\n";
?>
	</select>
	<input type="submit" value="Add"/>
	</p>
</form>

<h4>Copy Widgets</h4>
<form action="dashboard_admin.php?copy=1" method="POST">
	<p>From user
	<select name="from_user">
<?php
foreach ($emails as $email => $name)
echo '<option value="' . $email . '">' . htmlentities($name) . "</option>\n";
?>
	</select> to
	<select name="to_user">
<?php
foreach ($emails as $email => $name)
echo '<option value="' . $email . '">' . htmlentities($name) . "</option>\n";
?>
	</select>
	<input type="submit" value="Copy"/>
	</p>
</form>
</center>
</body>
</html>