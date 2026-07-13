<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transporter;
use Illuminate\Http\JsonResponse;

class TransporterController extends Controller
{
    /**
     * Get all transporters.
     */
    public function index(): JsonResponse
    {
        $transporters = Transporter::orderBy('description')
            ->get(['id', 'value', 'description']);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Transporters retrieved successfully.',
            'data' => $transporters,
        ], 200);
    }
}
