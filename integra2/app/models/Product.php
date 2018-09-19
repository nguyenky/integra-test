<?php

class Product extends Eloquent
{
    public $timestamps = false;

    public function components()
    {
        return $this->belongsToMany('Product', 'kit_components', 'product_id', 'component_product_id')->withPivot('id', 'quantity');
    }

    public function kits()
    {
        return $this->belongsToMany('Product', 'kit_components', 'component_product_id', 'product_id');
    }

    public function supplier()
    {
        return $this->belongsTo('Supplier');
    }

    public function compatibilities()
    {
        return $this->hasMany('Compatibility', 'sku', 'sku');
    }

    public function codes()
    {
        return $this->hasMany('ProductCode');
    }

    public function ebayListings()
    {
        return $this->hasMany('EbayListing', 'sku', 'sku');
    }

    public function supplierQuantity()
    {
        return $this->hasMany('SupplierQuantity', 'mpn', 'sku');
    }

    public function warehouses()
    {
        return $this->hasMany('Warehouse');
    }

    public static function bySku($sku)
    {
        return Product::where('sku', $sku)->first();
    }

    public static function updateKitsComponents($matches) {
        if(!empty($matches)) {
            Log::info("============ Product model update kit ===========");
            $sql = "UPDATE kit_components SET component_product_id = CASE ";
            foreach($matches as $match) {
                $sql .= " 
                        WHEN (product_id = ". $match['productId'] ." AND component_product_id = ". $match['oldMatchProductId'] 
                             .") THEN ".$match['newMatchProductId'];
            }
            $sql .= " ELSE component_product_id END; ";
            //Log::info($sql);

            return DB::update($sql);
        }
        return false;
    }

    public static function searchBySku($search) {
        $products = Product::where("sku", "LIKE", '%' .$search. '%')->take(20)->get();
        return array_column($products->toArray(), 'sku');
    }

    public static function byCode($code)
    {
        return Product::where('sku', $code)->orWhereHas('codes', function($q) use($code)
        {
            $q->where('code', $code);
        });
    }
}
