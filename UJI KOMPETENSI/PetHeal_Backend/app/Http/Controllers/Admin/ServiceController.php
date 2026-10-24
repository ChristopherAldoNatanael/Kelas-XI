<?php
namespace App\Http\Controllers\Admin;

use App\Exports\ServicesImportErrorReportExport;
use App\Exports\ServicesTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Service;
use App\Imports\ServicesImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class ServiceController extends Controller
{
    // PHASE 3 (B12): bump on every write so the 6h API cache invalidates.
    private function refreshServiceApiCache(): void
    {
        \Illuminate\Support\Facades\Cache::forever('services_version', now()->timestamp);
    }

    public function index()
    {
        $clinicId = currentClinicId();
        $services = Service::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('name')
            ->paginate(20);
        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'nullable|integer|min:1',
            'category' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = $request->only([
            'name', 'description', 'price', 'duration', 'category', 'is_active',
        ]);

        // PHASE 3 (F-06 orphan guard): refuse clinic-less rows in overview mode.
        $contextClinicId = currentClinicId();
        if (!$contextClinicId) {
            return redirect()->route('admin.services.index')
                ->with('error', __('admin.select_clinic_service'));
        }
        $data['clinic_id'] = $contextClinicId;

        $service = Service::create($data);
        $this->refreshServiceApiCache();

        AuditLog::log('service.create', "Created service {$service->name}", $service);

        return redirect()->route('admin.services.index')->with('success', __('admin.service_created'));
    }

    public function edit($id)
    {
        $clinicId = currentClinicId();
        $query = Service::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $service = $query->firstOrFail();
        return view('admin.services.edit', compact('service'));
    }

    public function update(Request $request, $id)
    {
        $clinicId = currentClinicId();
        $query = Service::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $service = $query->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'nullable|integer|min:1',
            'category' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $service->update($request->only([
            'name', 'description', 'price', 'duration', 'category', 'is_active',
        ]));
        $this->refreshServiceApiCache();

        AuditLog::log('service.update', "Updated service {$service->name}", $service);

        return redirect()->route('admin.services.index')->with('success', __('admin.service_updated'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
            'duplicate_strategy' => 'nullable|in:update,skip',
        ]);

        // PHASE 1 (C5): import runs inside the caller's clinic. Tenant users
        // always have clinic_id here (AdminAuth blocks clinic-less tenants);
        // super_admin in overview mode has no target clinic => refuse instead
        // of writing clinic-less rows into the shared table.
        $clinicId = currentClinicId();
        if (!$clinicId && !isSuperAdmin()) {
            return redirect()
                ->route('admin.services.index')
                ->with('error', __('admin.service_no_clinic'));
        }
        if (!$clinicId) {
            return redirect()
                ->route('admin.services.index')
                ->with('error', __('admin.select_clinic_import'));
        }

        $import = new ServicesImport($request->input('duplicate_strategy', 'update'), $clinicId);
        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        $readerType = match ($extension) {
            'csv', 'txt' => ExcelWriter::CSV,
            'xlsx' => ExcelWriter::XLSX,
            'xls' => ExcelWriter::XLS,
            default => null,
        };

        try {
            if ($readerType === null) {
                throw new \InvalidArgumentException('Unsupported service import file type.');
            }

            Excel::import($import, $file, null, $readerType);
        } catch (\Throwable $e) {
            Log::warning('Service import failed', [
                'file_name' => $file?->getClientOriginalName(),
                'extension' => $extension,
                'reader_type' => $readerType,
                'duplicate_strategy' => $request->input('duplicate_strategy', 'update'),
                'message' => $e->getMessage(),
            ]);

            $message = match ($extension) {
                'xlsx', 'xls' => __('admin.service_import_failed_xlsx'),
                'csv', 'txt' => __('admin.service_import_failed_csv'),
                default => __('admin.service_import_failed_generic'),
            };

            return redirect()
                ->route('admin.services.index')
                ->with('error', $message);
        }

        $summary = $import->summary;
        $errorRows = $import->errorRows;

        session()->put('services_import_error_report', $errorRows);
        if (empty($errorRows)) {
            session()->forget('services_import_error_report');
        }

        AuditLog::log('service.import', "Imported services: {$summary['success']} success, {$summary['failed']} failed");
        $this->refreshServiceApiCache();

        return redirect()
            ->route('admin.services.index')
            ->with('success', __('admin.service_import_done'))
            ->with('import_summary', $summary)
            ->with('import_has_errors', !empty($errorRows));
    }

    public function downloadTemplateCsv()
    {
        return Excel::download(new ServicesTemplateExport(), 'services-template.csv', ExcelWriter::CSV);
    }

    public function downloadTemplateXlsx()
    {
        return Excel::download(new ServicesTemplateExport(), 'services-template.xlsx');
    }

    public function downloadImportErrorReport(Request $request)
    {
        $rows = session('services_import_error_report', []);
        if (empty($rows)) {
            return redirect()
                ->route('admin.services.index')
                ->with('error', __('admin.service_import_no_report'));
        }

        $format = strtolower((string) $request->query('format', 'csv'));
        $filename = 'services-import-errors-' . now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return Excel::download(new ServicesImportErrorReportExport($rows), $filename . '.xlsx');
        }

        return Excel::download(new ServicesImportErrorReportExport($rows), $filename . '.csv', ExcelWriter::CSV);
    }

    public function destroy($id)
    {
        $clinicId = currentClinicId();
        $query = Service::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $service = $query->firstOrFail();

        AuditLog::log('service.delete', "Deleted service {$service->name}", $service);

        $service->delete();
        $this->refreshServiceApiCache();

        return redirect()->route('admin.services.index')->with('success', __('admin.service_deleted'));
    }
}
