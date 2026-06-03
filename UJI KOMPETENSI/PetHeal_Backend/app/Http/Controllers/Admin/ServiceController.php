<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Service;
use Illuminate\Http\Request;

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
            'category' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $service = Service::create($request->all());

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
            'category' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $service->update($request->all());

        AuditLog::log('service.update', "Updated service {$service->name}", $service);

        return redirect()->route('admin.services.index')->with('success', 'Service updated successfully');
    }

    public function destroy($id)
    {
        $service = Service::findOrFail($id);

        AuditLog::log('service.delete', "Deleted service {$service->name}", $service);

        $service->delete();

        return redirect()->route('admin.services.index')->with('success', 'Service deleted successfully');
    }
}
