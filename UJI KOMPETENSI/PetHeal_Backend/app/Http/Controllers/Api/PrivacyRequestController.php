<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Services\PrivacyRequestService;
use App\Services\PrivacyResponseService;
use Illuminate\Http\Request;

class PrivacyRequestController extends Controller
{
    public function __construct(
        private readonly PrivacyRequestService $privacy,
        private readonly PrivacyResponseService $responses
    ) {
    }

    public function index(Request $request)
    {
        $requests = PrivacyRequest::where('user_id', $request->user()->id)
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => collect($requests->items())
                ->map(fn (PrivacyRequest $item) => $this->serialize($item))
                ->values(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_type' => ['required', 'string', 'max:30'],
            'description' => ['required', 'string', 'max:20000'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        try {
            $created = $this->privacy->create($request->user(), $data, $request->user());
        } catch (MedicalRecordLifecycleException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan data berhasil dicatat dan akan ditinjau admin.',
            'data' => $this->serialize($created),
        ], 201);
    }

    public function downloadResponse(Request $request, int $id)
    {
        $item = PrivacyRequest::where('user_id', $request->user()->id)->find($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan data tidak ditemukan.',
            ], 404);
        }

        try {
            $bytes = $this->responses->download($item, $request->user());
        } catch (MedicalRecordLifecycleException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        }

        $name = $item->response_file_name ?: 'salinan-data-pdp-' . $item->id . '.pdf';
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?: 'salinan-data.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $ascii . '"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function serialize(PrivacyRequest $item): array
    {
        return [
            'id' => $item->id,
            'request_type' => $item->request_type,
            'status' => $item->status,
            'description' => $item->description,
            'requested_at' => $item->requested_at?->toDateTimeString(),
            'due_at' => $item->due_at?->toDateTimeString(),
            'verified_at' => $item->verified_at?->toDateTimeString(),
            'completed_at' => $item->completed_at?->toDateTimeString(),
            'rejection_reason' => $item->rejection_reason,
            'response_available' => ! empty($item->response_file_path),
            'response_file_name' => $item->response_file_name,
            'response_file_size' => $item->response_file_size !== null ? (int) $item->response_file_size : null,
            'response_download_count' => (int) $item->response_download_count,
            'is_overdue' => $item->isOverdue(),
            'created_at' => $item->created_at?->toDateTimeString(),
        ];
    }
}
