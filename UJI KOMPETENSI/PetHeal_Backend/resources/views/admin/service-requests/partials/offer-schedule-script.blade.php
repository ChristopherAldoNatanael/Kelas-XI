<script>
    (() => {
        const doctor = document.getElementById('offer_doctor_id');
        const date = document.getElementById('offer_schedule_date');
        const time = document.getElementById('offer_schedule_time');
        const note = document.getElementById('offer_slot_note');
        const dateHint = document.getElementById('offer_date_hint');
        const suggestedButton = document.getElementById('offer_use_suggested_date');
        const suggestedLabel = document.getElementById('offer_suggested_date_label');
        const doctorMeta = document.getElementById('offer_doctor_schedule');

        if (!doctor || !date || !time || !note || !dateHint) return;

        const dataEl = document.getElementById('offerScheduleData');
        let scheduleData = { doctors: {}, clinic: {} };
        try {
            scheduleData = JSON.parse(dataEl?.textContent || '{}') || scheduleData;
        } catch (error) {
            scheduleData = { doctors: {}, clinic: {} };
        }

        const doctorSchedules = scheduleData.doctors || {};
        const clinic = scheduleData.clinic || {};
        const clinicDays = Array.isArray(clinic.days) ? clinic.days : [];

        const slotsUrlTemplate = @json(route('admin.service-requests.doctor-slots', ['doctor' => 'DOCTOR_ID']));
        const validUntil = document.getElementById('offer_valid_until');
        const price = document.getElementById('offer_price');
        const priceHint = document.getElementById('offer_price_hint');
        const serviceSelect = document.getElementById('offer_service_id');
        const serviceName = document.getElementById('offer_service_name');
        const duration = document.getElementById('offer_duration_minutes');

        let requestSeq = 0;
        let desiredTime = (time.value || '').slice(0, 5);
        let suggestedDate = null;

        const pad = (value) => String(value).padStart(2, '0');

        const todayISO = () => {
            const now = new Date();
            return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        };

        const parseISO = (iso) => new Date(iso + 'T00:00:00');
        const dayNumber = (iso) => {
            const day = parseISO(iso).getDay();
            return day === 0 ? 7 : day;
        };

        const formatDate = (iso, style = 'long') => {
            if (!iso) return '';
            const options = style === 'long'
                ? { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }
                : { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' };
            try {
                return parseISO(iso).toLocaleDateString('id-ID', options);
            } catch (error) {
                return iso;
            }
        };

        const formatMoney = (value) => {
            const number = Number(value);
            if (!Number.isFinite(number) || value === '' || value === null) return '—';
            try {
                return 'Rp ' + number.toLocaleString('id-ID', { maximumFractionDigits: 0 });
            } catch (error) {
                return 'Rp ' + number;
            }
        };

        const selectedDoctor = () => doctorSchedules[doctor.value] || null;

        const doctorDays = (id) => {
            const schedule = doctorSchedules[id];
            return schedule && Array.isArray(schedule.days) ? schedule.days : [];
        };

        const effectiveDays = (id) => {
            const own = doctorDays(id);
            if (!clinicDays.length) return own;
            return own.filter((day) => clinicDays.includes(day));
        };

        const setNote = (text, tone = 'muted') => {
            note.textContent = text || '';
            note.className = 'mt-1 text-[11px] leading-4 ' + (
                tone === 'warn'
                    ? 'text-amber-600 dark:text-amber-400'
                    : tone === 'error'
                        ? 'text-rose-600 dark:text-rose-400'
                        : 'text-slate-500 dark:text-slate-400'
            );
        };

        const setDateHint = (text, tone = 'muted') => {
            dateHint.textContent = text || '';
            dateHint.className = 'mt-1 text-[11px] leading-4 ' + (
                tone === 'warn'
                    ? 'text-amber-600 dark:text-amber-400'
                    : tone === 'error'
                        ? 'text-rose-600 dark:text-rose-400'
                        : 'text-slate-500 dark:text-slate-400'
            );
        };

        const hideSuggestion = () => {
            suggestedDate = null;
            suggestedButton?.classList.add('hidden');
            suggestedButton?.classList.remove('inline-flex');
        };

        const showSuggestion = (iso) => {
            if (!suggestedButton || !suggestedLabel || !iso) return;
            suggestedDate = iso;
            suggestedLabel.textContent = 'Pakai ' + formatDate(iso);
            suggestedButton.classList.remove('hidden');
            suggestedButton.classList.add('inline-flex');
        };

        const nextWorkingDate = (fromISO, id) => {
            const days = effectiveDays(id);
            if (!days.length) return null;

            const start = parseISO(fromISO || todayISO());
            for (let offset = 0; offset <= 90; offset++) {
                const candidate = new Date(start.getTime());
                candidate.setDate(candidate.getDate() + offset);
                const iso = `${candidate.getFullYear()}-${pad(candidate.getMonth() + 1)}-${pad(candidate.getDate())}`;
                if (!days.includes(dayNumber(iso))) continue;
                if (iso < todayISO()) continue;
                return iso;
            }

            return null;
        };

        const refreshDoctorMeta = () => {
            if (!doctorMeta) return;
            const schedule = selectedDoctor();

            if (!schedule) {
                doctorMeta.classList.add('hidden');
                return;
            }

            doctorMeta.classList.remove('hidden');
            const days = document.getElementById('offer_doctor_days');
            const hours = document.getElementById('offer_doctor_hours');
            const clinicInfo = document.getElementById('offer_clinic_info');

            if (days) days.textContent = 'Hari praktik: ' + (schedule.days_label || 'belum diatur');

            if (hours) {
                hours.textContent = schedule.start && schedule.end
                    ? `Jam praktik: ${schedule.start}–${schedule.end}`
                    : 'Jam praktik belum diatur';
            }

            if (clinicInfo) {
                if (clinic.name) {
                    const window = clinic.opening && clinic.closing ? ` · ${clinic.opening}–${clinic.closing}` : '';
                    clinicInfo.textContent = `Klinik: ${clinic.name}${window}`;
                } else {
                    clinicInfo.textContent = 'Jam klinik mengikuti jam praktik dokter';
                }
            }
        };

        const resetOptions = (label) => {
            time.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = label;
            time.appendChild(option);
            time.disabled = true;
        };

        const renderSlots = (slots) => {
            time.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Pilih jam';
            time.appendChild(placeholder);

            slots.forEach((slot) => {
                const option = document.createElement('option');
                option.value = slot.time;
                option.textContent = slot.available ? slot.time : `${slot.time} — penuh`;
                option.disabled = !slot.available;
                if (slot.time === desiredTime) option.selected = true;
                time.appendChild(option);
            });

            time.disabled = false;

            const selected = time.value;
            if (desiredTime && !slots.some((slot) => slot.time === desiredTime && slot.available)) {
                if (!selected) {
                    setNote('Jam penawaran sebelumnya sudah tidak tersedia. Pilih jam lain.', 'warn');
                }
            }

            desiredTime = '';
            updateSummary();
        };

        const updateSummary = () => {
            const summaryService = document.getElementById('offer_summary_service');
            const summaryDoctor = document.getElementById('offer_summary_doctor');
            const summarySchedule = document.getElementById('offer_summary_schedule');
            const summaryPrice = document.getElementById('offer_summary_price');

            if (summaryService) summaryService.textContent = serviceName?.value?.trim() || '—';

            const option = doctor.selectedOptions && doctor.selectedOptions[0];
            if (summaryDoctor) {
                summaryDoctor.textContent = doctor.value && option && option.value
                    ? option.textContent.trim()
                    : '—';
            }

            if (summarySchedule) {
                const pieces = [];
                if (date.value) pieces.push(formatDate(date.value));
                if (time.value) pieces.push(time.value);
                summarySchedule.textContent = pieces.length ? pieces.join(' · ') : '—';
            }

            if (summaryPrice) {
                const type = document.getElementById('offer_price_type')?.value === 'estimate' ? 'estimasi' : 'final';
                const minutes = Number(duration?.value || 0);
                let text = formatMoney(price?.value);
                if (text !== '—') {
                    text += ` (${type})`;
                    if (minutes > 0) text += ` · ${minutes} menit`;
                }
                summaryPrice.textContent = text;
            }
        };

        const updateDateHint = () => {
            if (!doctor.value) {
                setDateHint('Pilih dokter dulu agar tanggal yang tersedia menyesuaikan hari praktiknya.');
                hideSuggestion();
                return;
            }

            if (!date.value) {
                setDateHint('Pilih tanggal praktik dokter.');
                hideSuggestion();
                return;
            }

            const day = dayNumber(date.value);
            const ownDays = doctorDays(doctor.value);
            const days = effectiveDays(doctor.value);

            if (ownDays.length && !ownDays.includes(day)) {
                setDateHint('Dokter tidak praktik pada hari ' + formatDate(date.value, 'long').split(',')[0] + '.', 'warn');
                showSuggestion(nextWorkingDate(date.value, doctor.value));
                return;
            }

            if (clinicDays.length && !clinicDays.includes(day)) {
                setDateHint('Klinik tutup pada hari ' + formatDate(date.value, 'long').split(',')[0] + '.', 'warn');
                showSuggestion(nextWorkingDate(date.value, doctor.value));
                return;
            }

            if (!days.length) {
                setDateHint('Jadwal dokter atau klinik belum diatur lengkap. Hubungi admin klinik.', 'warn');
                hideSuggestion();
                return;
            }

            if (date.value < todayISO()) {
                setDateHint('Tanggal ini sudah lewat.', 'warn');
                hideSuggestion();
                return;
            }

            setDateHint(formatDate(date.value) + ' · dokter praktik pada hari ini.');
            hideSuggestion();
        };

        const loadSlots = async () => {
            const doctorId = doctor.value;
            const selectedDate = date.value;
            const seq = ++requestSeq;

            if (!doctorId || !selectedDate) {
                resetOptions('Pilih tanggal dulu');
                setNote('');
                updateSummary();
                return;
            }

            resetOptions('Memuat jam...');
            setNote('Memeriksa hari praktik dokter dan jam operasional klinik...');

            try {
                const url = slotsUrlTemplate.replace('DOCTOR_ID', encodeURIComponent(doctorId))
                    + '?date=' + encodeURIComponent(selectedDate);
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat jadwal (' + response.status + ').');
                }

                const payload = await response.json();
                if (seq !== requestSeq) return;

                const meta = payload.meta || {};
                const slots = Array.isArray(payload.data) ? payload.data : [];

                if (meta.clinic_closed) {
                    setNote(meta.reason || 'Klinik tutup pada tanggal ini.', 'warn');
                } else if (meta.is_closed) {
                    setNote(meta.reason || 'Dokter cuti pada tanggal ini.', 'warn');
                } else if (!meta.is_working_day) {
                    setNote(meta.reason || 'Dokter tidak praktik pada tanggal ini.', 'warn');
                } else if (slots.length === 0) {
                    setNote('Tidak ada jam tersedia pada tanggal ini.', 'warn');
                } else {
                    setNote('Jam di bawah sudah dibatasi hari praktik dokter dan jam operasional klinik.');
                }

                if (slots.length === 0) {
                    resetOptions('Tidak ada jam tersedia');
                    updateSummary();
                    return;
                }

                renderSlots(slots);
            } catch (error) {
                if (seq !== requestSeq) return;
                resetOptions('Gagal memuat jam');
                setNote(error.message || 'Gagal memuat jadwal. Coba lagi.', 'error');
                updateSummary();
            }
        };

        // Katalog hanya mengisi nilai awal. Harga tetap milik admin.
        serviceSelect?.addEventListener('change', () => {
            const option = serviceSelect.selectedOptions[0];
            if (!option || !option.value) {
                if (priceHint) priceHint.textContent = 'Harga ditentukan admin sesuai kesepakatan klinik.';
                updateSummary();
                return;
            }

            if (serviceName) serviceName.value = option.dataset.name || '';
            const description = document.getElementById('offer_description');
            if (description) description.value = option.dataset.description || '';
            if (duration && option.dataset.duration) duration.value = option.dataset.duration;
            if (price) price.value = option.dataset.price || '';

            if (priceHint) {
                priceHint.textContent = 'Harga katalog ' + formatMoney(option.dataset.price) + '. Ubah bila perlu — harga penawaran tetap keputusan admin.';
            }

            updateSummary();
        });

        doctor.addEventListener('change', () => {
            refreshDoctorMeta();

            if (doctor.value && !date.value) {
                const suggested = nextWorkingDate(todayISO(), doctor.value);
                if (suggested) {
                    date.value = suggested;
                    setNote('Tanggal diisi otomatis ke hari praktik terdekat. Anda bisa menggantinya.');
                }
            }

            updateDateHint();
            updateSummary();
            loadSlots();
        });

        date.addEventListener('change', () => {
            updateDateHint();

            if (date.value && validUntil && !validUntil.value) {
                const end = parseISO(date.value);
                end.setDate(end.getDate() + 3);
                validUntil.value = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}T23:59`;
            }

            updateSummary();
            loadSlots();
        });

        suggestedButton?.addEventListener('click', () => {
            if (!suggestedDate) return;
            date.value = suggestedDate;
            time.value = '';
            updateDateHint();
            updateSummary();
            loadSlots();
        });

        time.addEventListener('change', updateSummary);
        price?.addEventListener('input', updateSummary);
        duration?.addEventListener('input', updateSummary);
        serviceName?.addEventListener('input', updateSummary);
        document.getElementById('offer_price_type')?.addEventListener('change', updateSummary);

        refreshDoctorMeta();
        updateDateHint();
        updateSummary();

        if (priceHint && !priceHint.textContent) {
            priceHint.textContent = 'Harga ditentukan admin sesuai kesepakatan klinik.';
        }

        loadSlots();
    })();
</script>
