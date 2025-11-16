@extends('layouts.app')

@section('title', 'Teacher Details')

@section('content')
<div class="container-fluid px-4 py-8">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Teacher Details: {{ $teacher->nama }}</h6>
                    <div>
                        <a href="{{ route('web-teachers.edit', $teacher->id) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit mr-2"></i>Edit Teacher
                        </a>
                        <a href="{{ route('web-teachers.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Teachers
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Teacher ID:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $teacher->id }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>NIP:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $teacher->nip }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Full Name:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $teacher->nama }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Gender:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $teacher->jenis_kelamin == 'L' ? 'Male' : 'Female' }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Address:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $teacher->alamat ?: 'Not provided' }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Phone:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $teacher->telepon ?: 'Not provided' }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Date of Birth:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $teacher->tanggal_lahir ? $teacher->tanggal_lahir->format('M d, Y') : 'Not provided' }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Status:</strong>
                                </div>
                                <div class="col-sm-9">
                                    @if($teacher->trashed())
                                        <span class="badge bg-warning">Deleted</span>
                                    @else
                                        <span class="badge bg-{{ $teacher->status == 'active' ? 'success' : 'secondary' }}">{{ ucfirst($teacher->status) }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>User Account:</strong>
                                </div>
                                <div class="col-sm-9">
                                    @if($teacher->user)
                                        {{ $teacher->user->nama }} ({{ $teacher->user->email }})
                                    @else
                                        <span class="text-muted">No associated user</span>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Created At:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $teacher->created_at->format('M d, Y H:i') }}
                                </div>
                            </div>

                            @if($teacher->updated_at != $teacher->created_at)
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Last Updated:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        {{ $teacher->updated_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            @endif

                            @if($teacher->trashed())
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Deleted At:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        {{ $teacher->deleted_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <div class="card border-left-primary">
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-chalkboard-teacher fa-4x text-primary mb-3"></i>
                                        <h5>{{ $teacher->nama }}</h5>
                                        <p class="text-muted">{{ $teacher->nip }}</p>
                                        <p class="text-muted">{{ $teacher->jenis_kelamin == 'L' ? 'Male' : 'Female' }}</p>
                                    </div>
                                </div>
                            </div>

                            @if(!$teacher->trashed())
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('web-teachers.destroy', $teacher->id) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to delete this teacher?')">
                                            <i class="fas fa-trash mr-2"></i>Delete Teacher
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('web-teachers.restore', $teacher->id) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-undo mr-2"></i>Restore Teacher
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('web-teachers.force-delete', $teacher->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to permanently delete this teacher? This action cannot be undone.')">
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
