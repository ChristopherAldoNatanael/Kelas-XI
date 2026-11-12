@php
    /**
     * Lampiran rekam medis: rontgen, hasil laboratorium, foto kondisi hewan.
     *
     * `enctype` form WAJIB multipart — dikontrol oleh view pemanggil.
     * @var \App\Models\MedicalRecord|null $record
     */
    $record = $record ?? null;
    $existing = $record ? $record->attachments : collect();
@endphp

<div class="space-y-4">
    <div>
        <label for="attachments" class="admin-label">Lampiran pemeriksaan</label>
        <input type="file" name="attachments[]" id="attachments" multiple
               accept=".jpg,.jpeg,.png,.webp,.pdf"
               class="admin-input file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
        <p class="admin-hint">
            JPG, PNG, WEBP, atau PDF. Maksimal 5 MB per berkas, sampai 10 berkas sekali unggah.
            Gambar otomatis dikecilkan supaya hemat ruang penyimpanan.
        </p>
        @error('attachments') <p class="admin-error">{{ $message }}</p> @enderror
        @error('attachments.*') <p class="admin-error">{{ $message }}</p> @enderror
    </div>

    @if($existing->isNotEmpty())
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Lampiran tersimpan</p>

            @foreach($existing as $attachment)
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700">
                    <span class="material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">
                        {{ $attachment->isImage() ? 'image' : 'picture_as_pdf' }}
                    </span>

                    <div class="min-w-0 flex-1">
                        @if($attachment->url)
                            <a href="{{ $attachment->url }}" target="_blank" rel="noopener"
                               class="block truncate text-sm font-medium text-slate-800 hover:underline dark:text-slate-200">
                                {{ $attachment->caption ?: $attachment->original_name }}
                            </a>
                        @else
                            <p class="truncate text-sm font-medium text-rose-600 dark:text-rose-400">
                                {{ $attachment->original_name }} — berkas hilang
                            </p>
                        @endif

                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ $attachment->original_name }}
                            @if($attachment->size_bytes)
                                · {{ number_format($attachment->size_bytes / 1024, 0, ',', '.') }} KB
                            @endif
                        </p>
                    </div>

                    {{-- Hanya tampil di form edit (record tersimpan). --}}
                    @if($record)
                        <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}"
                                   class="rounded border-slate-300">
                            Hapus
                        </label>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
