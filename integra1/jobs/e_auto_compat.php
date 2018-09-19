<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');

set_time_limit(0);
ini_set('memory_limit', '768M');

$seller = EBAY_SELLER;

while (true)
{
	$itemIds = array();

	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);

	$q = <<<EOD
SELECT item_id
FROM ebay_grid_summary egs
WHERE our_sold = 0
AND active = 1
AND EXISTS
(
    SELECT 1
    FROM ebay_grid eg
    WHERE eg.item_id = egs.item_id
    AND seller != '${seller}'
    AND num_compat > 0
    AND active = 1
)
ORDER BY RAND()
LIMIT 20
EOD;
	$res = mysql_query($q);

	while ($row = mysql_fetch_row($res))
		$itemIds[] = $row[0];

	foreach ($itemIds as $itemId)
	{
		$date = date_create("now", new DateTimeZone('America/New_York'));

		try
		{
			$result = GetStats($itemId);
			if (empty($result))
				continue;
			
			EbayUtils::ScrapeItem($itemId);
			
			$result = GetStats($itemId);
			if (empty($result))
				continue;
				
			$message = EbayUtils::CopyCompat($itemId, $result['maxItemId']);
			if ($message == 'OK')
			{
				file_put_contents("../e_auto_compat.txt",
					sprintf("[%s] Our listing %s had %d compatibilit%s, and was sold %dx. Integra successfully copied %d compatibilit%s from listing %s of %s, which was sold %dx.\r\n",
						date_format($date, 'Y-m-d H:i:s'),
						$itemId,
						$result['ourNumCompat'],
						$result['ourNumCompat'] > 1 ? 'ies' : 'y',
						$result['ourNumSold'],
						$result['maxNumCompat'],
						$result['maxNumCompat'] > 1 ? 'ies' : 'y',
						$result['maxItemId'],
						$result['maxSeller'],
						$result['maxNumSold']), FILE_APPEND);
			}
			else
			{
				file_put_contents("../e_auto_compat.txt",
					sprintf("[%s] Our listing %s had %d compatibilit%s, and was sold %dx. Integra tried to copy %d compatibilit%s from listing %s of %s, which was sold %dx, but encountered an error. %s\r\n",
						date_format($date, 'Y-m-d H:i:s'),
						$itemId,
						$result['ourNumCompat'],
						$result['ourNumCompat'] > 1 ? 'ies' : 'y',
						$result['ourNumSold'],
						$result['maxNumCompat'],
						$result['maxNumCompat'] > 1 ? 'ies' : 'y',
						$result['maxItemId'],
						$result['maxSeller'],
						$result['maxNumSold'],
						$message), FILE_APPEND);
			}
		}
		catch (Exception $e)
		{
			file_put_contents("../e_auto_compat.txt",
				sprintf("[%s] An exception occurred while processing listing %s. %s\r\n",
					date_format($date, 'Y-m-d H:i:s'),
					$itemId,
					$e->getMessage()), FILE_APPEND);
		}

		try
		{
			EbayUtils::ScrapeItem($itemId);
		}
		catch (Exception $e)
		{
		}
	}

	mysql_close();
}

return;

function GetStats($itemId)
{
	$seller = EBAY_SELLER;
	$result = array();

	$q = <<<EOD
SELECT num_compat, num_sold
FROM ebay_grid
WHERE active = 1
AND this_item = '${itemId}'
AND seller = '${seller}'
EOD;
	$res = mysql_query($q);
	$row = mysql_fetch_row($res);
	if (empty($row))
		return null;

	$ourNumCompat = $row[0];
	$result['ourNumCompat'] = $ourNumCompat;
	$result['ourNumSold'] = $row[1];
	
	$q = <<<EOD
SELECT this_item, seller, num_compat, num_sold
FROM ebay_grid
WHERE active = 1
AND item_id = '${itemId}'
AND seller != 'qeautoparts1'
AND num_compat > ${ourNumCompat}
ORDER BY num_compat DESC , num_sold DESC , score DESC 

EOD;
	$res = mysql_query($q);
	$row = mysql_fetch_row($res);
	if (empty($row))
		return null;

	$result['maxItemId'] = $row[0];
	$result['maxSeller'] = $row[1];
	$result['maxNumCompat'] = $row[2];
	$result['maxNumSold'] = $row[3];
	
	return $result;	
}

?>
