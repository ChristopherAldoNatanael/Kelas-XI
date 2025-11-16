@extends('layouts.app')

@section('title', 'Class Details')

@section('content')
<div class="container-fluid px-4 py-8">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Class Details: {{ $class->nama_kelas }}</h6>
                    <div>
                        <a href="{{ route('web-classes.edit', $class->id) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit mr-2"></i>Edit Class
                        </a>
                        <a href="{{ route('web-classes.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Classes
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Class ID:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $class->id }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Class Name:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $class->nama_kelas }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Grade Level:</strong>
                                </div>
                                <div class="col-sm-9">
                                    Grade {{ $class->tingkat_kelas }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Academic Year:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $class->tahun_ajaran }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Description:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $class->deskripsi ?: 'No description provided' }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Status:</strong>
                                </div>
                                <div class="col-sm-9">
                                    @if($class->trashed())
                                        <span class="badge bg-warning">Deleted</span>
                                    @else
                                        <span class="badge bg-{{ $class->status == 'active' ? 'success' : 'secondary' }}">{{ ucfirst($class->status) }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Created At:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $class->created_at->format('M d, Y H:i') }}
                                </div>
                            </div>

                            @if($class->updated_at != $class->created_at)
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Last Updated:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        {{ $class->updated_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            @endif

                            @if($class->trashed())
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Deleted At:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        {{ $class->deleted_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <div class="card border-left-primary">
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-school fa-4x text-primary mb-3"></i>
                                        <h5>{{ $class->nama_kelas }}</h5>
                                        <p class="text-muted">Grade {{ $class->tingkat_kelas }}</p>
                                        <p class="text-muted">{{ $class->tahun_ajaran }}</p>
                                    </div>
                                </div>
                            </div>

                            @if(!$class->trashed())
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('web-classes.destroy', $class->id) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to delete this class?')">
                                            <i class="fas fa-trash mr-2"></i>Delete Class
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('web-classes.restore', $class->id) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-undo mr-2"></i>Restore Class
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('web-classes.force-delete', $class->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to permanently delete this class? This action cannot be undone.')">
                                            <i class="fas fa-times mr-2"></i>Force Delete
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
