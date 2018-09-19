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
    <title>Integra :: eBay Listings Matching Tool</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"/>
    <link rel="stylesheet" type="text/css" href="css/jquery.fileupload.css">
    <link rel="stylesheet" type="text/css" href="css/dropzone.css">
    <link rel="stylesheet" type="text/css" href="css/ematch.css"/>
    <style type="text/css">
        .disabled {
            pointer-events: none;
            opacity: 0.5;
            background: #CCC;
            padding: 10px;
        }

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

<div class="pull-right" style="width: 500px; margin-right: 100px;">
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
<h2>eBay Listings Matching Tool</h2>

<?php

$src = $_REQUEST['src'];
$dest = $_REQUEST['dest'];

if (empty($src) || empty($dest))
{
    ?>

    <form method="GET">
        <table>
            <tr>
                <td>Source Item ID:</td>
                <td><input type="text" name="src" value="<?= $src ?>"/></td>
            </tr>
            <tr>
                <td>Destination Item ID:</td>
                <td><input type="text" name="dest" value="<?= $dest ?>"/></td>
            </tr>
            <tr>
                <td></td>
                <td><input id="load" type="submit" value="Load Listings"/></td>
            </tr>
        </table>
    </form>

<?
}
else
{
    $action = $_POST['action'];
    $error = false;
    $srcItem = EbayUtils::GetItem($src);
    if (empty($srcItem))
    {
        echo '<div class="alert alert-danger">The source listing was not found.</div>';
        $error = true;
    }
    else
    {
        $destItem = EbayUtils::GetItem($dest);
        if (empty($destItem))
        {
            echo '<div class="alert alert-danger">The destination listing was not found.</div>';
            $error = true;
        }
    }
    if ($action == 'revise' && !$error)
    {
        /*

           To Fixed Error: "One or more compatibility combinations are invalid. Name, value, or name-value pair are not recognized"

           Removed attributes:

              <NameValueList> <Name>Trim</Name> <Value>All</Value> </NameValueList>

              <NameValueList> <Name>Engine</Name> <Value>All</Value> </NameValueList>

           Fixed : 2015-03-03 (Reyn)

         */
        if ($_REQUEST['copy_compat'] == '1')
        {
            $namevaluelist_name = array('Year', 'Make', 'Model', 'Trim', 'Engine'); //filter
            $data = new SimpleXMLElement($srcItem['compatibility']);
            $compatibilityItems = '<ItemCompatibilityList>';
            foreach ($data->Compatibility as $compatibility)
            {
                $compatibilityItems .= '<Compatibility>';
                foreach ($compatibility->NameValueList as $namevaluelist)
                {
                    if ($namevaluelist->Value != 'All' && in_array($namevaluelist->Name, $namevaluelist_name))
                    {
                        //org.xml.sax.SAXParseException: The entity name must immediately follow the &apos;&amp;&apos; in the entity reference.
                        $compatibilityItems .= '<NameValueList> <Name>' . $namevaluelist->Name . '</Name>';
                        $compatibilityItems .= '<Value>' . $namevaluelist->Value . '</Value></NameValueList>';
                    }
                }
                $compatibilityItems .= '<CompatibilityNotes>' . trim(strip_tags($compatibility->CompatibilityNotes)) . '</CompatibilityNotes></Compatibility>';
            }
            $compatibilityItems .= '</ItemCompatibilityList>';
        }
        //Add the ability to Add/Upload more than 1 picture , Add the ability to Edit the Listing Description.
        $picture_element = '';
        if ($_REQUEST['change_picture'] == '1')
        {
            $explode_url = explode(',', $_REQUEST['new_picture']);
            foreach ($explode_url as $key => $value)
            {
                $picture_element .= '<PictureURL>' . trim($value) . '</PictureURL>';
            }
        }

        $response = EbayUtils::ReviseItem($dest,
            $_REQUEST['change_title'] == '1' ? $_REQUEST['new_title'] : $destItem['title'], //$description_title null,
            $_REQUEST['change_price'] == '1' ? $_REQUEST['new_price'] : null,
            $_REQUEST['change_picture'] == '1' ? $picture_element : null, //$_REQUEST['new_picture'] : null,
            $_REQUEST['copy_compat'] == '1' ? $compatibilityItems : $destItem['compatibility'],
            $_REQUEST['change_category'] == '1' ? $_REQUEST['new_category'] : $destItem['category'],
            $_REQUEST['change_mpn'] == '1' ? $_REQUEST['new_mpn'] : $destItem['mpn'],
            $_REQUEST['change_ipn'] == '1' ? $_REQUEST['new_ipn'] : $destItem['ipn'],
            $_REQUEST['change_opn'] == '1' ? $_REQUEST['new_opn'] : $destItem['opn'],
            $_REQUEST['change_placement'] == '1' ? $_REQUEST['new_placement'] : $destItem['placement'],
            $_REQUEST['change_brand'] == '1' ? $_REQUEST['new_brand'] : $destItem['brand'],
            $_REQUEST['change_part_brand'] == '1' ? $_REQUEST['new_part_brand'] : $destItem['brand'],
            $_REQUEST['change_surface_finish'] == '1' ? $_REQUEST['new_surface_finish'] : $destItem['surface_finish'],
            $_REQUEST['change_warranty'] == '1' ? $_REQUEST['new_warranty'] : $destItem['warranty'],
            $destItem['other_attribs'],
            $_REQUEST['change_description'] == '1' ? $_REQUEST['new_description'] : null,
            $_REQUEST['change_description'] == '1' ? $_REQUEST['new_notes'] : null,
            $destItem
        );
        if ($response == 'OK')
        {
            echo '<div class="alert alert-success">The <a target="_blank" href="http://www.ebay.com/itm/' . $dest . '">listing</a> was successfully revised! Changes sometimes take several seconds to reflect on the eBay site.</div>';
            $error = false;
        }
        else if ($response == 'COMPAT') //compatibility issue,
        {
            echo '<div class="alert alert-success">The <a target="_blank" href="http://www.ebay.com/itm/' . $dest . '">listing</a> was successfully revised! But some compatibilities were not recognized.</div>';
            $error = true;
        }
        else
        {
            echo '<div class="alert alert-danger">' . htmlentities($response) . '</div>';
            $error = false;
        }
        if ($_REQUEST['change_title'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Title', $destItem['title'], $_REQUEST['new_title']));
        if ($_REQUEST['change_price'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Price', $destItem['price'], $_REQUEST['new_price']));
        if ($_REQUEST['change_category'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Category', $destItem['category'], $_REQUEST['new_category']));
        if ($_REQUEST['change_mpn'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'MPN', $destItem['mpn'], $_REQUEST['new_mpn']));
        if ($_REQUEST['change_opn'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'OPN', $destItem['opn'], $_REQUEST['new_opn']));
        if ($_REQUEST['change_ipn'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'IPN', $destItem['ipn'], $_REQUEST['new_ipn']));
        if ($_REQUEST['change_placement'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Placement', $destItem['placement'], $_REQUEST['new_placement']));
        if ($_REQUEST['change_brand'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Brand', $destItem['brand'], $_REQUEST['new_brand']));
        if ($_REQUEST['change_part_brand'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Brand', $destItem['brand'], $_REQUEST['new_part_brand']));
        if ($_REQUEST['change_surface_finish'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Surface Finish', $destItem['surface_finish'], $_REQUEST['new_surface_finish']));
        if ($_REQUEST['change_warranty'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Warranty', $destItem['warranty'], $_REQUEST['new_warranty']));
        if ($_REQUEST['change_description'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Description', '', $_REQUEST['new_description'] . '; ' . $_REQUEST['new_notes']));
        if ($_REQUEST['copy_compat'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Compatibility', $destItem['num_compat'], $srcItem['num_compat']));
        if ($_REQUEST['change_picture'] == '1')
            mysql_query(query("INSERT INTO ebay_edit_log (item_id, source_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $dest, $src, $user,
                'Picture', $destItem['picture_big'], str_replace('</PictureURL>', '', str_replace('<PictureURL>', '', $picture_element))));
    }
    if (!$error)
    {
        ?>

        <form method="POST">
        <input type="hidden" name="src" value="<?= $src ?>"/>
        <input type="hidden" name="dest" value="<?= $dest ?>"/>
        <input type="hidden" name="action" value="revise"/>
        <table id="comparison" class="table table-striped table-bordered table-condensed">
        <thead>
        <tr>
            <th></th>
            <th>Source Listing</th>
            <th>Destination Listing</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Item ID</td>
            <td><a target="_blank" href="http://www.ebay.com/itm/<?= $src ?>"><?= $src ?></a></td>
            <td><a target="_blank" href="http://www.ebay.com/itm/<?= $dest ?>"><?= $dest ?></a></td>
            <td></td>
        </tr>
        <tr>
            <td>Seller</td>
            <td><?= htmlentities($srcItem['seller_id']) ?></td>
            <td><?= htmlentities($destItem['seller_id']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td>SKU</td>
            <td><?= htmlentities($srcItem['sku']) ?></td>
            <td><?= htmlentities($destItem['sku']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td>Category ID</td>
            <td><?= htmlentities($srcItem['category']) ?></td>
            <td><?= htmlentities($destItem['category']) ?></td>
            <td>
                <input type="checkbox" id="change_category" name="change_category" value="1">
                Change to:
                <input type="text" id="new_category" name="new_category" disabled="disabled"
                       value="<?= htmlentities($srcItem['category']) ?>" maxlength="20"/>
            </td>
        </tr>
        <tr>
            <td>Quantity Sold</td>
            <td><?= htmlentities($srcItem['num_sold']) ?></td>
            <td class="<?= ($srcItem['num_sold'] <= $destItem['num_sold']) ? 'success' : 'danger' ?>"><?= htmlentities($destItem['num_sold']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td>Page Hits</td>
            <td><?= htmlentities($srcItem['num_hit'] == '-1' ? 'No data' : $srcItem['num_hit']) ?></td>
            <td class="<?= ($srcItem['num_hit'] == '-1' || $destItem['num_hit'] == '-1') ? '' : (($srcItem['num_hit'] <= $destItem['num_hit']) ? 'success' : 'danger') ?>"><?= htmlentities($destItem['num_hit'] == '-1' ? 'No data' : $destItem['num_hit']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td>Title</td>
            <td><?= htmlentities($srcItem['title']) ?></td>
            <td><?= htmlentities($destItem['title']) ?></td>
            <td>
                <input type="checkbox" id="change_title" name="change_title" value="1">
                Change to:
                <input type="text" id="new_title" maxlength="80" name="new_title" disabled="disabled"
                       value="<?= htmlentities($srcItem['title']) ?>"/>
            </td>
        </tr>
        <tr>
            <td>Price</td>
            <td>
                $<?= htmlentities($srcItem['price']) . (($srcItem['shipping_cost'] == 0) ? '' : ('+ $' . $srcItem['shipping_cost'] . ' (' . htmlentities($srcItem['shipping_type']) . ')')) ?></td>
            <td class="<?= ($srcItem['price'] >= $destItem['price']) ? 'success' : 'danger' ?>">
                $<?= htmlentities($destItem['price']) ?></td>
            <td>
                <input type="checkbox" id="change_price" name="change_price" value="1">
                Change to:
                <input type="text" id="new_price" name="new_price" disabled="disabled"
                       value="<?= htmlentities($srcItem['price']) ?>"/>
            </td>
        </tr>
        <tr>
            <td>Manufacturer Part Number</td>
            <td><?= htmlentities($srcItem['mpn']) ?></td>
            <td><?= htmlentities($destItem['mpn']) ?></td>
            <td>
                <input type="checkbox" id="change_mpn" name="change_mpn" value="1">
                Change to:
                <input type="text" id="new_mpn" name="new_mpn" disabled="disabled"
                       value="<?= htmlentities($srcItem['mpn']) ?>" maxlength="65"/>
            </td>
        </tr>
        <tr>
            <td>Interchange Part Number</td>
            <td><?= htmlentities($srcItem['ipn']) ?></td>
            <td><?= htmlentities($destItem['ipn']) ?></td>
            <td>
                <input type="checkbox" id="change_ipn" name="change_ipn" value="1">
                Change to:
                <input type="text" id="new_ipn" name="new_ipn" disabled="disabled"
                       value="<?= htmlentities($srcItem['ipn']) ?>" maxlength="65"/>
            </td>
        </tr>
        <tr>
            <td>Other Part Number</td>
            <td><?= htmlentities($srcItem['opn']) ?></td>
            <td><?= htmlentities($destItem['opn']) ?></td>
            <td>
                <input type="checkbox" id="change_opn" name="change_opn" value="1">
                Change to:
                <input type="text" id="new_opn" name="new_opn" disabled="disabled"
                       value="<?= htmlentities($srcItem['opn']) ?>" maxlength="65"/>
            </td>
        </tr>
        <tr>
            <td>Placement on Vehicle</td>
            <td><?= htmlentities($srcItem['placement']) ?></td>
            <td><?= htmlentities($destItem['placement']) ?></td>
            <td>
                <input type="checkbox" id="change_placement" name="change_placement" value="1">
                Change to:
                <input type="text" id="new_placement" name="new_placement" disabled="disabled"
                       value="<?= htmlentities($srcItem['placement']) ?>"/>
            </td>
        </tr>
        <tr>
            <td>Brand</td>
            <td><?= htmlentities($srcItem['brand']) ?></td>
            <td><?= htmlentities($destItem['brand']) ?></td>
            <td>
                <input type="checkbox" id="change_brand" name="change_brand" value="1">
                Change to:
                <input type="text" id="new_brand" name="new_brand" disabled="disabled"
                       value="<?= htmlentities($srcItem['brand']) ?>"/>
            </td>
        </tr>
        <tr>
            <td>Part Brand</td>
            <td><?= htmlentities($srcItem['brand']) ?></td>
            <td><?= htmlentities($destItem['brand']) ?></td>
            <td>
                <input type="checkbox" id="change_part_brand" name="change_part_brand" value="1">
                Change to:
                <input type="text" id="new_part_brand" name="new_part_brand" disabled="disabled"
                       value="<?= htmlentities($srcItem['brand']) ?>"/>
            </td>
        </tr>
        <tr>
            <td>Surface Finish</td>
            <td><?= htmlentities($srcItem['surface_finish']) ?></td>
            <td><?= htmlentities($destItem['surface_finish']) ?></td>
            <td>
                <input type="checkbox" id="change_surface_finish" name="change_surface_finish" value="1">
                Change to:
                <input type="text" id="new_surface_finish" name="new_surface_finish" disabled="disabled"
                       value="<?= htmlentities($srcItem['surface_finish']) ?>"/>
            </td>
        </tr>
        <tr>
            <td>Warranty</td>
            <td><?= htmlentities($srcItem['warranty']) ?></td>
            <td><?= htmlentities($destItem['warranty']) ?></td>
            <td>
                <input type="checkbox" id="change_warranty" name="change_warranty" value="1">
                Change to:
                <input type="text" id="new_warranty" name="new_warranty" disabled="disabled"
                       value="<?= htmlentities($srcItem['warranty']) ?>"/>
            </td>
        </tr>
        <tr>
            <td>Compatibility Entries</td>
            <td><?= $srcItem['num_compat'] ?></td>
            <td class="<?= ($srcItem['num_compat'] <= $destItem['num_compat']) ? 'success' : 'danger' ?>"><?= $destItem['num_compat'] ?></td>
            <td>
                <input type="checkbox" id="copy_compat" name="copy_compat" value="1">
                Copy compatibility from source
            </td>
        </tr>
        <tr>
            <td>Item Description</td>
            <td>
            </td>
            <td>
            </td>
            <td>
                <input type="checkbox" id="change_description" name="change_description" value="1"> Change to
                <br/>
                Description:
                <textarea class="form-control" id="new_description" rows="8" name="new_description" placeholder="Description" disabled="disabled"></textarea>
                <br/>
                Notes:
                <textarea class="form-control" id="new_notes" rows="8" name="new_notes"placeholder="Notes" disabled="disabled"></textarea>
            </td>
        </tr>
        <tr>
            <td>Picture</td>
            <td><?= empty($srcItem['picture_big']) ? 'None' : '<img src="' . $srcItem['picture_big'] . '" />' ?></td>
            <td class="<?= empty($destItem['price']) ? 'danger' : '' ?>"><?= empty($destItem['picture_big']) ? 'None' : '<img src="' . $destItem['picture_big'] . '" />' ?></td>
            <td>
                <input type="checkbox" id="change_picture" name="change_picture" value="1">
                Change to:
                <fieldset id="upload_div" class="disabled">
                    <input type="text" id="new_picture" disabled="disabled" name="new_picture"
                           value="<?= htmlentities($srcItem['picture_big']) ?>"/>

                    <div id="dz_div" class="dropzone"></div>
                </fieldset>
            </td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td><input type="submit" id="revise" class="btn btn-large btn-primary" value="Revise Listing"/></td>
        </tr>
        </tbody>
        </table>
        </form>

    <?
    }
    echo '<a href="ematch.php">Start Over</a>';
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

$(function ()
{

    'use strict';

    var arFiles = [];

    var ematchDropzone = new Dropzone("div#dz_div",
        {

            url: "ematch_multiple_upload.php",

            accept: function (file, done)
            {
                var re = /(?:\.([^.]+))?$/;
                var ext = re.exec(file.name)[1];
                ext = ext.toUpperCase();

                if (ext == "JPG" || ext == "JPEG" || ext == "PNG" || ext == "GIF" || ext == "BMP")
                {
                    done();
                }
                else if (jQuery.inArray(file.name, arExistingFiles) > -1)
                {
                    arErrorFiles.push(file.name);
                    done("File already exists.");
                }
                else
                {
                    done("Please select only supported picture files.");
                }
            },
            success: function (file, response)
            {
                arFiles.push('http://qeautoparts.eocenterprise.com/qe_img_upload/' + response);
                $('#new_picture').val(arFiles);
            }
        });

    $('#revise').click(function ()
    {

        if (!$('#change_title').prop('checked')

            && !$('#change_price').prop('checked')

            && !$('#change_mpn').prop('checked')

            && !$('#change_category').prop('checked')

            && !$('#change_ipn').prop('checked')

            && !$('#change_opn').prop('checked')

            && !$('#change_placement').prop('checked')

            && !$('#change_brand').prop('checked')

            && !$('#change_part_brand').prop('checked')

            && !$('#change_surface_finish').prop('checked')

            && !$('#change_warranty').prop('checked')

            && !$('#change_picture').prop('checked')

            && !$('#copy_compat').prop('checked')

            && !$('#change_description').prop('checked'))
        {

            alert('Please check which action(s) you want to perform on the destination listing.');

            return false;

        }

    });

    $('#change_title').change(function ()
    {

        if (this.checked)

            $('#new_title').removeAttr('disabled');

        else

            $('#new_title').attr('disabled', 'disabled');

    });

    $('#change_category').change(function ()
    {

        if (this.checked)

            $('#new_category').removeAttr('disabled');

        else

            $('#new_category').attr('disabled', 'disabled');

    });
    
    $('#change_price').change(function ()
    {

        if (this.checked)

            $('#new_price').removeAttr('disabled');

        else

            $('#new_price').attr('disabled', 'disabled');

    });

    $('#change_mpn').change(function ()
    {

        if (this.checked)

            $('#new_mpn').removeAttr('disabled');

        else

            $('#new_mpn').attr('disabled', 'disabled');

    });

    $('#change_ipn').change(function ()
    {

        if (this.checked)

            $('#new_ipn').removeAttr('disabled');

        else

            $('#new_ipn').attr('disabled', 'disabled');

    });

    $('#change_opn').change(function ()
    {

        if (this.checked)

            $('#new_opn').removeAttr('disabled');

        else

            $('#new_opn').attr('disabled', 'disabled');

    });

    $('#change_placement').change(function ()
    {

        if (this.checked)

            $('#new_placement').removeAttr('disabled');

        else

            $('#new_placement').attr('disabled', 'disabled');

    });

    $('#change_brand').change(function ()
    {

        if (this.checked)

            $('#new_brand').removeAttr('disabled');

        else

            $('#new_brand').attr('disabled', 'disabled');

    });

    $('#change_part_brand').change(function ()
    {

        if (this.checked)

            $('#new_part_brand').removeAttr('disabled');

        else

            $('#new_part_brand').attr('disabled', 'disabled');

    });

    $('#change_surface_finish').change(function ()
    {

        if (this.checked)

            $('#new_surface_finish').removeAttr('disabled');

        else

            $('#new_surface_finish').attr('disabled', 'disabled');

    });

    $('#change_warranty').change(function ()
    {

        if (this.checked)

            $('#new_warranty').removeAttr('disabled');

        else

            $('#new_warranty').attr('disabled', 'disabled');

    });

    $('#change_picture').change(function ()
    {

        if (this.checked)
        {

            $('#new_picture').removeAttr('disabled');

            $('#upload').removeAttr('disabled');

            $('#upload_div').removeClass('disabled');

        }

        else
        {

            $('#new_picture').attr('disabled', 'disabled');

            $('#upload').attr('disabled', 'disabled');

            $('#upload_div').addClass('disabled');

        }

    });

    $('#change_description').change(function ()
    {

        if (this.checked) {

            $('#new_description').removeAttr('disabled');
            $('#new_notes').removeAttr('disabled');
        }

        else {

            $('#new_description').attr('disabled', 'disabled');
            $('#new_notes').attr('disabled', 'disabled');
        }

    });

});
</script>
</body>
</html>
