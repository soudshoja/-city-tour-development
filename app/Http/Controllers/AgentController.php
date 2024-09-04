<?php
// app/Http/Controllers/AgentController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;

class AgentController extends Controller
{
    public function index()
    {
        $agents = Agent::all();

        return view('agentsList', compact('agents'));
    }
}
