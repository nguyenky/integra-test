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
$unique_prefix = 'lnk_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = 'SELECT id, email, title, description, url, sort_num FROM links';
$default_order = array('email' => 'ASC', 'sort_num' => 'ASC', 'title' => 'ASC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, $default_order);
$layouts = array('view'=>'0', 'edit'=>'1', 'details'=>'0', 'filter'=>'2');
$dgrid->SetLayouts($layouts);
$modes = array
(
	'add'	  =>array('view'=>true, 'edit'=>false, 'type'=>'link',  'show_button'=>true, 'show_add_button'=>'inside'),
	'edit'	  =>array('view'=>true, 'edit'=>true,  'type'=>'link',  'show_button'=>true, 'byFieldValue'=>''),
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
$vm_table_properties = array('width'=>'70%');
$dgrid->SetViewModeTableProperties($vm_table_properties);

$dSet = $dgrid->ExecuteSQL('SELECT email, first_name, last_name FROM integra_users ORDER BY first_name, last_name');
while ($row = $dSet->fetch())
	$emails[$row[0]] = $row[1] . ' ' . $row[2];

$vm_columns = array
(
	'email'=>array('header'=>'User', 'type'=>'enum', 'align'=>'left', 'wrap'=>'nowrap', 'source'=>$emails),
	'title'=>array('header'=>'Title', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'description'=>array('header'=>'Description', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
	'url'=>array('header'=>'URL', 'type'=>'link', 'align'=>'left', 'wrap'=>'wrap', 'field_key'=>'url', 'field_data'=>'url', 'target'=>'_blank', 'href'=>'{0}'),
	'sort_num'=>array('header'=>'Sort Number', 'type'=>'label', 'align'=>'right', 'wrap'=>'nowrap', 'width'=>'100px'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'links';
$primary_key = 'id';
$condition   = 'links.id = ' . $_REQUEST['lnk_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'email' =>array('header'=>'User', 'type'=>'enum', 'req_type'=>'rt', 'width'=>'250px', 'maxlength'=>'50', 'source'=>$emails, 'view_type'=>'dropdownlist'),
	'title' =>array('header'=>'Title', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'250px', 'maxlength'=>'200'),
	'description'  =>array('header'=>'Description', 'type'=>'textarea', 'req_type'=>'st', 'width'=>'210px', 'edit_type'=>'wysiwyg', 'resizable'=>'both', 'rows'=>'7', 'cols'=>'50', 'upload_images'=>'true', 'maxlength'=>'1000'),
	'url' =>array('header'=>'URL', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'400px', 'maxlength'=>'700'),
	'sort_num'  =>array('header'=>'Sort Number', 'type'=>'textbox', 'req_type'=>'rn', 'width'=>'210px', 'maxlength'=>'5', 'default'=>'100'),
);
$dgrid->SetColumnsInEditMode($em_columns);

if ($_REQUEST['copy'] == '1')
{
	$from = $_REQUEST['from_user'];
	$to = $_REQUEST['to_user'];

	if (!empty($from) && !empty($to) && ($from != $to))
	{
		$q = "INSERT IGNORE INTO links (email, title, description, url, sort_num) (SELECT '%s', title, description, url, sort_num FROM links WHERE email = '%s')";
		$dgrid->ExecuteSQL(sprintf($q, str_replace("'", '', $to), str_replace("'", '', $from)));
	}
}
	
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Links Admin</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2, h4, p { font-family: tahoma, verdana; }
	input[type=submit]:hover { border: 2px inherit !important; }
	#to_user { width: 250px; }
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Links Admin</h2>
<?php
$dgrid->Bind();
ob_end_flush();

if ((!isset($_REQUEST['lnk_mode']) || ($_REQUEST['lnk_mode'] != 'add' && $_REQUEST['lnk_mode'] != 'edit')) && !empty($emails)):
?>
	<br/><hr/>
	<h4>Copy Links</h4>
	<form action="links_admin.php?copy=1" method="POST">
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
<?php endif; ?>

</center>
</body>
</html>