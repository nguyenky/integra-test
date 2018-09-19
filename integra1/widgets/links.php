<?php

require_once('../system/config.php');
session_start();
$user = $_SESSION['user'];
if (empty($user)) exit;

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>Integra :: Links</title>
<style>
li { font-family: tahoma, verdana; }
ul { padding-left: 20px; margin: 0px; }
body { padding: 0px; margin: 5px;}
</style>
</head>
<body>

<?php

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT title, description, url
FROM links
WHERE email = '%s'
ORDER BY sort_num
EOD;
$res = mysql_query(sprintf($q, $user));

echo "<ul>\r\n";

while ($row = mysql_fetch_row($res))
	echo '<li><a target="_blank" href="' . $row[2] . '">' . htmlentities($row[0]) . '</a><br/><i>' . $row[1] . "</i></li>\r\n";
	
echo "</ul>\r\n";
	
mysql_close();

?>
</body>
</html>