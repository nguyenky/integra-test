<?php

class EbayEtemNeedCalculateShipping extends Eloquent
{
	protected $connection= 'mysql_eoc';

    protected $table = 'ebay_item_need_calculate_shipping';

    public $timestamps = false;
}
