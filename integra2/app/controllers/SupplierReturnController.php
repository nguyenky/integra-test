<?php

class SupplierReturnController extends \BaseController
{
    public function index()
    {
        return IntegraUtils::paginate(SupplierReturn::get()->toArray());
    }

    public function show($id)
    {
        $return = SupplierReturn::with('items')->find($id)->toArray();
        $raDoc = public_path() . "/ra_doc/" . $return['id'] . ".htm";
        $return['has_doc'] = file_exists($raDoc);

        return $return;
    }

    public function create()
    {
        $res = array();
        $results = array();
        
        try {
            
            $results = SupplierReturn::getSupplierByFilter();

        } catch(Exception $ex) {
            Log::info('============ Exception ============');
            Log::error($ex->getMessage());
        }
        
        
        return $results;
    }

    public function store()
    {
        $items = Input::all();

        $supplierReturns = [];

        foreach ($items as $item)
        {
            $supplierReturns[$item['supplier_id']][] =
                [
                    'invoice_num' => $item['invoice_num'],
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'reason' => $item['reason']['title'],
                    'reason_id' => $item['reason']['id']
                ];
        }

        foreach ($supplierReturns as $supplierId => $items)
        {
            $supplier = Supplier::where('id', $supplierId)->pluck('name');

            if ($supplier == 'IMC')
            {
                $return = ImcUtils::RequestRa($items);
                if (empty($return))
                    return Response::json("Error while submitting request to warehouse {$supplierId}", 500);

                $returnId = $return->id;
            }
            else if ($supplier == 'SSF')
            {
                $return = SsfUtils::RequestRaByEmail($items);
                if (empty($return))
                    return Response::json("Error while submitting request to warehouse {$supplierId}", 500);

                $returnId = $return->id;
            }
        }

        if (count($supplierReturns) > 1)
            $returnId = 0;

        return $returnId;
    }
}
