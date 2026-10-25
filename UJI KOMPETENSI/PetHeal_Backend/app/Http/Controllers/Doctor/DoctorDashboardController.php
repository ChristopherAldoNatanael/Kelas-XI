<?php

namespace App\Http\Controllers\Doctor;

use App\Models\Booking;
use App\Models\DoctorScheduleException;
use App\Models\MedicalRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Ringkasan praktik dokter hari ini.
 *
 * Semua angka dihitung dari booking milik dokter yang sedang login pada klinik
 * yang sama — tidak ada parameter clinic/doctor dari request yang bisa dipakai
 * untuk melihat data dokter lain.
 */
class DoctorDashboardController extends DoctorPortalController
{
    public function index(Request $request)
    {
        $doctor = $this->doctor();
        $today = Carbon::today();

        $base = Booking::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id);

        $todayBookings = (clone $base)
            ->with(['pet', 'user', 'service'])
            ->whereDate('booking_date', $today->toDateString())
            ->orderBy('booking_time')
            ->get();

        $statusCounts = $todayBookings->countBy('status');

        $stats = [
            'today_total' => $todayBookings->count(),
            'today_pending' => (int) ($statusCounts['pending'] ?? 0),
            'today_confirmed' => (int) ($statusCounts['confirmed'] ?? 0),
            'today_completed' => (int) ($statusCounts['completed'] ?? 0),
        ];

        $pendingTotal = (clone $base)->where('status', 'pending')->count();

        $upcomingWeek = (clone $base)
            ->whereDate('booking_date', '>', $today->toDateString())
            ->whereDate('booking_date', '<=', $today->copy()->addDays(7)->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $recordsThisMonth = MedicalRecord::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->count();

        $nextBooking = (clone $base)
            ->with(['pet', 'user', 'service'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($today) {
                $query->whereDate('booking_date', '>', $today->toDateString())
                    ->orWhere(function ($sameDay) use ($today) {
                        $sameDay->whereDate('booking_date', $today->toDateString())
                            ->whereTime('booking_time', '>=', now()->format('H:i:s'));
                    });
            })
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->first();

        $todayException = DoctorScheduleException::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereDate('date', $today->toDateString())
            ->first();

        return view('doctor.dashboard', compact(
            'doctor',
            'todayBookings',
            'stats',
            'pendingTotal',
            'upcomingWeek',
            'recordsThisMonth',
            'nextBooking',
            'todayException'
        ));
    }
}
