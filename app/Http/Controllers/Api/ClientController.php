<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::withCount('transactions')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $clients
        ]);
    }

    public function show(Client $client)
    {
        $client->load(['transactions' => function($query) {
            $query->with(['gateway', 'products'])->latest();
        }]);

        return response()->json([
            'success' => true,
            'data' => $client
        ]);
    }
}