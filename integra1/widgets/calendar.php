<?php

session_start();
$user = $_SESSION['user'];
if (empty($user)) exit;

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>Integra :: Calendar</title>
</head>
<body>
<iframe src="https://www.google.com/calendar/embed?src=eocenterprise.com_48jjhc40fs8f0at3ke4620egck%40group.calendar.google.com&showTitle=0&amp;showPrint=0&amp;showCalendars=0&amp;height=600&amp;wkst=1&amp;bgcolor=%23FFFFFF&amp" style=" border-width:0; width: 600px; height: 400px" frameborder="0" scrolling="no"></iframe>
</body>
</html>