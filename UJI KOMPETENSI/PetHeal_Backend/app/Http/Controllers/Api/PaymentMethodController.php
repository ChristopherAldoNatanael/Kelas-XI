<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentMethodController extends Controller
{
    /**
     * Get all active payment methods
     */
    public function index()
    {
        $methods = Cache::remember('payment_methods', 21600, function () {
            return PaymentMethod::where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $methods,
        ]);
    }
}
