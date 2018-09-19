<?php

require_once('system/config.php');

require_once('system/utils.php');

require_once('system/e_utils.php');

require_once('system/acl.php');


$user = Login();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);

mysql_select_db(DB_SCHEMA);


$row = mysql_fetch_row(mysql_query(query("SELECT IFNULL(COUNT(DISTINCT item_id), 0) FROM ebay_edit_log WHERE created_on >= CURDATE() AND DATE(created_on) = CURDATE() AND created_by = '%s' AND is_new = 0", $user)));

$progress = $row[0];


$row = mysql_fetch_row(mysql_query(query("SELECT goal FROM goals WHERE metric = 1 AND (email = '%s' OR email = '') ORDER BY email DESC LIMIT 1", $user)));

$progressGoal = $row[0];


$progressPctEdit = ceil($progress * 100 / $progressGoal);

if ($progressPctEdit > 100) $progressPctEdit = 100;


if ($progressPctEdit >= 70)
    $progressColorEdit = 'success';
else if ($progressPctEdit >= 40)
    $progressColorEdit = 'warning';
else

    $progressColorEdit = 'danger';


$row = mysql_fetch_row(mysql_query(query("SELECT IFNULL(COUNT(DISTINCT item_id), 0) FROM ebay_edit_log WHERE created_on >= CURDATE() AND DATE(created_on) = CURDATE() AND created_by = '%s' AND is_new = 1", $user)));

$progress = $row[0];


$row = mysql_fetch_row(mysql_query(query("SELECT goal FROM goals WHERE metric = 2 AND (email = '%s' OR email = '') ORDER BY email DESC LIMIT 1", $user)));

$progressGoal = $row[0];


$progressPctNew = ceil($progress * 100 / $progressGoal);

if ($progressPctNew > 100) $progressPctNew = 100;


if ($progressPctNew >= 70)
    $progressColorNew = 'success';
else if ($progressPctNew >= 40)
    $progressColorNew = 'warning';
else

    $progressColorNew = 'danger';


?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Integra :: eBay Copy Listings Tool</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"/>
    <link rel="stylesheet" type="text/css" href="css/jquery.fileupload.css">
    <link rel="stylesheet" type="text/css" href="css/dropzone.css">
    <link rel="stylesheet" type="text/css" href="css/ecopy.css"/>

    <style type="text/css">
        .dz-image img {
            width: 120px !important;
        }

        .dz-size {
            display: none !important;
        }
    </style>
</head>
<body>
<?php include_once("analytics.php") ?>

<div class="pull-right" style="width: 500px; margin-right: 100px; ">
    <div class="row">
        <div class="col-xs-6">
            <strong>Today's Goal (Edited Listings)</strong>

            <div class="progress">
                <div class="progress-bar progress-bar-<?= $progressColorEdit ?>" role="progressbar"
                     aria-valuenow="<?= $progressPctEdit ?>" aria-valuemin="0" aria-valuemax="100"
                     style="width: <?= $progressPctEdit ?>%;">
                    <span class="sr-only"><?= $progressPctEdit ?>%</span>
                </div>
            </div>
        </div>
        <div class="col-xs-6">
            <strong>Today's Goal (New Listings)</strong>

            <div class="progress">
                <div class="progress-bar progress-bar-<?= $progressColorNew ?>" role="progressbar"
                     aria-valuenow="<?= $progressPctNew ?>" aria-valuemin="0" aria-valuemax="100"
                     style="width: <?= $progressPctNew ?>%;">
                    <span class="sr-only"><?= $progressPctNew ?>%</span>
                </div>
            </div>
        </div>
    </div>
</div>
<center>
    <h2>eBay Copy Listings Tool</h2>

    <?

    $src = $_REQUEST['src'];

    if (empty($src)) {
        ?>

        <form method="GET">
            <table>
                <tr>
                    <td>Source Item ID:</td>
                    <td><input type="text" name="src" value="<?= $src ?>"/></td>
                </tr>
                <tr>
                    <td></td>
                    <td><input id="load" type="submit" value="Load Listing"/></td>
                </tr>
            </table>
        </form>

        <?
    } else {
        $action = $_POST['action'];
        $error = false;
        $srcItem = EbayUtils::GetItem($src);
        if (empty($srcItem)) {
            echo '<div class="alert alert-danger">The source listing was not found.</div>';
            $error = true;
        }
        if ($action == 'copy' && !$error) {
            $response = EbayUtils::ListItem(
                $_REQUEST['title'],
                $_REQUEST['sku'],
                $_REQUEST['qty'],
                $_REQUEST['price'],
                $_REQUEST['shipping3d'],
                $_REQUEST['shipping2d'],
                $_REQUEST['mpn'],
                $_REQUEST['ipn'],
                $_REQUEST['opn'],
                $_REQUEST['placement'],
                $_REQUEST['brand'],
                $_REQUEST['surface_finish'],
                $_REQUEST['warranty'],
                $_REQUEST['new_picture'],
                $_REQUEST['description'],
                $_REQUEST['notes'],
                $srcItem['category'],
                $srcItem['compatibility'],
                $_REQUEST['conditionID']);
            if ($response['success']) {
                echo '<div class="alert alert-success">The <a target="_blank" href="http://www.ebay.com/itm/' . $response['id'] . '">new listing</a> was successfully created!</div>';
                $error = false;
		mysql_query(query("INSERT INTO ebay_edit_log (is_new, item_id, source_id, created_by, edited_field, before_value, after_value) VALUES (1, '%s', '%s', '%s', '%s', '%s', '%s')",
                    $response['id'], $src, $user, '', '', ''));
            } else {
                if (!empty($response['id'])) {
                    echo '<div class="alert alert-success">The <a target="_blank" href="http://www.ebay.com/itm/' . $response['id'] . '">new listing</a> was successfully created!</div><br/>';
		    mysql_query(query("INSERT INTO ebay_edit_log (is_new, item_id, source_id, created_by, edited_field, before_value, after_value) VALUES (1, '%s', '%s', '%s', '%s', '%s', '%s')",
                    	$response['id'], $src, $user, '', '', ''));
		}
                echo '<div class="alert alert-danger">' . htmlentities($response['error']) . '</div>';
                $error = true;
            }
        }
        if (!$error) {
            ?>

            <form id="copy-form" class="form-horizontal" role="form" method="POST" style="clear:both;">
                <input type="hidden" name="src" value="<?= $src ?>"/>
                <input type="hidden" name="action" value="copy"/>

                <div class="form-group">
                    <label for="title" class="col-sm-3 control-label">Title</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="title" name="title" maxlength="80"
                               placeholder="Title"
                               value="<?= htmlentities($srcItem['title']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="sku" class="col-sm-3 control-label">SKU</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="sku" name="sku" placeholder="SKU"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="qty" class="col-sm-3 control-label">Quantity Available</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="qty" name="qty" placeholder="Quantity"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="price" class="col-sm-3 control-label">Price (with free shipping)</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="price" name="price" placeholder="Price"
                               value="<?= htmlentities($srcItem['price'] + $srcItem['shipping']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="shipping3d" class="col-sm-3 control-label">3 Days Shipping Charge</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="shipping3d" name="shipping3d"
                               placeholder="Leave blank to disable" value=""/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="shipping2d" class="col-sm-3 control-label">2 Days Shipping Charge</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="shipping2d" name="shipping2d"
                               placeholder="Leave blank to disable" value=""/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="mpn" class="col-sm-3 control-label">Manufacturer Part Number</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="mpn" name="mpn"
                               placeholder="Manufacturer Part Number" maxlength="65"
                               value="<?= htmlentities($srcItem['mpn']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="ipn" class="col-sm-3 control-label">Interchange Part Number</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="ipn" name="ipn"
                               placeholder="Interchange Part Number" maxlength="65"
                               value="<?= htmlentities($srcItem['ipn']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="opn" class="col-sm-3 control-label">Other Part Number</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="opn" name="opn" placeholder="Other Part Number"
                               maxlength="65"
                               value="<?= htmlentities($srcItem['opn']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="placement" class="col-sm-3 control-label">Placement on Vehicle</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="placement" name="placement"
                               placeholder="Placement on Vehicle" value="<?= htmlentities($srcItem['placement']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="brand" class="col-sm-3 control-label">Part Brand</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="brand" name="brand" placeholder="Part Brand"
                               value="<?= htmlentities($srcItem['brand']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="surface_finish" class="col-sm-3 control-label">Surface Finish</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="surface_finish" name="surface_finish" maxlength="80"
                               placeholder="Surface Finish" value="<?= htmlentities($srcItem['surface_finish']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="warranty" class="col-sm-3 control-label">Warranty</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" id="warranty" name="warranty" placeholder="Warranty"
                               value="<?= htmlentities($srcItem['warranty']) ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_picture" class="col-sm-3 control-label">Picture URLs</label>

                    <div class="col-sm-6">
                        <div>
                            <input type="text" class="form-control" id="new_picture" name="new_picture"
                                   placeholder="Picture URLs" value="<?= htmlentities($srcItem['picture_big']) ?>"/>
                        </div>
                        <div id="dz_div" class="dropzone"></div>
                        <img id="preview"
                             src="<?= empty($srcItem['picture_big']) ? '" style="display:none' : $srcItem['picture_big'] ?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="conditionID" class="col-sm-3 control-label">Item Condition</label>

                    <div class="col-sm-6">
                        <select id="conditionID" name="conditionID" class="form-control">
                            <?php

                            $conditions = array(
                                '1000' => 'New',
                                '1500' => 'New other (see details)',
                                //'1750' => 'New with defects',
                                //'2000' => 'Manufacturer refurbished',
                                '2500' => 'Re-manufactured', //'Seller refurbished',
                                '3000' => 'Used',
                                //'4000' => 'Very Good',
                                '5000' => 'Good',
                                '6000' => 'Acceptable',
                                //'7000' => 'For parts or not working'
                            );

                            foreach ($conditions as $conditionKey => $conditionValue)
                                echo '<option value="' . $conditionKey . '">' . $conditionValue . '</option>';

                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description" class="col-sm-3 control-label">Item Description</label>

                    <div class="col-sm-6">
                <textarea class="form-control" id="description" rows="8" name="description"
                          placeholder="Item Description"></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label for="notes" class="col-sm-3 control-label">Part Notes</label>

                    <div class="col-sm-6">
                <textarea class="form-control" id="notes" rows="8" name="notes"
                          placeholder="Part Notes"></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label for="submit" class="col-sm-3 control-label"></label>

                    <div class="col-md-6">
                        <button class="btn btn-success" type="button" id="render">Preview</button>
                        <input type="submit" id="submit" class="btn btn-large btn-primary" value="Create Listing">
                    </div>
                </div>
            </form>

            <iframe id="render_frame" src="" style="display:none;"></iframe>

            <?
        }
        echo '<br/><a id="restart" href="ecopy.php">Start Over</a>';
    }

    ?>
</center>
<br/>
<br/>
<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js"></script>
<script src="js/jquery.iframe-transport.js"></script>
<script src="js/jquery.fileupload.js"></script>
<script src="js/dropzone.js"></script>
<script>
    /*jslint unparam: true */

    /*global window, $ */

    $(function () {

        'use strict';

        var arFiles = [];

        var ematchDropzone = new Dropzone("div#dz_div",
            {
                url: "ematch_multiple_upload.php",

                accept: function (file, done) {
                    var re = /(?:\.([^.]+))?$/;
                    var ext = re.exec(file.name)[1];
                    ext = ext.toUpperCase();

                    if (ext == "JPG" || ext == "JPEG" || ext == "PNG" || ext == "GIF" || ext == "BMP") {
                        done();
                    }
                    else if (jQuery.inArray(file.name, arExistingFiles) > -1) {
                        arErrorFiles.push(file.name);
                        done("File already exists.");
                    }
                    else {
                        done("Please select only supported picture files.");
                    }
                },
                success: function (file, response) {
                    arFiles.push('http://qeautoparts.eocenterprise.com/qe_img_upload/' + response);
                    $('#new_picture').val(arFiles);
                    $('#preview').hide();
                }
            });

        $('#submit').click(function () {

            return confirm('Please review all data. Do you want to create the listing now?');

        });

        $('#render').click(function () {
            $.post('raw_preview_v2_proxy.php', {
                title: $('#title').val(),
                desc: $('#description').val(),
                notes: $('#notes').val(),
                brand: $('#brand').val(),
                condition: $('#conditionID').val(),
                partNumbers: ($('#mpn').val() + "\n" + $('#ipn').val() + "\n" + $('#opn').val()).replace(',', ''),
                ranges: '(will be filled out automatically)'}, function (res) {

                $('#render_frame').show();
                $('#render_frame').contents().find('body').html(res);
            });
        });
    });

    function nl2br(str, is_xhtml) {

        var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';

        return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');

    }
</script>
</body>
</html>
