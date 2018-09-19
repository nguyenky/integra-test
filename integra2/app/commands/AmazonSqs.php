<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Aws\Sqs\SqsClient;

class AmazonSqs extends Command
{
	protected $name = 'amazon:sqs';
    protected $description = 'Processes SQS data';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
	{
		set_time_limit(0);
		ini_set('memory_limit', '768M');
        DB::disableQueryLog();

        Log::info("================ START AMAZON JOB AT ". date('Y-m-d H:i:s') ." ==============");

		$config = Config::get('integra');
		$sqs = SqsClient::factory(Config::get('queue.connections.sqs'));

		while (true)
		{
			try
			{
				$res = $sqs->receiveMessage(['QueueUrl' => Config::get('queue.connections.sqs.queue'), 'MaxNumberOfMessages' => 10]);

				#Log::info("Response: ".serialize($res));
				// empty response, quit
				if (empty($res)) break;

				$msgs = $res['Messages'];

				#Log::info("Response Messages: ".serialize($msgs));

				// no more messages, quit
				if (empty($msgs)) break;

				foreach ($msgs as $msg)
				{
					try
					{
						$xml = simplexml_load_string($msg['Body']);
						#Log::info("XML: ".serialize($xml));
						$json = json_encode($xml);
						$m = json_decode($json, TRUE);

						// ignore if not a AnyOfferNotification message
						if (!isset($m['NotificationPayload']['AnyOfferChangedNotification'])) break;

						$root = $m['NotificationPayload']['AnyOfferChangedNotification'];
						$asin = $root['OfferChangeTrigger']['ASIN'];
						$ts = str_replace('T', ' ', explode('.', $root['OfferChangeTrigger']['TimeOfOfferChange'])[0]);
						$tsDate = explode(' ', $ts)[0];

						echo "Processing message for $asin with timestamp $ts\n";

						// loop through array first to get our current price for this asin
						$ourPrice = null;

						foreach ($root['Offers']['Offer'] as $offer)
						{
							try
							{
								// filter by our seller ID
								if ($offer['SellerId'] == $config['amazon_mws']['merchant_id'])
								{
									$ourPrice = floatval($offer['ListingPrice']['Amount']) + floatval($offer['Shipping']['Amount']);
									break;
								}
							}
							catch (Exception $e)
							{
								Log::error("Error while looping through offers. " . $e->getTraceAsString() . "\n");
								echo "Error while looping through offers. " . $e->getTraceAsString() . "\n";
							}
						}

						if (empty($ourPrice))
						{
							Log::info("Can't retrieve our current price for ASIN\n");
							echo "Can't retrieve our current price for ASIN\n";
						}

						// now go through the loop again to process everyone's prices
						foreach ($root['Offers']['Offer'] as $offer)
						{
							try
							{
								// ignore if not new or remanufactured
								$cond = strtolower($offer['SubCondition']);
								if ($cond != 'new' && $cond != 'remanufactured') continue;

								$seller = $offer['SellerId'];
								$price = floatval($offer['ListingPrice']['Amount']) + floatval($offer['Shipping']['Amount']);
								$fba = ($offer['IsFulfilledByAmazon'] == 'true');
								$buybox = ($offer['IsBuyBoxWinner'] == 'true');

								// store the latest price for those that we are monitoring
								DB::update(<<<EOQ
UPDATE integra_prod.amazon_monitor
SET latest_price = ?, ts = NOW()
WHERE asin = ?
AND seller = ?
AND fba = ?
EOQ
										, [$price, $asin, $seller, $fba ? 1 : 0]);

								$ignore = true;

								// get last known buybox status
								$curBuybox = DB::select(<<<EOQ
SELECT buybox
FROM integra_prod.amazon_price_summary
WHERE asin = ?
AND seller = ?
AND fba = ?
ORDER BY ts DESC
LIMIT 1
EOQ
										, [$asin, $seller, $fba ? 1 : 0]);

								// currently in buybox but went out
								if (!empty($curBuybox) && !empty($curBuybox[0]['buybox']) && !$buybox)
								{
									echo "Seller $seller went out of buybox\n";
									// make sure this event is logged into history
									$ignore = false;

									// set flag of outof_buybox
									DB::update(<<<EOQ
UPDATE integra_prod.amazon_monitor
SET outof_buybox = 1, ts = NOW()
WHERE asin = ?
AND seller = ?
AND fba = ?
AND outof_buybox != 1
EOQ
											, [$asin, $seller, $fba ? 1 : 0]);
								}
								// currently not in buybox but went in
								else if (!empty($curBuybox) && empty($curBuybox[0]['buybox']) && $buybox)
								{
									echo "Seller $seller went into buybox\n";
									// make sure this event is logged into history
									$ignore = false;

									// set flag of into_buybox
									DB::update(<<<EOQ
UPDATE integra_prod.amazon_monitor
SET into_buybox = 1, ts = NOW()
WHERE asin = ?
AND seller = ?
AND fba = ?
AND into_buybox != 1
EOQ
											, [$asin, $seller, $fba ? 1 : 0]);
								}

								// get min/max prices for today for this asin + seller
								$curPrices = DB::select(<<<EOQ
SELECT min_price, max_price
FROM integra_prod.amazon_price_summary
WHERE asin = ?
AND seller = ?
AND fba = ?
AND ts = ?
ORDER BY id
LIMIT 1
EOQ
										, [$asin, $seller, $fba ? 1 : 0, $tsDate]);

								// no entries for today for this asin + seller yet, set values that will surely get overriden
								if (empty($curPrices))
								{
									$minPrice = 9999999;
									$maxPrice = 0;
								}
								else
								{
									// entries found, copy into variables
									$minPrice = $curPrices[0]['min_price'];
									$maxPrice = $curPrices[0]['max_price'];
									if (empty($minPrice)) $minPrice = 9999999;
									if (empty($maxPrice)) $maxPrice = 0;
								}

								// if current's price is greater than max price for today, store this
								if ($price > $maxPrice)
								{
									$maxPrice = $price;
									$ignore = false;
								}

								// if current's price is less than min price for today, store this
								if ($price < $minPrice)
								{
									$minPrice = $price;
									$ignore = false;
								}

								// if this seller is our competitor and is less than ours
								if (!empty($ourPrice) && $price < $ourPrice && $seller != $config['amazon_mws']['merchant_id'])
								{
									// load monitor settings for this competitor
									$monitorSettings = DB::select(<<<EOQ
SELECT strategy, min_price
FROM integra_prod.amazon_monitor
WHERE asin = ?
AND seller = ?
AND fba = ?
LIMIT 1
EOQ
											, [$asin, $seller, $fba ? 1 : 0]);


									// we are monitoring this seller and need to reprice
									if (!empty($monitorSettings))
									{
										// match
										if ($monitorSettings[0]['strategy'] == 1)
										{
											$newPrice = max($monitorSettings[0]['min_price'], $price);
											echo "Matching price $price of $seller. Repricing to $newPrice\n";
										}
										// go under
										else if ($monitorSettings[0]['strategy'] == 2)
										{
											$newPrice = max($monitorSettings[0]['min_price'], $price - 0.01);
											echo "Going under price $price of $seller. Repricing to $newPrice\n";
										}
										else
										{
											// invalid value for strategy. set newPrice = ourPrice to disable repricing
											$newPrice = $ourPrice;
											echo "Unknown repricing strategy " . $monitorSettings[0]['strategy'] . " for $seller with price $price\n";
										}

										// queue repricing request if newPrice is different from our current price
										if ($newPrice != $ourPrice)
										{
											DB::insert(<<<EOQ
INSERT INTO integra_prod.amazon_repricer_queue (asin, price, queue_date, proc_date, feed_id)
VALUES (?, ?, NOW(), NULL, '') ON DUPLICATE KEY
UPDATE price = IF(VALUES(price) < price, VALUES(price), price)
EOQ
													, [$asin, $newPrice]);
										}


										// competitor is below our minimum possible price
										if ($price < $monitorSettings[0]['min_price'])
										{
											// set below_min flag
											DB::update(<<<EOQ
UPDATE integra_prod.amazon_monitor
SET below_min = 1, ts = NOW()
WHERE asin = ?
AND seller = ?
AND fba = ?
AND below_min != 1
EOQ
													, [$asin, $seller, $fba ? 1 : 0]);
										}
									}
									// no monitor entry for this asin + seller. repricing disabled
									else
									{
										echo "Repricing not enabled against seller $seller with lower price of $price vs. our price of $ourPrice\n";
									}
								}

								// data is not noise, must be logged to today's price history
								if (!$ignore)
								{
									DB::insert(<<<EOQ
INSERT INTO integra_prod.amazon_price_summary
(asin, ts, seller, fba, min_price, max_price, buybox)
VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY
UPDATE min_price = VALUES(min_price), max_price = VALUES(max_price), buybox = VALUES(buybox)
EOQ
											, [$asin, $tsDate, $seller, $fba ? 1 : 0, $minPrice, $maxPrice, $buybox ? 1 : 0]);
								}
							}
							catch (Exception $e)
							{
								echo "Error while looping through offers. " . $e->getMessage() . "\n";
							}
						}

						DB::delete(<<<EOQ
DELETE sc
FROM eoc.amazon_scraper sc
WHERE EXISTS (SELECT 1 FROM integra_prod.amazon_price_summary aps1 WHERE aps1.ts = CURDATE() AND aps1.asin = ?)
AND sc.asin = ?
AND NOT EXISTS (
SELECT 1
FROM integra_prod.amazon_price_summary aps2
WHERE aps2.asin = ?
AND aps2.seller = sc.seller_code
AND aps2.ts = CURDATE()
)
EOQ
						, [$asin, $asin, $asin]);

						$res2 = $sqs->deleteMessage(['QueueUrl' => Config::get('queue.connections.sqs.queue'), 'ReceiptHandle' => $msg['ReceiptHandle']]);
					}
					catch (Exception $e2)
					{
						Log::error("Error while looping through messages E2. " . $e2->getTraceAsString() . "\n");
						echo "Error while looping through messages . " . $e2->getMessage() . "\n";
					}
				}
			}
			catch (Exception $e3)
			{
				Log::error("Error while looping through offers E3. " . $e3->getTraceAsString() . "\n");
				echo "Error while looping through offers. " . $e3->getMessage() . "\n";
			}
		}
	}
}
