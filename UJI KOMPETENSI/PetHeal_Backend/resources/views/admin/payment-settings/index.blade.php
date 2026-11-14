@extends('layouts.admin')

@section('title', __('payments.title') . ' — PetHeal')
@section('header', __('payments.title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('payments.title')"
        :eyebrow="__('menu.settings')"
        icon="account_balance_wallet"
        :description="__('payments.subtitle')" />

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div role="alert" aria-live="assertive" class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Akun pusat: hanya informasi, tidak dapat diubah dari sini. --}}
    <x-admin.panel :title="__('payments.central_account')" :subtitle="__('payments.fallback_hint')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="admin-label">{{ __('payments.environment') }}</p>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                    {{ $environment === 'production' ? __('payments.env_production') : __('payments.env_sandbox') }}
                </p>
            </div>
            <div>
                <p class="admin-label">{{ __('payments.status') }}</p>
                <x-admin.badge
                    :label="$centralReady ? __('payments.central_configured') : __('payments.central_not_configured')"
                    :tone="$centralReady ? 'primary' : 'rose'"
                    dot />
            </div>
            <div>
                <p class="admin-label">Fallback</p>
                <x-admin.badge
                    :label="$fallbackEnabled ? __('payments.fallback_enabled') : __('payments.fallback_disabled')"
                    :tone="$fallbackEnabled ? 'amber' : 'slate'"
                    dot />
            </div>
            <div>
                <p class="admin-label">{{ __('payments.callback_url') }}</p>
                <div class="mt-1 flex items-start gap-2">
                    <p id="notification-url"
                       data-copy-value="{{ $callbackUrl }}"
                       class="min-w-0 flex-1 break-all font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $callbackUrl }}</p>
                    <button type="button"
                            data-copy-target="notification-url"
                            data-copied-label="{{ __('payments.copied') }}"
                            class="shrink-0 rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:border-primary hover:text-primary dark:border-slate-700 dark:text-slate-300">
                        {{ __('payments.copy') }}
                    </button>
                </div>
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ __('payments.callback_hint') }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
            <x-admin.badge
                :label="__('payments.env_sandbox') . ': ' . ($centralReadiness['sandbox'] ? __('payments.central_configured') : __('payments.central_not_configured'))"
                :tone="$centralReadiness['sandbox'] ? 'primary' : 'slate'" />
            <x-admin.badge
                :label="__('payments.env_production') . ': ' . ($centralReadiness['production'] ? __('payments.central_configured') : __('payments.central_not_configured'))"
                :tone="$centralReadiness['production'] ? 'primary' : 'slate'" />
            <x-admin.badge
                :label="$fallbackApproved ? __('payments.production_approved') : __('payments.production_pending')"
                :tone="$fallbackApproved ? 'primary' : 'amber'" />
        </div>
    </x-admin.panel>

    @if($isSuperAdmin && !$clinic)
        <x-admin.panel :title="__('payments.overview_title')" :subtitle="__('payments.overview_desc')">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($clinics as $row)
                    @php($account = $accounts->get($row->id))
                    <div class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $row->name }}</p>
                            <p class="truncate font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                {{ $account?->currentCredentialVersion?->maskedMerchantId() ?? __('payments.no_active_merchant') }}
                            </p>
                        </div>
                        <x-admin.badge
                            :label="$account?->statusLabel() ?? __('payments.status_not_configured')"
                            :tone="match($account?->status) { 'active' => 'primary', 'pending_verification' => 'amber', 'invalid' => 'rose', default => 'slate' }"
                            dot />
                        <form method="POST" action="{{ route('admin.payment-settings.select-clinic') }}">
                            @csrf
                            <input type="hidden" name="clinic_id" value="{{ $row->id }}">
                            <button type="submit" class="admin-btn-secondary">{{ __('payments.configure') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('payments.no_versions') }}</p>
                @endforelse
            </div>
        </x-admin.panel>
    @endif

    @if($clinic && $summary)
        @php($account = $summary['account'])
        @php($activeVersion = $summary['active_version'])
        @php($availability = $summary['availability'])

        <x-admin.panel :title="$clinic->name" :subtitle="__('payments.credentials_desc')">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-admin.badge :label="$account->statusLabel()" :tone="match($account->status) { 'active' => 'primary', 'pending_verification' => 'amber', 'invalid' => 'rose', 'disabled', 'suspended' => 'slate', default => 'slate' }" dot />
                <x-admin.badge
                    :label="$availability['available'] ? __('payments.online_ready') : __('payments.online_blocked')"
                    :tone="$availability['available'] ? 'primary' : 'amber'"
                    dot />
                @if(!$availability['available'] && $availability['reason'])
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('payments.reason') }}: <span class="font-mono">{{ $availability['reason'] }}</span></span>
                @endif
                @if($account->last_validated_at)
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('payments.tested_at') }}: {{ $account->last_validated_at->diffForHumans() }}</span>
                @endif
            </div>

            @if($account->last_validation_message)
                <p class="mb-4 rounded-xl bg-slate-50 px-4 py-2.5 text-xs text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                    {{ $account->last_validation_message }}
                </p>
            @endif

            {{-- Penjelasan sumber dana: menghindari asumsi bahwa semua dana
                 masuk ke akun pusat. --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ __('payments.payment_source_title') }}</p>
                <p class="mt-1">{{ __('payments.payment_source_desc') }}</p>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('payments.environment_panels_title')" :subtitle="__('payments.environment_panels_desc')">
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($environmentPanels as $panel)
                    @php($latestVersion = $panel['latest'])
                    @php($panelStatus = $panel['active']?->status ?? $latestVersion?->status ?? 'not_configured')
                    {{-- Error hanya ditampilkan pada panel yang benar-benar dikirim,
                         karena kedua panel memakai nama field yang sama. --}}
                    @php($panelHasErrors = old('environment') === $panel['environment'])
                    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $panel['label'] }}</h3>
                            @if($panel['is_app_environment'])
                                <x-admin.badge :label="__('payments.env_active_app')" tone="primary" dot />
                            @else
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('payments.env_not_active_app') }}</span>
                            @endif
                            <x-admin.badge
                                :label="match($panelStatus) {
                                    'active' => __('payments.status_active'),
                                    'valid' => __('payments.status_verified_inactive'),
                                    'pending' => __('payments.status_pending_verification'),
                                    'invalid' => __('payments.status_invalid'),
                                    'retired' => __('payments.status_disabled'),
                                    default => __('payments.status_not_configured'),
                                }"
                                :tone="match($panelStatus) { 'active', 'valid' => 'primary', 'pending' => 'amber', 'invalid' => 'rose', default => 'slate' }"
                                dot />
                        </div>

                        <div class="mt-3 grid gap-2 text-[11px] text-slate-500 dark:text-slate-400 sm:grid-cols-2">
                            <p>{{ __('payments.latest_version') }}:
                                <span class="font-mono">{{ $latestVersion ? 'v' . $latestVersion->version : __('payments.no_version_short') }}</span>
                            </p>
                            <p>{{ __('payments.last_updated') }}: {{ $panel['last_updated']?->diffForHumans() ?? '-' }}</p>
                            <p>{{ __('payments.active_merchant') }}:
                                <span class="font-mono">{{ $panel['active']?->maskedMerchantId() ?? $panel['merchant'] ?? '-' }}</span>
                            </p>
                            <p>{{ __('payments.tested_at') }}: {{ $panel['last_validated_at']?->diffForHumans() ?? '-' }}</p>
                        </div>

                        <form method="POST" action="{{ route('admin.payment-settings.credentials.store') }}"
                              class="mt-4 grid gap-3 sm:grid-cols-2">
                            @csrf
                            <input type="hidden" name="environment" value="{{ $panel['environment'] }}">

                            @php($merchantError = $panelHasErrors && $errors->has('merchant_id'))
                            @php($clientError = $panelHasErrors && $errors->has('client_key'))
                            @php($serverError = $panelHasErrors && $errors->has('server_key'))
                            @php($passwordError = $panelHasErrors && $errors->has('current_password'))

                            <div class="sm:col-span-2">
                                <label class="admin-label" for="merchant_id_{{ $panel['environment'] }}">{{ __('payments.merchant_id') }}</label>
                                <input name="merchant_id" id="merchant_id_{{ $panel['environment'] }}" maxlength="64" required
                                       value="{{ $panelHasErrors ? old('merchant_id') : $latestVersion?->merchant_id }}"
                                       @if($merchantError) aria-invalid="true" aria-describedby="merchant_id_error_{{ $panel['environment'] }}" @endif
                                       class="admin-input font-mono {{ $merchantError ? 'border-rose-400 dark:border-rose-500' : '' }}" placeholder="G000002">
                                @if($merchantError)
                                    <p id="merchant_id_error_{{ $panel['environment'] }}" class="mt-1 text-[11px] font-medium text-rose-700 dark:text-rose-400">{{ $errors->first('merchant_id') }}</p>
                                @endif
                            </div>
                            <div>
                                <label class="admin-label" for="client_key_{{ $panel['environment'] }}">{{ __('payments.client_key') }}</label>
                                <input name="client_key" id="client_key_{{ $panel['environment'] }}" maxlength="190" required
                                       value="{{ $panelHasErrors ? old('client_key') : $latestVersion?->client_key }}"
                                       @if($clientError) aria-invalid="true" aria-describedby="client_key_error_{{ $panel['environment'] }}" @endif
                                       class="admin-input font-mono {{ $clientError ? 'border-rose-400 dark:border-rose-500' : '' }}" autocomplete="off">
                                @if($clientError)
                                    <p id="client_key_error_{{ $panel['environment'] }}" class="mt-1 text-[11px] font-medium text-rose-700 dark:text-rose-400">{{ $errors->first('client_key') }}</p>
                                @endif
                            </div>
                            <div>
                                <label class="admin-label" for="server_key_{{ $panel['environment'] }}">{{ __('payments.server_key') }}</label>
                                <input type="password" name="server_key" id="server_key_{{ $panel['environment'] }}" maxlength="500" required
                                       @if($serverError) aria-invalid="true" aria-describedby="server_key_error_{{ $panel['environment'] }}" @endif
                                       class="admin-input font-mono {{ $serverError ? 'border-rose-400 dark:border-rose-500' : '' }}" autocomplete="new-password"
                                       placeholder="{{ $latestVersion ? '••••••••' : '' }}">
                                @if($serverError)
                                    <p id="server_key_error_{{ $panel['environment'] }}" class="mt-1 text-[11px] font-medium text-rose-700 dark:text-rose-400">{{ $errors->first('server_key') }}</p>
                                @else
                                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ __('payments.server_key_help') }}</p>
                                @endif
                            </div>

                            @if($panel['environment'] === 'production' && !$reauthValid)
                                <div class="sm:col-span-2">
                                    <label class="admin-label" for="password_{{ $panel['environment'] }}">{{ __('payments.production_reauth_prompt') }}</label>
                                    <input type="password" name="current_password" id="password_{{ $panel['environment'] }}" maxlength="191"
                                           @if($passwordError) aria-invalid="true" aria-describedby="password_error_{{ $panel['environment'] }}" @endif
                                           class="admin-input {{ $passwordError ? 'border-rose-400 dark:border-rose-500' : '' }}" autocomplete="current-password">
                                    @if($passwordError)
                                        <p id="password_error_{{ $panel['environment'] }}" class="mt-1 text-[11px] font-medium text-rose-700 dark:text-rose-400">{{ $errors->first('current_password') }}</p>
                                    @else
                                        <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-400">{{ __('payments.production_reauth_help') }}</p>
                                    @endif
                                </div>
                            @endif

                            <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
                                <button type="submit" class="admin-btn-primary">{{ __('payments.save_credentials') }}</button>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $panel['environment'] === 'production' ? __('payments.production_panel_note') : __('payments.save_first') }}
                                </span>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>

            @if($account->status !== 'not_configured')
                <form method="POST" action="{{ route('admin.payment-settings.deactivate') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="admin-btn-secondary">{{ __('payments.deactivate') }}</button>
                </form>
            @endif

            @if($environment === 'production' && $isSuperAdmin && !$summary['production_approved'])
                <form method="POST" action="{{ route('admin.payment-settings.production.approve') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    @if(!$reauthValid)
                        <div>
                            <label class="admin-label" for="password_approve">{{ __('payments.production_reauth_prompt') }}</label>
                            <input type="password" name="current_password" id="password_approve" maxlength="191"
                                   class="admin-input" autocomplete="current-password">
                        </div>
                    @endif
                    <button type="submit" class="admin-btn-secondary">{{ __('payments.approve_production') }}</button>
                </form>
            @endif
        </x-admin.panel>

        <x-admin.panel :title="__('payments.version_history')" :subtitle="__('payments.security_note')">
            @if($versions->isEmpty())
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('payments.no_versions') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-2 pr-4">{{ __('payments.version') }}</th>
                                <th class="py-2 pr-4">{{ __('payments.environment') }}</th>
                                <th class="py-2 pr-4">{{ __('payments.merchant_id') }}</th>
                                <th class="py-2 pr-4">{{ __('payments.status') }}</th>
                                <th class="py-2 pr-4">{{ __('payments.tested_at') }}</th>
                                <th class="py-2">{{ __('payments.activation') ?? 'Aksi' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($versions as $version)
                                <tr>
                                    <td class="py-2.5 pr-4 font-mono text-xs">v{{ $version->version }}</td>
                                    <td class="py-2.5 pr-4 text-xs">{{ $version->environment }}</td>
                                    <td class="py-2.5 pr-4 font-mono text-xs">{{ $version->maskedMerchantId() }}</td>
                                    <td class="py-2.5 pr-4">
                                        <x-admin.badge :label="$version->status" :tone="match($version->status) { 'valid' => 'primary', 'pending' => 'amber', 'invalid' => 'rose', default => 'slate' }" />
                                        @if($version->is_active)
                                            <x-admin.badge :label="__('payments.routing_clinic_account')" tone="primary" dot />
                                        @endif
                                    </td>
                                    <td class="py-2.5 pr-4 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $version->last_validated_at?->diffForHumans() ?? '-' }}
                                    </td>
                                    <td class="py-2.5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if($version->status !== 'retired')
                                                <form method="POST" action="{{ route('admin.payment-settings.credentials.test', $version) }}">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-primary hover:underline">{{ __('payments.test_connection') }}</button>
                                                </form>
                                            @endif
                                            @if($version->status === 'valid' && !$version->is_active)
                                                <form method="POST" action="{{ route('admin.payment-settings.credentials.activate', $version) }}"
                                                      class="flex flex-wrap items-center gap-2">
                                                    @csrf
                                                    @if($version->environment === 'production' && !$reauthValid)
                                                        <input type="password" name="current_password" required maxlength="191"
                                                               class="admin-input w-40 py-1 text-xs" autocomplete="current-password"
                                                               placeholder="{{ __('payments.production_reauth_prompt') }}">
                                                    @endif
                                                    <button type="submit" class="text-xs font-semibold text-emerald-600 hover:underline">{{ __('payments.activate') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        <x-admin.panel :title="__('payments.routing_monitor')" :subtitle="__('payments.routing_monitor_desc')">
            <div class="mb-4 flex flex-wrap gap-2">
                <x-admin.badge
                    :label="__('payments.routing_failures_7d') . ': ' . $routingFailures"
                    :tone="$routingFailures > 0 ? 'rose' : 'primary'" dot />
                <x-admin.badge
                    :label="__('payments.notification_failures_7d') . ': ' . $notificationFailures"
                    :tone="$notificationFailures > 0 ? 'amber' : 'primary'" dot />
            </div>

            @if($routingLogs->isEmpty())
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('payments.no_routing_logs') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-2 pr-4">{{ __('payments.routing_time') }}</th>
                                <th class="py-2 pr-4">{{ __('payments.clinic') }}</th>
                                <th class="py-2 pr-4">{{ __('payments.routing_outcome') }}</th>
                                <th class="py-2 pr-4">{{ __('payments.environment') }}</th>
                                <th class="py-2 pr-4">{{ __('payments.reason') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($routingLogs as $log)
                                <tr>
                                    <td class="py-2 pr-4 text-xs text-slate-500 dark:text-slate-400">{{ $log->created_at?->diffForHumans() }}</td>
                                    <td class="py-2 pr-4 text-xs">{{ $log->clinic?->name ?? '-' }}</td>
                                    <td class="py-2 pr-4">
                                        <x-admin.badge
                                            :label="$log->outcome === 'resolved' ? __('payments.routing_resolved') : __('payments.routing_rejected')"
                                            :tone="$log->outcome === 'resolved' ? 'primary' : 'rose'" />
                                    </td>
                                    <td class="py-2 pr-4 text-xs">{{ $log->environment }}</td>
                                    <td class="py-2 pr-4 font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $log->reason ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        <x-admin.panel :title="__('payments.audit_trail')" :subtitle="__('payments.audit_trail_desc')">
            @if($auditLogs->isEmpty())
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('payments.no_audit_logs') }}</p>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($auditLogs as $log)
                        <li class="flex flex-wrap items-start gap-2 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-slate-700 dark:text-slate-200">{{ $log->description }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $log->created_at?->diffForHumans() }}
                                    · {{ __('payments.by_actor') }}: {{ $log->user?->name ?? '-' }}
                                </p>
                            </div>
                            <span class="font-mono text-[10px] text-slate-500 dark:text-slate-400">{{ $log->action }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.panel>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        function fallbackCopy(value, done) {
            var area = document.createElement('textarea');
            area.value = value;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();

            try {
                document.execCommand('copy');
                done();
            } catch (error) {
                // Clipboard tidak tersedia: biarkan teks tetap bisa disalin manual.
            }

            document.body.removeChild(area);
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-copy-target]');

            if (!button) {
                return;
            }

            var target = document.getElementById(button.dataset.copyTarget);

            if (!target) {
                return;
            }

            var value = target.dataset.copyValue || target.textContent.trim();
            var original = button.textContent.trim();
            var done = function () {
                button.textContent = button.dataset.copiedLabel || 'Copied';
                window.setTimeout(function () {
                    button.textContent = original;
                }, 1600);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(done).catch(function () {
                    fallbackCopy(value, done);
                });
            } else {
                fallbackCopy(value, done);
            }
        });
    })();
</script>
@endpush
