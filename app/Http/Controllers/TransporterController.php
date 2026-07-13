<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transporter;

class TransporterController extends Controller
{
    /**
     * Get all transporters.
     */
    public function index()
    {
        $transporters = Transporter::orderBy('description')->get();
        
        return response()->json([
            'success' => true,
            'data' => $transporters
        ]);
    }
}
