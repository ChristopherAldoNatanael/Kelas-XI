<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pendaftaran pasien datang langsung dan darurat dari web admin.
 *
 * Kunjungan tetap memakai Booking agar nomor antrian, papan antrian, dan form
 * rekam medis yang sudah ada bekerja tanpa jalur kedua. Pembeda kunjungan
 * disimpan di `service_type` (walk_in/emergency) dan `triage_level`.
 */
class WalkInController extends Controller
{
    public function __construct(private readonly QueueService $queue)
    {
    }

    public function index(Request $request)
    {
        $clinicId = currentClinicId();

        if (! $clinicId) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Pilih klinik terlebih dahulu sebelum membuka pendaftaran walk-in.');
        }

        $date = today()->toDateString();

        $visits = Booking::with(['pet', 'doctor', 'user'])
            ->where('clinic_id', $clinicId)
            ->where('booking_date', $date)
            ->whereIn('service_type', ['walk_in', 'emergency'])
            ->orderByDesc('created_at')
            ->get();

        $doctors = Doctor::where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $owner = null;
        $ownerId = (int) $request->input('owner_id', 0);

        if ($ownerId) {
            $owner = User::with('pets')->where('role', 'user')->find($ownerId);
        }

        $ownerQuery = trim((string) $request->input('owner_q', ''));
        $ownerResults = collect();

        if ($ownerQuery !== '' && ! $owner) {
            $ownerResults = User::where('role', 'user')
                ->where(function ($query) use ($ownerQuery) {
                    $like = '%' . $ownerQuery . '%';
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                })
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'email', 'phone']);
        }

        return view('admin.walk-ins.index', compact(
            'visits',
            'doctors',
            'owner',
            'ownerQuery',
            'ownerResults',
            'date'
        ));
    }

    /**
     * Pencarian pemilik untuk autocomplete form walk-in.
     */
    public function searchOwners(Request $request)
    {
        $query = trim((string) $request->input('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $owners = User::with(['pets:id,user_id,name,species'])
            ->where('role', 'user')
            ->where(function ($builder) use ($query) {
                $like = '%' . $query . '%';
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json([
            'data' => $owners->map(fn (User $owner) => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'pets' => $owner->pets->map(fn (Pet $pet) => [
                    'id' => $pet->id,
                    'name' => $pet->name,
                    'species' => $pet->species,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $clinicId = currentClinicId();

        if (! $clinicId) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Pilih klinik terlebih dahulu.');
        }

        $validated = $request->validate([
            'visit_type' => 'required|in:walk_in,emergency',
            'owner_mode' => 'required|in:existing,new',
            'existing_owner_id' => 'nullable|required_if:owner_mode,existing|integer|exists:users,id',
            'pet_id' => 'nullable|required_if:owner_mode,existing|integer|exists:pets,id',
            'owner_name' => 'nullable|string|max:120|required_if:owner_mode,new',
            'owner_email' => 'nullable|email|max:190',
            'owner_phone' => 'nullable|string|max:30',
            'pet_name' => 'nullable|string|max:120|required_if:owner_mode,new',
            'species' => 'nullable|string|max:60|required_if:owner_mode,new',
            'breed' => 'nullable|string|max:100',
            'gender' => 'nullable|in:male,female,unknown',
            'doctor_id' => 'required|integer|exists:doctors,id',
            'triage_level' => 'nullable|required_if:visit_type,emergency|in:red,orange,yellow,green',
            'complaint' => 'nullable|string|max:2000',
            'needs_payment' => 'nullable|boolean',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $doctor = Doctor::where('id', $validated['doctor_id'])
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->firstOrFail();

        $owner = null;
        $pet = null;

        if ($validated['owner_mode'] === 'existing') {
            $owner = User::where('role', 'user')
                ->whereKey($validated['existing_owner_id'] ?? 0)
                ->firstOrFail();

            $pet = Pet::where('user_id', $owner->id)
                ->whereKey($validated['pet_id'] ?? 0)
                ->firstOrFail();
        }

        $booking = DB::transaction(function () use ($validated, $clinicId, $doctor, &$owner, &$pet) {
            if ($validated['owner_mode'] === 'new') {
                $email = trim((string) ($validated['owner_email'] ?? ''));

                if ($email !== '') {
                    $owner = User::where('email', $email)->where('role', 'user')->first();
                }

                if (! $owner) {
                    $owner = User::create([
                        'name' => $validated['owner_name'],
                        'email' => $email !== '' ? $email : 'walkin.' . Str::lower(Str::random(10)) . '@petheal.local',
                        'phone' => $validated['owner_phone'] ?? null,
                        'password' => Hash::make(Str::random(32)),
                        'role' => 'user',
                    ]);
                }

                $pet = Pet::create([
                    'user_id' => $owner->id,
                    'name' => $validated['pet_name'],
                    'species' => $validated['species'],
                    'breed' => $validated['breed'] ?? null,
                    'gender' => $validated['gender'] ?? 'unknown',
                ]);
            }

            $needsPayment = (bool) ($validated['needs_payment'] ?? false);
            $amount = $needsPayment ? (float) ($validated['amount'] ?? 0) : 0.0;
            $complaint = trim((string) ($validated['complaint'] ?? ''));
            $typeLabel = $validated['visit_type'] === 'emergency' ? 'DARURAT' : 'WALK-IN';

            $booking = Booking::create([
                'user_id' => $owner->id,
                'pet_id' => $pet->id,
                'doctor_id' => $doctor->id,
                'clinic_id' => $clinicId,
                'booking_date' => today()->toDateString(),
                'booking_time' => now()->format('H:i:s'),
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'service_type' => $validated['visit_type'],
                'triage_level' => $validated['visit_type'] === 'emergency'
                    ? $validated['triage_level']
                    : null,
                'notes' => mb_substr(
                    '[' . $typeLabel . '] ' . ($complaint !== '' ? 'Keluhan: ' . $complaint : 'Kunjungan langsung'),
                    0,
                    2000
                ),
                'payment_method' => 'cash',
                'payment_type' => $needsPayment ? 'full' : null,
                'total_amount' => $amount,
                'paid_amount' => 0,
                'remaining_amount' => $amount,
                'payment_status' => $needsPayment ? 'unpaid' : 'not_required',
            ]);

            AuditLog::log(
                'booking.' . $validated['visit_type'] . '.created',
                "Pendaftaran {$typeLabel} booking #{$booking->id} untuk {$pet->name}",
                $booking
            );

            return $booking;
        });

        try {
            $booking = $this->queue->checkIn($booking, today()->toDateString(), (string) auth()->id());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.walk-ins.index')
                ->with('error', 'Kunjungan tersimpan, tetapi nomor antrian gagal dibuat: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.walk-ins.index')
            ->with('success', 'Kunjungan ' . $booking->pet->name . ' terdaftar dengan nomor '
                . QueueService::label($booking->queue_number) . '.');
    }
}
