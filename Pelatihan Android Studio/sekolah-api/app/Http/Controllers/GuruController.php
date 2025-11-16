<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GuruController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Guru::query();

            // Filter berdasarkan kode jika ada
            if ($request->has('kode') && !empty($request->kode)) {
                $query->byKode($request->kode);
            }

            // Filter berdasarkan nama jika ada
            if ($request->has('nama') && !empty($request->nama)) {
                $query->byNama($request->nama);
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $gurus = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diambil',
                'data' => $gurus
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), Guru::rules(), Guru::messages());

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buat guru baru
            $guru = Guru::create($request->only(['kode', 'nama_guru', 'telepon']));

            return response()->json([
                'success' => true,
                'message' => 'Guru berhasil ditambahkan',
                'data' => $guru
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $guru = Guru::find($id);

            if (!$guru) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diambil',
                'data' => $guru
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $guru = Guru::find($id);

            if (!$guru) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru tidak ditemukan'
                ], 404);
            }

            // Validasi input dengan mengabaikan unique untuk record saat ini
            $rules = Guru::rules();
            $rules['kode'] = 'required|string|max:20|unique:gurus,kode,' . $id;
            $rules['nama_guru'] = 'required|string|max:255';
            $rules['telepon'] = 'nullable|string|max:20';

            $validator = Validator::make($request->all(), $rules, Guru::messages());

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update guru
            $guru->update($request->only(['kode', 'nama_guru', 'telepon']));

            return response()->json([
                'success' => true,
                'message' => 'Guru berhasil diperbarui',
                'data' => $guru
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $guru = Guru::find($id);

            if (!$guru) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru tidak ditemukan'
                ], 404);
            }

            // Hapus guru
            $guru->delete();

            return response()->json([
                'success' => true,
                'message' => 'Guru berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get guru by kode
     */
    public function getByKode(string $kode): JsonResponse
    {
        try {
            $guru = Guru::byKode($kode)->first();

            if (!$guru) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru dengan kode tersebut tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diambil',
                'data' => $guru
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
