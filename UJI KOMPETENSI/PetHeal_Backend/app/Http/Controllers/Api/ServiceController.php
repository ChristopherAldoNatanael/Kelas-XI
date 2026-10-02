<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Cache::remember('services_active', 21600, function () {
            return Service::active()->orderBy('name')->get();
        });

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    public function show($id)
    {
        $service = Cache::remember("service_{$id}", 21600, function () use ($id) {
            return Service::findOrFail($id);
        });

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }
}
