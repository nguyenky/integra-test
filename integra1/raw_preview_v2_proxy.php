<?php

$ch = curl_init('http://integra2.eocenterprise.com/api/ebay/raw_preview_v2');
curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

echo $res;