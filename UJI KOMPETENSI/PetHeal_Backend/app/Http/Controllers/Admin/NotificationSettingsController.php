<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    private const TEMPLATE_DEFAULTS = [
        'booking_reminder' => [
            'label' => 'Booking Reminder',
            'icon' => 'event_upcoming',
            'accent' => 'emerald',
            'description' => 'Reminder sebelum konsultasi dimulai.',
            'title' => 'Pengingat Booking PetHeal',
            'body' => '{pet_name} memiliki jadwal dengan {doctor_name} pada {date} pukul {time}.',
        ],
        'payment_reminder' => [
            'label' => 'Payment Reminder',
            'icon' => 'payments',
            'accent' => 'amber',
            'description' => 'Tagihan booking yang masih perlu dilunasi.',
            'title' => 'Pengingat Pembayaran PetHeal',
            'body' => 'Sisa pembayaran untuk {pet_name} sebesar {remaining_amount}. Silakan selesaikan pembayaran booking Anda.',
        ],
        'vaccination_reminder' => [
            'label' => 'Vaccination Reminder',
            'icon' => 'vaccines',
            'accent' => 'sky',
            'description' => 'Pengingat kunjungan vaksinasi atau kontrol berikutnya.',
            'title' => 'Pengingat Vaksinasi PetHeal',
            'body' => 'Saatnya kunjungan berikutnya untuk {pet_name}. Jadwal tindak lanjut: {next_visit}.',
        ],
        'booking_status_confirmed' => [
            'label' => 'Booking Confirmed',
            'icon' => 'check_circle',
            'accent' => 'blue',
            'description' => 'Notifikasi saat booking dikonfirmasi admin.',
            'title' => 'Booking Dikonfirmasi',
            'body' => 'Booking {pet_name} pada {date} sudah dikonfirmasi. Sampai jumpa di klinik.',
        ],
        'booking_status_completed' => [
            'label' => 'Booking Completed',
            'icon' => 'task_alt',
            'accent' => 'emerald',
            'description' => 'Notifikasi setelah konsultasi selesai.',
            'title' => 'Kunjungan Selesai',
            'body' => 'Kunjungan {pet_name} pada {date} sudah selesai. Silakan cek catatan medis terbaru di aplikasi.',
        ],
        'booking_status_cancelled' => [
            'label' => 'Booking Cancelled',
            'icon' => 'cancel',
            'accent' => 'rose',
            'description' => 'Notifikasi saat booking dibatalkan.',
            'title' => 'Booking Dibatalkan',
            'body' => 'Booking {pet_name} pada {date} dibatalkan. Silakan jadwalkan ulang bila diperlukan.',
        ],
        'booking_status_pending' => [
            'label' => 'Booking Created',
            'icon' => 'schedule',
            'accent' => 'slate',
            'description' => 'Notifikasi saat user baru membuat booking.',
            'title' => 'Booking Berhasil Dibuat',
            'body' => 'Booking {pet_name} untuk {date} pukul {time} sudah tercatat dan sedang menunggu konfirmasi.',
        ],
        'booking_status_rescheduled' => [
            'label' => 'Booking Rescheduled',
            'icon' => 'update',
            'accent' => 'violet',
            'description' => 'Notifikasi saat jadwal dipindahkan.',
            'title' => 'Jadwal Booking Diperbarui',
            'body' => 'Jadwal baru untuk {pet_name} adalah {date} pukul {time}. Pastikan Anda datang tepat waktu.',
        ],
    ];

    public function index()
    {
        $templates = collect(self::TEMPLATE_DEFAULTS)->map(function (array $meta, string $key) {
            return [
                'key' => $key,
                ...$meta,
                'stored_title' => AppSetting::getValue("notifications.{$key}.title", $meta['title']),
                'stored_body' => AppSetting::getValue("notifications.{$key}.body", $meta['body']),
            ];
        })->values();

        return view('admin.notification-settings.index', compact('templates'));
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (array_keys(self::TEMPLATE_DEFAULTS) as $key) {
            $rules["templates.{$key}.title"] = 'required|string|max:120';
            $rules["templates.{$key}.body"] = 'required|string|max:320';
        }

        $validated = $request->validate($rules);
        $templates = $validated['templates'] ?? [];

        foreach ($templates as $key => $template) {
            AppSetting::setValue("notifications.{$key}.title", trim((string) ($template['title'] ?? '')));
            AppSetting::setValue("notifications.{$key}.body", trim((string) ($template['body'] ?? '')));
        }

        return redirect()
            ->route('admin.notification-settings.index')
            ->with('success', 'Notification template settings updated successfully.');
    }
}
