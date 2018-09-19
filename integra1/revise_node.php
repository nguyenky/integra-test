<?php

require_once('system/e_utils.php');
require_once('system/acl.php');

$user = Login('ematch');

$value = trim($_POST['value']);
$field = trim($_REQUEST['field']);
$id = trim($_POST['id']);
$old = trim($_POST['prev']);

if (empty($id))
	return;

if ($field != 'title' && $field != 'price' && $field != 'picture' && $field != 'minprice' && $field != 'vsours' && $field != 'strategy')
    return;

file_put_contents(LOGS_DIR . "revise_node.txt", date_format(date_create("now", new DateTimeZone('America/New_York')), 'YmdHis') . " - " . $field . " - " . json_encode($_POST) . "\n", FILE_APPEND);

$i = explode('_', $id);
$itemId = $i[1];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

if ($field == 'title')
{
    $oldItem = EbayUtils::GetItem($itemId);

    // get old values
    $oldDescHtml = str_replace(' class="bold"', '', $oldItem['description']);
    $oldDescHtml = str_replace(' center vcenter', '', $oldDescHtml);
    preg_match("/label\">Part Number<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
    $oldPartNumber = $matches['val'];
    $partNumbers = [];
    $pns = explode('/', $oldPartNumber);
    foreach ($pns as $pn) $partNumbers[] = trim($pn);

    preg_match("/label\">Description<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
    $oldDescription = str_replace('<br>', "\n", $matches['val']);

    preg_match("/label\">Notes<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
    $oldNotes = str_replace('<br>', "\n", $matches['val']);

    if (stripos($oldDescHtml, 'Reman') !== false
        || stripos($oldDescHtml, 'Refurb') !== false)
        $oldCondition = 'Remanufactured';
    else $oldCondition = 'New';

    $newDescription = empty($description) ? $oldDescription : $description;

    if (empty($newDescription)) {
        echo 'Please upgrade the listing to template v2 first.';
    }

    $ranges = implode("\n", EbayUtils::EbayCompatToRanges($oldItem['compatibility']));
    $ch = curl_init('http://integra2.eocenterprise.com/api/ebay/raw_preview_v2');
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'title' => trim($value),
        'desc' => $newDescription,
        'brand' => trim($oldItem['brand']),
        'condition' => $oldCondition,
        'partNumbers' => implode("\n", $partNumbers),
        'notes' => empty($notes) ? $oldNotes : $notes,
        'ranges' => $ranges
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $descNode = curl_exec($ch);
    curl_close($ch);

    $ret = EbayUtils::ReviseNode($itemId, '<Title><![CDATA[' . trim($value) . "]]></Title><Description><![CDATA[{$descNode}]]></Description>");

    if ($ret == 'OK')
    {
        echo $value;

        mysql_query(query("INSERT INTO ebay_edit_log (item_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s')",
            $itemId, $user, 'Title', $old, $value));
    }
    else echo $old;
}
else if ($field == 'price')
{
    $ret = EbayUtils::ReviseNode($itemId, '<StartPrice><![CDATA[' . trim($value) . ']]></StartPrice>');

    if ($ret == 'OK')
    {
        file_put_contents(LOGS_DIR . "revise_node.txt", "Revised Successfully \n", FILE_APPEND);
        echo $value;

        mysql_query(query("INSERT INTO ebay_edit_log (item_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s')",
            $itemId, $user, 'Price', $old, $value));
    }
    else  { 
        file_put_contents(LOGS_DIR . "revise_node.txt", "Revised ERROR: " . $ret . " \n", FILE_APPEND);
        echo $old;
    }
}
else if ($field == 'picture')
{
    if (is_array($value) || stripos($value, ',') !== false)
        $pictures = explode(',', $value);
    else $pictures = [ $value ];

    $pictureNode = '<PictureDetails>';

    foreach ($pictures as $p)
    {
        $pictureNode .= '<ExternalPictureURL><![CDATA[' . trim($p) . ']]></ExternalPictureURL>';
    }

    $pictureNode .= '</PictureDetails>';

    $ret = EbayUtils::ReviseNode($itemId, $pictureNode);

    if ($ret == 'OK')
    {
        echo $pictures[0];

        mysql_query(query("INSERT INTO ebay_edit_log (item_id, created_by, edited_field, before_value, after_value) VALUES ('%s', '%s', '%s', '%s', '%s')",
            $itemId, $user, 'Picture', $old, $value));
    }
    else echo $old;
}
else if ($field == 'vsours')
{
    mysql_query(query("INSERT IGNORE INTO ebay_monitor (item_id, keywords, last_scraped, cur_title, prev_title, cur_price, prev_price)
(SELECT item_id, keywords, last_scraped, title, title, price, price FROM ebay_research WHERE item_id = '%s')", $itemId));
    mysql_query(query("UPDATE eoc.ebay_monitor SET disable = 0, vs_ours = '%s' WHERE item_id = '%s'", trim($value), $itemId));
    echo $value;
}
else if ($field == 'minprice')
{
    mysql_query(query("UPDATE eoc.ebay_listings SET min_price = '%s' WHERE item_id = '%s'", trim($value), $itemId));
    echo $value;
}
else if ($field == 'strategy')
{
    mysql_query(query("UPDATE eoc.ebay_monitor SET strategy = '%s' WHERE item_id = '%s'", trim($value), $itemId));
    echo $value;
}
