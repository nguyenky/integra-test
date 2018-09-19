<?php

require_once('../system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');

if (empty($_REQUEST['id_mode']))
{
	$_REQUEST['id_mode'] = "add";
    $_REQUEST['id_rid'] = "-1";
}

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();
$user = $_SESSION['user'];
if (empty($user)) exit;

ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'id_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = 'SELECT id, user, body, created FROM ideas';
$default_order = array('id' => 'DESC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, $default_order);
$layouts = array('view'=>'0', 'edit'=>'1', 'details'=>'0', 'filter'=>'2');
$dgrid->SetLayouts($layouts);
$modes = array
(
	'add'	  =>array('view'=>true, 'edit'=>false, 'type'=>'link',  'show_button'=>true, 'show_add_button'=>'inside'),
	'edit'	  =>array('view'=>true, 'edit'=>true,  'type'=>'link',  'show_button'=>true, 'byFieldValue'=>''),
	'details' =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true),
	'delete'  =>array('view'=>false, 'edit'=>true,  'type'=>'link', 'show_button'=>true)
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
$vm_table_properties = array('width'=>'97%');
$dgrid->SetViewModeTableProperties($vm_table_properties);  

$dSet = $dgrid->ExecuteSQL('SELECT email, first_name, last_name FROM integra_users ORDER BY first_name, last_name');
while ($row = $dSet->fetch())
	$emails[$row[0]] = $row[1] . ' ' . $row[2];

$vm_columns = array
(
	'user'=>array('header'=>'Submitted By', 'type'=>'enum', 'align'=>'left', 'wrap'=>'wrap', 'source'=>$emails),
	'body'=>array('header'=>'Idea', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
	'created'=>array('header'=>'Date', 'type'=>'label', 'align'=>'right', 'wrap'=>'wrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'97%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'ideas';
$primary_key = 'id';
$condition   = 'ideas.id = ' . $_REQUEST['id_rid'] . " AND ideas.user = '" . $user . "'";
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'body'  =>array('header'=>'Idea', 'type'=>'textarea', 'req_type'=>'stl', 'width'=>'97%', 'edit_type'=>'simple', 'title'=>'How can we improve Integra?', 'resizable'=>'both', 'upload_images'=>'false', 'rows'=>'7', 'cols'=>'50'),
	'user' =>array('header'=>'', 'type'=>'hidden', 'req_type'=>'st', 'default'=>$user, 'visible'=>'false'),
);
$dgrid->SetColumnsInEditMode($em_columns);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Ideas</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2 { font-family: tahoma, verdana; }
		
	#id__contentTable_bottom
	{
		border-top-width: 0;
	}
	
	#id__contentTable_bottom a[title="Cancel"], table.tblToolBar
	{
		display: none;
	}
	
	#id__contentTable_bottom img
	{
		display: none;
	}
	
	#id__contentTable_bottom div
	{
		float: none !important;
		text-align: center;
	}
	
	#id_frmEditRow br
	{
		display: none;
	}
	
	#id__contentTable br
	{
		display: block !important;
	}
</style>
</head>
<body style="display:none">
<center>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
<script src="../js/jquery.min.js"></script>
<script>
$(document).ready(function()
{
	$('.tblToolBar').remove();
	
	if ($('b:contains("Field Value")').length > 0)
	{
		$('#id__contentTable').css('border-bottom-width', '0');
		$('#id__contentTable tr').first().remove();
		$('#id__contentTable td').first().hide();
		$('textarea').parent().html('How can we improve Integra?<br/>' + $('textarea').parent().html());
		$('#id__contentTable_bottom a:contains("Create")').text('Submit');
		$('#id__contentTable_bottom a:contains("Submit")').attr('title', 'Submit');
		$('td:contains("No data found")').show().text('You can only edit your own entries.');
		var delHtml = $('a[title="Delete record"]').parent().html();
		$('a[title="Delete record"]').remove();
		$('a[title="Update record"]').parent().html(delHtml + ' | ' + $('a[title="Update record"]').parent().html());
	}

	$("span:contains('The adding operation completed successfully')").text('Thank you for sharing your idea!');
	$("span:contains('The deleting operation completed successfully')").text('Your entry has been deleted!');
	$("span:contains('The updating operation completed successfully')").text('Your entry has been updated!');
	$('body').show();
});
</script>
</body>
</html>