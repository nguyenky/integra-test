<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login('sales');
$running = false;

if ($_POST['supplier'] === 'w1e')
{
    exec('nohup flock -xn /tmp/w1e_order -c "php /var/www/webroot/ROOT/integra2/artisan imc:order_export"');
    $running = "Orders are now being processed...";
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <title>W1E Adhoc Ordering</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <style>
        body { font-family: tahoma, verdana; }
    </style>
</head>
<body>
<center>
    <h1>W1E Adhoc Ordering</h1>
    <br/>
    <?php if (!$running): ?>
        <form method="POST">
            <input type="hidden" name="supplier" value="w1e" />
            <input type="submit" value="Process W1E Orders" />
        </form>
    <?php else: ?>
        <p><?=$running?></p>
    <?php endif ?>
    <br/>
</center>
</body>
</html>