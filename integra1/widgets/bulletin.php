<?php

require_once('../system/config.php');
session_start();
$user = $_SESSION['user'];
if (empty($user)) exit;

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>Integra :: Bulletin</title>
<style>
p { font-family: tahoma, verdana; }
</style>
</head>
<body>

<?php

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT content
FROM bulletins
WHERE published = 1
AND
(
	LENGTH(recipients) = 0
	OR
	LOWER(recipients) LIKE '%%%s%%'
)
ORDER BY sort_num
EOD;
$res = mysql_query(sprintf($q, $user));

while ($row = mysql_fetch_row($res))
	$messages[] = $row[0];

echo "<p>" . implode("</p><hr/>\r\n<p>", $messages) . "</p>\r\n";
	
mysql_close();

?>
</body>
</html>