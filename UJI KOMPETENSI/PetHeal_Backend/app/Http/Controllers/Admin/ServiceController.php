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
    public function index()
    {
        $services = Service::orderBy('name')->paginate(20);
        return view('admin.services.index', compact('services'));
    }

    public function show($id)
    {
        return redirect()->route('admin.services.edit', $id);
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

        $service = Service::create($request->only([
            'name', 'description', 'price', 'duration', 'category', 'is_active',
        ]));

        AuditLog::log('service.create', "Created service {$service->name}", $service);

        return redirect()->route('admin.services.index')->with('success', 'Service created successfully');
    }

    public function edit($id)
    {
        $service = Service::findOrFail($id);
        return view('admin.services.edit', compact('service'));
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

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

        AuditLog::log('service.update', "Updated service {$service->name}", $service);

        return redirect()->route('admin.services.index')->with('success', 'Service updated successfully');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
            'duplicate_strategy' => 'nullable|in:update,skip',
        ]);

        $import = new ServicesImport($request->input('duplicate_strategy', 'update'));
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
                'xlsx', 'xls' => 'XLSX/XLS import failed. Make sure the file is a valid Excel workbook and try again.',
                'csv', 'txt' => 'CSV import failed. Make sure the file uses the required headers and try again.',
                default => 'Import failed. Please check the file format and try again.',
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

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Import selesai.')
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
                ->with('error', 'No import error report is available.');
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
        $service = Service::findOrFail($id);

        AuditLog::log('service.delete', "Deleted service {$service->name}", $service);

        $service->delete();

        return redirect()->route('admin.services.index')->with('success', 'Service deleted successfully');
    }
}
