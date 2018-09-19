<?php
class SalesItem extends Eloquent {
	protected $table = 'sales_items';

	public static function getItemIdAsin($search) {
		$items = SalesItem::where('ebay_item_id', 'LIKE', '%'.$search.'%')->orWhere('amazon_asin', 'LIKE', '%'.$search.'%')->take(30)->get();
		
		$items = $items->toArray();
		$itemIds = array_filter(array_column($items, 'ebay_item_id'));
		$asins = array_filter(array_column($items, 'amazon_asin'));
		
		return array_merge($asins, $itemIds);
	}
}