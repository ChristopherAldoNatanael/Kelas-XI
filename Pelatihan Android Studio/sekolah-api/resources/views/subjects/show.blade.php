@extends('layouts.app')

@section('title', 'Subject Details')

@section('content')
<div class="container-fluid px-4 py-8">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Subject Details: {{ $subject->nama_mapel }}</h6>
                    <div>
                        <a href="{{ route('web-subjects.edit', $subject->id) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit mr-2"></i>Edit Subject
                        </a>
                        <a href="{{ route('web-subjects.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Subjects
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Subject ID:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $subject->id }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Subject Code:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $subject->kode_mapel }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Subject Name:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $subject->nama_mapel }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Description:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $subject->deskripsi ?: 'No description provided' }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Status:</strong>
                                </div>
                                <div class="col-sm-9">
                                    @if($subject->trashed())
                                        <span class="badge bg-warning">Deleted</span>
                                    @else
                                        <span class="badge bg-{{ $subject->status == 'active' ? 'success' : 'secondary' }}">{{ ucfirst($subject->status) }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Created At:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $subject->created_at->format('M d, Y H:i') }}
                                </div>
                            </div>

                            @if($subject->updated_at != $subject->created_at)
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Last Updated:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        {{ $subject->updated_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            @endif

                            @if($subject->trashed())
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Deleted At:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        {{ $subject->deleted_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <div class="card border-left-primary">
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-book fa-4x text-primary mb-3"></i>
                                        <h5>{{ $subject->nama_mapel }}</h5>
                                        <p class="text-muted">{{ $subject->kode_mapel }}</p>
                                        <p class="text-muted">{{ ucfirst($subject->status) }}</p>
                                    </div>
                                </div>
                            </div>

                            @if(!$subject->trashed())
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('web-subjects.destroy', $subject->id) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to delete this subject?')">
                                            <i class="fas fa-trash mr-2"></i>Delete Subject
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('web-subjects.restore', $subject->id) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-undo mr-2"></i>Restore Subject
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('web-subjects.force-delete', $subject->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to permanently delete this subject? This action cannot be undone.')">
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
