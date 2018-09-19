<?php

require_once('system/imc_utils.php');
require_once('system/acl.php');

$user = Login('w1_shipping');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$action = $_REQUEST['a'];
$error = '';

if ($action == 'submit')
{
    if (!empty($_REQUEST['record_num']))
    {
        $res = mysql_query(query("SELECT street, city, state, zip, id FROM sales WHERE record_num = '%s'", $_REQUEST['record_num']));
        $row = mysql_fetch_row($res);
        if (!empty($row) && !empty($row[0]))
        {
            $street = $row[0];
            $city = $row[1];
            $state = convert_state($row[2], 'abbrev');
            $zip = $row[3];
            $salesId = $row[4];
            $items = GetOrderComponents($salesId);
        }
    }
    else
    {
        $street = $_REQUEST['street'];
        $city = $_REQUEST['city'];
        $state = $_REQUEST['state'];
        $zip = $_REQUEST['zip'];
        $items = [];

        for ($i = 0; $i < count($_REQUEST['mpn']); $i++)
        {
            $items[$_REQUEST['mpn'][$i]] = $_REQUEST['qty'][$i];
        }
    }

    if (empty($items))
    {
        $error = 'Order not found or no items entered';
    }
    else
    {
        $res = ImcUtils::ScrapeFreight($items, $street, $city, $state, $zip);

        if (!empty($res['error']))
            $error = $res['error'];
    }
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>W1 Shipping Cost</title>
        <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
        <style>
            .margin-20
            {
                margin-left: 20px !important;
            }
            .margin-bottom-20
            {
                margin-bottom: 20px !important;
            }
        </style>
	</head>
<body>
<center>
	<form method="POST" action="w1_shipping.php?a=submit" name="items" class="margin-20">
        <div class="row">
            <div class="col-xs-6">
                <h1>Compute W1 Shipping Cost</h1>
                <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th>Existing Record #</th>
                        <td><input type="text" name="record_num" value="<?=$_REQUEST['record_num']?>"/></td>
                    </tr>
                    <tr>
                        <th>Street</th>
                        <td><input type="text" name="street" value="<?=$street?>"/></td>
                    </tr>
                    <tr>
                        <th>City</th>
                        <td><input type="text" name="city" value="<?=$city?>"/></td>
                    </tr>
                    <tr>
                        <th>State Code</th>
                        <td><input type="text" maxlength="2" name="state" value="<?=$state?>"/></td>
                    </tr>
                    <tr>
                        <th>ZIP Code</th>
                        <td><input type="text" name="zip" value="<?=$zip?>"/></td>
                    </tr>
                </tbody>
                </table>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-6">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>MPN</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <tr>
                            <td><input type="text" name="mpn[]" /></td>
                            <td><input type="number" name="qty[]" min="1"/></td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($items as $mpn => $qty): ?>
                                <tr>
                                    <td><input type="text" name="mpn[]" value="<?=$mpn?>"/></td>
                                    <td><input type="number" name="qty[]" min="1" value="<?=$qty?>"/></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="row margin-bottom-20">
            <div class="col-xs-6">
                <?php if (!empty($error)): ?> <p><?=htmlentities($error)?></p> <?php endif; ?>
                <a class="btn btn-warning" href="w1_shipping.php">Start Over</a>&nbsp;&nbsp;&nbsp;
                <input class="btn btn-primary" type="submit" value="Compute" />
            </div>
        </div>
        <?php if (!empty($res['options'])): ?>
        <div class="row">
            <div class="col-xs-6">
                <table class="table table-bordered">
                    <tbody>
                    <tr>
                        <th>Subtotal</th>
                        <td>$<?=number_format($res['subtotal'], 2)?></td>
                    </tr>
                    <tr>
                        <th>Total Weight</th>
                        <td><?=$res['weight']?> lbs</td>
                    </tr>
                    <tr>
                        <th>&nbsp;</th>
                        <td>&nbsp;</td>
                    </tr>
                    <?php foreach ($res['options'] as $opt): ?>
                    <tr>
                        <th><?=$opt['service']?></th>
                        <td>$<?=number_format($opt['cost'], 2)?>
                            <?php if ($opt['cost'] != $opt['cost_nodisc']): ?>
                                (without discount: $<?=number_format($opt['cost_nodisc'], 2)?>)
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
	</form>
</center>
<script src="js/jquery.min.js"></script>
</body>
</html>