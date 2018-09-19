<?php

set_time_limit(0);
ini_set('memory_limit', '256M');
set_include_path(get_include_path() . PATH_SEPARATOR . realpath(__DIR__ . '/../system/phpseclib'));

require_once(__DIR__ . '/../system/config.php');
include('Net/SFTP.php');

$sftp = new Net_SFTP(SSF_FTP_SERVER);
if (!$sftp->login(SSF_FTP_USERNAME, SSF_FTP_PASSWORD))
{
    error_log('Unable to download file from W2 FTP');
    return;
}

$files = $sftp->nlist();
$file = '';

foreach ($files as $entry)
{
    if (empty($entry) || strpos($entry, '.') === 0) continue;
    $file = $entry;
    break;
}

$local_file = __DIR__ . '/tmp/ftp/ssf.csv';
if (file_exists($local_file)) unlink($local_file);

echo "Downloading $file...\n";
$sftp->get($file, $local_file);

if (!is_file($local_file))
{
    error_log('Unable to download file from W2 FTP');
    return;
}

echo "Inserting into database...\n";

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

mysql_query("TRUNCATE TABLE ssf_ftp");

$abs = realpath($local_file);
$res = mysql_query("LOAD DATA LOCAL INFILE '${abs}' INTO TABLE ssf_ftp FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\\r\\n' IGNORE 1 LINES (mpn_spaced, mpn_unspaced, brand_id, unit_price, core_price, qty, name)");
if (!$res) echo mysql_error();

$q = <<< EOQ
INSERT INTO ssf_items (mpn, mpn_spaced, brand_id, name, unit_price, core_unit_price, with_core_price, qty_avail, inactive)
(SELECT mpn_unspaced, mpn_spaced, brand_id, name, unit_price, core_price, unit_price + core_price, qty, 0 FROM ssf_ftp)
ON DUPLICATE KEY UPDATE
	mpn = VALUES(mpn),
	mpn_spaced = VALUES(mpn_spaced),
	brand_id = VALUES(brand_id),
	name = VALUES(name),
	unit_price = VALUES(unit_price),
	core_unit_price = VALUES(core_unit_price),
	with_core_price = VALUES(with_core_price),
	qty_avail = VALUES(qty_avail),
	inactive = VALUES(inactive),
	timestamp = NOW()
EOQ;

$res = mysql_query($q);
if (!$res) echo mysql_error();

$q = <<< EOQ
UPDATE ssf_items
SET inactive = 1
WHERE (mpn, brand_id)
NOT IN (SELECT mpn_unspaced, brand_id FROM ssf_ftp)
EOQ;

$res = mysql_query($q);
if (!$res) echo mysql_error();

mysql_close();

echo "Done!\n";

?>