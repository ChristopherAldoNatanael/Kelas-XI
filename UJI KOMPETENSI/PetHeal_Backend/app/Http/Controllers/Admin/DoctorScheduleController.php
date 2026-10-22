<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorScheduleException;
use App\Services\DoctorScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * PHASE 4: cuti dokter & jam khusus per tanggal.
 *
 * Semua aksi clinic-scoped memakai `currentClinicId()`. Super admin yang masih
 * di system overview diarahkan memilih klinik dulu — sama seperti papan jadwal.
 *
 * Booking yang sudah ada TIDAK dibatalkan otomatis. Setelah pengecualian
 * disimpan, booking aktif di luar jadwal dilaporkan sebagai peringatan supaya
 * admin meninjau/menjadwalkan ulang secara manual.
 */
class DoctorScheduleController extends Controller
{
    public function __construct(private readonly DoctorScheduleService $schedule) {}

    public function show(Request $request, $id)
    {
        $clinicId = currentClinicId();
        if (!$clinicId) {
            return redirect()->route('admin.clinics.index')
                ->with('error', 'Pilih klinik terlebih dahulu sebelum mengatur jadwal dokter.');
        }

        $request->validate([
            'date' => 'nullable|date',
        ]);

        $doctor = Doctor::query()
            ->where('clinic_id', $clinicId)
            ->findOrFail($id);

        $today = Carbon::today();
        $prefillDate = $request->filled('date')
            ? Carbon::parse($request->input('date'))->toDateString()
            : $today->copy()->addDay()->toDateString();

        // Tanggal yang sudah lewat tidak bisa dipilih (validasi store), jadi
        // daftar cukup dimulai dari hari ini.
        $exceptions = DoctorScheduleException::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinicId)
            ->whereDate('date', '>=', $today->toDateString())
            ->orderBy('date')
            ->limit(30)
            ->get();

        $conflictsByDate = $exceptions->mapWithKeys(function (DoctorScheduleException $exception) use ($doctor) {
            return [
                $exception->date => $this->schedule->bookingsOutsideSchedule($doctor, $exception->date)->count(),
            ];
        });

        $upcomingBookings = \App\Models\Booking::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinicId)
            ->whereDate('booking_date', '>=', $today->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->limit(8)
            ->get();

        $weeklyDays = $this->schedule->availableDays($doctor);

        return view('admin.doctors.schedule', compact(
            'doctor',
            'exceptions',
            'conflictsByDate',
            'prefillDate',
            'weeklyDays',
            'upcomingBookings'
        ));
    }

    public function store(Request $request, $id)
    {
        $clinicId = currentClinicId();
        if (!$clinicId) {
            return redirect()->route('admin.clinics.index')
                ->with('error', 'Pilih klinik terlebih dahulu sebelum mengatur jadwal dokter.');
        }

        $data = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'mode' => 'required|in:closed,custom',
            'start_time' => 'nullable|required_if:mode,custom|date_format:H:i',
            'end_time' => 'nullable|required_if:mode,custom|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:160',
        ]);

        $doctor = Doctor::query()
            ->where('clinic_id', $clinicId)
            ->findOrFail($id);

        $date = Carbon::parse($data['date'])->toDateString();
        $isClosed = $data['mode'] === 'closed';
        $reason = trim((string) ($data['reason'] ?? ''));

        $exception = DoctorScheduleException::updateOrCreate(
            [
                'doctor_id' => $doctor->id,
                'date' => $date,
            ],
            [
                'clinic_id' => $doctor->clinic_id,
                'is_closed' => $isClosed,
                'start_time' => $isClosed ? null : $data['start_time'],
                'end_time' => $isClosed ? null : $data['end_time'],
                'reason' => $reason !== '' ? $reason : null,
                'created_by' => $request->user()->id,
            ]
        );

        AuditLog::log(
            'doctor.schedule_exception.save',
            sprintf(
                '%s jadwal %s pada %s%s',
                $isClosed ? 'Menandai cuti' : 'Mengubah jam praktik',
                $doctor->name,
                Carbon::parse($date)->isoFormat('D MMMM YYYY'),
                $reason !== '' ? " ({$reason})" : ''
            ),
            $doctor
        );

        $conflicts = $this->schedule->bookingsOutsideSchedule($doctor, $date);

        $redirect = redirect()
            ->route('admin.doctors.schedule', ['id' => $doctor->id, 'date' => $date])
            ->with(
                'success',
                $isClosed
                    ? 'Cuti dokter tersimpan untuk ' . Carbon::parse($date)->isoFormat('D MMMM YYYY') . '.'
                    : 'Jam khusus tersimpan: ' . $exception->start_time . ' - ' . $exception->end_time . '.'
            );

        if ($conflicts->isNotEmpty()) {
            $redirect->with(
                'warning',
                $conflicts->count() . ' booking aktif pada tanggal tersebut berada di luar jadwal baru. '
                . 'Booking tidak dibatalkan otomatis — tinjau dan jadwalkan ulang manual.'
            );
        }

        return $redirect;
    }

    public function destroy($id, $exceptionId)
    {
        $clinicId = currentClinicId();
        if (!$clinicId) {
            return redirect()->route('admin.clinics.index')
                ->with('error', 'Pilih klinik terlebih dahulu sebelum mengatur jadwal dokter.');
        }

        $doctor = Doctor::query()
            ->where('clinic_id', $clinicId)
            ->findOrFail($id);

        $exception = DoctorScheduleException::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinicId)
            ->findOrFail($exceptionId);

        $date = $exception->date;
        $exception->delete();

        AuditLog::log(
            'doctor.schedule_exception.delete',
            sprintf('Menghapus pengecualian jadwal %s pada %s', $doctor->name, Carbon::parse($date)->isoFormat('D MMMM YYYY')),
            $doctor
        );

        return redirect()
            ->route('admin.doctors.schedule', ['id' => $doctor->id, 'date' => $date])
            ->with('success', 'Pengecualian jadwal dihapus. Dokter kembali memakai jadwal mingguan.');
    }
}
