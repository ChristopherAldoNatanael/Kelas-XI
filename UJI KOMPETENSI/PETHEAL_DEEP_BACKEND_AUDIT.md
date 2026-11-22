# PETHEAL — EXTREME DEEP BACKEND AUDIT

**Document type:** Forensic backend audit (read-only)
**Project:** `PetHeal_Backend` (Laravel 12.62.0 / PHP 8.3.31) + `PetHeal_Android` (Kotlin) contract
**Audit date:** 2026-05 (single-pass static + flow-trace audit)
**Auditor role:** Principal Software Architect / Senior Backend Engineer / Laravel Expert / Android Backend Integration Specialist / DB Architect / Security Auditor
**Source modification:** NONE. No file, migration, config, view or database row was created, edited or deleted during this audit.

---

## 1. Executive Summary

PetHeal is a multi-tenant veterinary clinic platform with three web portals (Clinic Admin, Doctor Portal, Super Admin) and one Android patient app. The backend is a well-structured Laravel 12 modular monolith (171 PHP files under `app/`, 83 migrations, 44 Eloquent models). Fault tracing was performed from HTTP request → route → throttle → middleware → controller → service → model → DB → side effect → response → consumer (Android `ApiService.kt` or Blade view).

**Overall verdict: the runtime logic is unusually disciplined for a student-scale codebase (row locks, monotonic payment state machine, idempotent webhook, fail-closed tenancy), but the *record-retention* and *money-reconciliation* boundaries are structurally unsound.**

The 33 findings in this report (3 CRITICAL, 10 HIGH, 9 MEDIUM, 7 LOW, 4 INFO) cluster into four failure families:

1. **Destructive history.** Clinical and financial rows are hard-deleted by privileged-but-routine user actions (`PetController::destroy`, `AuthController::deleteAccount`, `Admin\MedicalRecordController::destroy`). FK `cascadeOnDelete` on `bookings.pet_id`, `medical_records.booking_id` and `payment_events.booking_id` turns a "delete my pet" tap into loss of invoices, SOAP notes and payment audit trail — while the *same* destruction is explicitly forbidden in the admin UI (`Admin\UserController`, `Admin\DoctorController`). Two contradictory data-retention policies exist in one codebase.
2. **Duplicated money ledgers.** Money is tracked simultaneously in `bookings.paid_amount/remaining_amount`, `payments.gross_amount/status/refunded_amount`, `payment_events`, `booking_refunds`, `payment_refunds`, and `medical_records.extra_payment_*`. Each writer updates a different subset. The result is confirmed wrong money: extra-payment amounts are computed once and never recomputed after a booking settles (§9, PB-C-02), and refunds never propagate to the booking or to `payments` (§10, PB-H-01/PB-M-02).
3. **Payment-order lifecycle gaps.** A Pet Shop order that is paid a few seconds after its 30-minute reservation window becomes simultaneously `paid` and `expired` with stock already released, and nothing initiates a refund (PB-H-02). Concurrent Snap-token requests can create several payable rows for the same debt, and DP + full can both settle, pushing `paid_amount` above `total_amount` (PB-H-03).
4. **Trust-boundary hygiene.** `trustProxies(at: '*')` makes every IP-keyed rate limiter and every `audit_logs.ip_address` client-controlled (PB-H-08). The unauthenticated `/api/health` endpoint discloses absolute filesystem paths and raw DB exception text (PB-H-09), and `PaymentController` echoes `$e->getMessage()` for self-handled 500s, bypassing the sanitizer installed in `bootstrap/app.php` (PB-H-10).

**Nothing found indicates an unauthenticated full-database compromise.** All authenticated API endpoints reviewed are owner-scoped (`$request->user()->bookings()`, `pets()`, `serviceRequests()`, `where('user_id', ...)`), the doctor portal is scoped by `doctor_id + clinic_id` on every query, and clinic-admin controllers are scoped by `currentClinicId()`. No mass-assignment hole exists (all 44 models use explicit `fillable`, none use `$guarded = []`, and no controller calls `$request->all()` into a model).

**Verified-safe list (explicitly checked, negatives are real negatives):** webhook signature (SHA512 merchant *and* partner, no bypass), webhook idempotency (`payment_events.order_id` UNIQUE + `insertOrIgnore`), payment state monotonicity (`STATUS_RANK`, no paid→pending regression), price authority (server recomputes every Pet Shop line from DB, client amounts are never trusted), queue numbering uniqueness (`bookings_queue_unique`), clinic isolation on the doctor portal, join-request approval atomicity, and OTP storage (hashed, attempt-capped, per-purpose TTL, pruned on issue).

| Severity | Count | Confirmed | Potential | Recommendation |
|---|---|---|---|---|
| CRITICAL | 3 | 3 | 0 | 0 |
| HIGH | 10 | 6 | 4 | 0 |
| MEDIUM | 9 | 8 | 0 | 1 |
| LOW | 7 | 7 | 0 | 0 |
| INFO | 4 | 1 | 0 | 3 |
| **Total** | **33** | 25 | 4 | 4 |

*(Counts every numbered finding in §22. `Potential` = the act requires a timing/concurrency/deployment condition; PB-M-06 is mixed (logging = Confirmed, cache-key collision = Potential) and is counted as Confirmed. CRITICAL/HIGH are the only groups that should block a production launch.)*

---

## 2. Audit Scope, Method & Constraints

### 2.1 In scope

| Area | Basis |
|---|---|
| Laravel backend (`app/`, `routes/`, `database/migrations/`, `bootstrap/`, `config/`) | full static read of controllers, services, middleware, models, migrations, providers |
| Web Admin portal (`routes/web.php`, `app/Http/Controllers/Admin/*`) | flow trace of every write endpoint |
| Web Doctor portal (`app/Http/Controllers/Doctor/*`) | flow trace, ownership scoping, state transitions |
| Web Super Admin (`AdminAuthController`, `ClinicController`, `EnsureSuperAdmin`) | privilege boundary, cross-clinic operations |
| Android-facing API (`routes/api.php`, `app/Http/Controllers/Api/*`) | endpoint-by-endpoint cross-check with `ApiService.kt` |
| Android consumer contract (`PetHeal_Android/**/ApiService.kt`, payment screens, models) | call-site and payload comparison only |
| Database design (83 migrations) | constraint, FK, index, unique-key and cascade review |
| Scheduled jobs (`routes/console.php`, `app/Console/Commands/*`) | scheduler semantics vs. domain invariants |

### 2.2 Method

1. **Inventory**: 171 PHP files under `app/`, 44 models, 83 migrations, 3 route files, 22 API controllers, 24 admin controllers, 9 doctor controllers.
2. **Flow tracing**: each endpoint traced request → route → throttle limiter → middleware (`ApiAuthenticate`, `AdminAuth`, `DoctorAuth`, `EnsureSuperAdmin`) → controller → service → Eloquent → DB write → FCM/audit/cache → response payload → Android field consumption.
3. **Cross-module consistency**: for every invariant (payment state, stock, queue, slot occupancy, tenant) all *writers* were enumerated to prove or disprove a single source of truth.
4. **Falsification pass**: every candidate finding was re-checked against the actual code path; findings that turned out to be intentional (e.g. `register-direct` → 410, `bookings.payment_method` string instead of `payment_method_id`) were demoted to INFO or dropped.

### 2.3 Constraints (affect confidence)

- **No runtime execution.** No `php artisan`, no HTTP calls, no queue worker, no MySQL connection. Every finding is *code-path proven*, not *runtime reproduced*. Status labels reflect this: `Confirmed` = the code path unambiguously produces the effect; `Potential` = the code permits it and an external condition (concurrency, timing, deployment topology) decides whether it happens.
- **Schema read from migrations, not from the live MySQL schema.** If the deployed database drifted from `database/migrations/` this report cannot see it (§24 lists this as the #1 residual gap).
- **No Blade view audit.** Views were read only where they carry authorization or money logic (payment panels, receipt/invoice). UI-only defects are out of scope.
- **No penetration testing, no load testing, no dependency CVE scan** (`composer audit` was not run).
- **Android audit is a contract audit only.** The Android app's own logic (state machines, retries, WebView handling) was read at the call sites that consume backend responses; no APK was built or instrumented.

---

## 3. System Architecture & Module Map

```
PetHeal_Backend/
├─ bootstrap/app.php          middleware aliases, trustProxies('*'), API exception envelopes
├─ routes/
│  ├─ api.php                 212 lines — 60+ endpoints (auth, pets, bookings, payments, pet shop)
│  ├─ web.php                 358 lines — admin + doctor + super admin + join request
│  └─ console.php             scheduler: reminders, otp prune, petshop expiry, payment reconcile
├─ app/Http/Controllers/
│  ├─ Api/       (22 controllers, 6 850 lines)   ← Android contract surface
│  ├─ Admin/     (24 controllers)                ← clinic admin + super admin
│  └─ Doctor/    (9 controllers)                 ← doctor portal
├─ app/Services/  (25 services)                  ← domain logic (payment, stock, queue, schedule)
├─ app/Models/    (44 models)
└─ database/migrations/ (83)
```

**Domain ownership map (single-writer claims, as designed by the authors):**

| Invariant | Designated owner | Actually enforced? |
|---|---|---|
| Booking slot occupancy | `Api/BookingController::store` lock | **No** — 3 write paths, no DB constraint (§17) |
| Queue number | `QueueService` + `bookings_queue_unique` | Yes |
| Doctor availability | `DoctorScheduleService` | Partially — bypassed by ServiceRequest accept |
| Payment money | `PaymentService` + `PaymentStatusService` | Partially — bookings ledger is a second writer |
| Refund money | `RefundService` (cash) / `PaymentService` (provider) | **Two parallel ledgers** (§10) |
| Pet Shop stock | `ProductStockService` | Yes (reserve/commit/release/log, all locked) |
| Clinical record money | `MedicalRecord::recalculatePaymentState` | **Computed once, never refreshed** (§9) |
| Tenant scope | `currentClinicId()` + `PatientScope` | Yes in admin/doctor, N/A for global patients |

**Observed architectural debts (structural, detailed in §21):** no `app/Policies`, no `app/Events`, no `app/Listeners`, no `app/Notifications`; the only queued job is `App\Jobs\SendFcmNotification` and it is *disabled by default* (`config('services.fcm.queue')` defaults to `false`).

---

## 4. Route, Middleware & Throttle Inventory

### 4.1 Global middleware pipeline (`bootstrap/app.php`)

| Element | Value | Audit note |
|---|---|---|
| `trustProxies` | `at: '*'` | **PB-H-08** — any `X-Forwarded-For` is honoured |
| aliases | `admin.auth`, `doctor.auth`, `super_admin` | `super_admin` = `EnsureSuperAdmin` (role string compare) |
| web prepend | `SetLocale` | OK |
| append | `UseRequestAssetOrigin`, `CacheStaticAssets` | presentation only |
| `dontFlash` | `server_key` | prevents Midtrans key leaking into session — good |
| API auth error | canonical 401/422/404 JSON envelopes | OK |
| API 500 | generic `Internal server error.` | **bypassed by self-handled catches** — PB-H-10 |

`web.php`/`api.php` carry no route-level `can:` / `authorize()` — consistent with the absence of Policies (**PB-M-08**).

### 4.2 Throttle map (`AppServiceProvider`)

| Limiter | Key | Limit | Applies to |
|---|---|---|---|
| `auth` | `email|ip` | 8/min | login, register, firebase-login, register-direct |
| `admin-login` | `email|ip` | 5/min | admin/doctor login forms |
| `password-reset` | `email|ip` | 3/min | forgot-password |
| `password-reset-verify` | `email|ip` | 10/min | verify-reset-code, reset-password |
| `otp-send` | `email|ip` | 3/min + 20/day | register OTP send/resend |
| `otp-verify` | `email|ip` | 10/min | register OTP verify |
| `account-otp-send` | `user id` | 3/min + 10/day | delete-account / email-change OTP |
| `account-otp-verify` | `user id` | 10/min | same verify |
| `admin-otp-send` / `doctor-otp-send` | `user id` | 2/min + 10/day | portal password change |
| `api` | `user id` / `ip` | 60/min | all authenticated routes |
| `api-heavy` | `user id` / `ip` | 30/min | defined, **not referenced by any route** |
| `uploads` | `user id` / `ip` | 10/min | pet/profile photo |
| `payment` | `user id` / `ip` | 10/min | snap-token, remaining, extra-pay, shop order/pay/sync |
| `webhook` | `ip` | 120/min | Midtrans webhook |
| `public-catalog` | `ip` | 120/min | public clinics/services/health |

Throttling coverage is genuinely good and the OTP key choice (per-account rather than per-input-email for post-auth flows) is correct. The single structural weakness is that every `ip` in that table is spoofable under `trustProxies('*')` (**PB-H-08**).

### 4.3 Endpoint families (API)

- **Public:** `auth/login|register|firebase-login|register-direct(410)`, `auth/forgot-password`, `auth/verify-reset-code`, `auth/reset-password`, `auth/register/request-otp|resend-otp|verify-otp`, `health`, `public/*`, `payment-methods`, `services`, `services/{id}`, `midtrans/webhook`.
- **Authenticated (`throttle:api` + `ApiAuthenticate`):** auth profile/logout/photo/email-change/delete, dashboard, device-token, notifications, pets (+photo, weight, vaccination), doctors (+slots, reviews), service-requests (index/show/store/accept/reject), bookings (index/store/show/destroy/upcoming/check-in/cancel/reschedule/medical-record), medical-records (index/show/byPet/pay/payment-status), payment (preflight/snap-token/transaction-status/sync-status/remaining/booking), pet-shop (catalog/orders/receipt/pickup-pass).

### 4.4 Scheduler (`routes/console.php`)

| Command | Cadence | Domain effect |
|---|---|---|
| `reminders:send` | daily 08:00 | FCM for tomorrow's bookings + vaccinations; guarded by `last_reminder_at` |
| `otp_codes` prune | daily | expired OTP cleanup |
| `petshop:expire-orders` | every 5 min | `ProductOrderService::expireStaleOrders` → **PB-H-02** |
| `payments:reconcile --limit=50` | every 15 min | provider re-read for `pending` payments |

No `queue:work` scheduling exists anywhere in the repo; `QUEUE_CONNECTION=database` is configured but the only dispatch site is opt-in (**PB-L-06**).

---

## 5. Authentication & Session Model

### 5.1 Android (Sanctum personal access tokens)

- Guard chain: `ApiAuthenticate` → `bearerToken()` presence → `auth('sanctum')->check()` → `auth()->setUser()`.
- Token lifetime: `config/sanctum.php` `expiration = env('SANCTUM_EXPIRATION_MINUTES', 43200)` → **30 days**, non-null (tokens do expire — verified, not a finding).
- Logout deletes `currentAccessToken()` only; account deletion deletes *all* tokens inside the transaction.
- `X-Clinic-Slug` is *context only*: for staff it must equal their own clinic slug, for patients it is ignored as an authorization source. Correctly documented and correctly implemented.

### 5.2 Session-based tenants (Admin / Doctor)

- `AdminAuth`: unauthenticated → redirect to `admin.login`; a `doctor` role is bounced to the doctor dashboard; allowed roles `super_admin, clinic_admin, admin`; **fail-closed** — a non-`super_admin` without `clinic_id`, or bound to a non-active clinic, is rejected. `Clinic::toggleActive` therefore instantly locks out that clinic's admins.
- `DoctorAuth`: resolves the doctor profile from the session user; portal queries are always `where('doctor_id', $doctor->id)->where('clinic_id', $doctor->clinic_id)`.
- `EnsureSuperAdmin`: exact role-string compare; used on `/switch-clinic`, `/join-requests*`, `/clinics*`, `/notification-settings`, service categories, merchant config.

### 5.3 OTP subsystem (`OtpService`)

Hashed storage (`hash('sha256')`), `MAX_ATTEMPTS = 5`, per-purpose TTL (register/reset 10 min, delete-account/email-change 15 min), opportunistic pruning on issue, purge on success. Purposes are closed-set (`register`, `reset`, `delete_account`, `email_change`, `admin_register`, `admin_password`, `doctor_password`), so an OTP minted for one flow cannot be replayed into another. `verifyDeleteOtp` additionally guards cross-account reuse (`DELETE_VERIFIED_PREFIX . user->id`). **No OTP abuse finding.**

### 5.4 Residual authentication notes

- No MFA on admin/super-admin logins; portal password change uses email OTP instead (`admin-otp-send`, 2/min). Acceptable for the domain, worth an explicit decision record.
- Token abilities are not used (no `createToken(..., ['booking:read'])`); a token is all-powerful for its user. Not a defect, but it removes the ability to limit a leaked token.

---

## 6. Authorization & Multi-Tenant Isolation

### 6.1 Model in force

There are **no Laravel Policies and no Gates** (`app/Policies` does not exist; `Gate::`, `@can`, `authorize(` and Spatie `HasRoles` appear nowhere in `app/` or `routes/`). Authorization is 100% imperative:

| Layer | Mechanism |
|---|---|
| Android | `ApiAuthenticate` tenant binding + per-query `user_id` scoping in controllers |
| Admin | `AdminAuth` + `currentClinicId()` filters + `PatientScope::scopeClinicUsers` |
| Doctor | `DoctorAuth` + `doctor_id`+`clinic_id` filters |
| Super Admin | `EnsureSuperAdmin` + explicit session clinic switching |

### 6.2 Verified isolation results

| Check | Result | Evidence |
|---|---|---|
| Patient reads another patient's booking | **Blocked** | `$request->user()->bookings()->find($id)` in `Api/BookingController` index/show/cancel/checkIn |
| Patient reads another patient's medical record | **Blocked** | `whereHas('booking', fn($q) => $q->where('user_id', ...))` in `Api/MedicalRecordController` |
| Patient pays another patient's record | **Blocked** | `createExtraPayment` ownership `whereHas`; `getTransactionStatus` re-checks ownership per parsed prefix |
| Patient reads another patient's pet weight/vaccination | **Blocked** | `$request->user()->pets()->findOrFail($petId)` |
| Patient reads another patient's service request | **Blocked** | `$request->user()->serviceRequests()->find($id)` |
| Patient reads another patient's shop order | **Blocked** | `ProductOrder::where('user_id', ...)` on all 12 order endpoints |
| Clinic admin reads another clinic's booking/record/user | **Blocked** | `currentClinicId()` filter on every admin query reviewed |
| Clinic admin reads another clinic's product/order | **Blocked** | `PetShopProductController`/`PetShopOrderController` scope by `currentClinicId()` |
| Doctor reads another doctor's records | **Blocked** | `doctor_id` + `clinic_id` on all `DoctorMedicalRecordController` queries |
| Doctor escalates to admin | **Blocked** | `AdminAuth` redirects `role=doctor` to doctor dashboard |
| Clinic admin performs super-admin action | **Blocked** | `/clinics*`, `/join-requests*`, `/switch-clinic`, `/notification-settings` behind `EnsureSuperAdmin` |
| Clinic admin edits another staff's credentials | **Blocked** | `canManageAccount()` in `Admin/UserController` (only `user` role, self, or super admin) |
| Cross-clinic doctor/service pairing in a booking | **Blocked** | doctor/service `clinic_id` equality + tenant-scoped `Rule::exists` |
| Deactivated clinic's admin keeps access | **Blocked** | `AdminAuth` requires `clinic->is_active`; `ApiAuthenticate` requires it for staff |
| Cross-account device-token theft | **Blocked** | `DeviceTokenController` returns 409 if the token belongs to another user |

### 6.3 Isolation residue

- Global patients (`role = user`) legitimately have `clinic_id = NULL` and may book anywhere; that is a deliberate multi-clinic design, **but** it means the patient side has no tenant filter at all — isolation there depends entirely on correct `user_id` scoping inside each controller. One missing `->where('user_id', ...)` in a future endpoint is a cross-patient leak with no second line of defence (**PB-M-08**).
- `ApiAuthenticate` bypasses tenant checks for `api/auth/account*` and `api/auth/email/change/*`. The bypass is currently justified (self-scoped endpoints must stay reachable for a suspended clinic). However it is a **prefix-based** bypass: any future route added under `api/auth/account/...` inherits it silently (noted, not scored).
- `NotificationController`, `DashboardController` and `PetController` are scoped by `Auth::id()` / `user->pets()` — verified correct.

---

## 7. Booking Lifecycle Flow Trace

### 7.1 Create (`POST /api/bookings` → `Api\BookingController::store`)

```
throttle:api → ApiAuthenticate (tenant bind)
  → validate: pet_id(owner), doctor_id(active, clinic-scoped), service_id(active, clinic-scoped),
              booking_date >= today, booking_time H:i, payment_type in {dp,full}, medical_record_id int
  → re-fetch pet (owner), doctor (active), service (active)
  → medical_record_id must belong to pet AND to doctor's clinic            [guard present]
  → doctor.clinic_id === service.clinic_id                                 [guard present]
  → DoctorScheduleService::validateSlot(doctor, date, time)                [guard present]
  → amount = service.price (server), dp = Money::percentOf(50), remaining, payment_status
  → DB::transaction:
        SELECT ... FOR UPDATE booking where doctor+clinic+date+time and status in (pending,confirmed)
        if exists → return null (→ 409 slot_conflict)
        Booking::create(...)
  → FCM sendBookingStatusUpdate(...)      [outside transaction]
  → 201 { data: booking with queue_label, queue_position }
```

**Correct:** price authority, clinic pairing, schedule validation, tenant-scoped exists rules, lock-wrapped insert, distinct 409.
**Broken:**
- The uniqueness guarantee is *application-only*: there is **no UNIQUE index** on `(clinic_id, doctor_id, booking_date, booking_time)` for active statuses, and two other code paths create bookings with *different* duplicate checks (`ServiceRequestService::accept` uses `exists()` under `lockForUpdate`; the admin flow uses neither) → **PB-H-04**.
- `ServiceRequestService::accept` never calls `DoctorScheduleService::validateSlot` → **PB-H-05**.

### 7.2 Check-in (`POST /api/bookings/{id}/check-in` → `::checkIn` → `QueueService::checkIn`)

Guard chain: owner → `booking_date == today` → `QueueService` requires status `confirmed` → unique queue number per `(clinic_id, queue_date)` inside a locked transaction → FCM after commit. Correct and idempotent.

### 7.3 Cancel / Reschedule (`POST .../cancel`, `.../reschedule`)

`cancel` sets `status = cancelled` and `cancellation_reason`; it does **not** touch `payment_status`, `paid_amount`, or `payments`, and creates no refund row. `Admin\BookingController::cancel` behaves identically. Compare with `ProductOrderService::transition` (Pet Shop), which *does* flip `payment_status` to `refund_pending` on cancel-after-payment. The asymmetry is the core of **PB-H-01**.

### 7.4 Complete (`Admin\BookingController::complete`)

Correct: `whereIn('status', ['pending','confirmed'])` + `lockForUpdate`, settled check `payment_status === 'paid' || remaining_amount <= 0`, explicit `confirm_unpaid` override, then FCM + audit.

### 7.5 Delete (`DELETE /api/bookings/{id}`)

`apiResource('bookings')->only([...,'destroy'])` → default `destroy` hard-deletes the booking. `payment_events.booking_id` is `cascadeOnDelete`, so the payment audit trail disappears with it, while `payments` rows (polymorphic `payable`) survive as orphans. This is the same destructive family as **PB-C-01/PB-C-03**.

### 7.6 Booking state machine reality

```
pending ──confirm──► confirmed ──complete──► completed
   │                     │
   └────cancel───────────┴──► cancelled
            ▲
            └── doctor/admin may create a MedicalRecord from a `pending` booking,
                which force-sets `completed` (see §9.1) — an undocumented 4th edge.
```
There is **no transition table / no guard object** for bookings (contrast with `QueueService::TRANSITIONS`, `ServiceRequest::TRANSITIONS`, `ProductOrder::canTransitionTo`). Transitions are enforced ad-hoc by `whereIn` clauses that differ per call site, which is what allows the `pending → completed` edge in §9.1 (**PB-H-06**).

---

## 8. Queue Lifecycle Flow Trace

| Transition | Allowed from | Implemented in |
|---|---|---|
| → `called` | waiting | `QueueService::transition` |
| → `in_service` | waiting, called | same |
| → `done` | waiting, called, in_service | same (+ auto after medical record) |
| → `no_show` | waiting, called | same |
| → `waiting` (re-queue) | called, in_service, no_show, cancelled | same |

Verified properties: `TRANSITIONS` map is closed-set and rejects unknown status; numbering is `max(queue_number)+1` per `(clinic_id, queue_date)` guarded by `UNIQUE(clinic_id, queue_date, queue_number)`; `releaseOnCancel` marks the queue row cancelled and preserves the number (no renumbering → no duplicate labels); `positionOf` counts `waiting`+`called` ahead within the same clinic+date; FCM dispatch occurs after commit.

**Confirmed defect (narrow):** the auto-close hook in `DoctorMedicalRecordController` and `AdminMedicalRecordController` uses `in_array(queue_status, QueueService::ACTIVE_STATUSES)` and then transitions to `done`. Because `waiting → done` is a legal edge, a medical record written for a **never-checked-in** booking silently consumes and closes a queue slot that was never announced to the patient. Root cause is shared with **PB-H-06** (record creation does not validate booking lifecycle) and is reported there rather than duplicated.

Non-findings checked: no counter table to drift; no `no_show` deletion; no cross-clinic number leakage.

---

## 9. Medical Record, SOAP & Prescription Flow

### 9.1 Creation (`Admin\MedicalRecordController::store`, `Doctor\MedicalRecordController::store`)

```
guard: booking belongs to (clinic) / (doctor+clinic); booking.status != cancelled
guard: one record per booking
cost := normalizeMoneyInput('cost') ?: booking.total_amount
DB::transaction:
   record.save()
   PrescriptionService::syncForRecord(record, prescription[])   // restore-then-recreate stock, clinic-checked
   if prescriptionTotal > 0 → record.medicine_cost = prescriptionTotal
   record.recalculatePaymentState(booking.paid_amount)
   record.save()
   MedicalRecordAttachmentService::storeMany(...)               // upload failure rolls back whole record
   booking.update(status: 'completed', completed_at: now())
```

**What is right:** prescriptions cannot leak stock across clinics; stock is restored before recreation so repeated edits don't double-deduct; attachments are inside the transaction; `medicine_cost` is recomputed from the prescription, not trusted from the client.

**Two confirmed defects:**

1. **Payment state is ignored.** A `pending` (unpaid, not checked-in) booking is force-completed. `PaymentService::createBookingPayment` then refuses to create a payment for `completed` bookings (`if (in_array($booking->status, ['cancelled','completed']))`) — so the receivable can never be collected through the app, and the queue slot is closed from `waiting` (§8). → **PB-H-06**
2. **`recalculatePaymentState` is write-once.** It is called from exactly four places — all four are *record* create/edit controllers. No payment path recomputes it:

```
record.extra_payment_amount = max(0, total_medical_cost − booking.paid_amount)   // at record save
```
If the booking settles *after* the record is written, `extra_payment_amount` keeps the pre-payment deficit and the patient is charged twice for the same money → **PB-C-02**.

### 9.2 Clinical-field gating (`can_view_full_record` / `maskClinicalFields`)

`MedicalRecord::can_view_full_record` is true only when `extra_payment_status ∈ {not_required, paid}`. Masking is **caller-driven**, so every raw serialization must remember it. Audit of all raw serialization points:

| Endpoint / view | Masks? |
|---|---|
| `Api\MedicalRecordController::serializeRecord` | Yes (explicit gate, diagnosis/SOAP/treatment) |
| `Api\PetController::index` / `show` | Yes (`->each->maskClinicalFields()`) |
| `Api\BookingController::show` | Yes (`$booking->medicalRecord?->maskClinicalFields()`) |
| `Api\PetController::index` nested `medicalRecords` | Yes |
| `Api\DashboardController` recent visits | Yes |
| `Api\BookingController::index` (booking list, eager `pet`) | **No medical record included** — not a leak |
| Admin/Doctor views | Intentionally unmasked (clinical staff) |

Conclusion: the gate is currently complete. It remains a **fragile invariant** (one new endpoint returning the model raw leaks a diagnosis), recorded inside **PB-M-08**.

### 9.3 Prescription / stock coupling

`PrescriptionService::syncForRecord` runs inside the record transaction, validates every `medicine_id` against `clinic_id`, restores previously issued stock via `restoreForMedicalRecord` before deleting/recreating items, and recomputes `medicine_cost`. `Admin\MedicalRecordController::destroy` calls `restoreOnDelete($record)` before `$record->delete()`, so deleted records no longer silently consume medicine stock. **No defect found** in the prescription/stock path.

---

## 10. Payment, Settlement, Refund & Reconciliation Flow

### 10.1 Ledger inventory (the core problem)

| Store | Written by | Read by |
|---|---|---|
| `bookings.paid_amount / remaining_amount / payment_status` | `PaymentStatusService::applyTransactionStatus`, `Admin\PaymentController::confirm` | dashboards, app, admin payment list, `PaymentService::bookingAmountFor` |
| `payments.gross_amount / status / refunded_amount` | `PaymentService` create + `applyProviderNotification` | reconciliation, pet-shop receipts, admin config panels |
| `payment_events` (UNIQUE `order_id`) | `applyTransactionStatus`, `applyMedicalRecordTransactionStatus` | idempotency only |
| `booking_refunds` | `RefundService::record` (manual admin cash/transfer) | `PaymentController::paymentStats`, booking invoice |
| `payment_refunds` + `payments.refunded_amount` | `PaymentService` provider-refund path | `ReceiptService`, `PetShopOrderController` |
| `medical_records.extra_payment_*` | `recalculatePaymentState` | clinical gate, app, dashboard outstanding |

Each individual writer is internally correct (locks, monotonic ranks, idempotent inserts). The **problem is that no single statement reconciles these six views of "how much money exists"**. Three findings follow from this (PB-M-01, PB-M-02, PB-C-02).

### 10.2 Create payment (`POST /api/payment/snap-token`)

```
validate order_id regex ^BOOKING-\d+-\d+$ ; gross_amount >= 1
parse → booking must be owned by caller
reject if booking.payment_status === 'paid'
expected = payment_type === 'dp' ? dp_amount : total_amount
if requested != expected → 422 (correct: client amount never trusted)
kind = payment_type === 'dp' ? 'dp' : 'full'
PaymentService::createBookingPayment:
    reject cancelled/completed ; reject paid
    findReusableBookingPayment(payable, amount, status=pending, redirect_url not null, not expired)
    if found → return it                                   [idempotent reuse]
    if requested order_id exists with same payable+amount+pending → return it
    resolve clinic merchant account (PaymentAccountResolver)
    Payment::create(status: unpaid, expires_at: now+30m, split_settings snapshot)
    gateway->createTransaction(...) ; on failure → status failed + history + exception
    status: pending + snap_token + redirect_url
```

**Confirmed correct:** server-side amount, server-side order id, account routing snapshot, no client-controlled money.
**Confirmed defect:** there is **no unique constraint** on `(payable_type, payable_id, gross_amount, status)` and `findReusableBookingPayment` is a plain SELECT — two concurrent requests both miss the reuse check and both insert a payable `pending` row. A user can then settle both (DP *and* full), and `applyTransactionStatus` accumulates `paid_amount` without a cap → `paid_amount > total_amount`. → **PB-H-03**

**Amount-kind trap (Android side):** the app computes `if (isDpPayment) booking.dpAmount ?: totalAmount else booking.totalAmount` (`PaymentScreen.kt:154`), and `PaymentViewModel.kt:540` falls back to `orderId = "BOOKING-$bookingId"`. The first sends the wrong gross for a DP booking with a null `dpAmount` (backend → 422 amount mismatch); the second fails the backend regex `^BOOKING-\d+-\d+$` (backend → 422 invalid booking order ID). Both are hard dead-ends for the patient. → **PB-M-04**

### 10.3 Webhook (`POST /api/midtrans/webhook`)

```
throttle:webhook(ip) → load Payment by provider_order_id (nullable!)
record payment_notifications{signature_valid, payload, ip}
verifySignature:
   1. routed payment → accountGateway->verifySignature (SHA512 with that account's key)
   2. partner notification → partnerGateway->verifyNotification
   3. legacy → SHA512(order_id+status_code+gross_amount + central/legacy server key), hash_equals
   fail → 403 (never processed)
dispatch by prefix: SHOP-* → handlePetShop ; MEDREC-* → handleMedicalRecord ; BOOKING-* → handleBooking
each handler: merchant_id match, gross_amount match, currency match → 422 on mismatch
apply via PaymentService/PaymentStatusService (idempotent, monotonic)
```

**Verified strong:** the signature cannot be bypassed (403 before any state change), merchant/gross/currency are cross-checked, replay-safe via `payment_events.order_id` UNIQUE + `insertOrIgnore`, `STATUS_RANK` prevents paid→pending and pending→failed regressions, and the raw payload is persisted in `payment_notifications` for forensics.

**Confirmed defects:**
- Unmatched orders return `200 {"status":"ok"}` (`SHOP-*` with no `payments` row; any prefix not matching the three patterns): the settlement is acknowledged and never retried, with only a `Log::warning`. There is no dead-letter table. → **PB-M-03**
- A paid Pet Shop order whose `reservation_expires_at` already passed is expired by the 5-minute scheduler (`expireStaleOrders` filters `payment_status != paid` at *selection* time); if the webhook lands between selection and transition, `applyToPayable` sets `payment_status = paid` but only advances `status` when it is `pending_payment`. Result: `status = expired`, `payment_status = paid`, stock released, no refund initiated. → **PB-H-02**

### 10.4 Reconciliation (`payments:reconcile`, every 15 min, limit 50)

`syncPending` re-reads provider status for `Payment::pendingCheck()` rows older than 15 minutes, rejects on gross/currency/merchant mismatch, otherwise delegates to `applyProviderNotification`. Correct, rate-limited, provider-truthful. It can only repair `payments` rows — it never repairs the `bookings` ledger or `medical_records.extra_payment_*` (**PB-M-01 / PB-C-02**).

### 10.5 Refund flow

Two entirely separate implementations:

| Path | Entry | Writes | Propagates to |
|---|---|---|---|
| Manual cash/transfer refund | `Admin\PaymentController::refund` → `RefundService::record` | `booking_refunds` + `audit_logs` | nothing else |
| Provider refund | `PaymentService` refund request path | `payment_refunds`, `payments.refunded_amount`, `payments.status` | `payments` only |

`RefundService::record` requires `booking.status === cancelled`, guards over-refund with integer rupiah arithmetic under `lockForUpdate` (correct), but leaves `bookings.payment_status = 'paid'`, leaves the patient's app showing "lunas", sends no notification, and is invisible to the provider ledger. Conversely, the provider path never writes `booking_refunds`. → **PB-H-01 / PB-M-02**

### 10.6 Manual confirmation (`Admin\PaymentController::confirm`)

Correct implementation (row lock, `remaining` cap, `Money::rupiah` integer math, status recompute, audit + cache invalidation) — **but it writes only the `bookings` ledger**. No `payments`/`payment_events` row is created, so reconciliation and payment-domain receipts cannot see this money → **PB-M-01**.

---

## 11. Pet Shop Order & Stock Flow

```
POST /pet-shop/orders  → ProductOrderService::placeOrder
  method ∈ {online, pay_at_clinic}
  merge duplicate variant rows (prevents per-line stock bypass)
  DB::transaction:
     SELECT variants (with product.clinic) WHERE id IN (...) ORDER BY id FOR UPDATE
     require exactly one clinic, clinic active
     per line: quantity <= max, product active & not trashed, variant active,
               available = stock_on_hand - stock_reserved >= quantity
               unit/effective price, discount, subtotal  (all recomputed from DB)
     total = subtotal - discount  (integer rupiah)
     expires_at = +30m (online) / +24h (pay at clinic)
     order.status = pending_payment | awaiting_confirmation
     create items → status history → ProductStockService::reserve()
```

**Verified strong:** `ORDER BY id ... FOR UPDATE` gives a deterministic lock order (deadlock-resistant), prices/discounts/totals are DB-derived, `available` uses physical minus reserved, stock movements are logged with before/after counters, `reserve/commit/release` are individually idempotent via `stock_reserved_at`/`stock_committed_at`/`stock_released_at`, and `commit` refuses when the reservation was already released.

**Confirmed defects:**

| Issue | Detail | ID |
|---|---|---|
| Paid-but-expired order | Scheduler + late webhook can produce `paid` + `expired`; stock released, no refund | PB-H-02 |
| Bypassed transition table | `applyToPayable` writes status/`payment_status` and calls `ProductStockService::release` + `ProductOrderService::log` directly instead of `transition()`, so `cancelled_at`, `cancellation_reason`, `completed_at` bookkeeping and pickup-pass issue/revoke follow a second, divergent code path | PB-M-09 |
| Pickup pass revocation | `transition()` issues/revokes passes; the webhook expiry branch does not, so a pass issued before expiry survives | PB-M-09 |

Not found: no path lets a client set price, total, or stock; no path commits stock twice; no path releases stock for a committed order.

---

## 12. Service Request (Offer → Booking) Flow

```
patient submit → clinic/admin offer → patient accept/reject
statuses: submitted → under_review → offered → converted | rejected | cancelled | expired
TRANSITIONS map is closed-set (verified) and advance() rejects illegal edges
```

**Verified strong:** `accept()` runs inside `lockForUpdate`, is idempotent for double-accept (`status === converted` returns the existing booking, and refuses to return null), re-validates that the offered doctor is still active *in that clinic*, re-checks slot occupancy, and never creates a payment before the owner approves. `submit()` validates `clinic_id` (active), `pet_id` (owner), `medical_record_id` (pet + clinic) and `doctor_id` (doctor of that clinic) in the controller. `expireIfNeeded` is idempotent and only notifies on an actual change.

**Confirmed defects:**

1. **`accept()` bypasses `DoctorScheduleService::validateSlot`.** A clinic can offer a time outside practice hours, on a leave day, or beyond the doctor's weekly schedule; the resulting booking is created without the schedule check that `Api\BookingController::store` performs. → **PB-H-05**
2. **`accept()` does not verify the clinic is active** (only that the doctor is active *within* that clinic). An inactive clinic can still be converted into a live booking. → **PB-H-05**
3. **`advance()` and `offer()` perform unlocked read-modify-write** (`$request->fill($attributes)->save()`) with no `lockForUpdate` and no version column, so two admins editing concurrently produce a silent lost update — and the loser's notification still fires. → **PB-M-07**
4. `offer()` does not validate that `offer_doctor_id` belongs to `clinic_id` at write time (only at accept time), so an invalid offer can be presented to the patient and then fail on accept. → **PB-L-07**

---

## 13. Doctor Portal Audit

Coverage: `DoctorAuthController`, `DoctorBookingController`, `DoctorDashboardController`, `DoctorMedicalRecordController`, `DoctorPortalController`, `DoctorProfileController`, `DoctorQueueController`, `DoctorScheduleController`, `DoctorServiceRequestController` (1 642 lines).

| Area | Result |
|---|---|
| Cross-doctor data access | **Blocked** — every query carries `doctor_id` + `clinic_id`; schedule exceptions scoped by `doctor_id+clinic_id+date` |
| Cross-clinic data access | **Blocked** — `clinic_id` is part of every filter; a doctor cannot switch clinic |
| Login / credential change | Email-OTP based (`doctor-otp-send` 2/min + 10/day, `doctor-password-verify` 10/min), invite-token based account creation |
| Medical record creation | Correctly transactional (record + prescription + attachments + booking completion), but inherits **PB-H-06** (no payment/lifecycle guard) and **PB-C-02** (single-shot money recompute) |
| Prescription | Clinic-scoped medicine validation, stock restore-before-recreate |
| Queue control | Full transition matrix available; auto-close after record inherits the §8 note |
| Schedule management | `DoctorScheduleService` is the single source of truth for the API path; portal writes are restricted to the doctor's own weekly rows/exceptions |

**No doctor-portal-specific security finding.** Its defects are shared-domain (PB-H-06, PB-C-02). One residual risk is architectural: the portal's isolation is 100% dependent on every future query remembering the two `where` clauses (**PB-M-08**).

---

## 14. Admin & Super Admin Portal Audit

Coverage: 24 controllers under `Admin/`, `AdminAuthController` (join requests, clinic switching, approvals), `EnsureSuperAdmin`.

| Area | Result |
|---|---|
| Clinic scoping on reads/writes | **Consistent** — `currentClinicId()` applied to bookings, records, payments, users, doctors, products, orders, stock, queue, reports |
| Patient-scope definition | Centralised in `PatientScope::scopeClinicUsers` (staff of clinic **or** patient with booking/record in clinic) — one definition reused by `Admin/UserController` and dashboards |
| Destructive actions | History-preserving: `UserController::destroy` refuses patients with bookings; `DoctorController::destroy` deactivates instead of deleting when history exists. **Only `MedicalRecordController::destroy` breaks the pattern** → PB-H-07 |
| Join request approval | `lockForUpdate`, slug/email uniqueness re-check, clinic + `clinic_admin` user created in one transaction |
| Clinic switching | `super_admin` only; session key `current_clinic_id`; non-super users are always bound to their own clinic (session cannot override) |
| Credential visibility | `clinic_payment_accounts.server_key` dropped by migration; `dontFlash('server_key')`; `PaymentSettingsController` gated to super admin |
| Audit logging | `AuditLog::log` on booking confirm/complete/cancel, payment confirm/refund, user create/update/delete, doctor create/deactivate/delete, clinic CRUD, cleanup/reconcile |
| Cache invalidation | `forgetDashboardCaches` keys (`payment_stats_c{suffix}`, `dashboard_stats_v4_c{suffix}_all`) invalidated on money mutations |

**Confirmed admin-side defects:** PB-H-01 (cancel after payment), PB-H-07 (record delete), PB-M-01 (manual confirm writes one ledger), PB-L-01 (attachment files orphaned on record delete). Plus two contract-drift items: `HealthController` still requires the deprecated `register-direct` route (PB-L-03) and money-input normalisation / record serialisation is duplicated between Admin and Doctor controllers (PB-L-04).

---

## 15. Android ↔ Backend Contract Cross-Check

Every Retrofit call in `PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/data/remote/ApiService.kt` (74 methods) was matched against `routes/api.php` and the controller signature/payload.

| # | Android call | Backend route | Verdict |
|---|---|---|---|
| 1 | `POST auth/login` | `api/auth/login` | Match |
| 2 | `POST auth/register/request-otp` | same | Match (throttle `otp-send`) |
| 3 | `POST auth/register/verify-otp` | same | Match |
| 4 | `POST auth/register/resend-otp` | same | Match |
| 5 | `POST auth/firebase-login` | same | Match |
| 6 | `POST auth/register` | same | Match (`FirebaseRegisterRequest`) |
| 7 | `POST auth/logout` | same | Match (revokes current token) |
| 8 | `DELETE auth/account` | same | Match; 428 `delete_verification_required` on valuable data — app must handle 428 |
| 9 | `POST auth/account/delete/request-otp`, `/verify-otp` | same | Match |
| 10 | `POST auth/email/change/request-otp`, `/verify-otp` | same | Match |
| 11 | `GET auth/profile` / `PUT auth/profile` (Map<String,String>) | same | Match, but the app sends a free-form map: backend validation must stay explicit (it does) |
| 12 | `POST auth/profile/photo` (multipart) | same, `throttle:uploads` | Match |
| 13 | `POST auth/forgot-password`, `verify-reset-code`, `reset-password` | same | Match |
| 14 | `GET dashboard` | same | Match |
| 15 | `GET pets`, `GET pets/{id}`, `POST pets`, `POST pets/with-photo`, `PUT pets/{id}`, `POST pets/{id}/photo`, `DELETE pets/{id}` | same | Match — but `DELETE pets/{id}` is destructive (PB-C-01) |
| 16 | `GET pets/{petId}/weight-history`, `POST .../weight-records`, `DELETE .../weight-records/{recordId}` | same | Match |
| 17 | `GET pets/{petId}/vaccinations`, `POST ...`, `DELETE .../{vaccinationId}` | same | Match (no `PUT` call) |
| 18 | `GET public/clinics`, `GET public/clinics/{slug}` | same | Match (query params `latitude/longitude/city/search` accepted) |
| 19 | `GET public/service-categories`, `public/services/compare`, `public/services/{id}` | same | Match (`whereNumber` id) |
| 20 | `GET services`, `GET services/{id}` | same | Match |
| 21 | `GET doctors`, `GET doctors/{id}` | same | Match (`clinic_id`, `clinic_slug`) |
| 22 | `GET doctors/{id}/slots?date=` | same | Match (`date` required) |
| 23 | `GET/POST doctors/{id}/reviews` | same | Match |
| 24 | `GET bookings`, `bookings/upcoming`, `bookings/{id}`, `POST bookings`, `POST bookings/{id}/check-in`, `/cancel`, `/reschedule`, `DELETE bookings/{id}` | same | Match; `check-in` was previously 500 (route pointed at a missing method) and is now implemented |
| 25 | `GET service-requests`, `{id}`, `POST` `accept`, `reject` | same | Match (`whereNumber` guards) |
| 26 | `GET medical-records?page`, `GET medical-records/{id}`, `GET bookings/{id}/medical-record`, `GET pets/{petId}/medical-records` | same | Match |
| 27 | `POST medical-records/{id}/pay` | same, `throttle:payment` | Match (`SnapTokenResponse` = `paymentResponseData`) |
| 28 | `GET medical-records/{id}/payment-status` | same | Match |
| 29 | `POST device-token` (JSON body), `DELETE device-token` (body via `@HTTP`) | same | Match; 409 on foreign token |
| 30 | `GET notifications?limit`, `POST notifications/{id}/read` (String id), `POST notifications/read-all`, `DELETE notifications` | same | Match (`{'id}'` cast to `int`) |
| 31 | `GET payment-methods` | same | Match |
| 32 | `GET payment/preflight` | same | Match |
| 33 | `POST payment/snap-token` (`SnapTokenRequest`) | same | **Mismatch risk** — see PB-M-04 (amount + order-id format) |
| 34 | `GET payment/transaction-status/{orderId}` | same | Match (BOOKING/MEDREC prefixes only; any other prefix → 422) |
| 35 | `POST payment/sync-status` (`order_id`) | same | Match |
| 36 | `POST payment/remaining/{bookingId}` | same | Match |
| 37 | `GET payment/booking/{bookingId}` | same | Match |
| 38 | `GET pet-shop/categories`, `sellers`, `products` (`search/category/clinic_id/sort/page/per_page`), `products/{id}` (`latitude/longitude/variant_id/quantity`), `products/{id}/clinics`, `clinics/nearby`, `clinics/filters` | same | Match |
| 39 | `GET/POST pet-shop/orders`, `GET orders/{id}`, `POST orders/{id}/cancel`, `/pay`, `GET orders/{id}/payment-status`, `POST orders/{id}/payment/sync`, `GET orders/{id}/receipt`, `GET orders/{id}/pickup-pass`, `GET orders/{id}/pickup-pass/qr` (`@Streaming`) | same | Match |
| 40 | *(no Android call)* | `PUT pets/{petId}/vaccinations/{vaccinationId}` | **Backend endpoint unused** → PB-L-02 |
| 41 | *(no Android call)* | `GET pet-shop/orders/{id}/receipt/pdf` | Deliberately retired (comment in `ApiService.kt`: PDF is rendered on-device) |
| 42 | *(no Android call)* | `POST auth/register-direct` | Returns 410 `code:use_otp_flow` by design → PB-I-04 |

**Field-level checks performed:** `queue_label`/`queue_position` present on booking index/show/upcoming/check-in (yes); `clinic` object on booking payloads (yes); `maskClinicalFields` on every medical-record-bearing payload (§9.2); `payment_status`/`remaining_amount` present on booking payloads (yes); `SnapTokenResponse` reads `data.token`/`data.redirect_url` (matches `paymentResponseData`); 401/422/404/409/428/502 envelopes match the canonical shapes installed in `bootstrap/app.php`.

**Contract gaps that are *not* code defects but should be documented:** the app never calls `PUT auth/profile` with a typed model (free-form map ⇒ silent field drops if the backend ever tightens validation), and the app has no handling path documented for `409 slot_conflict`, `428 delete_verification_required`, or `422 slot_unavailable` beyond a toast — verified only in code paths, not in UI behaviour (out of scope).

---

## 16. Database Integrity, Constraints & Index Review

### 16.1 Referential integrity (from migrations)

| FK | Policy | Consequence |
|---|---|---|
| `bookings.user_id → users` | `cascadeOnDelete` | user deletion destroys bookings (guarded only in the admin path) |
| `bookings.pet_id → pets` | `cascadeOnDelete` | pet deletion destroys bookings (no guard anywhere) → **PB-C-01** |
| `bookings.doctor_id → doctors` | `cascadeOnDelete` | doctor deletion would destroy bookings (guarded in admin by deactivate-instead) |
| `medical_records.booking_id → bookings` | cascade | record destroyed with booking |
| `payment_events.booking_id → bookings` | `cascadeOnDelete` | payment audit trail destroyed with booking → **PB-C-01/C-03** |
| `payments.clinic_id / user_id` | `nullOnDelete` | payment survives as orphan with no owner |
| `payments.payable_type/payable_id` | **no FK (polymorphic)** | orphaned payments after booking/record delete; `applyToPayable` logs a warning and skips → **PB-C-03/H-07** |
| `payment_status_histories.payment_id` | cascade | history destroyed with payment |
| `payment_refunds.payment_id` | cascade | refund row destroyed with payment |
| `product_stock_movements.clinic_id` | FK | stock ledger is preserved |
| `doctors.user_id → users` | `nullOnDelete` | portal account removal keeps doctor profile |

### 16.2 Uniqueness coverage

| Constraint | Present | Note |
|---|---|---|
| `bookings_queue_unique (clinic_id, queue_date, queue_number)` | Yes | correct and load-bearing |
| `payments.provider_order_id` UNIQUE | Yes | provider-level idempotency |
| `payment_events.order_id` UNIQUE | Yes | webhook idempotency (global, not per booking) |
| `product_orders.order_number` UNIQUE | Yes | generated with existence loop |
| **booking slot `(clinic_id, doctor_id, booking_date, booking_time)`** | **No** | → **PB-H-04** |
| **payable `(payable_type, payable_id, status)` partial unique** | **No** | → **PB-H-03** |
| `clinic_merchant_configs.clinic_id` UNIQUE | Yes | one merchant config per clinic |
| `doctor_schedule_exceptions (doctor_id, clinic_id, date)` | Yes (per migration) | prevents duplicate exceptions |

### 16.3 Index review (what matters for the hot paths)

Covered: `bookings` (clinic/date, doctor/date, user, status), `medical_records` (clinic, booking, pet), `payments` (`clinic_id+status`, `payment_type+status`, `payable_type+payable_id`, `created_at`), `payment_events` (`booking_id+created_at`), `notifications` (`user_id+read_at`), `device_tokens` (token), `patients/search` indexes on users.

Missing / risky:
- **No index** on `bookings(doctor_id, clinic_id, booking_date, booking_time)` — the very predicate used by the duplicate check every booking creation. This is both a performance and a locking-behaviour issue (InnoDB gap locking needs a suitable index to protect the range; without it the `FOR UPDATE` may scan/lock far more rows than intended).
- `product_variants` stock queries filter on `is_active` + `product_id`; `lowStockVariants` uses `whereColumn('stock_on_hand','<=','low_stock_threshold')` which cannot use an index (dashboard-only, low volume).
- `bookings.remaining_amount > 0` dashboard aggregates are unindexed; acceptable at current volume, will degrade at scale.

### 16.4 Migration-level observations

- 83 migrations include data backfills (`backfill_clinic_data`, `backfill_medicine_batches`, `backfill_legacy_disposals_into_mutations`) that mutate production data with no idempotency guard visible in the audit scope; they are `up()`-only in the reviewed files.
- `database/database.sqlite` exists in the repo while the runtime connection is MySQL (`DB_CONNECTION=mysql`, database named `travelo`). Dead artefact; a developer who flips `DB_CONNECTION` will get a schema-less file and confusing failures.
- `2026_11_21_100001_drop_legacy_server_key_from_clinic_payment_accounts` correctly removes plaintext gateway keys from the tenant table and forces credential versions — good.

---

## 17. Concurrency, Transactions & Race Conditions

| # | Scenario | Locking in code | Verdict |
|---|---|---|---|
| 1 | Two patients book the same doctor slot | `SELECT ... FOR UPDATE` on a **non-unique, unindexed** predicate | **Potential** double booking → PB-H-04 |
| 2 | ServiceRequest accepted exactly when a competing booking is inserted | `Booking::...->lockForUpdate()->exists()` | **Potential** (EXISTS + FOR UPDATE does not guarantee gap protection the way a row select does) → PB-H-04 |
| 3 | Two `snap-token` requests for the same booking | none (plain SELECT reuse) | **Potential** double payable row → PB-H-03 |
| 4 | DP payment and full payment settle concurrently | `applyTransactionStatus` locks the booking, accumulates `paid_amount` | Overpayment possible, **no cap** → PB-H-03 |
| 5 | Webhook vs. scheduler on a Pet Shop order | both lock the order row, but the scheduler selects *before* the lock | `paid`+`expired` state → PB-H-02 |
| 6 | Two admins confirm/cancel/complete one booking | `lockForUpdate` around the transition | **Safe** |
| 7 | Two admins record refunds | `lockForUpdate` + integer arithmetic | **Safe** |
| 8 | Concurrent prescription edits | `syncForRecord` inside the record transaction | **Safe** |
| 9 | Stock reserve/commit/release racing | `ORDER BY id ... FOR UPDATE`, per-order state flags | **Safe** (deadlock-ordered) |
| 10 | Duplicate webhook delivery | `payment_events.order_id` UNIQUE + `insertOrIgnore` | **Safe** |
| 11 | Concurrent service-request offer edits | none | **Confirmed** lost update → PB-M-07 |
| 12 | Join request double approval | `lockForUpdate` + status re-check | **Safe** |
| 13 | Account deletion while a booking is created | deletion runs in one transaction; booking insert is independent | Rare orphan risk (`bookings.user_id` cascade would have removed it anyway) — not scored |

**Lock ordering risk note:** `ProductStockService` always locks by `product_variant_id` ascending, and `ProductOrderService` locks variants with `orderBy('id')`. Consistent ordering across the two services is what keeps deadlocks away; any future writer that locks variants in a different order (e.g. by cart order) reintroduces the deadlock class. Recorded as a design constraint, not a defect.

---

## 18. Security Findings (IDOR, Privilege, OTP, Throttling, Disclosure)

### 18.1 Summary of the security posture

| Control | Assessment |
|---|---|
| Authentication (Sanctum, 30-day expiry) | Sound |
| Authorization (imperative, no Policies) | Sound *today*, brittle structurally (PB-M-08) |
| Tenant isolation | Fail-closed on both web and API middleware |
| IDOR | No confirmed IDOR in any reviewed endpoint (13 explicit ownership checks verified, §6.2) |
| Mass assignment | None possible (all `fillable`, no `$guarded=[]`, no `$request->all()`) |
| OTP abuse | Well designed (hashing, purpose binding, attempt cap, per-account throttles) |
| Rate limiting | Comprehensive, but IP component is spoofable (PB-H-08) |
| Payment webhook | Signature-enforced, replay-safe, never trusts payload amounts |
| Secret handling | `server_key` not flashed, dropped from tenant table, `dontFlash` |
| Information disclosure | **Two confirmed channels** (PB-H-09, PB-H-10) |
| Transport/session config | `APP_DEBUG=true`, `APP_ENV=local` in the shipped `.env` — see PB-H-10 |

### 18.2 Detail on the disclosure channels

**PB-H-09** — `GET /api/health` is public (`throttle:public-catalog`, 120/min) and returns, on failure, `$e->getMessage()` from `DB::connection()->getPdo()` (typically `SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: YES)` — which leaks the DB username, host and that a password was attempted), the absolute path of the Firebase service account (`resolvePath`), the absolute public storage path and its writability, and the Midtrans environment/mode plus which keys are "configured"/"missing". This is a reconnaissance gift for any unauthenticated actor.

**PB-H-10** — `Api\PaymentController` catches `\Throwable`/`\Exception` itself and returns `'message' => 'Internal server error: ' . $e->getMessage()`. Because the exception never reaches the renderer in `bootstrap/app.php`, the sanitising `Throwable` handler that the authors *did* install (with an explicit comment about `APP_DEBUG`) is bypassed. With `APP_DEBUG=true` in `.env`, any SQL error inside the payment path prints SQLSTATE text, table names and file/line information to the Android client.

### 18.3 Privilege boundary checks (verified)

- No route exposes a super-admin action to a clinic admin (`EnsureSuperAdmin` on every cross-clinic route).
- `Admin\UserController::canManageAccount` prevents vertical escalation via the staff edit form.
- A doctor cannot reach admin routes (`AdminAuth` redirect), and a clinic admin cannot reach the doctor portal without a doctor profile (`DoctorAuth`).
- `DELETE /api/auth/account` cannot be used to delete another account: it is strictly `$request->user()`-scoped, and the OTP verification key is bound to `user->id`.
- `POST /api/device-token` returns 409 rather than stealing a token owned by another user.

### 18.4 OWASP-style residue (not individually scored)

| Risk | Note |
|---|---|
| A01 Broken access control | Mitigated, but no central policy layer (PB-M-08) |
| A02 Cryptographic failures | OTP hashed with SHA-256; no password pepper needed; Sanctum tokens are random |
| A03 Injection | All queries use Eloquent/parameter binding; `csv` export writes raw values into `fputcsv` (CSV-injection possible if a pet name starts with `=`, low impact, not scored) |
| A04 Insecure design | Record-retention and money-ledger designs (§§9-10) |
| A05 Misconfiguration | `APP_DEBUG=true`, `trustProxies('*')`, single-channel debug logging |
| A07 Auth failures | Throttles good; no MFA for `super_admin` |
| A09 Logging/monitoring failures | No alerting on payment anomalies; webhook drops only log (PB-M-03) |
| A10 SSRF | No user-controlled server-side URL fetching exists in the reviewed backend code (the payment gateway and FCM endpoints are fixed config URLs) |

*(Checked and dismissed: `PetController`'s `elseif ($request->filled('photo'))` branch looks like it accepts a free-form string, but the `nullable|image|mimes:...` rule rejects any non-file value, so the branch is unreachable dead code rather than an input vector. Tracked as part of PB-L-04's cleanup, not as a security finding.)*

---

## 19. Error Handling, Observability & Configuration

### 19.1 Error handling

| Strategy | Where | Assessment |
|---|---|---|
| Canonical JSON envelopes for 401/404/422/500 | `bootstrap/app.php` | Correct and Android-friendly |
| Self-handled controller catches | `PaymentController`, `PetShopOrderController`, some API endpoints | **Bypass the sanitizer** (PB-H-10); also LOG + return duplicates messages |
| Domain exceptions | `InsufficientStockException`, `PaymentRoutingException`, `PaymentGatewayException`, `RuntimeException` + `ValidationException` | Good separation; `RuntimeException` is also used as a control-flow signal for user-facing messages (works, but conflates "programmer error" with "business rule") |
| Silent catch | `FCMService` (all branches), pickup-pass issue/revoke, audit-log write in `AuthController::deleteAccount` | Intentional non-fatal degradation, logged as warnings — acceptable, but means a broken FCM credential is invisible to operators |
| Swallowed stock/queue errors | `DoctorMedicalRecordController` wraps queue transition in try/catch and only logs | Deliberate (record must not fail), but a stuck queue is never surfaced |

### 19.2 Observability

- `audit_logs` captures admin/doctor/portal mutations but **no API/patient-side mutation** except account deletion. A patient cancelling a booking, paying, or deleting a pet leaves no audit trace.
- No structured logging (no JSON channel, no correlation/request id).
- `Log::info` in the payment path writes order ids, amounts, user ids, and routing source on every checkout.
- Logging config: `default = stack → single`, level not set in `.env` → `debug`. The single channel grows without rotation → **PB-M-06**.
- No health/metrics beyond `/api/health` (which is the leaky one).

### 19.3 Configuration review

| Item | Value observed | Assessment |
|---|---|---|
| `APP_ENV` | `local` | Not production-shaped |
| `APP_DEBUG` | `true` | Drives PB-H-10 severity |
| `APP_TIMEZONE` | present (Asia/Jakarta), used by `config/app.php` default `UTC` | Correct via env; **UTC default is a foot-gun** for anyone deploying without the env line |
| `QUEUE_CONNECTION` | `database` | Correct table exists; no worker scheduled (PB-L-06) |
| `MAIL_MAILER` | `smtp` + Brevo API transport (`app/Support/Mail/BrevoApiTransport.php`) | Two mail paths exist; `VerificationMailer` selects one — verify in deployment which is active |
| `MIDTRANS_IS_PRODUCTION` | `false` | Sandbox; `HealthController` reports it publicly |
| `CACHE_STORE` / `SESSION_DRIVER` / `FILESYSTEM_DISK` | not present in `.env` | Framework defaults (`database`/`database`/`local`) apply; `local` filesystem for pet photos would break the `storage/` URL assumption — must be confirmed against `config/filesystems.php` at deploy |
| `config/payment.php` | absent | Payment settings live in `config/services.php` + DB credential versions → PB-I-02 |
| `config/cors.php` | absent | Framework default `allowed_origins: ['*']` → PB-I-03 |

---

## 20. Performance & Query Analysis

| # | Location | Observation | Impact |
|---|---|---|---|
| 1 | `Api\BookingController::index` | `attachQueueMeta()` called per row → `QueueService::positionOf()` runs 1 count-query + 1 select **per booking**, so a 20-row page issues ~40 extra queries | **PB-M-05** (N+1) |
| 2 | `Api\PetController::index` | eager-loads 5 latest medical records per pet; masks in PHP | Acceptable |
| 3 | `Admin\BookingController::schedule` | date-range query with eager loads | Not measured; no evidence of pathology |
| 4 | `Admin\PaymentController::paymentStats` | aggregates over `bookings` + separate `booking_refunds` sum, cached (`payment_stats_c*`) | Cache keys must include all filters; currently scoped by clinic suffix only — a filtered request may reuse an unfiltered cached stat (see note below) |
| 5 | `Api\DashboardController` | `Cache::remember("dashboard_user_{id}", 60)` | Correctly invalidated on account deletion; **not** invalidated when a payment/booking changes → up to 60 s of stale outstanding balance (accepted trade-off, noted) |
| 6 | `NotificationController::markAllRead` | single UPDATE | Fine |
| 7 | `PaymentReconciliationService::syncPending` | 50 rows / 15 min, sequential HTTP | Provider-quota safe |
| 8 | `FCMService::sendToMultiple` | sequential per device | Fine at 1-3 devices/user |
| 9 | Doctor catalogue (`Api\DoctorController`) | active-clinic guard then query | Fine |

**Cache-key correctness note (measured by reading, not runtime):** `Admin\PaymentController::paymentStats` caches under `payment_stats_c{clinicSuffix}` while the *same* key is used for filtered and unfiltered requests (filters live in the query string, not the key). If the cache is written by an unfiltered request and then read by a filtered one, the stat cards can show unfiltered totals. This is a **cache-key collision (Recommendation-level, folded into PB-M-06 area)**; it is listed in the remediation roadmap but not scored as a separate finding because the read path could not be confirmed to cache filtered requests without running the app.

---

## 21. Dead Code, Duplicate Logic & Contract Drift

| Item | Evidence | Classification |
|---|---|---|
| `Api\AuthController::registerDirect` | Returns `410 {code: use_otp_flow}`; still routed and documented in `docs/API_CONTRACT.md` | Intentional deprecation → PB-I-04 |
| `PUT /api/pets/{petId}/vaccinations/{vaccinationId}` | Registered in `routes/api.php`; no `@PUT` in `ApiService.kt`; no UI path calls it | Dead endpoint (Android side) → PB-L-02 |
| `GET /pet-shop/orders/{id}/receipt/pdf` | Implemented; Android explicitly stopped using it (on-device PDF) | Retired but alive server-side (documented in code comment) — accepted |
| `RouteLimiter 'api-heavy'` | Defined in `AppServiceProvider`, referenced by **no** route | Dead configuration |
| `HealthController` required-routes list | Includes `register_direct` (410 endpoint) as a *health requirement* | Drift → PB-L-03 |
| `normalizeMoneyInput()` | Identical private copy in `Admin\MedicalRecordController:48` and `Doctor\MedicalRecordController:399` | Duplicate logic → PB-L-04 |
| Record serialisation / clinical masking | Implemented once in `Api\MedicalRecordController::serializeRecord`, then *re-implemented as a model method* `maskClinicalFields` and re-invoked manually from 4 other controllers | Duplicated invariant → PB-M-08 |
| Refund logic | `RefundService` (cash) vs `PaymentService` refund path (provider) | Duplicate domain (PB-M-02) |
| Amount computation | `PaymentService::bookingAmountFor` vs `Api\PaymentController::createSnapToken` expected-amount check (same rules, two implementations) | Duplicate invariant; drift risk |
| `Pet.age` and `Pet.date_of_birth` | Both stored and independently editable; `age` never derived from `date_of_birth` | Dual truth → PB-L-05 |
| `payments.refunded_amount` vs `booking_refunds` | Both exist; used by different consumers | Dual ledger (PB-M-02) |
| `Order service: ProductOrderService::transition` vs `PaymentService::applyToPayable` | Two writers for one state machine | Drift → PB-M-09 |
| `docs/AUDIT_REPORT.md`, `AUDIT_FIXES_REPORT.md`, `DB_SCHEMA.md`, `TENANCY_RULES.md` | Present in `PetHeal_Backend/docs/`; several claims (e.g. "PHASE 3 / PHASE 7" comments) are embedded in code as historical narration | Documentation debt, not a defect |

**Dead-model candidates:** no model in `app/Models/` was found to be wholly unreferenced (all 44 appear in at least one controller/relationship), so the "dead code" surface here is endpoints and helpers, not entities.

**Historical-narration hazard:** ~40 inline comments reference internal "PHASE 1/2/3/4/5/7" audit rounds. They document *why* a guard exists but are not enforced by tests; a refactor that "cleans up" a guard will silently resurrect the original bug (this is the main structural risk of the current documentation style).

---

## 22. Findings Register

Format: 13 fields per finding. `Status` uses **Confirmed** (code path unambiguously produces the effect), **Potential** (code permits it; an external condition decides), **Recommendation** (design/ops improvement, no bug proven).
Severity: **CRITICAL** = data loss or money loss/corruption reachable in normal use · **HIGH** = money/records/security broken in realistic conditions · **MEDIUM** = wrong state, drift, or hardening gap · **LOW** = hygiene · **INFO** = documentation/design note.

---

### PB-C-01 — Pet deletion destroys bookings, medical records and the payment audit trail

| Field | Value |
|---|---|
| **ID** | PB-C-01 |
| **Severity** | CRITICAL |
| **Category** | Data integrity / record retention / authorization-of-destruction |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Api/PetController.php::destroy` (also reachable via `DELETE /api/pets/{id}`); cascade chain defined in `database/migrations/2024_01_15_000003_create_pets_table.php`, `2024_01_15_000005_create_bookings_table.php` (`pet_id` → `cascadeOnDelete`), `2024_01_15_000006_create_medical_records_table.php`, `2026_06_08_000004_create_payment_events_table.php`; contrast `app/Http/Controllers/Admin/UserController.php::destroy`, `Admin/DoctorController.php::destroy` |
| **Problem** | A patient can permanently delete a pet that has completed bookings, invoices, SOAP notes, prescriptions, vaccinations and payment events. The deletion is a hard `delete()` with no history check, no soft delete, no audit entry, and no confirmation that financial records are settled. |
| **Root Cause** | `Pet` has no `SoftDeletes` (only `ClinicProduct` does), and the destructive FK graph (`pets → bookings → medical_records → payment_events`) is `cascadeOnDelete`. The owner-facing controller was written as a simple CRUD delete; the retention policy that the admin portal enforces (`cannot_delete_user_with_history`) was never applied to the patient-facing pet endpoint. |
| **Evidence** | `PetController::destroy`: `$pet = Pet::where('user_id', ...)->find($id); ... $pet->delete();` — no `bookings()->exists()` check, no `AuditLog::log`, no transaction. `grep -rln "SoftDeletes" app/Models/` → only `ClinicProduct.php`. `2024_01_15_000005`: `$table->foreignId('pet_id')->constrained()->onDelete('cascade')`. Admin equivalent: `if ($user->bookings()->exists()) return ... 'cannot_delete_user_with_history'`. |
| **Execution Flow** | `DELETE /api/pets/{id}` → `throttle:api` → `ApiAuthenticate` (owner only) → `PetController::destroy` → owner check → photo file unlink → `$pet->delete()` → MySQL cascades `bookings` → cascades `medical_records` → cascades `payment_events` → `payments` rows survive as orphans (no FK) → 200 `{success:true}`. Clinic dashboards lose the visit; the invoice/receipt can no longer be regenerated; the patient's payment history disappears from the clinic's books while the money remains recorded in `payments`. |
| **Impact** | Irreversible loss of clinical and financial history for both the patient and the clinic; breaks reconciliation, receipts, medical history continuity, and any legal/record-retention obligation. Cross-tenant damage: a patient's tap deletes rows the *clinic* is accountable for. Because `payments` survives, the clinic's payment ledger and booking ledger permanently disagree. |
| **Recommended Fix** | Introduce `SoftDeletes` on `Pet` (and `Booking`/`MedicalRecord`), change the patient endpoint to a soft delete that only hides the pet from the owner's list, block hard deletion when `bookings()->exists()`, replace `cascadeOnDelete` on clinical FKs with `restrictOnDelete` (or `nullOnDelete` for non-essential links), and write an `AuditLog` entry for every patient-initiated destructive action. |
| **Dependencies** | Requires a migration adding `deleted_at` columns and rewriting every query that assumes hard absence (`PatientScope`, dashboards, exports, queue/pickup joins); `payments.payable` orphan policy must be decided at the same time (see PB-C-03); the admin `MedicalRecordController` retention rule (PB-H-07) shares the same policy. |
| **Testing Scenario** | 1) Create a pet, book a slot, get it confirmed, have the doctor write a record, pay the invoice. 2) From the app, `DELETE /api/pets/{id}`. 3) Assert: booking still visible in `admin/bookings`, record still visible in `admin/medical-records`, `payments` row still resolves to its payable, `payment_events` still present, audit log contains a `pet.delete` entry. 4) Assert a pending-booking pet can be removed from the owner's list without deleting history. |

---

### PB-C-02 — Medical-record extra-payment amount is computed once and never recomputed, causing double charging

| Field | Value |
|---|---|
| **ID** | PB-C-02 |
| **Severity** | CRITICAL |
| **Category** | Financial correctness / state derivation / multiple sources of truth |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Models/MedicalRecord.php::recalculatePaymentState`; called only from `Admin/MedicalRecordController.php:265,439` and `Doctor/DoctorMedicalRecordController.php:209,375`; payment writers that never call it: `app/Services/PaymentStatusService.php::applyTransactionStatus`, `Admin/PaymentController.php::confirm`, `Api/MedicalRecordController.php::createExtraPayment`; UI/app surfaces: `Api/DashboardController` (`extra_payment_amount - extra_payment_paid_amount`), `Api/MedicalRecordController` payment status |
| **Problem** | `extra_payment_amount = max(0, total_medical_cost − booking.paid_amount)` is a snapshot taken at record save time. When the booking is paid *after* the record exists (the normal DP flow: DP at booking → consult → pay remaining later), the snapshot is never refreshed, so the patient is asked for money that has already been paid. |
| **Root Cause** | The record's payable state is a *derived* value stored denormalised, but only the record-write paths recompute it. The payment domain (`bookings.paid_amount`) and the record domain (`extra_payment_*`) have no coupling, no event, and no scheduled reconciliation. |
| **Evidence** | `recalculatePaymentState(float $bookingPaidAmount = 0)` computes `$extraAmount = max(0, $totalMedicalCost - $bookingPaidAmount)` and is invoked from exactly four call sites, all in create/edit record controllers (`grep -rn "recalculatePaymentState" app/`). `PaymentStatusService::applyTransactionStatus` updates only `paid_amount`, `remaining_amount`, `payment_date`, `payment_status`. No listener/observer/queue job exists that touches `extra_payment_*`. |
| **Execution Flow** | Booking with `payment_type=dp`, `total=500 000`, `dp=250 000`. Patient pays DP → `bookings.paid_amount = 250 000`. Doctor writes a record with `total_medical_cost = 600 000` → `extra_payment_amount = 600 000 − 250 000 = 350 000`, `extra_payment_status = unpaid`. Patient pays the remaining 250 000 of the booking → `bookings.paid_amount = 500 000`, `payment_status = paid`, `remaining_amount = 0`. Record still demands 350 000. `medical_records.extra_payment_amount` total charged to the patient = 500 000 + 350 000 = 850 000 for a 600 000 bill. |
| **Impact** | Systematic overcharge of every DP patient whose record is written before the final settlement; incorrect outstanding balances in the app dashboard; the "paid" clinical gate keeps diagnosis hidden even though the patient has paid in full; collection disputes and refund handling (which has no automated trigger, PB-M-02). |
| **Recommended Fix** | Make the record payable state a *derived* value computed on read (accessor over `total_medical_cost − booking.paid_amount − extra_payment_paid_amount`), or recompute it inside the payment-apply transaction for every record linked to the settled booking, and add a reconciliation command (like `payments:reconcile`) that recomputes all `extra_payment_*` for open records. Whatever the choice, there must be exactly one implementation. |
| **Dependencies** | Must be coordinated with PB-M-01/PB-M-02 (ledger reconciliation) and with the clinical masking gate (`can_view_full_record`) which reads `extra_payment_status`; a recompute that flips status must also re-invalidate `dashboard_user_*` caches and refresh the patient's `medical-records/{id}/payment-status` payload. |
| **Testing Scenario** | 1) Book with DP, pay DP. 2) Doctor writes a record costing more than the DP. 3) Pay the remaining booking amount. 4) Assert `extra_payment_amount == total_medical_cost − booking.paid_amount` immediately after settlement, `extra_payment_status ∈ {paid, not_required}` if fully covered, the diagnosis/SOAP are unlocked, and the sum of all amounts demanded from the patient never exceeds `total_medical_cost`. |

---

### PB-C-03 — Self-service account deletion destroys financial/clinical history and orphans `payments`

| Field | Value |
|---|---|
| **ID** | PB-C-03 |
| **Severity** | CRITICAL |
| **Category** | Data integrity / financial audit trail / policy inconsistency |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Api/AuthController.php::deleteAccount` (lines ~1033-1195), `requestDeleteOtp`, `verifyDeleteOtp`; tables: `bookings`, `medical_records`, `payment_events`, `payments`, `payment_refunds`, `payment_status_histories`, `booking_refunds`; contrast `Admin/UserController.php::destroy` |
| **Problem** | A patient can delete their account and, in one transaction, hard-delete all bookings, medical records and payment events, while `payments`/`payment_refunds`/`payment_status_histories` remain (polymorphic `payable`, `nullOnDelete` on user). The clinic loses its invoices and clinical history; the payment domain keeps money rows whose payable no longer exists. |
| **Root Cause** | Account deletion was implemented as "delete everything that belongs to the user" for GDPR-style expectations, without distinguishing *personal data* from *clinic-accountable records*. The admin portal implements the opposite policy (`cannot_delete_user_with_history`), so two contradictory retention rules coexist. |
| **Evidence** | Inside `DB::transaction`: `payment_events ... delete(); DoctorReview ... delete(); MedicalRecord::whereIn('id',$recordIds)->delete(); Booking::where('user_id',$userId)->delete(); Vaccination/WeightRecord/Pet ... delete(); Notification/DeviceToken/token ... delete(); $user->delete();` — no reference to `payments`, `payment_refunds`, `payment_status_histories` or `booking_refunds`. Admin path refuses when `$user->bookings()->exists()`. |
| **Execution Flow** | `POST auth/account/delete/request-otp` → email code → `POST .../verify-otp` (sets `account-delete-verified:{id}` in cache) → `DELETE auth/account` → `ApiAuthenticate` self-action bypass → transaction above → `Cache::forget` + disk photo cleanup → audit row with `user_id = null` → 200. Clinic now has `payments` rows whose `payable_id` points to a deleted booking; `PaymentService::applyToPayable` will log "Payment has no matching booking" and silently skip any later provider event for that money. |
| **Impact** | Destruction of legally/fiscally relevant clinic records by a patient action; unreconcilable provider settlements; refunds impossible to trace (the payable is gone); the patient's receipts and visit history vanish for the clinic; contradicts the retention rule enforced everywhere else. |
| **Recommended Fix** | Split "delete my account" into (a) anonymise personal data (name/email/phone/photo/tokens/notifications) and (b) retain financial/clinical records under the clinic's tenant with a pseudonymous subject id; or refuse self-deletion when unsettled/past records exist and route the request to clinic support. Never delete `payment_events`, `payments` or `booking_refunds`; add `softDeletes` + anonymisation instead. |
| **Dependencies** | Same migration/policy work as PB-C-01; the `payments.payable` orphan policy; `AuditLog` (`user_id` is already null-safe); the Android `deleteAccount` flow and its 428 verification contract must keep working after the change. |
| **Testing Scenario** | 1) User with a paid booking + record + refund requests deletion. 2) Assert the request either anonymises but retains bookings/payments/records, or is refused with an explicit reason. 3) In both cases assert `payments`, `payment_events`, `booking_refunds` and `payment_status_histories` still exist and still resolve to a payable. 4) Assert a user with no history can still self-delete without an OTP (current behaviour, keep). |

---

### PB-H-01 — Cancelling a paid booking leaves the money recorded as paid with no refund and no user-visible state change

| Field | Value |
|---|---|
| **ID** | PB-H-01 |
| **Severity** | HIGH |
| **Category** | Financial state machine / cross-module consistency |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Admin/BookingController.php::cancel`; `app/Http/Controllers/Api/BookingController.php::cancel`; `app/Services/RefundService.php::record`; `app/Http/Controllers/Admin/PaymentController.php::refund`; contrast `app/Services/ProductOrderService.php::transition` (sets `PAYMENT_REFUND_PENDING`) |
| **Problem** | Cancelling a booking that has `paid_amount > 0` (DP paid, or fully paid) changes only `status` and `cancellation_reason`. `payment_status` stays `paid`/`dp_paid`, no refund row is created, the `payments` row stays `paid`, and the patient receives no notification that money will be returned. |
| **Root Cause** | Booking cancellation and refund are two independent admin actions with no enforced ordering and no domain coupling; unlike the Pet Shop state machine, the booking state machine has no "cancelled-but-refund-pending" state and the cancel handler was never taught about money. |
| **Evidence** | `Admin\BookingController::cancel`: `$booking->update(['status' => 'cancelled', 'cancellation_reason' => ...]); $this->queue->releaseOnCancel(...)` — no payment/refund handling. `Admin\PaymentController::refund` → `RefundService::record` writing only `booking_refunds` + audit. `ProductOrderService::transition` for `STATUS_CANCELLED` explicitly sets `payment_status = PAYMENT_REFUND_PENDING`. |
| **Execution Flow** | Patient pays DP 250 000. Admin cancels → booking `cancelled`, `payment_status = dp_paid`, `paid_amount = 250 000`. Dashboard revenue still counts 250 000 (subtracting only `booking_refunds`, which is empty). Patient's app shows the booking cancelled but the payment still "paid". Refund only happens if an admin separately opens the refund form; if they forget, the money is silently retained. |
| **Impact** | Retained customer money without a compensating record; revenue/refund reporting wrong until someone manually reconciles; no audit linkage between cancellation and reimbursement; disputes and potential consumer-protection exposure. |
| **Recommended Fix** | On cancel with `paid_amount > 0`: set an explicit pending-refund state on the booking (mirroring `ProducerOrder::PAYMENT_REFUND_PENDING`), require a cancellation reason that forces the refund decision, notify the patient, and surface the booking in an admin "refunds due" queue. Add a DB-level invariant test: no `cancelled` booking with `paid_amount − refunded > 0` may exist without an open refund obligation. |
| **Dependencies** | `booking_refunds`, `RefundService`, `payments` refund path (PB-M-02), FCM notification templates, admin booking/payment views, dashboard "total_collected" formula in `Admin/PaymentController::paymentStats`. |
| **Testing Scenario** | 1) Pay DP, cancel as admin. 2) Assert booking carries a pending-refund marker and appears in the refunds-due list. 3) Record the refund → assert booking payment state becomes `refunded`/`partially_refunded`, dashboard totals subtract it exactly once, and the patient receives a notification. 4) Cancel a paid **Pet Shop** order and confirm both domains end in the same terminal state naming. |

---

### PB-H-02 — A paid Pet Shop order can be expired by the scheduler, releasing stock without refunding

| Field | Value |
|---|---|
| **ID** | PB-H-02 |
| **Severity** | HIGH |
| **Category** | Concurrency / financial state machine / inventory |
| **Status** | Potential (code path confirmed; requires a webhook inside the scheduler window) |
| **Affected Module/Files/Methods** | `app/Services/ProductOrderService.php::expireStaleOrders` + `transition`; `app/Services/PaymentService.php::applyToPayable` (ProductOrder branch); `app/Console/Commands/ExpirePetShopOrders.php`; `routes/console.php` (`everyFiveMinutes`); `app/Services/PickupPassService.php` |
| **Problem** | `expireStaleOrders` selects `open()` orders whose `reservation_expires_at < now()` **and** `payment_status != paid`, then calls `transition(... EXPIRED)`. If the Midtrans settlement webhook arrives between selection and the locked transition, the webhook sets `payment_status = paid` while `applyToPayable` only promotes `status` when it is `pending_payment`. The order ends `status = expired`, `payment_status = paid`, with stock already released — and nothing initiates a refund. |
| **Root Cause** | Expiry is decided on stale data outside the lock (filter at query time, re-check missing inside the transaction), and `applyToPayable` treats "paid" and "expired" as orthogonal facts instead of mutually exclusive terminal states. There is no compensating transition for "money arrived for an already-closed reservation". |
| **Evidence** | `expireStaleOrders`: `ProductOrder::query()->open()->whereNotNull('reservation_expires_at')->where('reservation_expires_at','<',now())->where('payment_status','!=',PAYMENT_PAID)->chunkById(50, ... transition($order, STATUS_EXPIRED ...))` — no `payment_status` re-read under lock. `applyToPayable`: `if ($internalStatus === STATUS_PAID) { $order->forceFill(['payment_status'=>PAYMENT_PAID])->save(); if ($order->status === STATUS_PENDING_PAYMENT) { ... awaiting_confirmation } return; }`. `transition` to `EXPIRED` calls `$this->stock->release(...)`. |
| **Execution Flow** | Order created 12:00, `reservation_expires_at = 12:30`, stock reserved. 12:30:00 scheduler selects it (`payment_status = unpaid`). 12:30:01 patient settles (Snap session still valid) → `applyToPayable` sets `payment_status = paid`, order still `pending_payment`. 12:30:02 scheduler's locked transition runs: state is still `pending_payment` → `canTransitionTo(expired)` true → stock released → order `expired`. Money captured, stock released, order dead. Orders later pay-after-expiry: `applyToPayable` sets `paid` on an `expired` order, same terminal inconsistency. |
| **Impact** | Customer is charged for goods that will never be fulfilled or reserved; inventory was released to other buyers; the clinic has no queue entry, no notification and no refund obligation record. Direct financial loss and a support incident per occurrence. |
| **Recommended Fix** | Inside the expiry transaction, re-read `payment_status` under `lockForUpdate()` and abort the expiry when it is `paid` (or `refund_pending`); in `applyToPayable`, when a settlement arrives for an `expired`/`cancelled` order, transition to a new `paid_after_expiry`/`refund_pending` state and raise a refund obligation + admin alert rather than leaving a paid-dead order. Add an invariant/monitoring query for `status in (expired,cancelled) and payment_status = paid`. |
| **Dependencies** | `ProductOrder` status/payment status constants, `ProductOrderService::transition` table, `PickupPassService`, `Admin/PetShopOrderController` refund surface, `PaymentService` refund path, admin alerting. |
| **Testing Scenario** | 1) Create an online order with a short `reservation_expires_at` (test-only override). 2) Fire a settlement webhook in the window between scheduler selection and transition (or simulate the two transactions in sequence with a paused lock). 3) Assert the final state is never `expired + paid`; assert stock is either still reserved (paid wins) or restored *and* a refund obligation exists (expiry wins) with an admin-visible alert. 4) Assert a late settlement for an `expired` order produces a refund obligation, not a silent `paid` flag. |

---

### PB-H-03 — Concurrent payment creation and DP+full settlement can push `paid_amount` above `total_amount`

| Field | Value |
|---|---|
| **ID** | PB-H-03 |
| **Severity** | HIGH |
| **Category** | Concurrency / overpayment / missing constraint |
| **Status** | Potential (requires concurrent or rapid sequential requests) |
| **Affected Module/Files/Methods** | `app/Services/PaymentService.php::createBookingPayment`, `findReusableBookingPayment`, `bookingAmountFor`; `app/Services/PaymentStatusService.php::applyTransactionStatus`; `app/Http/Controllers/Api/PaymentController.php::createSnapToken`, `createRemainingPaymentSnapToken`; table `payments` (no partial unique index) |
| **Problem** | Payment creation performs a non-locking "reuse" lookup; two parallel `snap-token` calls therefore both create a `pending` `payments` row for the same debt. Independently, a `dp` payment and a `full` payment can both be created while `paid_amount = 0`, and both can settle; `applyTransactionStatus` accumulates `paid_amount = paid_amount + gross` with no clamp to `total_amount`. |
| **Root Cause** | Idempotency is implemented as best-effort read-then-write without a uniqueness guarantee, and the settlement reducer trusts the sum of events instead of reconciling against the debt. `total_amount`/`paid_amount` have no invariant (`paid_amount <= total_amount`) enforced anywhere. |
| **Evidence** | `findReusableBookingPayment`: `Payment::where('payable_type', Booking::class)->where('payable_id',...)->where('gross_amount',$amount)->where('status','pending')->whereNotNull('redirect_url')->where('expires_at','>',now())->latest('id')->first()` — plain SELECT. `createBookingPayment` guards only `payment_status === 'paid'` and `in_array(status,[cancelled,completed])`. `applyTransactionStatus`: `$currentPaidAmount = (float) $booking->paid_amount + $gross;` then sets `paid_amount` with no `min(total, ...)`; `remaining_amount` clamps to 0 only. Migration `2026_11_13_100003_create_payment_tables.php` has no unique index on `(payable_type, payable_id, status)`. |
| **Execution Flow** | T0: DP booking, `paid_amount = 0`. Two devices call `POST payment/snap-token` with the same order id: both miss the reuse check → two `payments` rows (both `pending`, same gross). User pays via both Snap pages → two distinct order ids → two `payment_events` rows → `paid_amount = 2 × dp`. Alternatively, `snap-token` (full) succeeds while the DP payment is still `pending`; both settle → `paid_amount = dp + total > total`. No refund, no flag. |
| **Impact** | Customer is charged twice; `remaining_amount` becomes 0 while `paid_amount > total_amount`, so "overpaid" is invisible in every report; refund must be manual; receipts (`ReceiptService`, admin invoice) show inconsistent paid amounts. |
| **Recommended Fix** | Add a partial/functional unique guarantee for one open payable per debt (e.g. `UNIQUE(payable_type, payable_id, gross_amount, status)` limited to `status IN ('unpaid','pending')` via a generated column, or an advisory lock keyed on the booking id), clamp and detect overpayment (`min(total, paid + gross)` + an `overpaid_amount` column or a refund obligation), and refuse a new payment intent while another intent for the same debt is open. |
| **Dependencies** | Payment migration, `Payment` model constants/`STATUS_RANK`, reconciler (`PaymentReconciliationService`), refund surfaces (PB-H-01/PB-M-02), Android retry behaviour in `PaymentViewModel` (it already polls; it must not create a second intent after a 5xx). |
| **Testing Scenario** | 1) Fire two identical `snap-token` requests simultaneously (parallel clients). 2) Assert exactly one open `payments` row exists and both responses return the same `order_id`/token. 3) Settle both provider order ids if a second one was somehow created → assert the booking caps at `total_amount`, overpayment is recorded as a refund obligation, and the dashboard shows the overpayment. 4) Repeat with `dp` then `full` intent. |

---

### PB-H-04 — No database uniqueness for a booking slot, enforced by three divergent application checks

| Field | Value |
|---|---|
| **ID** | PB-H-04 |
| **Severity** | HIGH |
| **Category** | Data integrity / concurrency / missing constraint |
| **Status** | Potential |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Api/BookingController.php::store`; `app/Services/ServiceRequestService.php::accept`; `app/Http/Controllers/Admin/BookingController.php` (manual creation); migrations `2024_01_15_000005_create_bookings_table.php`, `2026_10_02_000005_add_clinic_id_to_bookings_table.php`, `2026_04_06_000004_add_performance_indexes.php`, `2026_06_04_000003_cleanup_duplicate_indexes.php` |
| **Problem** | Nothing in the schema prevents two `pending`/`confirmed` bookings for the same doctor, clinic, date and time. Duplicate prevention exists only as three *different* application checks: a `first()` under `lockForUpdate` (API), an `exists()` under `lockForUpdate` (service request), and an admin path with its own rules. Concurrency, a different isolation level, or a future fourth write path produces a real double booking. |
| **Root Cause** | Slot occupancy is a business invariant with no declarative counterpart. Additionally, the predicate `(doctor_id, clinic_id, booking_date, booking_time)` has **no index**, which makes InnoDB's gap-locking behaviour for the `FOR UPDATE` select unreliable/over-broad. |
| **Evidence** | `grep -rn "unique" database/migrations/*booking*` → only `bookings_queue_unique (clinic_id, queue_date, queue_number)`. `Api/BookingController::store`: `Booking::where(...)->whereIn('status',['pending','confirmed'])->lockForUpdate()->first()`. `ServiceRequestService::accept`: `Booking::where(...)->whereIn('status',['pending','confirmed'])->lockForUpdate()->exists()`. Admin creation does not run either check in the reviewed code. |
| **Execution Flow** | Two patients (or a patient and a ServiceRequest acceptance) submit the same doctor/date/time concurrently. Under READ COMMITTED, or when the `FOR UPDATE` scan does not cover the gap, both transactions see no conflicting row and both insert. Result: two bookings, two queue numbers for the same hour, doctor double-booked, both patients notified. |
| **Impact** | Operational double booking, queue collapse, patient-facing scheduling errors, refund/rebooking churn; no way to detect the condition after the fact other than by querying for duplicates. |
| **Recommended Fix** | Add a real index plus a declarative guard: `UNIQUE(clinic_id, doctor_id, booking_date, booking_time, active_flag)` where `active_flag` is a generated column (`1` when status in pending/confirmed, `NULL` otherwise — MySQL allows multiple NULLs) or a dedicated `booking_slot_locks` table; then keep the application checks only for friendly error messages. Route all three write paths through one `BookingCreationService`. |
| **Dependencies** | Migration with backfill/cleanup of any existing duplicates, `ServiceRequestService::accept` refactor, admin manual booking path, `DoctorScheduleService::validateSlot`, queue assignment. |
| **Testing Scenario** | 1) Run N parallel booking requests for one slot (concurrency harness). 2) Assert exactly one 201 and N−1 either `409 slot_conflict` or a DB-level integrity violation mapped to 409. 3) Accept a ServiceRequest whose slot was just booked → assert a clean rejection, not a duplicate. 4) Attempt an admin manual create on an occupied slot → same rejection. |

---

### PB-H-05 — ServiceRequest acceptance creates bookings without schedule validation or clinic-active check

| Field | Value |
|---|---|
| **ID** | PB-H-05 |
| **Severity** | HIGH |
| **Category** | Business rule bypass / cross-module validation gap |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Services/ServiceRequestService.php::accept`; `app/Services/DoctorScheduleService.php::validateSlot` (not called); `app/Http/Controllers/Admin/ServiceRequestController.php` (offer form) |
| **Problem** | `Api/BookingController::store` rejects times outside the doctor's effective schedule, but the offer→booking path (`accept`) never calls `validateSlot`. A clinic can offer (or an admin can accidentally offer) a slot on a closed day, during leave, outside practice hours, or in the past-after-today, and the acceptance will create a live `pending` booking. The clinic's `is_active` flag is also not re-checked at acceptance. |
| **Root Cause** | Booking creation logic is duplicated across paths instead of centralised; each new path only re-implements the checks its author remembered. The schedule service is the designated single source of truth but is only wired into one call site. |
| **Evidence** | `ServiceRequestService::accept` validates: `offer_valid_until`, presence of `offer_doctor_id/offer_schedule_date/offer_schedule_time`, doctor active + same clinic, slot not taken. It does **not** call `DoctorScheduleService::validateSlot`, does not check `Clinic::is_active`, and does not check `offer_schedule_date >= today`. `Api/BookingController::store` does call `validateSlot` and returns `code: slot_unavailable`. |
| **Execution Flow** | Doctor takes leave (exception row) on 2026-06-10. Admin sends an offer for 2026-06-10 10:00. Patient accepts → `Booking::create(status pending, date 2026-06-10, time 10:00)`. The doctor sees a patient on a leave day; the patient got a confirmation push. Same flow with a deactivated clinic produces a booking at a clinic that cannot serve it. |
| **Impact** | Invalid appointments, patient arrivals with no doctor, manual cleanup, and eroded trust in the scheduling system; the "single source of truth" claim for `DoctorScheduleService` becomes false and future maintainers cannot rely on it. |
| **Recommended Fix** | Extract a single `BookingCreationService` used by API store, ServiceRequest accept and admin manual creation, which performs (in order): tenant/active-clinic check, doctor/service clinic pairing, `DoctorScheduleService::validateSlot`, slot uniqueness, then insert. Never duplicate these checks per controller. |
| **Dependencies** | `DoctorScheduleService`, `ServiceRequestService`, `Api/BookingController`, admin manual booking, PB-H-04 (slot uniqueness should live in the same service), `ServiceRequest` status model. |
| **Testing Scenario** | 1) Create a doctor-schedule exception (leave) for tomorrow. 2) Create a ServiceRequest offer for that date/time via the admin. 3) Accept via the app → assert a clear 422 ("slot unavailable"), no booking row. 4) Deactivate the clinic → offer/accept → assert rejection. 5) Assert the API store path and the accept path now return the same error code and wording for the same situation. |

---

### PB-H-06 — Writing a medical record force-completes the booking regardless of payment or check-in state

| Field | Value |
|---|---|
| **ID** | PB-H-06 |
| **Severity** | HIGH |
| **Category** | State transition error / receivable loss / lifecycle bypass |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Doctor/DoctorMedicalRecordController.php::store` (booking completion ~line 218); `app/Http/Controllers/Admin/MedicalRecordController.php::store` (~line 277); guard that then blocks payment: `app/Services/PaymentService.php::createBookingPayment` (`in_array($booking->status, ['cancelled','completed'])` → throw); queue auto-close in both controllers |
| **Problem** | Creating a medical record sets `bookings.status = 'completed'` unconditionally (the only guard is `status !== 'cancelled'`). A `pending` (unconfirmed, unchecked-in, unpaid) booking is therefore completed by a single doctor form submit, and from that moment `createBookingPayment` refuses to create any payment for it — the debt becomes uncollectable through the platform. The same hook also closes a queue slot from `waiting`, i.e. a patient who never checked in is silently marked as served. |
| **Root Cause** | Record creation was treated as "the visit happened", which the authors considered equivalent to completion. The payment domain, however, uses `completed` as a terminal *financial* state too. One enum value carries two meanings (clinical completion vs. financial closure) and each module interprets it differently. |
| **Evidence** | Doctor path: `if ($booking->status === 'cancelled') { reject } if (MedicalRecord::where('booking_id',...)->exists()) { reject } ... $booking->update(['status'=>'completed','completed_at'=>now()])` inside the record transaction. Admin path: same `whereIn`-free check at line ~203 and `status => 'completed'` at ~279. `PaymentService::createBookingPayment` line ~114: `if (in_array($booking->status, ['cancelled','completed'], true)) throw RuntimeException(__('payments.booking_not_payable'));`. |
| **Execution Flow** | Patient books (status `pending`, `remaining_amount = 500 000`). Doctor opens the record form directly from the booking list (allowed: status not cancelled) and saves a record. Booking becomes `completed`. Patient later tries to pay → 422 "booking not payable". Admin must use the manual `payment.confirm` path (which writes only the booking ledger, PB-M-01) to record the money; the app can never settle it. |
| **Impact** | Uncollectable receivables and inconsistent books; unchecked-in patients appear served; the visit history/payment attention counters (`remaining_amount > 0` with `status != cancelled`) keep nagging the patient about a booking they can no longer pay. |
| **Recommended Fix** | Separate the two meanings: keep `bookings.status` for the appointment lifecycle and require `confirmed` + (checked-in or explicit admin override) before completion; forbid record creation on `pending` bookings or create it in a "draft/provisional" state; and let payment creation work for `completed` bookings with an outstanding balance (the real block should be "already paid", not "completed"). |
| **Dependencies** | `QueueService` auto-close hook, `Admin\BookingController::complete` settled-rule (which already blocks unpaid completion — the doctor path should reuse it), Android booking/payment screens, `payment_status` semantics. |
| **Testing Scenario** | 1) Create a `pending` booking with `remaining_amount > 0`. 2) Attempt doctor record creation → assert rejection (or draft-only record) and that the booking stays `pending`. 3) Complete the booking as admin with an unpaid balance and `confirm_unpaid` → then attempt payment in the app → assert payment can still be created for the outstanding amount. |

---

### PB-H-07 — Admin medical-record deletion has no payment guard, no audit trail and leaks storage

| Field | Value |
|---|---|
| **ID** | PB-H-07 |
| **Severity** | HIGH |
| **Category** | Financial data integrity / destructive action |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Admin/MedicalRecordController.php::destroy` (~line 528-546); `app/Services/MedicalRecordAttachmentService.php::delete` (not called); `app/Services/PrescriptionService.php::restoreOnDelete` (called, correct); `payments` (polymorphic payable), `medical_record_attachments`, `prescription_items` |
| **Problem** | Deleting a medical record is a bare `$record->delete()`. If the record had an extra-cost online payment (`payments.payable_type = MedicalRecord`), the payment row survives while its payable disappears — the money becomes untraceable to any clinical document. No `AuditLog` entry is written, no refund obligation is checked, and attachment files are not removed from disk even though their rows are cascaded away. |
| **Root Cause** | The delete endpoint predates the extra-payment feature and was never revisited when `payments` started pointing at records. Retention/audit policy is applied per-controller by hand, so newer controllers got guards and older ones did not. |
| **Evidence** | `destroy`: `$this->prescriptions->restoreOnDelete($record); $record->delete(); $this->forgetDashboardCaches($cacheClinicId);` — no `$record->payments` check, no `DB::transaction`, no `AuditLog::log`. `MedicalRecordAttachmentService::delete` (which unlinks the file) is only called from `update()`. `payments` has no FK to `medical_records` (`payable_type/payable_id`). |
| **Execution Flow** | Patient pays an extra record charge (online) → `payments` row `payable = MedicalRecord#77`, `medical_records.extra_payment_status = paid`. Admin clicks delete → record, attachments rows and prescription items disappear; files remain in `storage/app/public`; `payments` row survives; `applyToPayable` will later log "Payment has no matching medical record" and drop any provider event for it. No one can prove what the patient paid for. |
| **Impact** | Untraceable money, orphaned receipt, storage leak, no audit trail for a destructive clinical action; contradicts the retention rules applied to users/doctors. |
| **Recommended Fix** | Refuse deletion when a `payments` row exists for the record (or force a refund/void flow first), soft-delete the record, write an `AuditLog` entry with the actor and reason, wrap the delete in a transaction, and delete attachment files through `MedicalRecordAttachmentService::delete` before the row disappears. |
| **Dependencies** | `payments` payable policy (PB-C-03), `MedicalRecordAttachmentService`, `PrescriptionService::restoreOnDelete`, admin medical-record views, `AuditLog`. |
| **Testing Scenario** | 1) Create a record with an online extra payment and two attachments. 2) Attempt admin delete → assert refusal with an explicit reason. 3) Refund/void, then delete → assert the record is soft-deleted (or blocked if hard delete is retained), `storage` files are gone, an audit row exists, and no `payments` row points at a missing payable. |

---

### PB-H-08 — `trustProxies(at: '*')` makes every IP-keyed throttle and audit IP client-controlled

| Field | Value |
|---|---|
| **ID** | PB-H-08 |
| **Severity** | HIGH |
| **Category** | Security / rate-limit bypass / audit integrity |
| **Status** | Potential (depends on deployment topology: direct exposure vs. a proxy that overwrites headers) |
| **Affected Module/Files/Methods** | `bootstrap/app.php` (`$middleware->trustProxies(at: '*')`); `app/Providers/AppServiceProvider.php` (all limiters using `$request->ip()`: `auth`, `admin-login`, `password-reset`, `password-reset-verify`, `otp-send`, `otp-verify`, `webhook`, `public-catalog`, `api`, `uploads`, `payment`); `audit_logs.ip_address` writes (e.g. `AuthController::deleteAccount`) |
| **Problem** | Trusting `*` as proxy means any client-supplied `X-Forwarded-For` becomes `$request->ip()`. All IP-keyed throttles can therefore be rotated per request, and every audit row records an attacker-chosen IP. |
| **Root Cause** | The setting was introduced to fix HTTPS detection behind ngrok (documented in a comment) and was never narrowed once the app started relying on IP for security decisions. |
| **Evidence** | `bootstrap/app.php` comment + `trustProxies(at: '*')`; limiter definitions keyed on `email|ip`; `webhook` limiter keyed solely on `ip`; `AuditLog::create(['ip_address' => request()->ip(), ...])`. |
| **Execution Flow** | Attacker sends `POST /api/auth/login` with rotating `X-Forwarded-For: 10.0.0.N` and rotating emails → every request is a fresh `email|ip` bucket → the 8/min login limit never triggers, enabling credential stuffing against a known email list. Same technique against `webhook` (120/min per spoofed IP) and against `otp-send` day-limits. Audit rows show attacker-chosen IPs, defeating incident forensics. |
| **Impact** | Loss of brute-force/abuse protection precisely on the authentication, OTP and webhook surfaces; corrupted audit trail; the 30-day token lifetime makes any successful login long-lived. |
| **Recommended Fix** | Replace `'*'` with the actual proxy ranges (ngrok/load-balancer CIDRs) or drop the setting and let the deployment terminate TLS properly; additionally key the most sensitive limiters on a server-controlled identifier (`user id`, `email`) rather than the IP component alone, and log both `ip` and `x-forwarded-for` for forensics. |
| **Dependencies** | Deployment topology (ngrok vs. real proxy), `SetLocale`/asset-origin middleware that also rely on the forwarded scheme, load-balancer configuration, `.env` (`ASSET_URL`). |
| **Testing Scenario** | 1) Send 20 login attempts with distinct spoofed `X-Forwarded-For` values for one email → assert the request is throttled (i.e. the limiter no longer trusts the header). 2) Same for `otp-send` daily quota. 3) Assert `audit_logs.ip_address` equals the socket/edge IP, not the spoofed header. 4) Re-verify HTTPS scheme detection still works behind the real proxy. |

---

### PB-H-09 — Unauthenticated `/api/health` discloses paths, DB error text and payment configuration

| Field | Value |
|---|---|
| **ID** | PB-H-09 |
| **Severity** | HIGH |
| **Category** | Information disclosure / reconnaissance |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Api/HealthController.php::index` (+ `resolvePath`, `routeExists`); route `GET /api/health` (`throttle:public-catalog`) |
| **Problem** | The public health endpoint returns raw exception messages from `DB::connection()->getPdo()` (which commonly include `SQLSTATE`, the DB user name, host and whether a password was used), absolute filesystem paths for the Firebase service-account file and the public storage directory, the Midtrans environment/mode, and which secrets are configured vs. missing. No authentication is required. |
| **Root Cause** | The endpoint was built as a developer self-test ("is everything wired?") and deployed on the public API prefix without redaction. |
| **Evidence** | `catch (\Throwable $e) { $checks['database'] = ['status'=>'failed','message'=>$e->getMessage()]; }`; `'path' => $firebaseCredentialsPath`; `'path' => $publicStorage`; `'mode' => $midtransProduction ? 'production' : 'sandbox'`, `'server_key' => 'configured'|'missing'`; route registered with `throttle:public-catalog` and no `ApiAuthenticate`. |
| **Execution Flow** | Anonymous `GET /api/health` → 200 (healthy) reveals environment shape; when the DB is down or misconfigured → 503 with the driver's error text and the DB username/host. A scanner enumerating the host obtains the storage layout and hidden routes for targeted follow-up (e.g. direct `storage/app/public` access). |
| **Impact** | Free reconnaissance (users, hosts, paths, modes) to any unauthenticated caller; assists credential attacks and path-based attacks; violates least-disclosure on a public route. |
| **Recommended Fix** | Move the detailed report behind `super_admin` (or remove it), return only `{"status":"ok"}` to the public, log details server-side, and never echo exception messages. Also reconcile the check with the real source of truth for payments (per-clinic routing instead of the legacy central env key). |
| **Dependencies** | Monitoring/uptime check configuration (if an external monitor depends on the body shape), `services.php`, `PaymentAccountResolver`, `MerchantConfigurationService`. |
| **Testing Scenario** | 1) Anonymous `GET /api/health` on a healthy stack → assert the body contains no path, no key-presence info, no environment mode. 2) Break the DB credentials → assert the public body stays generic (503 with `{"status":"failed"}`), while the log contains the detail. 3) Assert the authenticated super-admin version (if kept) does show details. |

---

### PB-H-10 — PaymentController's self-handled exceptions bypass the API error sanitiser and echo internals

| Field | Value |
|---|---|
| **ID** | PB-H-10 |
| **Severity** | HIGH |
| **Category** | Error handling / information disclosure / configuration |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Api/PaymentController.php::createSnapToken`, `getTransactionStatus`, `syncPaymentStatus` (all `catch (\Throwable|\Exception)` → `'Internal server error: ' . $e->getMessage()`); `bootstrap/app.php` sanitising `Throwable` renderer (bypassed); `.env` `APP_DEBUG=true`, `APP_ENV=local`; same anti-pattern exists in `PetShopOrderController` warning logs |
| **Problem** | Controllers catch fatal errors themselves and return the raw message to the client. Because the exception never reaches Laravel's renderer, the sanitising handler the authors installed never runs. With `APP_DEBUG=true` in the shipped `.env`, SQL errors, file paths, class names and table names are exposed to Android clients on the most sensitive (payment) endpoints. |
| **Root Cause** | Defensive try/catch was added early to make the Android client see structured JSON; the generic renderer was added later, but the local catches were left in place. **Configuration:** `APP_DEBUG`/`APP_ENV` were never flipped for deployment. |
| **Evidence** | `return response()->json(['success'=>false,'message'=>'Internal server error: ' . $e->getMessage()], 500);` (three occurrences); `bootstrap/app.php` has `$exceptions->render(function (\Throwable $e, $request) { ... return response()->json(['success'=>false,'message'=>'Internal server error.'], 500); });` with an explicit comment "Several controllers echo $e->getMessage() (SQLSTATE/paths) and APP_DEBUG may be true behind a public URL". |
| **Execution Flow** | A malformed split payload or a DB timeout inside `createBookingPayment` throws → the controller's own catch returns the driver/exception text → the Android app shows or logs it; an attacker can deliberately trigger failures to map the schema (`Table 'travelo.payments' doesn't exist`, column names, file paths). |
| **Impact** | Schema/implementation disclosure on payment endpoints; combined with PB-H-08, an attacker can probe without throttling by IP; noisy client-side error surfaces. |
| **Recommended Fix** | Delete the controller-level `catch (\Throwable)` blocks (or rethrow after logging) so the global sanitizer applies; set `APP_DEBUG=false` and `APP_ENV=production` in the deployment env; keep detailed messages only in logs (with a request id returned to the client for support correlation). |
| **Dependencies** | `.env`/deployment env management, Android error handling (it must cope with the generic message), log correlation id, monitoring. |
| **Testing Scenario** | 1) Force a DB failure inside `snap-token` (bad credentials in a staging env) → assert the response body is exactly `{"success":false,"message":"Internal server error."}` and the log has the detail. 2) Trigger an invalid split payload → same assertion. 3) Assert `APP_DEBUG=false` in the production env file and that a `NotFoundHttpException` still returns the canonical 404 envelope. |

---

### PB-M-01 — Money lives in two ledgers; manual confirmation writes only one

| Field | Value |
|---|---|
| **ID** | PB-M-01 |
| **Severity** | MEDIUM |
| **Category** | Multiple sources of truth / reconciliation |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Admin/PaymentController.php::confirm` (writes `bookings`); `app/Services/PaymentStatusService.php::applyTransactionStatus` (writes `bookings` + `payment_events`); `app/Services/PaymentService.php::applyProviderNotification` (writes `payments`); `app/Services/PaymentReconciliationService.php` (reads `payments` only); `Admin/PaymentController::paymentStats` + `Admin/MedicalRecordController::forgetDashboardCaches` (read `bookings`) |
| **Problem** | The same payment can be recorded in `bookings.paid_amount` and/or `payments.gross_amount/status` depending on who recorded it. Manual admin confirmation creates no `payments`/`payment_events` row, so reconciliation cannot see it; a webhook-created payment is visible in both. There is no statement that proves the two ledgers agree. |
| **Root Cause** | The `payments` domain was introduced later ("payment domain terpusat" migration comment) as an *additive* ledger instead of a replacement; the older `bookings.paid_amount` chapter was never retired. |
| **Evidence** | `Admin\PaymentController::confirm` updates `paid_amount`, `remaining_amount`, `payment_date`, `payment_status` and writes an audit row — no `Payment::create`, no `payment_events`. `PaymentReconciliationService::syncPending` iterates `Payment::pendingCheck()` only. `applyTransactionStatus` writes `payment_events` but not `payments` (`applyProviderNotification` does that separately in the webhook path). |
| **Execution Flow** | Booking paid by bank transfer, admin uses the manual confirm form → `bookings.paid_amount = total`. Later the provider (or an auditor) queries `payments` → no row exists → reconciliation/receipt/report surfaces under-report the clinic's takings. Reverse case: webhook records `payments` + `bookings`; manual confirm afterwards is capped by `remaining_amount` so it cannot double count — but a manual confirm followed by a *late* webhook (same money) can, because `payment_events` dedupe only applies to the same provider `order_id`. |
| **Impact** | Reports depend on which path was used; auditors cannot derive revenue from one table; manual and online money cannot be reconciled automatically. |
| **Recommended Fix** | Make `payments` the single ledger: every money mutation (manual confirm included) creates a `payments` row (source `clinic_admin`, provider `manual`) plus a `payment_status_history` row, and `bookings.paid_amount/remaining_amount` becomes a derived cache refreshed from `payments`. Add a nightly reconciliation command that asserts `sum(payments.settled) == bookings.paid_amount − refunds`. |
| **Dependencies** | `Payment` model/`PaymentService`, refund ledgers (PB-M-02), dashboards (`Admin/DashboardController`, `Admin/PaymentController::paymentStats`, `Api/DashboardController`), `payment_events`, Android payment status payloads. |
| **Testing Scenario** | 1) Record a manual payment for a booking. 2) Assert a `payments` row exists with source `clinic_admin`, a status history entry, and that `bookings.paid_amount` equals the settled `payments` sum. 3) Run the reconciliation command on a dataset containing manual + online + refunded bookings → assert zero variance reported. |

---

### PB-M-02 — Refunds are split across two independent ledgers and never propagate to the patient-visible state

| Field | Value |
|---|---|
| **ID** | PB-M-02 |
| **Severity** | MEDIUM |
| **Category** | Financial consistency / duplicated domain |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Services/RefundService.php::record` (`booking_refunds`); `app/Services/PaymentService.php` refund paths (`payment_refunds`, `payments.refunded_amount`, `payments.status`); `app/Http/Controllers/Admin/PaymentController.php::refund`; `app/Services/ReceiptService.php` (`payments.refunded_amount`); Android `PaymentReceipt.kt` / booking payloads |
| **Problem** | A cash/transfer refund writes only `booking_refunds`; a provider refund writes only `payment_refunds` + `payments.refunded_amount`. Neither updates `bookings.payment_status`, neither notifies the patient, and the patient-facing booking/receipt still presents the pre-refund amount. |
| **Root Cause** | Two refund flows were built at different times by different owners (manual clinic accounting vs. Midtrans integration) with no shared abstraction, and the booking state machine has no refunded state (see PB-H-01). |
| **Evidence** | `RefundService::record` → `BookingRefund::create([...])` only. `PaymentService` refund path → `$attributes['refunded_amount'] = ...`, `PaymentRefund` row. `ReceiptService` reads `$payment?->refunded_amount` — unaware of `booking_refunds`. No `FCMService` call in `Admin\PaymentController::refund`. |
| **Execution Flow** | Clinic refunds 250 000 in cash → `booking_refunds` row (correct, capped, audited). Patient app opens the booking/receipt → still shows the full amount paid, `payment_status = paid`; no push notification. Admin invoice view *does* subtract the refund (it calls `RefundService::refundedTotal`), so internal and external views disagree. |
| **Impact** | Patient/CS confusion, disputes, and the appearance of non-refunded money; a provider refund is invisible to the clinic's own accounting report; two "total refunded" numbers exist. |
| **Recommended Fix** | One refund domain: `RefundService` should record against `payments` when a provider payment exists and against `booking_refunds` only for cash; always derive an aggregated `refunded_total` per booking, expose it in the booking payload and receipt, transition `payment_status` to `partially_refunded`/`refunded`, and notify the patient via FCM. |
| **Dependencies** | PB-H-01 (cancel/refund state), PB-M-01 (ledger unification), `ReceiptService`, Android booking/receipt rendering, `PaymentRefund`, `BookingRefund`. |
| **Testing Scenario** | 1) Refund part of a paid booking in cash → assert app booking payload shows `refunded_amount` and `payment_status = partially_refunded`, receipt shows the net amount, and a notification was queued. 2) Do the same via a provider refund → assert both the app and the admin report show the same total refunded. 3) Assert refunding more than paid is still rejected in both paths. |

---

### PB-M-03 — Webhooks for unknown or missing payables are acknowledged with 200 and dropped

| Field | Value |
|---|---|
| **ID** | PB-M-03 |
| **Severity** | MEDIUM |
| **Category** | Error handling / lost settlement / observability |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Api/MidtransWebhookController.php::handle` (unknown prefix → `['status'=>'ok']`), `handlePetShop` (payment not found → `['status'=>'ok']`), `handleBooking`/`handleMedicalRecord` (`Payment::where(...)` may be null), `app/Services/PaymentService.php::applyToPayable` ("Payment has no matching booking/record/order" → `Log::warning` + return) |
| **Problem** | When the settlement cannot be applied (payment row missing, payable deleted, prefix unknown), the endpoint answers 200 OK. Midtrans treats the notification as delivered and will not retry; nothing records a dead letter, and no alert fires. |
| **Root Cause** | The handler treats "unmatched" as "nothing to do" (correct for idempotent replays) and does not distinguish it from "money arrived that we cannot account for". |
| **Evidence** | `if (...) { ... } else { Log::warning('Order ID format not recognized', ...); return response()->json(['status'=>'ok']); }`; `handlePetShop`: `if (!$payment) { Log::warning('Pet shop payment not found for webhook', [...]); return response()->json(['status'=>'ok']); }`; `applyToPayable`: `Log::warning('Payment has no matching booking' ...); return;`. |
| **Execution Flow** | Patient pays `SHOP-123-...`; the `payments` row was removed by an admin fix or a failed migration → webhook arrives → 200 OK → money captured by Midtrans, absent from all internal ledgers, no retry, only a log line among thousands (debug level, PB-M-06). Discovered weeks later during provider settlement reconciliation. |
| **Impact** | Silent revenue loss / unfulfilled orders; no automated detection; audits depend on log archaeology. |
| **Recommended Fix** | Introduce an `unmatched_webhook` (dead-letter) table populated for every notification that cannot be applied, return a non-2xx (or 202 with a stored record) so retries/alerts are possible, and add a dashboard/monitor on that table. Also alert on `gross_amount`/merchant mismatches currently returned as 422. |
| **Dependencies** | `payment_notifications` table (extend with `applied`/`reason`), alerting channel, `PaymentReconciliationService` (could re-drive dead letters), admin operations UI. |
| **Testing Scenario** | 1) Send a valid-signature webhook for a `provider_order_id` that does not exist → assert a dead-letter row is created and the response is not a silent 200 (unless it is also persisted + monitored). 2) Delete a `payments` row then replay its settlement → same assertion. 3) Replay the same dead letter after fixing the data → assert it applies and clears. |

---

### PB-M-04 — Android payment payload fallbacks produce guaranteed 422s

| Field | Value |
|---|---|
| **ID** | PB-M-04 |
| **Severity** | MEDIUM |
| **Category** | Integration contract mismatch |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/ui/screens/payment/PaymentScreen.kt:154`; `.../payment/PaymentViewModel.kt:540` and `:94`; backend `Api/PaymentController::createSnapToken` (`regex /^BOOKING-\d+-\d+$/`, expected-amount equality check) |
| **Problem** | Two client fallbacks are incompatible with the backend contract: (a) `if (isDpPayment) booking.dpAmount ?: totalAmount` sends the full total for a DP booking whose `dpAmount` is null/0, which the backend rejects with 422 "Payment amount does not match booking amount"; (b) `currentOrderId ?: "BOOKING-$bookingId"` produces an order id with two segments, which fails the `^BOOKING-\d+-\d+$` regex and is rejected with 422 "Invalid booking order ID". Both are dead-ends with no recovery path in the app. |
| **Root Cause** | The client is allowed to choose the amount and the order id even though the backend is authoritative; the fallbacks were written for legacy booking payloads that no longer exist. |
| **Evidence** | `PaymentScreen.kt:154` (`val amount = if (isDpPayment) booking.dpAmount ?: totalAmount else booking.totalAmount ?: totalAmount`); `PaymentViewModel.kt:540` (`val orderId = currentOrderId ?: "BOOKING-$bookingId"`); `PaymentController::createSnapToken` validates `transaction_details.order_id` against `^BOOKING-\d+-\d+$` and compares `gross_amount` to `dp_amount` or `total_amount` exactly (`$requestedAmount !== $expectedAmount` → 422). |
| **Execution Flow** | Legacy/API-created booking with `dp_amount = 0` and `payment_type = dp` → app sends `gross_amount = total_amount` → backend expects `0` (or the DP amount) → 422 → checkout screen shows a failure and there is no alternative path. Second case: `currentOrderId` lost after process death → fallback `BOOKING-42` → 422 invalid order id → same. |
| **Impact** | Patient cannot pay; booking remains unpaid and later auto-cancelled/blocked; support ticket per occurrence. |
| **Recommended Fix** | Prefer server-authoritative payment intent: add `GET /payment/booking/{id}` before creating the intent and use the returned `amount`/`order_id`, or accept a `payment_kind` parameter server-side and let the server decide amount and order id (no client amount at all). On the client, remove both fallbacks and show a retry that re-fetches the booking. |
| **Dependencies** | `PaymentController::createSnapToken`, `paymentResponseData`, Android `SnapTokenRequest`/`Booking` models, `PaymentViewModel` retry logic. |
| **Testing Scenario** | 1) Booking with `payment_type = dp` and `dp_amount = 0` → open the payment screen → assert the app obtains a valid token (server-derived amount) or shows an actionable error, never a 422 dead-end. 2) Clear `currentOrderId` (simulate process death) → assert a valid three-segment order id is used. 3) Assert the app never sends a `gross_amount` that differs from the server's expectation. |

---

### PB-M-05 — Booking list performs a queue-position query per row (N+1)

| Field | Value |
|---|---|
| **ID** | PB-M-05 |
| **Severity** | MEDIUM |
| **Category** | Performance |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Api/BookingController.php::index` / `upcoming` / `checkIn` → `attachQueueMeta()` → `app/Services/QueueService.php::positionOf` |
| **Problem** | `attachQueueMeta()` is applied to every row of a paginated list and calls `QueueService::positionOf()`, which issues its own `count()` query (and a `whereDate` on `queue_date`) per booking. A 20-row page issues ~20-40 extra round trips, each with a non-index-friendly `whereDate`. |
| **Root Cause** | Queue meta is computed lazily per model instance instead of being eager-loaded/aggregated for the whole page. |
| **Evidence** | `->map(fn (Booking $booking) => $this->attachQueueMeta($booking))` in `index`; `positionOf` executes `Booking::where('clinic_id',...)->whereDate('queue_date',...)->whereIn('queue_status',[waiting,called])...count()`. |
| **Execution Flow** | `GET /api/bookings?page=1` → 20 bookings → 20 `positionOf` calls → ~20 extra queries + possible `whereDate` full scans on `queue_date`. Response latency grows linearly with page size; the Android home screen (`upcoming`, limit 1) is unaffected, but the history screen is. |
| **Impact** | Slower list responses and extra DB load; the `whereDate()` predicate prevents index usage, so the cost grows with total bookings per clinic per day. |
| **Recommended Fix** | Compute positions in one aggregate query for all bookings on the page (grouped by `clinic_id, queue_date`), or add a `queue_positions` cache/derived column; replace `whereDate('queue_date', ...)` with a range predicate on an indexed `date` column. |
| **Dependencies** | `QueueService`, `bookings` indexes (`clinic_id, queue_date, queue_number`), Android list rendering. |
| **Testing Scenario** | 1) Seed 500 bookings and request page 1 with `DB::listen` counting queries → assert the query count does not scale with page size (constant number of queries). 2) Assert `queue_position` values match the single-booking endpoint for the same data. |

---

### PB-M-06 — Debug-level single-file logging of payment payloads, plus a cache-key collision in payment stats

| Field | Value |
|---|---|
| **ID** | PB-M-06 |
| **Severity** | MEDIUM |
| **Category** | Observability / configuration / privacy |
| **Status** | Confirmed (logging) / Potential (cache-key collision) |
| **Affected Module/Files/Methods** | `config/logging.php` (`default = stack → single`, no rotation, no `LOG_LEVEL` in `.env`); `Api/PaymentController` `Log::info` calls (user id, order id, gross amount, routing source, merchant label); `Admin/PaymentController::paymentStats` cache key `payment_stats_c{suffix}`; `Admin/MedicalRecordController::forgetDashboardCaches` |
| **Problem** | (a) Logs go to a single non-rotating file at debug level, including per-checkout payment metadata for every user. (b) The payment stat cache key does not include the request filters, so a filtered page can render unfiltered cached totals (and vice versa) until the TTL/next invalidation. |
| **Root Cause** | Logging config was left at framework defaults; the cache key was designed for clinic scoping only and filters were added later without touching the key. |
| **Evidence** | `'default' => env('LOG_CHANNEL','stack')`, `'stack' => ['channels' => explode(',', env('LOG_STACK','single'))]`, `single` channel with no daily rotation; `.env` has no `LOG_LEVEL`; `Log::info('Creating snap token', ['user_id'=>..., 'order_id'=>..., 'gross_amount'=>...])`; `Cache::forget('payment_stats_c' . ($cacheClinicId ?: 'all'))` — no filter component in the key. |
| **Execution Flow** | Sustained traffic writes an unbounded `laravel.log` containing user ids + amounts + order ids (PII/financial metadata). Simultaneously, admin A opens `/admin/payments` with no filters (cache written), admin B opens `/admin/payments?status=paid&from=...` within the TTL → the stat cards show the unfiltered numbers while the table is filtered. |
| **Impact** | Disk exhaustion risk and unnecessary PII retention; misleading financial dashboards for clinic staff; harder incident triage because everything is at one level in one file. |
| **Recommended Fix** | Use a daily/rotating channel with `LOG_LEVEL=warning` in production, drop verbose payment logging to debug, hash or omit user identifiers in info-level logs, and include a hash of the normalised filter set in the stats cache key (or cache the query result per filter signature). |
| **Dependencies** | Deployment log shipping, `Admin/PaymentController::index/paymentStats`, `Admin/MedicalRecordController::forgetDashboardCaches` key format, monitoring. |
| **Testing Scenario** | 1) Set `LOG_LEVEL=warning` → assert no per-checkout info lines are written. 2) Request `/admin/payments` unfiltered, then with `status=paid` → assert the two responses show different (correct) stat totals. 3) Mutate a payment → assert both cache variants are invalidated. |

---

### PB-M-07 — ServiceRequest offer/status updates are unlocked read-modify-write

| Field | Value |
|---|---|
| **ID** | PB-M-07 |
| **Severity** | MEDIUM |
| **Category** | Concurrency / lost update |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Services/ServiceRequestService.php::advance` and `::offer` (both `$request->fill($attributes)->save()`); callers `Admin/ServiceRequestController`, `Doctor/ServiceRequestController`, `Api/ServiceRequestController::accept/reject` (accept is locked, advance/offer are not) |
| **Problem** | Two staff members editing the same request concurrently both read the pre-edit model, both write, and the second silently overwrites the first — including offer price, doctor, schedule and status. Notifications are sent based on each writer's view, so the patient can receive an "offer updated" push for a version that no longer exists. |
| **Root Cause** | `accept()` was hardened with `lockForUpdate` (the money-relevant path) but the general status/offer mutators were not, and no optimistic version column exists. |
| **Evidence** | `advance`: `$request->fill($attributes)->fill(['status' => $status])->save();` then `$this->fcm->sendServiceRequestEvent(...)` — no `DB::transaction`, no lock, no re-read. `offer`: `$request->fill($attributes)->save();` same. `accept` does use `ServiceRequest::whereKey(...)->lockForUpdate()`. |
| **Execution Flow** | Admin A sets offer price 300 000 at 10:00:00. Admin B (same page, stale form) submits price 250 000 at 10:00:01 → A's row is overwritten; the patient receives two "offer" notifications, the last of which points at B's version; the audit trail has no record of the overwritten values. |
| **Impact** | Wrong price/schedule presented to the patient (financial consequence on acceptance), duplicate/contradictory notifications, no audit of who overwrote what. |
| **Recommended Fix** | Wrap `advance`/`offer` in `DB::transaction` with `lockForUpdate` on the row, re-read the current status and reject the write when the caller's expected status/`updated_at` no longer matches (optimistic concurrency), then notify once from the committed result. |
| **Dependencies** | `ServiceRequest` model (maybe an `updated_at`-based version check), admin/doctor offer forms, `FCMService::sendServiceRequestEvent`, `ServiceRequest::TRANSITIONS`. |
| **Testing Scenario** | 1) Open the offer form in two sessions. 2) Submit both with different prices near-simultaneously. 3) Assert exactly one write succeeds (the other returns a conflict/refresh message), the patient receives one notification, and the stored price matches the winning submission with an audit entry. 4) Assert status transitions still reject illegal edges after the change. |

---

### PB-M-08 — No authorization policy layer; the clinical masking invariant is caller-driven

| Field | Value |
|---|---|
| **ID** | PB-M-08 |
| **Severity** | MEDIUM |
| **Category** | Architecture / authorization durability |
| **Status** | Recommendation (current behaviour verified safe) |
| **Affected Module/Files/Methods** | absence of `app/Policies`, `app/Gates`, `Gate::`, `@can`, `authorize(` across `app/` and `routes/`; `app/Models/MedicalRecord.php::maskClinicalFields` invoked manually from `Api/MedicalRecordController`, `Api/PetController`, `Api/BookingController`, `Api/DashboardController`; `ApiAuthenticate` prefix bypass `api/auth/account*` |
| **Problem** | Every authorization decision is imperative and repeated per controller/query. Two invariants are especially exposed: (a) clinical masking must be remembered by every endpoint that serialises a `MedicalRecord` raw; (b) tenant/pet ownership must be remembered by every query. A single future endpoint that forgets either produces a data leak with no second line of defence, and the existing unit of enforcement (a comment in the model) is not executable. |
| **Root Cause** | The project chose middleware + manual scoping over Laravel's policy/gate layer; the masking rule arrived later and was attached to call sites rather than to serialization. |
| **Evidence** | `grep -r "Gate::\|@can\|authorize(\|HasRoles" app/ routes/` → no matches; `app/Policies` does not exist; `maskClinicalFields` docblock itself warns "Endpoints that return the model raw must call this". Verified call sites: 5 controllers do, and no currently-shipping endpoint was found missing it. |
| **Execution Flow (failure mode)** | A new endpoint `GET /api/pets/{id}/visits` returns `MedicalRecord` models without calling `maskClinicalFields` → the diagnosis/SOAP of an unpaid record is served to the patient, silently, with no test to catch it. Similarly, a new admin query omitting `currentClinicId()` would serve another clinic's data. |
| **Impact** | Latent cross-tenant/clinical-data disclosure risk that grows with every added endpoint; review cost on each PR; security depends on reviewer memory. |
| **Recommended Fix** | Introduce Policies (`PetPolicy`, `BookingPolicy`, `MedicalRecordPolicy`, `ProductOrderPolicy`) with `before()` letting `super_admin` through, and enforce them in `authorizeResource`/`Gate::authorize`. Move masking into an API Resource/`JsonResource` so it cannot be forgotten, and add a Pest/PHPUnit test asserting that every route returning a `MedicalRecord` masks it when unpaid. |
| **Dependencies** | All controllers (touching), Android payload shapes (must not change), test suite addition, `PatientScope` can be reused inside policies. |
| **Testing Scenario** | 1) Add a policy and `authorizeResource` to two resources (pets, medical records). 2) Assert a patient requesting another patient's ids receives 403 (not 404-only by accident). 3) Add a data-driven test: for each API route whose response contains a medical record, an unpaid record's `diagnosis`/`soap` fields are null. 4) Assert `super_admin` bypass still works. |

---

### PB-M-09 — Pet Shop webhook path bypasses `ProductOrderService::transition`

| Field | Value |
|---|---|
| **ID** | PB-M-09 |
| **Severity** | MEDIUM |
| **Category** | Duplicate logic / state bookkeeping drift |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Services/PaymentService.php::applyToPayable` (ProductOrder branch: direct `forceFill(['status'=>..., 'payment_status'=>...])->save()` + `stock->release()` + `orders->log()`), vs. `app/Services/ProductOrderService.php::transition` (sets `cancelled_at`, `cancellation_reason`, `confirmed_at`, `ready_at`, `completed_at`, issues/revokes pickup passes) |
| **Problem** | The payment webhook performs its own order transitions instead of delegating to the domain transition method, so the two writers produce different row shapes for the same status change: webhook-expired orders have no `cancelled_at` and no `cancellation_reason`, pickup passes are not revoked, and the status-history `note`/`source` differ from the documented flow. |
| **Root Cause** | The payment domain needed to nudge orders and implemented the minimal writes locally instead of reusing the single transition entry point (the service's own docblock says "Semua perubahan status melewati satu pintu"). |
| **Evidence** | `applyToPayable`: `$order->forceFill(['status' => ProductOrder::STATUS_EXPIRED, 'cancelled_at' => now(), ...])->save()` for the *pending* branch, and the `STATUS_PAID` branch only sets `payment_status`. `transition()` is the only place that calls `$pickupPasses->issue()/revoke()`, and it is not used by the webhook. |
| **Execution Flow** | Order reaches `ready_for_pickup` (pickup pass issued). Provider later reports `expire`/`cancel` for the payment → `applyToPayable` writes status directly → the pass is never revoked → a customer can still present a valid QR for an order that is no longer valid. |
| **Impact** | Stale pickup passes (fraud/operational risk), incomplete audit rows for webhook-driven cancellations, and two code paths that must be kept in sync forever. |
| **Recommended Fix** | Make `applyToPayable` call `ProductOrderService::transition()` (and only that) for every status change, so `completed_at`/`cancelled_at`/pass issue-revoke happen exactly once, in one place. Add a regression test asserting every `ProductOrder` terminal state has its cancellation/completion bookkeeping populated. |
| **Dependencies** | `ProductOrderService::transition` re-entrancy (`transition` opens its own transaction — must be called outside an existing one or made composable), `PickupPassService`, `PaymentService`, status history consumers (`Admin/PetShopOrderController`). |
| **Testing Scenario** | 1) Drive an order to `ready_for_pickup`, capture the pass. 2) Send a cancel/expire webhook. 3) Assert the order is `cancelled`/`expired` **with** `cancelled_at` + `cancellation_reason`, the pickup pass is revoked, and the status history row matches what `transition()` produces. 4) Assert no double stock release occurred. |

---
### PB-L-01 — Deleting a medical record orphans attachment files on disk

| Field | Value |
|---|---|
| **ID** | PB-L-01 |
| **Severity** | LOW |
| **Category** | Storage hygiene |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Admin/MedicalRecordController.php::destroy`; `app/Services/MedicalRecordAttachmentService.php::delete` (exists but unused here) |
| **Problem** | `$record->delete()` cascades the `medical_record_attachments` rows but never unlinks the files in `storage/app/public`, so every deleted record leaves its uploads behind forever. |
| **Root Cause** | The `update()` path removes attachments through the service (which unlinks), but `destroy()` was never updated after attachments were introduced. |
| **Evidence** | `destroy()` calls only `$this->prescriptions->restoreOnDelete($record); $record->delete();`. `MedicalRecordAttachmentService::delete()` (the only unlink path) is invoked from `update()` only. |
| **Execution Flow** | Admin deletes a record with 3 photos → 3 DB rows removed → 3 files remain in `storage/app/public/medical-records/...` with no owning row. |
| **Impact** | Unbounded disk growth and orphaned clinical images (a privacy concern: files that should be deleted remain retrievable by path). |
| **Recommended Fix** | In `destroy()`, iterate `$record->attachments` and call `$this->attachments->delete($attachment)` before deleting the record, and add a `storage:prune-orphans` command for existing leftovers. |
| **Dependencies** | `MedicalRecordAttachmentService`, `filesystems` disk configuration, PB-H-07 (same endpoint is being redesigned). |
| **Testing Scenario** | Create a record with attachments, delete the record via admin → assert the DB rows and the files are gone. Then run the prune command on a seeded orphan → assert it is removed. |

---

### PB-L-02 — `PUT /api/pets/{petId}/vaccinations/{vaccinationId}` is implemented but never called by the app

| Field | Value |
|---|---|
| **ID** | PB-L-02 |
| **Severity** | LOW |
| **Category** | Dead code / contract drift |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `routes/api.php` (vaccination PUT); `app/Http/Controllers/Api/VaccinationController.php::update`; `ApiService.kt` (no `@PUT` for vaccinations — only GET/POST/DELETE) |
| **Problem** | The backend exposes a vaccination update endpoint that no client uses, so it is untested from the consumer side and drifts silently (validation, masking, response shape) from the endpoints that are used. |
| **Root Cause** | A full CRUD surface was generated for vaccinations while the Android UI only ever needed create/delete. |
| **Evidence** | `grep -n "@GET\|@POST\|@PUT\|@DELETE" ApiService.kt` shows no `PUT` vaccination call; `routes/api.php` registers `Route::put('/pets/{petId}/vaccinations/{vaccinationId}', ...)`. |
| **Execution Flow** | No production flow reaches it. Any future regression (e.g. missing ownership check) would be undetected by app-level testing. |
| **Impact** | Maintenance surface without coverage; a hidden unauthenticated-tested-but-unused endpoint. |
| **Recommended Fix** | Either delete the route+method, or add an app entry point and a test asserting ownership (`$request->user()->pets()->findOrFail($petId)` + `$pet->vaccinations()->findOrFail($id)`). |
| **Dependencies** | Android vaccination UI, `ApiService.kt`, API contract doc. |
| **Testing Scenario** | If kept: patient A tries to update patient B's vaccination → 404. If removed: assert no route matches and the Android build still compiles. |

---

### PB-L-03 — `HealthController` treats the deprecated `register-direct` endpoint as a required route

| Field | Value |
|---|---|
| **ID** | PB-L-03 |
| **Severity** | LOW |
| **Category** | Contract drift / dead requirement |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Http/Controllers/Api/HealthController.php` (`$requiredRoutes['register_direct'] = 'api/auth/register-direct'`); `Api/AuthController::registerDirect` (returns 410); `docs/API_CONTRACT.md` |
| **Problem** | The health check asserts the presence of an endpoint that is intentionally retired (410 `use_otp_flow`). If someone finally removes it, `/api/health` starts failing and monitoring alerts on a non-issue. |
| **Root Cause** | The readiness list was written before the OTP migration and never updated. |
| **Evidence** | `'register_direct' => 'api/auth/register-direct'` in the required-routes array; `registerDirect` returns 410 with `code: use_otp_flow`. |
| **Execution Flow** | Route removed for cleanup → `routeExists()` returns false → `success: false` + 503 on health → false incident. |
| **Impact** | Noisy/wrong readiness signal; couples monitoring to a deprecated API. |
| **Recommended Fix** | Drop `register_direct` from the required list, replace it with the endpoints the current contracts truly require (OTP register flow, service requests, shop orders), and make the check derive from named routes instead of raw URIs. |
| **Dependencies** | PB-H-09 (health endpoint redesign), deployment monitoring, `docs/API_CONTRACT.md`. |
| **Testing Scenario** | Remove the deprecated route in a branch → assert `/api/health` still reports healthy for all *live* contracts. Add a required live route and delete it → assert health reports the failure. |

---

### PB-L-04 — Money-input normalisation and record billing logic are duplicated between Admin and Doctor controllers

| Field | Value |
|---|---|
| **ID** | PB-L-04 |
| **Severity** | LOW |
| **Category** | Duplicate logic |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `Admin/MedicalRecordController.php:48` and `Doctor/DoctorMedicalRecordController.php:399` (both `private function normalizeMoneyInput`); duplicated record billing sequence (`syncForRecord` → `medicine_cost` → `recalculatePaymentState` → `save`) in both controllers |
| **Problem** | The same "parse Indonesian/English money input" and the same billing recomputation exist twice. Any fix (e.g. handling a thousands separator or clamping negatives) must be applied twice, and the two call sites already differ in their `$fallback` usage. |
| **Root Cause** | Two portals were built independently; shared logic was copy-pasted instead of extracted into a service or a `Money` helper. |
| **Evidence** | Two identical private methods with the same name in two controllers; the billing block appears in both `store` and `update` of each controller. |
| **Execution Flow** | A bug fix applied only in the Admin copy (the dashboard most operators use) leaves the doctor portal producing different `cost`/`medicine_cost` values for the same input. |
| **Impact** | Divergent billing behaviour between portals; double maintenance; inconsistent audit values. |
| **Recommended Fix** | Move normalisation into `App\Support\Money` (or a `MoneyInput` cast) and move the billing sequence into a `MedicalRecordBillingService` used by both portals. |
| **Dependencies** | `Money`, `PrescriptionService`, `MedicalRecord::recalculatePaymentState`, PB-C-02 (billing should be recomputed anyway). |
| **Testing Scenario** | Feed identical inputs ("1.500.000", "1500000,00", "", "-5") to both portals → assert identical stored `cost`, `treatment_cost`, `medicine_cost`, `total_medical_cost`, `extra_payment_*`. |

---

### PB-L-05 — `pets.age` and `pets.date_of_birth` are two independent truths

| Field | Value |
|---|---|
| **ID** | PB-L-05 |
| **Severity** | LOW |
| **Category** | Data model redundancy |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `database/migrations/2024_01_15_000003_create_pets_table.php`, `2026_03_02_024323_add_weight_to_pets_table.php`, `2026_03_02_080241_add_date_of_birth_to_pets_table.php`; `Api/PetController::store/update` (both fields writable, no derivation) |
| **Problem** | Age can be entered as an integer and/or as a birth date; nothing keeps them consistent, so a pet can simultaneously be "3 years old" and "born in 2015", and age never advances over time. |
| **Root Cause** | `date_of_birth` was added later as an additive field without retiring or deriving `age`. |
| **Evidence** | Both columns exist; `store()` validation accepts `age` (`integer|min:0`) and `date_of_birth` (`date`) independently; no accessor/mutator derives one from the other anywhere in the model. |
| **Execution Flow** | Patient enters age 3 at creation, then later enters a birth date; both persist. A vaccination-due computation or weight chart that uses `age` shows a different value than one that uses the birth date. |
| **Impact** | Clinical displays can contradict each other; age never updates as the pet grows. |
| **Recommended Fix** | Make `date_of_birth` authoritative and expose `age` as a derived accessor (or store `age_months` computed on write and recompute via a scheduled job); drop the editable `age` input from the API. |
| **Dependencies** | `Pet` model, `Api/PetController`, Android pet forms/models, any report using `age`. |
| **Testing Scenario** | Create a pet with `date_of_birth = 2023-01-01` → assert the reported age matches today's date and that no client can set a contradicting `age`. |

---

### PB-L-06 — Queue is configured on `database` but FCM dispatch is disabled and no worker is provisioned

| Field | Value |
|---|---|
| **ID** | PB-L-06 |
| **Severity** | LOW |
| **Category** | Configuration / delivery reliability |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `.env` (`QUEUE_CONNECTION=database`); `config/services.php` (`fcm.queue` default false); `app/Services/FCMService.php::sendToUser` (dispatch only when the flag is true); `app/Jobs/SendFcmNotification.php`; `routes/console.php` (no `queue:work`); comment referencing `start-queue.bat` |
| **Problem** | The queue infrastructure exists but is off by default, and nothing in the repository runs a worker. Flipping `FCM_QUEUE=true` without a supervised worker silently parks notifications in the `jobs` table — exactly the failure the code comment warns about. |
| **Root Cause** | A deliberate conservative default (send synchronously so nothing is silently queued) plus a manual batch script instead of a supervised worker. |
| **Evidence** | `if ((bool) config('services.fcm.queue', false)) { SendFcmNotification::dispatch(...); return true; }`; `start-queue.bat` referenced in a comment; no supervisor/systemd/pm2/`queue:work` entry under any deployment directory in the repo. |
| **Execution Flow** | Operator enables `FCM_QUEUE=true` for performance → dispatches land in `jobs` → no worker runs → patients stop receiving pushes, and `sendToUser()` still returns `true` (it returns `true` right after dispatch) → no error anywhere. |
| **Impact** | Silent notification outage if the flag is flipped; wasted `jobs` table growth. Currently mitigated by the default being off. |
| **Recommended Fix** | Either keep synchronous delivery and remove the queue path, or add a supervised worker (`queue:work --tries=3 --timeout=30`) with a health check on `jobs` backlog and `failed_jobs`, and make `sendToUser()` return `false`/defer the truth until the job is processed. |
| **Dependencies** | Deployment tooling, `jobs`/`failed_jobs` tables (present via `0001_01_01_000002_create_jobs_table.php`), monitoring. |
| **Testing Scenario** | Set `FCM_QUEUE=true`, dispatch a notification, assert a `jobs` row exists; run one worker tick → assert the row is consumed and the notification is delivered (or `failed_jobs` records a real failure). Assert the backlog is visible in monitoring. |

---

### PB-L-07 — `ServiceRequestService::offer` does not validate the offered doctor against the request's clinic at write time

| Field | Value |
|---|---|
| **ID** | PB-L-07 |
| **Severity** | LOW |
| **Category** | Validation placement / late failure |
| **Status** | Confirmed |
| **Affected Module/Files/Methods** | `app/Services/ServiceRequestService.php::offer` (persists `offer_doctor_id` unchecked); `::accept` (validates doctor belongs to clinic later); `Admin/ServiceRequestController` (offer form validation) |
| **Problem** | An offer can reference a doctor from another clinic (or an inactive/deleted doctor) and is presented to the patient as a valid offer; the failure only surfaces when the patient presses "accept", producing a confusing dead end after the clinic's promise. |
| **Root Cause** | Validation was placed at the consumption point (accept) instead of at the write point (offer), where the data is authored. |
| **Evidence** | `offer()` builds `$attributes` including `'offer_doctor_id' => $offer['offer_doctor_id'] ?? null` with no lookup; `accept()` performs `Doctor::where('id', ...)->where('clinic_id', $locked->clinic_id)->where('is_active', true)->first()` and throws if missing. |
| **Execution Flow** | Admin selects a doctor from a stale list (or another clinic's doctor id) → offer saved and pushed → patient accepts → `RuntimeException('Dokter pada penawaran tidak lagi tersedia di klinik ini.')` → 422. Offer is now unusable and must be re-issued. |
| **Impact** | Broken patient-facing promise, extra round trip, support load; makes the "offer" object temporarily non-authoritative. |
| **Recommended Fix** | Validate in `offer()`: doctor exists, `is_active`, `clinic_id == request.clinic_id`; service (if any) belongs to the same clinic; schedule fields present and in the future; then keep the accept-time check as a cheap re-verification. |
| **Dependencies** | `Doctor` model, admin offer form (`Rule::exists` already used for clinic), `accept()` (keep as defence in depth), FCM templates. |
| **Testing Scenario** | Attempt to save an offer with a doctor from another clinic → assert 422 at offer time, no notification sent. Attempt with an inactive doctor → same. Assert a valid offer still converts on accept. |

---

### PB-I-01 — No events/listeners/notifications: all side effects are inline and mostly post-commit

| Field | Value |
|---|---|
| **ID** | PB-I-01 |
| **Severity** | INFO |
| **Category** | Architecture |
| **Status** | Recommendation |
| **Affected Module/Files/Methods** | absence of `app/Events`, `app/Listeners`, `app/Notifications`, observers; inline calls to `FCMService`, `AuditLog::log`, `Cache::forget`, `PickupPassService`, `QueueService` from controllers/services |
| **Problem** | Every side effect is a direct call in the request path. Calling `FCMService` after commit is mostly correct (the push happens outside the transaction), but failures are swallowed without retry, and side effects cannot be replayed or observed centrally. |
| **Root Cause** | Deliberate minimalism (one queue job only, no event infrastructure). |
| **Evidence** | No `app/Events`/`app/Listeners`/`app/Notifications` directories; `app/Jobs` contains only `SendFcmNotification.php`; FCM/audit calls appear inline in controllers (`Admin/BookingController`, `Api/BookingController`, `AuthController`, `Admin/PaymentController`, …). |
| **Execution Flow** | Booking confirmed → `$this->fcmService->sendBookingStatusUpdate(...)` inline → if FCM fails the response still succeeds and no retry exists. |
| **Impact** | Silent delivery gaps, no replay mechanism, side effects untestable in isolation. |
| **Recommended Fix** | Emit domain events after commit (`BookingConfirmed`, `PaymentSettled`, `RecordCreated`) and dispatch listeners to the queue for FCM/audit/notifications with retries + `failed_jobs` visibility. |
| **Dependencies** | PB-L-06 (queue/worker), `FCMService`, `AuditLog`. |
| **Testing Scenario** | Fire a booking confirmation with FCM disabled → assert the event is recorded and a retryable job exists; assert the booking response is unaffected. |

---

### PB-I-02 — Payment configuration is split between `config/services.php` and DB credential versions (no `config/payment.php`)

| Field | Value |
|---|---|
| **ID** | PB-I-02 |
| **Severity** | INFO |
| **Category** | Configuration architecture |
| **Status** | Recommendation |
| **Affected Module/Files/Methods** | `config/` (no `payment.php`); `config/services.php` (legacy `midtrans.central.*`, `midtrans.server_key`, environment); `clinic_merchant_configs`, `clinic_payment_accounts`, `payment_credential_versions`, `App\Services\PaymentAccountResolver`, `MidtransAccountGateway`, `MidtransService` |
| **Problem** | There are effectively three payment configuration sources: legacy env/`services.php` keys, `clinic_merchant_configs` (tenant), and versioned credentials. `HealthController` and `MidtransService` still read the central env keys, so health and legacy fallbacks do not reflect the actual per-clinic routing that production now uses. |
| **Root Cause** | Multi-clinic routing was layered on top of an original single-account integration without retiring the central path. |
| **Evidence** | `HealthController::index` reads `config('services.midtrans.server_key')`; `MidtransWebhookController::verifySignature` has an explicit legacy branch "Transaksi lama bisa dibuat dengan key env sebelum blok central ada"; the tenant tables carry the current configuration. |
| **Execution Flow** | Health reports "midtrans ok/missing" based on the env key while every real transaction uses the clinic's account → false healthy/false alarm. |
| **Impact** | Misleading readiness signal; legacy code paths remain reachable and must be maintained indefinitely. |
| **Recommended Fix** | Introduce `config/payment.php` for non-secret defaults, make readiness checks resolve per-clinic routing (`PaymentAccountResolver` + `MerchantConfigurationService`), and time-box the legacy central-key fallback with a removal date. |
| **Dependencies** | PB-H-09, `PaymentAccountResolver`, `MerchantConfigurationService`, config cache, `.env` documentation. |
| **Testing Scenario** | Configure a clinic with no merchant account and one with a valid account → assert readiness reflects per-clinic state and that legacy keys alone no longer mark the system healthy. |

---

### PB-I-03 — No `config/cors.php`, so the framework default (`allowed_origins: ['*']`) applies

| Field | Value |
|---|---|
| **ID** | PB-I-03 |
| **Severity** | INFO |
| **Category** | Configuration |
| **Status** | Recommendation |
| **Affected Module/Files/Methods** | `config/` (no `cors.php`); `bootstrap/app.php` (no CORS customisation); `routes/api.php` |
| **Problem** | Any browser origin may call the API. The Android client does not need CORS, and the web portals are same-origin, so a wildcard policy is broader than necessary. |
| **Root Cause** | Laravel's default CORS config was never published/tightened. |
| **Evidence** | `ls config/` → `app, auth, cache, database, filesystems, firebase, logging, mail, medicines, petshop, queue, sanctum, services, session`; no `cors.php`; `supports_credentials` remains false, which limits the practical impact (no cookie-authenticated cross-origin calls). |
| **Execution Flow** | A malicious page can read API responses that are token-gated only if the token is already in that page's context; bearer-token APIs are largely unaffected, but a wildcard remains a needless exposure and a compliance-review finding. |
| **Impact** | Low direct risk today; prevents tightening to an allowlist before any future cookie/CSRF-based flow is added. |
| **Recommended Fix** | Publish `config/cors.php`, restrict `paths` to `api/*` and `sanctum/csrf-cookie` if unused, set `allowed_origins` to the known admin/doctor hosts, keep `supports_credentials` false. |
| **Dependencies** | Deployment hostnames, any future web client. |
| **Testing Scenario** | Send a preflight from an unknown origin → assert no `Access-Control-Allow-Origin: *`; from the allowlisted origin → assert success; assert the Android app is unaffected. |

---

### PB-I-04 — `register-direct` returning 410 is intentional and correctly documented

| Field | Value |
|---|---|
| **ID** | PB-I-04 |
| **Severity** | INFO |
| **Category** | Contract note (false-positive guard) |
| **Status** | Confirmed (by design) |
| **Affected Module/Files/Methods** | `routes/api.php` (`POST /auth/register-direct`, `throttle:auth`); `Api/AuthController::registerDirect`; `docs/API_CONTRACT.md`; `HealthController` required-routes list (PB-L-03) |
| **Problem** | None functionally. Recorded so that a future auditor does not report the 410 as a broken endpoint, and so the only real defect in its vicinity (the health-check dependency) is attributed to PB-L-03. |
| **Root Cause** | Legacy client compatibility: the endpoint must keep answering so old APKs get a machine-readable `code: use_otp_flow` instead of a 404. |
| **Evidence** | Controller returns HTTP 410 with `{success:false, code:'use_otp_flow'}`; the Android client no longer calls it (`ApiService.kt` has no `register-direct`); the contract doc states the deprecation. |
| **Execution Flow** | Old APK → 410 + code → client redirects to the OTP flow. |
| **Impact** | Intentional; only cost is a permanent legacy route. |
| **Recommended Fix** | Keep, but add a sunset note (date + version) in `docs/API_CONTRACT.md`, remove it from `HealthController` (PB-L-03), and eventually delete the route with a coordinated app release. |
| **Dependencies** | PB-L-03, Android release policy, API contract doc. |
| **Testing Scenario** | Call the endpoint → assert 410 + `code: use_otp_flow`; assert no account is created; assert the OTP flow endpoints still work. |

---
## 23. Remediation Roadmap

Ordered by *risk removed per unit of effort*. No item in this section has been implemented — this audit made no source changes.

### Wave 0 — before any public deployment (blocking)

| # | Action | Findings | Effort | Why blocking |
|---|---|---|---|---|
| 0.1 | `APP_DEBUG=false`, `APP_ENV=production`, `LOG_LEVEL=warning`, rotating log channel | PB-H-10, PB-M-06 | XS | Removes the amplifier on every other error path |
| 0.2 | Replace `trustProxies(at:'*')` with the real proxy CIDRs; re-verify HTTPS detection | PB-H-08 | S | Restores all IP-based abuse protection |
| 0.3 | Remove controller-level `catch (\Throwable)` echoes; depend on the global sanitiser | PB-H-10 | S | Payment endpoints currently leak SQL/state |
| 0.4 | Reduce `/api/health` to a generic public body (or gate it behind `super_admin`) | PB-H-09, PB-L-03 | S | Unauthenticated recon |
| 0.5 | Add `payments` uniqueness for one open intent + cap `paid_amount` at `total_amount` | PB-H-03 | M | Double charging |
| 0.6 | Re-read `payment_status` under lock before expiring a Pet Shop order; add a `paid_after_expiry` refund obligation | PB-H-02 | M | Charged customer, no goods |

### Wave 1 — data safety (highest business value)

| # | Action | Findings | Effort |
|---|---|---|---|
| 1.1 | Soft-delete + history guard for `Pet`; `cascadeOnDelete` → `restrictOnDelete` on clinical FKs | PB-C-01 | L (migration + query sweep) |
| 1.2 | Rework account deletion into anonymise-and-retain (never delete payments/events/refunds) | PB-C-03 | L |
| 1.3 | Recompute medical-record payable state inside every payment-apply path (or derive it on read) + reconciliation command | PB-C-02 | M |
| 1.4 | Guard `MedicalRecordController::destroy` (payments, audit, transaction, file unlink) | PB-H-07, PB-L-01 | S |
| 1.5 | Booking cancel with money → explicit refund-pending state + patient notification + refunds-due queue | PB-H-01 | M |
| 1.6 | Unify refund ledgers; expose refunded amount on booking payload/receipt | PB-M-02, PB-M-01 | M |

### Wave 2 — domain correctness

| # | Action | Findings | Effort |
|---|---|---|---|
| 2.1 | Single `BookingCreationService` (clinic active, doctor/service pairing, `validateSlot`, slot uniqueness) used by API, ServiceRequest accept, admin | PB-H-04, PB-H-05, PB-H-06 | L |
| 2.2 | SQL uniqueness for booking slots (generated-column unique or slot-lock table) + index on `(clinic_id, doctor_id, booking_date, booking_time)` | PB-H-04 | M |
| 2.3 | Route every Pet Shop status change through `ProductOrderService::transition` | PB-M-09 | S |
| 2.4 | Lock `ServiceRequestService::advance/offer` + validate `offer_doctor_id` at write time | PB-M-07, PB-L-07 | S |
| 2.5 | Dead-letter table + alerting for unmatched/mismatched webhooks | PB-M-03 | M |
| 2.6 | Make `payments` the single money ledger (manual confirm writes a `payments` row; `bookings.paid_amount` derived) | PB-M-01 | L |

### Wave 3 — hardening & maintainability

| # | Action | Findings | Effort |
|---|---|---|---|
| 3.1 | Policies + API Resources so tenant scope and clinical masking are not caller-dependent | PB-M-08 | L |
| 3.2 | Fix Android payment amount/order-id fallbacks (server-derived intent) | PB-M-04 | S |
| 3.3 | Batch queue-position computation for booking lists | PB-M-05 | S |
| 3.4 | Fix `payment_stats` cache key (include filter signature) | PB-M-06 | XS |
| 3.5 | Extract `Money`/billing duplication between portals | PB-L-04 | S |
| 3.6 | Derive `pet.age` from `date_of_birth`; retire the editable age | PB-L-05 | S |
| 3.7 | Remove or cover the unused vaccination update endpoint | PB-L-02 | XS |
| 3.8 | Domain events + supervised queue worker for FCM/audit | PB-I-01, PB-L-06 | M |
| 3.9 | `config/payment.php` + per-clinic readiness checks; sunset legacy central keys | PB-I-02 | M |
| 3.10 | Publish and tighten `config/cors.php` | PB-I-03 | XS |

### Suggested regression suite (minimum to keep this audit honest)

1. **Retention**: pet/account/record deletion never destroys bookings, records, payments or events.
2. **Money invariants**: for every booking, `paid_amount ≤ total_amount`, `paid_amount − refunded == Σ settled payments`, and `extra_payment_amount == max(0, total_medical_cost − booking.paid_amount − extra_payment_paid_amount)`.
3. **Concurrency**: parallel slot booking → exactly one success; parallel snap-token → exactly one open intent; webhook-vs-scheduler → never `expired + paid`.
4. **Tenancy**: for every route with an `{id}`, patient B requesting patient A's id returns 404/403 for the full route matrix.
5. **Masking**: every route returning a medical record masks diagnosis/SOAP while unpaid.
6. **Webhook**: invalid signature → 403 and zero state change; replay → idempotent no-op; unknown order → dead letter.

---

## 24. Final Audit Coverage & Limitations

### 24.1 Coverage checklist

| Area | Coverage | Confidence | Notes |
|---|---|---|---|
| Route inventory (`api.php`, `web.php`, `console.php`) | 100% read | High | 3 files, fully enumerated |
| Middleware (`ApiAuthenticate`, `AdminAuth`, `DoctorAuth`, `EnsureSuperAdmin`, `SetLocale`, asset middleware) | ~95% | High | `SetLocale`/asset middleware skimmed (presentation-only) |
| Rate limiters | 100% | High | All `RateLimiter::for` definitions reviewed |
| API controllers (22) | ~90% | High (targeted: auth, booking, medical record, payment, webhook, pet shop, service request, doctor, pet, dashboard, notification, device token, health, weight/vaccination) | Medium (public clinic/service catalog read at signature level only) |
| Admin controllers (24) | ~75% | Medium-High | Read: User, Booking, MedicalRecord, Payment, Doctor, AdminAuth (join requests/switch), PaymentSettings (grep), PetShop* (grep/inventory). Not line-by-line: Medicine*, PetShopSales/Dashboard/Pickup, NotificationSettings, Queue, Service*, ServiceRequest, DoctorSchedule |
| Doctor controllers (9) | ~85% | High | Full scoping trace; some method bodies read by pattern |
| Services (25) | ~65% | Medium-High | Deep: PaymentService (core paths), PaymentStatusService, PaymentReconciliationService, ProductOrderService, ProductStockService, ServiceRequestService, QueueService, PrescriptionService, FCMService, RefundService, ReceiptService, OtpService, PatientScope. Signature/inventory only: Midtrans*Gateway, PaymentSplitCalculator, PaymentConfigurationService, MerchantConfigurationService, ClinicDeletionService, ClinicDiscoveryService, ClinicRatingService, ImageService, MedicalRecordAttachmentService, PickupPassService, PasswordOtpFlow, FirebaseService, MedicineStock/MutationService, MeasurementSupport |
| Models (44) | ~55% | Medium-High | Deep: Booking, User, Clinic, MedicalRecord, Doctor, Service, Payment, Pet (via controller), ProductOrder/Variant, Notification, DeviceToken. Inventory only: the remaining ~25 (casts/fillable scanned via grep for `$guarded`/`fillable`/`SoftDeletes`) |
| Migrations (83) | ~60% | Medium-High | Read in full: users/pets/doctors/bookings/medical_records, payment tables, payment_events, pet shop catalog/order, service requests, queue fields, clinic tables. Index/FK extracted by grep for the rest |
| Android contract | 100% of `ApiService.kt` (74 methods) | High | Plus `PaymentScreen`, `PaymentViewModel`, `PaymentRepository`, `MidtransModels`, `PetRepository` call sites |
| Scheduler semantics | 100% of `routes/console.php` + all 7 commands' entry points | High | Command bodies read for Reconcile/Expire; others at signature level |
| Blade views | ~10% | Low | Only payment/invoice/receipt views referenced by money logic; UI/UX out of scope |
| Config files | 100% of `config/` filenames + `app.php`, `logging.php`, `sanctum.php`, `services.php` (relevant keys) | Medium-High | `database.php`, `filesystems.php`, `cache.php`, `session.php` values not opened (env-dependent) |
| Tests | 0% | — | Not executed, not reviewed (no test suite was part of the traced paths) |
| Runtime behaviour | 0% | — | No app boot, no HTTP, no DB, no queue worker |

### 24.2 What is explicitly NOT claimed

- **This is not a 100% backend audit.** Substantial parts of the admin controller set (medicine/batch/stock, pet-shop sales/dashboard/pickup, queue, service catalog, notification settings), ~35% of the service bodies, and ~45% of model files were reviewed at inventory level, not line-by-line. Findings in those areas would have to come from a follow-up pass.
- **No finding was reproduced at runtime.** Every `Confirmed` label means "the code path provably produces this effect when reached", not "observed in a running system". Concurrency findings are labelled `Potential` precisely because the trigger is external.
- **Schema was inferred from migrations, not from the live MySQL database.** If production drifted (manual columns, missing indexes, extra triggers), this audit cannot see it. Verifying `SHOW CREATE TABLE` for `bookings`, `payments`, `payment_events`, `bookings_queue_unique` and the FK `ON DELETE` actions on the live server is the single highest-value follow-up.
- **No dependency/CVE scan** (`composer audit`, `npm audit`, Gradle dependency check) was performed.
- **No infrastructure/deployment review** (web server config, PHP-FPM settings, MySQL isolation level, backups, TLS termination) — several findings (PB-H-08, PB-L-06, PB-I-03) depend on deployment facts that are not in the repository.
- **No legal/compliance determination.** Retention statements (PB-C-01/PB-C-03) are framed as data-integrity risks, not as a verdict on any specific regulation.
- **Android app internals** (navigation, offline behaviour, WebView hardening) were only inspected where they consume backend responses.

### 24.3 Most valuable follow-ups (ranked)

1. Reflect the live MySQL schema (`SHOW CREATE TABLE` + `information_schema`) and diff it against `database/migrations/` — confirms or refutes PB-H-04's index/FK half and the cascade assumptions behind PB-C-01/C-03.
2. Read the remaining Admin controllers line-by-line (medicine stock/batch, pet-shop sales/pickup, queue) — the only area where a *financial* or *cross-tenant* defect could still be hiding.
3. Execute the §23 regression suite in a staging environment with a seeded dataset — turns 4 `Potential` findings into Confirmed or refuted.
4. Review the deployment topology (proxy, worker supervision, backup/retention policy) — required to close PB-H-08, PB-L-06, PB-I-03.
5. Run `composer audit` + a static analyser (Larastan) — cheap, catches the class of drift this audit found by hand.

### 24.4 Bottom line

The backend is **substantially better engineered than its surrounding documentation suggests**: locking is deliberate, the payment state machine is monotonic and replay-safe, tenant isolation is fail-closed, and the webhook is genuinely signature-enforced. The failures that matter are not "sloppy code" failures — they are **policy failures**: the system has no single owner for *"who may destroy history"* and no single owner for *"what is the balance"*. Until those two are unified (Wave 1 of §23), every additional feature increases the amount of history and money that can silently diverge.

**Audit integrity statement:** while *producing* this report (sections 1–24) no file, migration, config, view or database row in `PetHeal_Backend/` or `PetHeal_Android/` was created, modified or deleted. The report itself is the only artefact that was written. Remediation applied *afterwards* is listed in §25 and documented in `PetHeal_Backend/docs/DEEP_AUDIT_REMEDIATION.md`.

---

## 25. Remediation Status (post-audit)

This section was added after the fixes were implemented, so the report and the codebase do not contradict each other. All changes are application-level only; **no migration was added or applied**.

| Finding | Status | Where |
|---|---|---|
| PB-C-01 | Fixed (delete blocked when pet has history → 409 `pet_has_history`) | `Api/PetController::destroy` |
| PB-C-02 | Fixed (recalculation centralised, called from every settlement path) | `MedicalRecord::syncExtraPaymentForBooking`, `PaymentStatusService`, `Admin/PaymentController::confirm` |
| PB-C-03 | Fixed (anonymise + retain history; empty accounts still hard-deleted) | `Api/AuthController::deleteAccount` |
| PB-H-01 | Partially fixed (audit log `booking.refund_due`, FCM, `refund_due` in API; enum state still deferred) | Admin + Api `BookingController::cancel` |
| PB-H-02 | Fixed (expiry refused for paid orders, late settlement → `refund_pending`) | `ProductOrderService`, `PaymentService::applyToPayable` |
| PB-H-03 | Fixed (`Cache::lock` around intent creation; `paid_amount` capped with overpayment log) | `PaymentService`, `PaymentStatusService` |
| PB-H-04 | **Deferred** (needs a DB migration; see remediation doc §4) | — |
| PB-H-05 | Fixed (slot re-validated, clinic active checked) | `ServiceRequestService::accept` |
| PB-H-06 | Fixed (`completed` bookings payable while a balance remains) | `PaymentService::createBookingPayment` |
| PB-H-07 | Fixed (money guard, attachments deleted, audit log, transaction) | `Admin/MedicalRecordController::destroy` |
| PB-H-08 | Fixed (trusted proxies limited to loopback unless `TRUSTED_PROXIES` is set) | `bootstrap/app.php` |
| PB-H-09 | Fixed (public health response carries status only; details logged) | `Api/HealthController` |
| PB-H-10 | Fixed (7 catch blocks now return `ERR-XXXXXXXX` references) | `Api/PaymentController` |
| PB-M-02 | Partially fixed (patient notified on refund; ledger unification deferred) | `Admin/PaymentController::refund` |
| PB-M-03 | **Deferred** (needs a dead-letter table) | — |
| PB-M-04 | Partially fixed (backend accepts 2-segment `BOOKING-{id}`; Android fix still recommended) | `Api/PaymentController::parsePaymentOrderId` |
| PB-M-05 | Fixed (one query per clinic+date instead of per row) | `QueueService::positionsFor`, `Api/BookingController` |
| PB-M-06 | **Corrected — cache half was not a defect** (identity is already bypassed for filtered requests); logging rotation deferred | — |
| PB-M-07 | Fixed (row lock in `advance`/`offer`, notification after commit) | `ServiceRequestService` |
| PB-L-01 | Fixed (attachments removed with the record) | `Admin/MedicalRecordController::destroy` |
| PB-L-03 | Fixed (`register_direct` dropped from required health routes) | `Api/HealthController` |
| PB-M-10 | **Withdrawn.** The `photo` free-string branch is unreachable: the `image` validation rule rejects non-file values before it | — |

Verification after remediation: `php artisan test` → 521 passed / 3131 assertions / 0 failed; `php -l` clean on all 17 changed files; HTTP smoke tests 200 on `/api/health`, `/api/pets`, `/api/bookings`, `/api/bookings/upcoming`, `/api/dashboard` and the admin/doctor pages; the pet-deletion guard and the payment lock were exercised against the live development database.
