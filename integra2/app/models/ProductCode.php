<?php

class ProductCode extends Eloquent
{
    public $timestamps = false;
    protected $fillable = ['product_id', 'code'];

    public function product()
    {
        return $this->belongsTo('Product');
    }

    public static function clean($code)
    {
        $code = preg_replace("/[^A-Z0-9.]/", '', strtoupper(str_replace('/', '.', trim($code))));

        if (stripos($code, '.') !== false && stripos($code, 'EOCS') === 0)
            $code = substr($code, 4);
        else if (stripos($code, 'EOC') === 0) $code = substr($code, 3);

        return $code;
    }
}
