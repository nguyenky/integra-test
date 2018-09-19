<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . '../system/e_utils.php');

set_time_limit(0);
ini_set('memory_limit', '768M');

file_put_contents(LOGS_DIR . "egrid_research.log", "================= START EGRID RESEARCH FOR AT: ". date('Y-m-d H:i:s') ." ============== \r\n", FILE_APPEND);

function research($lines, $userId) {
	foreach ($lines as $line) {
		$keywords = trim($line);
		if (empty($keywords)) continue;
		EbayUtils::ResearchKeyword($keywords, $userId);
	}

}

function researchV2($lines, $userId) {
	$keywords = [];
	foreach($lines as $line) {
		array_push($keywords, trim($line));
	}

	$ebayUtil = new EbayUtils();

	$ebayUtil->ResearchKeywordV2($keywords, $userId);
}

if ($argc != 3) {
	echo "No user ID specified\n";

	file_put_contents(LOGS_DIR . "egrid_research.log", "======= NOT ENOUGH PARAMS NUMBERS ==========");

	return;
}

try {

	$userId = $argv[1];
	$version = $argv[2];

	file_put_contents(LOGS_DIR . "egrid_research.log", "======= USER ID ". $userId ." version ". $version ." ==========");

	
	settype($userId, 'integer');

	$file = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'egrid.' . $userId;
	if (!is_file($file))
	{
		echo "Missing input file: $file\n";
		return;
	}

	$lines = explode("\n", file_get_contents($file));

	if($version == 1) {
		research($lines, $userId);
	} else {
		researchV2($lines, $userId);
	}

	echo "Done!\n";
	unlink($file);
	file_put_contents(LOGS_DIR . "egrid_research.log", "================= END EGRID RESEARCH AT: ". date('Y-m-d H:i:s') ." ============== \r\n", FILE_APPEND);

} catch(Exeption $ex) {
	file_put_contents(LOGS_DIR . "egrid_research.log", "EXCEPTION: ". $ex->getMessage() ."\r\n", FILE_APPEND);
}


