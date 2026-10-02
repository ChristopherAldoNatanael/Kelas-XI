# PROGRESS.md — Catatan Pengerjaan (update tiap selesai fase)

## 2026-10-02 — Fase 0 selesai
- Audit backend: 36 migration single-tenant, 14 model, route web/api dipetakan. Tidak ada `clinics`/`clinic_id`.
- Audit Android: 1 APK `com.christopheraldoo.petheal`, branding hardcoded, tanpa konsep klinik.
- Fix: `routes/web.php` `/` pakai `app()->isDownForMaintenance()` (sebelumnya `config('app.maintenance')` array → redirect abadi ke `/maintenance`). Verifikasi `/` = 200.
- Verifikasi ngrok: `/admin/login` 200, `/api/health` success (DB, Firebase, Midtrans Sandbox OK).
- Keputusan final: shared DB + clinic_id; 1 APK dinamis; user terikat 1 klinik; super-admin approval; 100% gratis.
- File konteks dibuat: `AGENTS.md` + `docs/` (9 file) backend, `AGENTS.md` + `docs/ANDROID_ARCH.md` Android.

## Cara Update File Ini
- Setiap selesai fase di PLAN.md: tambah seksi `## YYYY-MM-DD — Fase N ...`, isi: file diubah, keputusan, hasil tes (curl/http code), masalah tersisa.
- Contoh: `- Ubah Admin/BookingController.php:12 tambah where clinic_id. Tes: admin A /admin/bookings hanya 5 data A. Sisa: export PDF belum filter.`
