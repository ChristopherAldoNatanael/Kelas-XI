<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin.export_records_title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 6px; vertical-align: top; }
        th { background: #ecfdf5; color: #047857; text-align: left; }
    </style>
</head>
<body>
    @php($locale = app()->getLocale())
    <h1>{{ __('admin.export_records_title') }}</h1>
    <p class="subtitle">
        {{ __('admin.export_generated') }} {{ now()->locale($locale)->translatedFormat('d M Y H:i') }}.
        {{ __('admin.export_records_limited') }}
    </p>
    <table>
        <thead>
            <tr>
                <th>{{ __('admin.export_booking') }}</th>
                <th>{{ __('admin.export_owner') }}</th>
                <th>{{ __('admin.export_pet') }}</th>
                <th>{{ __('admin.export_doctor') }}</th>
                <th>{{ __('admin.export_date') }}</th>
                <th>{{ __('admin.export_diagnosis') }}</th>
                <th>{{ __('admin.export_treatment') }}</th>
                <th>{{ __('admin.export_medicine') }}</th>
                <th>{{ __('admin.export_consultation') }}</th>
                <th>{{ __('admin.export_treatment_cost') }}</th>
                <th>{{ __('admin.export_medicine_cost') }}</th>
                <th>{{ __('admin.export_total') }}</th>
                <th>{{ __('admin.export_payment') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>#{{ $record->booking_id }}</td>
                    <td>{{ $record->booking?->user?->name ?? '-' }}</td>
                    <td>{{ $record->pet?->name ?? '-' }}</td>
                    <td>{{ $record->doctor?->name ?? '-' }}</td>
                    <td>{{ optional($record->created_at)->locale($locale)->translatedFormat('d M Y') }}</td>
                    <td>{{ $record->diagnosis }}</td>
                    <td>{{ $record->treatment }}</td>
                    <td>{{ $record->medicine }}</td>
                    <td>Rp {{ number_format((float) ($record->cost ?? 0), 0, $locale === 'id' ? ',' : '.', $locale === 'id' ? '.' : ',') }}</td>
                    <td>Rp {{ number_format((float) ($record->treatment_cost ?? 0), 0, $locale === 'id' ? ',' : '.', $locale === 'id' ? '.' : ',') }}</td>
                    <td>Rp {{ number_format((float) ($record->medicine_cost ?? 0), 0, $locale === 'id' ? ',' : '.', $locale === 'id' ? '.' : ',') }}</td>
                    <td>Rp {{ number_format((float) ($record->total_medical_cost ?? 0), 0, $locale === 'id' ? ',' : '.', $locale === 'id' ? '.' : ',') }}</td>
                    <td>{{ \App\Support\AdminStatus::extraPaymentLabel($record->extra_payment_status ?? 'not_required') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" style="text-align:center; color:#999;">{{ __('admin.export_no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
