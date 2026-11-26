# Gap Analysis — Rekam Medis Hewan PetHeal (Profesional + Kepatuhan Hukum Indonesia)

Ditinjau dari kode aktual: `MedicalRecord`, `MedicalRecordAttachment`, `PrescriptionItem`,
`Vaccination`, `AuditLog`, `Admin/MedicalRecordController`, `Doctor/DoctorMedicalRecordController`,
`Api/MedicalRecordController`, 84 migrasi di `PetHeal_Backend/database/migrations`.

> Catatan: dokumen ini bukan nasihat hukum. Rujukan peraturan dipakai sebagai acuan desain;
> untuk praktik nyata, format final sebaiknya dikonsultasikan ke Dinas Peternakan/Otoritas
> Veteriner setempat dan PDHI.

---

## 1. Yang sudah benar (jangan diubah)

| Sudah ada | Bukti di kode |
|---|---|
| SOAP (subjective/objective/assessment/plan) | migrasi `2026_11_10_000001_add_soap_to_medical_records` |
| Resep terstruktur + snapshot nama/harga obat | `prescription_items` |
| Pemotongan stok FEFO + batch + ED | `MedicineBatch`, `MedicineStockService` |
| Lampiran hasil lab/rontgen | `medical_record_attachments` |
| Audit log sebagian aksi + multi-tenant `clinic_id` | `AuditLog`, `TENANCY_RULES.md` |
| Vaksinasi + berat badan berkala | `Vaccination`, `WeightRecord` |
| Ekspor PDF/CSV rekam medis | `exportPdf`, `exportCsv` |
| Pembatasan akses per klinik | `PatientScope`, query `clinic_id` |

Ini sudah di atas rata-rata aplikasi klinik hewan lokal. Kekurangannya ada di **integritas
dokumen, identitas legal, dan kepatuhan data pribadi**.

---

## 2. P0 — Risiko hukum / kebocoran data (kerjakan dulu)

### P0-1. Rekam medis bisa dihapus permanen
`Admin/MedicalRecordController::destroy()` menghapus record + lampiran (hanya dicegah kalau
sudah ada pembayaran). Secara kaidah rekam medis, dokumen ini **tidak boleh dihapus**; koreksi
dilakukan dengan addendum/void, bukan delete. Analog yang lazim dipakai: Permenkes
269/MENKES/PER/III/2008 (rekam medis manusia: retensi 5 tahun, tidak boleh dihapus), dan
UU 8/1997 tentang Dokumen Perusahaan (dokumen transaksi 10 tahun).

**Perbaikan**
- Ganti delete menjadi `void` (kolom `voided_at`, `voided_by`, `void_reason`) + tampil sebagai
  baris dicoret, tetap ikut ekspor.
- Retensi: minimum 5 tahun sejak kunjungan terakhir; data keuangan 10 tahun.
- Job `PruneExpiredMedicalRecords` hanya untuk *arsip*, bukan hapus, dan wajib log.

### P0-2. Lampiran medis tersimpan di disk publik
`MedicalRecordAttachmentService` memakai `Storage::disk('public')`; `MedicalRecordAttachment::getUrlAttribute`
mengembalikan URL `/storage/...` tanpa autentikasi. Rontgen/lab pasien bisa dibuka siapa pun
yang memegang URL, dan URL ikut terindeks/cache.

**Perbaikan**
- Pindah ke disk privat (`local` / S3 private), akses lewat route ber-middleware
  `GET /medical-records/{record}/attachments/{attachment}` dengan cek `clinic_id` + role,
  atau temporary signed URL 5 menit.
- Simpan `checksum` SHA-256 untuk integritas berkas.
- Migrasi file lama + hapus exposure `/storage/medical-records/*`.

### P0-3. Data klinis dikunci di balik tagihan
`MedicalRecord::maskClinicalFields()` mengosongkan diagnosis/SOAP/lampiran bila
`extra_payment_status` belum lunas. Secara etika dan hak pemilik atas salinan rekam medis,
**isi medis tidak boleh jadi alat penagihan**. Yang wajar ditahan adalah *layanan/obat berikutnya*,
bukan riwayat diagnosis.

**Perbaikan**
- Tampilkan selalu ringkasan klinis (diagnosis, terapi, instruksi) ke pemilik.
- Status pembayaran ditampilkan terpisah sebagai banner tagihan.
- Bila tetap ingin ada gate, buat kebijakan tertulis + opsi "minta salinan rekam medis" yang
  selalu tersedia (audit-logged).

### P0-4. Jejak audit belum memadai untuk dokumen medis
- `update()` tidak menulis audit log sama sekali dan tidak menyimpan nilai lama/baru.
- `exportPdf`/`exportCsv` tidak dicatat (padahal ini eksfiltrasi massal data pribadi).
- Akses baca (siapa membuka rekam medis siapa) tidak dicatat.
- `audit_logs` bisa diubah/dihapus dari DB; tidak ada rantai hash.

**Perbaikan**
- Tabel `medical_record_revisions`: `record_id`, `changed_by`, `changed_at`, `before` (JSON),
  `after` (JSON), `reason`. Tulis di `store/update/void`.
- Log `medical_record.viewed`, `medical_record.exported` (jumlah baris, filter, IP).
- Tambah kolom `prev_hash`/`hash` pada `audit_logs` → tamper-evident chain.
- Retensi audit log minimal sama dengan retensi rekam medis.

### P0-5. Identitas legal dokter & klinik belum ada
`Doctor` tidak punya nomor STR (Surat Tanda Registrasi) dan SIP/SIPDH (Surat Izin Praktik
Dokter Hewan); `Clinic` tidak punya nomor izin usaha klinik hewan. Padahal ini identitas wajib
pada dokumen medis dan dasar kewenangan praktik (UU 18/2009 tentang Peternakan & Kesehatan
Hewan; PP 95/2012 tentang Kesehatan Hewan, Kesmavet, dan Kesejahteraan Hewan).

**Perbaikan**
- `doctors`: `str_number`, `str_expiry`, `sip_number`, `sip_expiry`, `signature_path`.
- `clinics`: `license_number`, `license_expiry`, `vet_responsible_doctor_id`.
- Validasi: dokter non-aktif bila SIP kedaluwarsa; tampilkan nomor STR/SIP pada form & cetak.

### P0-6. Kepatuhan UU 27/2022 (Pelindungan Data Pribadi)
Data pemilik hewan = data pribadi. Belum ada:
- Consent tercatat (tujuan, versi kebijakan, timestamp, cara withdraw).
- Kebijakan privasi & syarat layanan yang bisa diaudit versinya.
- Alur hak subjek data: akses, koreksi, salinan, pembatasan, penghapusan (kecuali retensi).
- Pencatatan aktivitas pemrosesan (RoPA) + penunjukan DPO/PIC.
- Prosedur notifikasi kebocoran ≤ 3x24 jam ke subjek & otoritas.
- DPA dengan pihak ketiga: Midtrans, Brevo, Firebase FCM, hosting.
- Retensi per jenis data + minimisasi.

**Perbaikan (teknis)**
- Tabel `consents` (`user_id`, `purpose`, `policy_version`, `granted_at`, `revoked_at`, `ip`).
- Halaman "Privasi & Data Saya" di aplikasi: unduh data, minta koreksi, cabut consent.
- `data_retention_policies` + job arsip.
- Catat transfer lintas negara (Firebase/Midtrans) di RoPA.
- **Jangan pakai ngrok** untuk data produksi (`start-with-ngrok.bat`) — tunnel publik tanpa
  kontrol akses adalah kebocoran data pribadi.

### P0-7. Kewajiban PSE & tanda tangan elektronik
UU 11/2008 jo. UU 19/2016 jo. UU 1/2024 (ITE) dan PP 71/2019: sistem elektronik publik wajib
terdaftar sebagai PSE, dan dokumen elektronik perlu bukti integritas. Saat ini rekam medis hanya
baris DB yang bisa diedit tanpa jejak.

**Perbaikan**
- Daftar PSE (Kominfo/Komdigi) bila layanan dipublikasikan.
- Saat record difinalisasi: hitung `content_hash`, `signed_at`, `signed_by`, `signature_method`
  (minimal hash + akun terautentikasi; idealnya PSrE tersertifikasi).
- Halaman verifikasi publik `GET /verify/{token}` menampilkan status keaslian.

### P0-8. Resep belum memenuhi kaidah farmasi veteriner
`prescription_items` belum menyimpan: nomor batch/lot, tanggal kedaluwarsa obat yang diserahkan,
golongan obat (bebas / bebas terbatas / keras / narkotika), rute, frekuensi, durasi, dan
instruksi pemakaian terstruktur. Ini penting untuk:
- klasifikasi obat hewan (Permentan No. 14/Permentan/PK.350/5/2017 tentang Klasifikasi Obat Hewan)
  → obat keras hanya boleh atas resep dokter hewan;
- narkotika/psikotropika (UU 35/2009) → pencatatan & pelaporan khusus;
- audit resistensi antimikroba (AMR) → perlu flag antibiotik + indikasi.

**Perbaikan**
- `medicines`: `drug_class` (enum), `is_antibiotic`, `requires_special_record`.
- `prescription_items`: `batch_number`, `expiry_date`, `route`, `frequency`, `duration_days`,
  `instruction`, `is_compounded`.
- Cetak resep format `Rp.` + identitas dokter + nomor SIP + tanda tangan + QR verifikasi.
- Laporan bulanan pemakaian obat keras/antibiotik per klinik.

### P0-9. Penyakit hewan menular & zoonosis belum bisa dilaporkan
UU 18/2009 & PP 95/2012 mewajibkan pelaporan penyakit hewan menular (rabies, AI, anthrax, dll.)
ke Otoritas Veteriner/Dinas, umumnya lewat iSIKHNAS. Belum ada penanda diagnosis "wajib lapor".

**Perbaikan**
- Master `notifiable_diseases` (kode iSIKHNAS, nama, masa inkubasi, zona).
- Saat diagnosis cocok → wajibkan isi form pelaporan, simpan `reported_at`, `report_ref`,
  `reported_to`, dan cetak bukti.
- Dashboard "kasus wajib lapor belum dilaporkan" — ini nilai jual besar untuk Dinas.

---

## 3. P1 — Kelengkapan klinis profesional

### P1-1. Vitals belum terstruktur
`objective` masih teks bebas. Rekam medis standar veteriner mencatat: suhu (°C), frekuensi
jantung, frekuensi napas, CRT, mukosa, hidrasi, Body Condition Score (1–9), skala nyeri,
berat saat kunjungan.

**Perbaikan**: tabel `medical_record_vitals` (1:1) + grafik tren per pasien. Ini juga membuat
tanda bahaya (mis. suhu > 39,5 °C) bisa ditandai otomatis.

### P1-2. Alergi & riwayat obat tidak ada
Belum ada flag alergi obat/makanan, reaksi sebelumnya, dan riwayat pemberian obat. Ini
keselamatan pasien paling dasar.

**Perbaikan**: `pet_allergies` (zat, reaksi, tingkat, sumber, dicatat oleh) + badge merah di
form resep saat obat bertabrakan.

### P1-3. Identitas hewan belum lengkap
`pets` belum punya: nomor microchip, tanda khusus/warna, status steril, golongan darah,
status asuransi, dan `species` masih string bebas.

**Perbaikan**: `microchip_number` (unique), `color`, `markings`, `neutered_at`, `blood_type`,
`species_code` (enum + master), `sex` enum (`male/female`, bukan string bebas).

### P1-4. Satu booking = satu record, tidak ada catatan progres
Pasien rawat inap butuh banyak catatan SOAP per hari. Sekarang struktur memaksa 1 record
per booking, sehingga rawat inap tidak terdokumentasi.

**Perbaikan**: `medical_record_entries` (SOAP harian, tanda vital, tindakan, shift, dokter/
perawat) yang berinduk ke `medical_records`, atau ubah `medical_records` jadi episode dan
`entries` sebagai progres.

### P1-5. Resume medis & surat keterangan belum bisa dicetak per pasien
Ekspor sekarang hanya daftar tabel (PDF/CSV). Belum ada "resume medis" 1 pasien berisi
identitas, anamnesis, diagnosis, terapi, instruksi pulang, jadwal kontrol, tanda tangan dokter,
kop klinik, dan QR verifikasi — dokumen inilah yang diminta pemilik, asuransi, atau sekolah.

### P1-6. Informed consent belum ada
Tindakan berisiko (operasi, anestesi, euthanasia, rawat inap, transfusi) perlu persetujuan
tertulis pemilik. Saat ini hanya ada form rekam medis.

**Perbaikan**: `treatment_consents` (jenis tindakan, risiko yang dijelaskan, nama & hubungan
pemilik, tanda tangan gambar, waktu, saksi).

### P1-7. Prognosis, instruksi pulang, dan follow-up belum terstruktur
`plan` teks bebas. Tambahkan `prognosis` (baik/sedang/buruk/dubius), `discharge_instruction`,
`followup_status`, dan pengingat kontrol yang terhubung ke `SendReminders`.

### P1-8. Vaksinasi tidak terhubung ke rekam medis
`Vaccination` tidak punya `clinic_id`, `doctor_id`, `medical_record_id`, `certificate_number`.
Padahal sertifikat vaksin (terutama rabies) adalah dokumen yang sering diminta.

**Perbaikan**: tambahkan kolom tersebut + cetak sertifikat + nomor batch vaksin + masa berlaku.

### P1-9. Kasus mati/euthanasia tidak terdokumentasi
Belum ada `outcome` (sembuh/membaik/rujuk/mati/euthanasia), `cause_of_death`,
`euthanasia_reason`, `disposal_method`. Diperlukan untuk kesejahteraan hewan dan statistik klinik.

### P1-10. Rujukan & hasil lab belum terstruktur
Belum ada `referrals` (tujuan, alasan, hasil balik) dan `lab_results` (parameter, nilai, satuan,
rentang rujukan, flag H/L). Sekarang hasil lab hanya lampiran PDF.

---

## 4. P2 — Peningkatan kelas (opsional)

1. **Kode diagnosis standar** (VeNom / SNOMED-Vet) supaya bisa statistik penyakit & klaim asuransi.
2. **Template SOAP per spesies/kasus** (kucing, anjing, eksotik) mempercepat input dan
   menstandarkan mutu.
3. **Timeline pasien** lintas kunjungan (SOAP, vaksin, berat, resep, lab, pembayaran) dalam 1 layar.
4. **Duplicate patient detection** via microchip/nama+owner+telepon.
5. **Audit mutu klinik**: waktu tunggu, rasio antibiotik, kepatuhan kontrol, readmission 14 hari.
6. **Backup & DR**: jadwal, uji restore, enkripsi at-rest, RPO/RTO tertulis (bagian dari
   kewajiban keamanan PDP).
7. **Break-glass access**: admin/dokter lain bisa buka record darurat dengan alasan wajib diisi
   dan log khusus.
8. **iSIKHNAS / e-Surat Keterangan Kesehatan Hewan** integrasi bila hendak dipakai instansi.

---

## 5. Urutan implementasi yang disarankan (4 sprint)

| Sprint | Isi | Alasan |
|---|---|---|
| 1 | P0-2 (lampiran privat), P0-4 (audit + revisi), P0-5 (STR/SIP), P0-1 (void + retensi) | Risiko hukum & keamanan tertinggi, perubahan skema kecil |
| 2 | P0-3 (hapus gate klinis), P0-6 (consent + halaman privasi), P0-8 (resep: batch/ED/golongan) | Kepatuhan PDP & farmasi |
| 3 | P0-7 (hash + verifikasi), P0-9 (wajib lapor), P1-1 (vitals), P1-2 (alergi) | Nilai klinis + dokumen sah |
| 4 | P1-4 (progres rawat inap), P1-5 (resume medis), P1-6 (consent tindakan), P1-8 (sertifikat vaksin) | Kelengkapan profesional |

---

## 6. Checklist kepatuhan ringkas (untuk presentasi/uji kompetensi)

- [ ] Rekam medis tidak bisa dihapus, hanya di-void dengan alasan + jejak.
- [ ] Retensi 5 tahun (klinis) / 10 tahun (keuangan) tertulis & dijalankan job.
- [ ] Setiap perubahan punya revisi (nilai lama → baru, siapa, kapan, kenapa).
- [ ] Cetak rekam medis memuat: identitas hewan & pemilik, tanggal, anamnesis, pemeriksaan,
      diagnosis, terapi, instruksi, nama + no. STR/SIP dokter, tanda tangan, kop klinik, QR verifikasi.
- [ ] Lampiran medis tidak dapat diakses tanpa login.
- [ ] Consent pemilik tercatat untuk pemrosesan data & tindakan berisiko.
- [ ] Hak subjek data (akses/koreksi/salinan) punya alur & SLA.
- [ ] Notifikasi insiden kebocoran ≤ 3x24 jam (prosedur + kontak).
- [ ] Obat keras/antibiotik tercatat golongan, batch, ED; narkotika punya register khusus.
- [ ] Diagnosis wajib lapor (zoonosis) memicu form pelaporan + bukti lapor.
- [ ] PSE terdaftar bila layanan dipublikasikan; tidak memakai tunnel publik.
- [ ] Backup teruji, enkripsi at-rest, akses berbasis peran + break-glass.

---

## 7. Rujukan peraturan yang dipakai

| Regulasi | Relevansi |
|---|---|
| UU 18/2009 jo. UU 41/2014 jo. UU 6/2023 (Peternakan & Kesehatan Hewan) | Kewenangan dokter hewan, obat hewan, pelaporan penyakit menular |
| PP 95/2012 (Kesehatan Hewan, Kesmavet, Kesejahteraan Hewan) | Fasilitas pelayanan kesehatan hewan, pencatatan, obat hewan |
| Permentan 14/Permentan/PK.350/5/2017 | Klasifikasi obat hewan (bebas/bebas terbatas/keras) → dasar resep |
| UU 35/2009 (Narkotika) | Pencatatan & pelaporan narkotika/psikotropika |
| UU 27/2022 (Pelindungan Data Pribadi) | Consent, hak subjek, retensi, notifikasi kebocoran, DPO, transfer lintas negara |
| UU 11/2008 jo. 19/2016 jo. 1/2024 (ITE) + PP 71/2019 | Dokumen elektronik, tanda tangan elektronik, kewajiban PSE |
| UU 8/1997 (Dokumen Perusahaan) | Retensi 10 tahun untuk dokumen transaksi/keuangan |
| Permenkes 269/MENKES/PER/III/2008 | **Acuan praktik baik** (bukan mengikat klinik hewan): isi rekam medis, retensi 5 tahun, larangan hapus, hak salinan |
