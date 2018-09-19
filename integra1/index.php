<?php

require_once('system/acl.php');

$user = Login();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Integra :: Dashboard</title>
	<link rel="stylesheet" type="text/css" href="css/dashboard.css" />
	<link rel="stylesheet" type="text/css" href="css/jquery-ui.css"/>
</head>
<body>
	<?php include_once("analytics.php") ?>
	<img class="banner" src="img/dashboard_header.png" />
	
<?php
	for ($i = 1; $i <= 2; $i++)
	{
		echo '<div class="column" id="column' . $i . '" >';
		$rows = mysql_query("SELECT ud.id, ud.collapsed, ud.height, dw.title, dw.url FROM user_dash ud INNER JOIN dash_widgets dw ON ud.widget = dw.widget WHERE ud.column_id='${i}' AND ud.email = '${user}' ORDER BY ud.sort_no");
		
		while ($widget = mysql_fetch_array($rows))
		{
			echo '<div class="dragbox" style="height:' . (intval($widget['height']) + 29) . 'px" id="item'.$widget['id'].'">';
			echo '<h2>' . $widget['title'] . '</h2>';
			echo '<div class="dragbox-content" style="height:' . $widget['height'] . 'px">';
			echo '<iframe class="widgetframe" src="' . $widget['url'] . '"></iframe></div></div>';
		}
		
		echo '</div>';
	}
?>
	<div style="clear:both"></div>
	<br/>
	<br/>
	<br/>
	<script src="js/jquery.min.js"></script>
	<script src="js/jquery.json-2.4.min.js"></script>
	<script src="js/jquery-ui.min.js"></script>
	<script src="js/dashboard.js"></script>
</body>
</html>