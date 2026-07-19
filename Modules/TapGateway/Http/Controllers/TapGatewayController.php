<?php

namespace Modules\TapGateway\Http\Controllers;

use App\Http\Controllers\Controller;

class TapGatewayController extends Controller
{
    /**
     * Display the module welcome screen
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('TapGateway::index');
    }
}
