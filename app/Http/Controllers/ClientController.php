<?php

namespace App\Http\Controllers;

use App\Dealer;
use App\Client;
use App\TransactionDetail;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function getClients()
    {
        try {
            $query = Client::query();

            if (auth()->check() && auth()->user()->usesSrpPricing()) {
                $chiefMfi = trim((string) optional(auth()->user()->centerChief)->mfi);

                if ($chiefMfi === '') {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereRaw('LOWER(TRIM(mfi)) = ?', [strtolower($chiefMfi)]);
                }
            }

            $clients = $query->get()->all();
            
            $clientsArray = array_map(function($client) {
                $clientData = (array) $client;
                
                foreach ($clientData as $key => $value) {
                    if (is_null($value)) {
                        $clientData[$key] = '';
                    }
                }
                
                return $clientData;
            }, $clients);
            
            return response()->json($clientsArray);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch clients: ' . $e->getMessage()
            ], 500);
        }
    }
}
