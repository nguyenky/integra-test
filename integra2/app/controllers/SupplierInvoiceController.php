<?php

class SupplierInvoiceController extends \BaseController
{
    public function index()
    {
        return IntegraUtils::paginate(SupplierInvoice::get()->toArray());
    }

    public function show($id)
    {
        return SupplierInvoice::with('items')->find($id);
    }

    public function itemQuantities($invoiceNum)
    {
        $invoice = SupplierInvoice::where('invoice_num', $invoiceNum)->first();
        if (empty($invoice)) return null;

        return $invoice->items()->select('sku', 'quantity')->get();
    }
}
