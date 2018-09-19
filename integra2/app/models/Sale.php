<?php

class Sale extends Eloquent
{
	public static function getSaleRecord($search) {
		$sales = Sale::where('id', 'LIKE', '%'.$search.'%')->take(20)->get();
		return array_column($sales->toArray(), 'id');
	}
}