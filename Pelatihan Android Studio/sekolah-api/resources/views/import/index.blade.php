@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Import Data (Excel/CSV)</h1>
        <div>
            <a href="{{ route('import.template', ['type' => 'users']) }}" class="btn btn-sm btn-outline-primary">Template Users</a>
            <a href="{{ route('import.template', ['type' => 'teachers']) }}" class="btn btn-sm btn-outline-primary">Template Teachers</a>
            <a href="{{ route('import.template', ['type' => 'subjects']) }}" class="btn btn-sm btn-outline-primary">Template Subjects</a>
            <a href="{{ route('import.template', ['type' => 'classes']) }}" class="btn btn-sm btn-outline-primary">Template Classes</a>
            <a href="{{ route('import.template', ['type' => 'classrooms']) }}" class="btn btn-sm btn-outline-primary">Template Classrooms</a>
            <a href="{{ route('import.template', ['type' => 'schedules']) }}" class="btn btn-sm btn-outline-primary">Template Schedules</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @if(session('details'))
            <div class="card mb-3">
                <div class="card-header">Detail</div>
                <div class="card-body">
                    <ul class="small mb-0">
                        @foreach(session('details') as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('import.process') }}" enctype="multipart/form-data">
                @csrf
                @if(request('redirect_to'))
                    <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">
                @endif
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Jenis Data</label>
                        <select class="form-select" name="type" required>
                            @php($defaultType = old('type', request('type')))
                            <option value="users" @selected($defaultType==='users')>Users</option>
                            <option value="teachers" @selected($defaultType==='teachers')>Teachers</option>
                            <option value="subjects" @selected($defaultType==='subjects')>Subjects</option>
                            <option value="classes" @selected($defaultType==='classes')>Classes</option>
                            <option value="classrooms" @selected($defaultType==='classrooms')>Classrooms</option>
                            <option value="schedules" @selected($defaultType==='schedules')>Schedules</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">File (CSV/XLSX)</label>
                        <input class="form-control" type="file" name="file" accept=".csv,.xlsx,.txt" required />
                        <div class="form-text">Gunakan template agar kolom sesuai. Maks 2MB.</div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="bi bi-upload"></i> Import
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
