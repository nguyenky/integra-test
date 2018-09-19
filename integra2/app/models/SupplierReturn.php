<?php

class SupplierReturn extends Eloquent
{
    public $timestamps = false;

    public function items()
    {
        return $this->hasMany('SupplierReturnItem');
    }

    public function supplier()
    {
        return $this->belongsTo('Supplier', 'supplier_id', 'id');
    }

    public static function getSupplierByFilter() {
    	$sql = "
    		SELECT sii.id AS item_id, si.order_date, si.supplier_id, si.invoice_num, si.order_num, si.po_num, sii.sku, sii.quantity as max_quantity, sii.unit_price
    		FROM supplier_invoices si, supplier_invoice_items sii
    		WHERE si.id = sii.supplier_invoice_id 
    	";

    	$countSql = "
    		SELECT count(*) as total FROM supplier_invoices si, supplier_invoice_items sii 
    		WHERE si.id = sii.supplier_invoice_id 
    	";

    	$filters = array();
    	$dates = array();
    	if(Input::has('filter')) {
    		
    		$foundDate = 0;
    		foreach (Input::get('filter') as $fields => $value) {

    			$fields = urldecode($fields);
    			
    			$tmp = explode('@', $fields);
    			if(count($tmp) > 1) {
    				$value = str_replace('"', '', $value);
    				$d = DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $value);
    				$dates[$foundDate] = $d->format('Y-m-d');
    				$foundDate += 1;
    				
    			} else {
    				array_push($filters, $fields.' = '.$value);
    			}
    		}

    		$filters = implode(' AND ', $filters);

    		if(!empty($filters)) {
    			$sql .= " AND ".$filters;

    			$countSql .= " AND ".$filters;
    		}

    	}
    	
    	if(!empty($dates) && count($dates) > 1) {
    		$sql .= " AND (order_date BETWEEN '". $dates[0]. "' AND '".$dates[1] ."' )";
    		$countSql .= " AND (order_date BETWEEN '". $dates[0]. "' AND '".$dates[1] ."' )";
    	}

    	$offset = 0;

    	if (Input::has('count')) {
    	    
    	    if (Input::has('page'))
    	        $offset = (Input::get('page') - 1) * Input::get('count');
    	}

    	$sql .= " LIMIT ". Input::get('count'). " OFFSET ".$offset;

    	Log::info($sql);
    	Log::info($countSql);

    	$records = DB::select($sql);
    	$total = DB::select($countSql)[0]['total'];

    	return array('result' => $records, 'total' => $total);

    }
}
