<?php

namespace App\Http\Controllers\Doctor;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\DoctorScheduleException;
use App\Services\DoctorScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Cuti & jam khusus milik dokter sendiri.
 *
 * Mengubah jadwal TIDAK membatalkan booking yang sudah ada. Booking di luar
 * jadwal baru ditampilkan sebagai peringatan supaya dokter/admin menindaklanjuti
 * secara manual — sama seperti perilaku panel admin.
 */
class DoctorScheduleController extends DoctorPortalController
{
    public function __construct(private readonly DoctorScheduleService $schedule)
    {
    }

    public function index(Request $request)
    {
        $doctor = $this->doctor();

        $request->validate([
            'date' => 'nullable|date',
        ]);

        $today = Carbon::today();
        $prefillDate = $request->filled('date')
            ? Carbon::parse($request->input('date'))->toDateString()
            : $today->copy()->addDay()->toDateString();

        $exceptions = DoctorScheduleException::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereDate('date', '>=', $today->toDateString())
            ->orderBy('date')
            ->limit(30)
            ->get();

        $conflictsByDate = $exceptions->mapWithKeys(fn (DoctorScheduleException $exception) => [
            $exception->date => $this->schedule->bookingsOutsideSchedule($doctor, $exception->date)->count(),
        ]);

        $upcomingBookings = Booking::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereDate('booking_date', '>=', $today->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->limit(8)
            ->get();

        $weeklyDays = $this->schedule->availableDays($doctor);

        return view('doctor.schedule.index', compact(
            'doctor',
            'exceptions',
            'conflictsByDate',
            'prefillDate',
            'weeklyDays',
            'upcomingBookings'
        ));
    }

    public function store(Request $request)
    {
        $doctor = $this->doctor();

        $data = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'mode' => 'required|in:closed,custom',
            'start_time' => 'nullable|required_if:mode,custom|date_format:H:i',
            'end_time' => 'nullable|required_if:mode,custom|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:160',
        ], [
            'date.after_or_equal' => 'Tanggal cuti tidak boleh di masa lalu.',
        ]);

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
                'Dokter %s %s pada %s%s',
                $doctor->name,
                $isClosed ? 'menandai cuti' : 'mengubah jam praktik',
                Carbon::parse($date)->isoFormat('D MMMM YYYY'),
                $reason !== '' ? " ({$reason})" : ''
            ),
            $doctor
        );

        $conflicts = $this->schedule->bookingsOutsideSchedule($doctor, $date);

        $redirect = redirect()
            ->route('doctor.schedule.index', ['date' => $date])
            ->with(
                'success',
                $isClosed
                    ? 'Cuti tersimpan untuk ' . Carbon::parse($date)->isoFormat('D MMMM YYYY') . '.'
                    : 'Jam khusus tersimpan: ' . $exception->start_time . ' - ' . $exception->end_time . '.'
            );

        if ($conflicts->isNotEmpty()) {
            $redirect->with(
                'warning',
                $conflicts->count() . ' booking aktif pada tanggal tersebut berada di luar jadwal baru. '
                . 'Booking tidak dibatalkan otomatis — koordinasikan dengan admin klinik.'
            );
        }

        return $redirect;
    }

    public function destroy($exceptionId)
    {
        $doctor = $this->doctor();

        $exception = DoctorScheduleException::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($exceptionId);

        $date = $exception->date;
        $exception->delete();

        AuditLog::log(
            'doctor.schedule_exception.delete',
            sprintf(
                'Dokter %s menghapus pengecualian jadwal pada %s.',
                $doctor->name,
                Carbon::parse($date)->isoFormat('D MMMM YYYY')
            ),
            $doctor
        );

        return redirect()->route('doctor.schedule.index', ['date' => $date])
            ->with('success', 'Pengecualian jadwal dihapus.');
    }
}
