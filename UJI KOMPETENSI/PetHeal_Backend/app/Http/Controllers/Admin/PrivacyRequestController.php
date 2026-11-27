<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Services\PrivacyRequestService;
use App\Services\PrivacyResponseService;
use Illuminate\Http\Request;

/**
 * Dashboard admin untuk permintaan PDP (UU 27/2022).
 *
 * Admin hanya melihat permintaan kliniknya (atau permintaan tanpa klinik).
 * Aksi status tetap melalui PrivacyRequestService agar audit dan aturan
 * transisi tidak diduplikasi di controller.
 */
class PrivacyRequestController extends Controller
{
    public function __construct(
        private PrivacyRequestService $requests,
        private PrivacyResponseService $responses
    ) {
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:received,verified,in_progress,completed,rejected',
            'type' => 'nullable|in:access,correction,erasure,restriction,portability,objection',
            'overdue' => 'nullable|in:1',
        ]);

        $clinicId = currentClinicId();

        $base = PrivacyRequest::query()->when($clinicId, function ($query) use ($clinicId) {
            $query->where(function ($inner) use ($clinicId) {
                $inner->where('clinic_id', $clinicId)->orWhereNull('clinic_id');
            });
        });

        $stats = [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->open()->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'completed' => (clone $base)->where('status', PrivacyRequest::STATUS_COMPLETED)->count(),
        ];

        $query = (clone $base)->with(['user', 'clinic', 'handledBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('request_type', $request->input('type'));
        }

        if ($request->input('overdue') === '1') {
            $query->overdue();
        }

        $privacyRequests = $query
            ->orderByRaw("FIELD(status, 'received', 'verified', 'in_progress', 'rejected', 'completed')")
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.privacy-requests.index', compact('privacyRequests', 'stats'));
    }

    public function show(int $id)
    {
        $privacyRequest = $this->findScoped($id);

        $erasureBlockReason = null;

        if ($privacyRequest->request_type === PrivacyRequest::TYPE_ERASURE && $privacyRequest->user) {
            $erasureBlockReason = $this->requests->erasureBlockReason($privacyRequest->user);
        }

        return view('admin.privacy-requests.show', compact('privacyRequest', 'erasureBlockReason'));
    }

    public function verify(int $id)
    {
        return $this->run($id, fn (PrivacyRequest $privacyRequest, $actor) => $this->requests->verify($privacyRequest, $actor), 'Permintaan berhasil diverifikasi.');
    }

    public function start(int $id)
    {
        return $this->run($id, fn (PrivacyRequest $privacyRequest, $actor) => $this->requests->start($privacyRequest, $actor), 'Permintaan mulai diproses.');
    }

    public function complete(Request $request, int $id)
    {
        $validated = $request->validate([
            'response_path' => 'nullable|string|max:255',
        ]);

        return $this->run(
            $id,
            fn (PrivacyRequest $privacyRequest, $actor) => $this->requests->complete($privacyRequest, $validated, $actor),
            'Permintaan ditandai selesai.'
        );
    }

    public function attachResponse(Request $request, int $id)
    {
        $validated = $request->validate([
            'response_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $privacyRequest = $this->findScoped($id);

        try {
            $this->responses->attach(
                $privacyRequest,
                $validated['response_file'],
                auth()->user()
            );
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.privacy-requests.show', $privacyRequest->id)
            ->with('success', 'Berkas jawaban berhasil diunggah dan disimpan terenkripsi.');
    }

    public function generateResponse(int $id)
    {
        $privacyRequest = $this->findScoped($id);

        try {
            $this->responses->generate($privacyRequest, auth()->user());
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.privacy-requests.show', $privacyRequest->id)
            ->with('success', 'Salinan data berhasil dibuat otomatis dari data pemohon.');
    }

    public function downloadResponse(int $id)
    {
        $privacyRequest = $this->findScoped($id);

        try {
            $bytes = $this->responses->download($privacyRequest, auth()->user());
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        $name = $privacyRequest->response_file_name ?: 'salinan-data-pdp-' . $privacyRequest->id . '.pdf';
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?: 'salinan-data.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $ascii . '"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:20000',
        ]);

        return $this->run(
            $id,
            fn (PrivacyRequest $privacyRequest, $actor) => $this->requests->reject(
                $privacyRequest,
                (string) $validated['rejection_reason'],
                $actor
            ),
            'Permintaan ditolak dengan alasan tercatat.'
        );
    }

    private function run(int $id, callable $action, string $success)
    {
        $privacyRequest = $this->findScoped($id);

        try {
            $action($privacyRequest, auth()->user());
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.privacy-requests.show', $privacyRequest->id)
            ->with('success', $success);
    }

    private function findScoped(int $id): PrivacyRequest
    {
        $clinicId = currentClinicId();

        return PrivacyRequest::with(['user', 'clinic', 'handledBy'])
            ->when($clinicId, function ($query) use ($clinicId) {
                $query->where(function ($inner) use ($clinicId) {
                    $inner->where('clinic_id', $clinicId)->orWhereNull('clinic_id');
                });
            })
            ->findOrFail($id);
    }
}
