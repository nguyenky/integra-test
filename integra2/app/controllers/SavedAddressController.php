<?php

class SavedAddressController extends \BaseController
{
    public function index()
    {
        return SavedAddress::get()->toArray();
    }
}
