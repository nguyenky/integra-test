<?php 

require_once('config.php');

class EbayGridModel {

	public function __construct() {
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
	}

	public function addOrUpdateListItems($items) {
		foreach($items as $item) {
			$this->CreateOrUpdate($item);
		}
	}

	public function updateSummaryItems($activeItems) {
		foreach($activeItems as $key => $items) {
			$this->updateItemNotInEgrid($items['active_ids'], $key);

			$priceDiff = $items['ourTotal'] - $items['lowTotal'];
			if ($items['lowTotal'] > 0)
			    $priceDiffPct = $priceDiff * 100 / $items['lowTotal'];
			else

			    $priceDiffPct = 0;
			$soldDiff = $items['topSold' - $items['ourSold'];
			if ($items['ourSold'] > 0)
			    $soldDiffPct = $soldDiff * 100 / $ourSold;
			else

			    $soldDiffPct = $soldDiff * 100;
			if ($items['topSold'] == 0)
			    $items['topSoldSeller'] = '';
			$this->updateSummaryInfo($key, $items, $priceDiff, $priceDiffPct, $soldDiff, $soldDiffPct);
		}
	}

	public function updateSummaryInfo($itemId, $item, $priceDiff, $priceDiffPct, $soldDiff, $soldDiffPct) {
		$sql = "
			INSERT INTO eoc.ebay_grid_summary (item_id, mpn, brand, our_total, low_total, low_total_seller, price_diff, price_diff_pct, our_sold, top_sold, top_sold_seller, sold_diff, sold_diff_pct, active)

			VALUES('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 1)

			ON DUPLICATE KEY UPDATE

			    mpn = VALUES(mpn),

			    brand = VALUES(brand),

			    our_total = VALUES(our_total),

			    low_total = VALUES(low_total),

			    low_total_seller = VALUES(low_total_seller),

			    price_diff = VALUES(price_diff),

			    price_diff_pct = VALUES(price_diff_pct),

			    our_sold = VALUES(our_sold),

			    top_sold = VALUES(top_sold),

			    top_sold_seller = VALUES(top_sold_seller),

			    sold_diff = VALUES(sold_diff),

			    sold_diff_pct = VALUES(sold_diff_pct),

			    timestamp = NOW(),

			    active = 1
		";

		$query = sprintf($sql, 
                cleanup($itemId),
                cleanup($item['comp_mpn']),
                cleanup($item['comp_brand']),
                cleanup($$item['ourTotal']),
                cleanup($$item['lowTotal']),
                cleanup($$item['lowTotalSeller']),
                cleanup($priceDiff),
                cleanup($priceDiffPct),
                cleanup($item['ourSold']),
                cleanup($item['topSold']),
                cleanup($item['topSoldSeller']),
                cleanup($soldDiff),
                cleanup($soldDiffPct));

		mysql_query($query);
	}

	public function updateItemNotInEgrid($activeIds, $itemId) {
		mysql_query(sprintf("UPDATE eoc.ebay_grid SET active = 0 WHERE item_id = '${itemId}' AND this_item NOT IN ('%s')",
		    implode("','", $activeIds)));

	}

	public function CreateOrUpdate($item) {
		$sql = "
			INSERT INTO eoc.ebay_grid (item_id, this_item, active, title, image_url, big_image_url, price, shipping, seller, score, rating, top, pos, num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, placement, brand, comp_mpn, comp_brand, comp_name, part_notes, comp_weight)

			VALUES('%s', '%s', 1, '%s', %s, %s, '%s', '%s', '%s', '%s', '%s', '%s', 0, '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s)

			ON DUPLICATE KEY UPDATE

			    active = 1,

			    title = VALUES(title),

			    image_url = VALUES(image_url),

			    big_image_url = VALUES(big_image_url),

			    price = VALUES(price),

			    shipping = VALUES(shipping),

			    seller = VALUES(seller),

			    score = VALUES(score),

			    rating = VALUES(rating),

			    top = VALUES(top),

			    pos = VALUES(pos),

			    num_hit = GREATEST(num_hit, VALUES(num_hit)),

			    num_sold = VALUES(num_sold),

			    num_compat = VALUES(num_compat),

			    timestamp = NOW(),

			    category = VALUES(category),

			    mpn = VALUES(mpn),

			    ipn = VALUES(ipn),

			    opn = VALUES(opn),

			    placement = VALUES(placement),

			    brand = VALUES(brand),

			    comp_mpn = VALUES(comp_mpn),

			    comp_brand = VALUES(comp_brand),

			    comp_name = VALUES(comp_name),

			    part_notes = VALUES(part_notes),

			    comp_weight = VALUES(comp_weight)
		";

		$qw = sprintf($sql,
		    cleanup($item['id']),
		    cleanup($item['id']),
		    cleanup($item['title']),
		    empty($item['picture_small']) ? 'NULL' : "'" . cleanup($item['picture_small']) . "'",
		    empty($item['picture_big']) ? 'NULL' : "'" . cleanup($item['picture_big']) . "'",
		    cleanup($item['price']),
		    cleanup($item['shipping_cost']),
		    cleanup($item['seller_id']),
		    cleanup($item['seller_score']),
		    cleanup($item['seller_rating']),
		    cleanup($item['seller_top']),
		    ($item['num_hit'] == '-1') ? 0 : $item['num_hit'],
		    cleanup($item['num_sold']),
		    cleanup($item['num_compat']),
		    cleanup($item['num_avail']),
		    cleanup($item['category']),
		    cleanup($item['mpn']),
		    cleanup($item['ipn']),
		    cleanup($item['opn']),
		    cleanup($item['placement']),
		    cleanup($item['brand']),
		    cleanup($item['comp_mpn']),
		    cleanup($item['comp_brand']),
		    cleanup($item['comp_name']),
		    cleanup($item['part_notes']),
		    empty($item['comp_weight']) ? 'NULL' : "'" . cleanup($item['comp_weight']) . "'");
		mysql_query($qw);
	}
}

?>