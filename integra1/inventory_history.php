<?php 
require_once('system/acl.php');

$user = Login();

function formatBytes($bytes, $precision = 2)
{ 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
   
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
   
    $bytes /= pow(1024, $pow); 
   
    return round($bytes, $precision) . ' ' . $units[$pow]; 
} 
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Inventory History</title>
	<style>
		body
		{
			margin: 50px;
			font: 16px arial;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<h1>Inventory History</h1>
<ul>

<?php
	foreach (glob('inventory_history/*.zip') as $file)
	{
		echo "<li><a href='${file}'>" . basename($file) . "</a> &nbsp;(" . formatBytes(filesize($file)) . ")</li>\r\n";
	}
?>

</ul>

</body>
</html>