<?php

require_once('system/config.php');
require_once('system/imc_utils.php');
require_once('system/acl.php');

$user = Login();

set_time_limit(0);

$mpns = $_POST['mpns'];

if (!empty($mpns))
{
	$l = explode("\n", $mpns);
	
	foreach ($l as $line)
	{
		if (startsWith($line, 'EOC'))
			$line = substr($line, 3);
			
		$line = trim(str_replace(' ', '', $line));
				
		if (empty($line))
			continue;
		
		$lines[] = $line;
	}
		
	$lineStr = implode("','", $lines);

	ImcUtils::ScrapeSiteItems($lines);
	
	$tmpDir = '/tmp/scrape_' . time();
	mkdir($tmpDir);
	$fitFile = "${tmpDir}/fitment.csv";
	$basicFile = "${tmpDir}/basic.csv";
	$zipFile = "${tmpDir}.zip";
	
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
	
	$file = fopen($fitFile, "w");
	fputcsv($file, array("MPN", "Make", "Model", "Year", "Position", "Fitment Notes", "Misc Notes"));
	
	$q = "SELECT mpn, make, model, year, position, fit_notes, misc_notes FROM imc_fitment WHERE mpn IN ('$lineStr')";
	$rows = mysql_query($q);

	while ($row = mysql_fetch_row($rows))
		fputcsv($file, $row);

	fclose($file);
	
	$file = fopen($basicFile, "w");
	fputcsv($file, array("MPN", "Brand", "Name", "Unit Price", "Weight", "Pack Qty", "Part Notes"));

	$q = "SELECT mpn, brand, name, unit_price, weight, pack_qty, part_notes FROM imc_items WHERE mpn IN ('$lineStr')";
	$rows = mysql_query($q);

	while ($row = mysql_fetch_row($rows))
		fputcsv($file, $row);

	fclose($file);
	
	mysql_close();
	
	exec("zip -9j -r ${zipFile} ${tmpDir}");
	unlink($fitFile);
	unlink($basicFile);
	rmdir($tmpDir);
	
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: public");
	header("Content-Description: File Transfer");
	header("Content-type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"" . basename($zipFile) . "\"");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: " . filesize($zipFile));
	ob_end_flush();
	@readfile($zipFile);
	exit;
}

?>


<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Warehouse 1 Scraper</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		input[type=submit]:hover
		{
			border: 2px inherit !important;
		}
		h2, h4, form, i
		{
			font-family: tahoma, verdana;
		}
		h4
		{
			margin-bottom: 10px;
			font-size: 16px;
		}
		form, i
		{
			font-size: 14px;
		}
	</style>
  </head>
<body>
<center>
<h2>Warehouse 1 Scraper</h2>
<form method="POST">
	<h4>Input MPNs to scrape (one per line)</h4>
	<textarea rows="20" cols="30" name="mpns"></textarea><br/>
	<input type="submit" value="Start Scraping" />
	<p>This process will take a few minutes. 100 MPNs take about 4 minutes to finish. If it times out, try a fewer set of MPNs.<br/>
	Do not scrape in parallel and do not log in with the W1 user name "<?= IMC_WEB_USERNAME ?>" while this tool is processing.</p>
	<p>All scraped product images for QE from warehouse 1 can be found at http://catalog.eocenterprise.com/img/<i><b>mpn</b></i><br/>
	Sample: Image for MPN 008855017 can be found at <a target="_blank" href="http://catalog.eocenterprise.com/img/008855017">http://catalog.eocenterprise.com/img/008855017</a></p>
</form>
</center>
</body>
</html>