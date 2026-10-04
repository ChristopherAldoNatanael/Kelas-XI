<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;

class PublicClinicController extends Controller
{
    /**
     * GET /api/public/clinics
     * List all active clinics
     */
    public function index()
    {
        $clinics = Clinic::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'address' => $c->address,
                'phone' => $c->phone,
                'email' => $c->email,
                'logo_url' => $c->logo_url,
                'primary_color' => $c->primary_color,
                'description' => $c->description,
            ]);

        return response()->json([
            'success' => true,
            'data' => $clinics,
        ]);
    }

    /**
     * GET /api/public/clinics/{slug}
     * Show clinic detail with counts
     */
    public function show(string $slug)
    {
        $clinic = Clinic::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'Klinik tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'address' => $clinic->address,
                'phone' => $clinic->phone,
                'email' => $clinic->email,
                'logo_url' => $clinic->logo_url,
                'primary_color' => $clinic->primary_color,
                'description' => $clinic->description,
                'doctors_count' => $clinic->doctors()->where('is_active', true)->count(),
                'services_count' => $clinic->services()->where('is_active', true)->count(),
            ],
        ]);
    }
}