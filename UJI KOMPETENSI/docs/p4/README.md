# Dokumen P4 — Hardening dan Performa

Urutan baca:

1. `P4_1_ENKRIPSI_DAN_HEALTH_CHECK.md`
   - enkripsi lampiran AES-256-GCM;
   - migrasi lampiran lama;
   - health check read-only.

2. `P4_2_BACKUP_KUNCI_DAN_ALERT.md`
   - backup kunci offline;
   - webhook alert;
   - runbook restore.

3. `P4_3_PERFORMA_DAN_PAGINATION.md`
   - indeks rekam medis;
   - EXPLAIN;
   - pagination.

4. `P4_4_DASHBOARD_ADMIN_PDP.md`
   - halaman admin permintaan PDP;
   - verifikasi/proses/selesai/tolak;
   - audit.

5. `P4_5_UJI_RESTORE_DAN_KESIAPAN.md`
   - restore database terpisah;
   - full suite saat itu;
   - status kesiapan.

6. `P4_6_BERKAS_JAWABAN_PDP.md`
   - upload/generate salinan data;
   - unduhan pemilik di Android;
   - audit unduhan.

7. `P4_7_MOBILE_PERFORMA.md`
   - endpoint ringan rekam medis;
   - lazy pagination rekam medis.

8. `P4_8_GLOBAL_PERFORMA.md`
   - kompresi JSON global;
   - cache HTTP per akun;
   - lazy pagination booking;
   - batas halaman Home/Doctors;
   - catatan handoff.

## Status verifikasi terakhir

- Full suite terakhir yang tercatat lulus: **652 passed, 4.044 assertions**
  (sebelum perubahan P4.6–P4.8).
- Setelah P4.6–P4.8, yang sudah dijalankan:
  - test P45 berkas PDP: **4 passed, 29 assertions**;
  - regresi P45 + P44 + P34: **11 passed, 107 assertions**;
  - test summary/kompresi P46: **1 passed, 20 assertions**;
  - Android `compileDebugKotlin`: **BUILD SUCCESSFUL**.
- Full suite dan Android test/build/lint final **belum dijalankan ulang**.

## Aturan handoff

- Jangan mengubah kontrak `/api/medical-records`; daftar Android memakai
  `/api/medical-records/summary`.
- Jangan mengambil semua halaman di awal untuk layar daftar.
- Jangan menghapus pagination server.
- Jangan menghapus audit, trigger, atau enkripsi saat refactor.
- Jalankan full regression sebelum menyatakan selesai.
