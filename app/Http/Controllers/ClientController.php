<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return view('clients.index');
    }

    public function list($id = null)
    {

        $clients = Client::all();

        if (isset($id)) {
            $clients = $clients->where('agent_id', $id);
        }

        return view('clients.list', compact('clients'));
    }
}
