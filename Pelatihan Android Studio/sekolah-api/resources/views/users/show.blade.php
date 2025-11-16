@extends('layouts.app')

@section('title', 'User Details')

@section('content')
<div class="container-fluid px-4 py-8">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">User Details: {{ $user->nama }}</h6>
                    <div>
                        <a href="{{ route('web-users.edit', $user->id) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit mr-2"></i>Edit User
                        </a>
                        <a href="{{ route('web-users.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Users
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>User ID:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $user->id }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Full Name:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $user->nama }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Email:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $user->email }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Role:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <span class="badge bg-{{ $user->role == 'admin' ? 'danger' : ($user->role == 'teacher' ? 'info' : 'secondary') }}">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Status:</strong>
                                </div>
                                <div class="col-sm-9">
                                    @if($user->trashed())
                                        <span class="badge bg-warning">Deleted</span>
                                    @else
                                        <span class="badge bg-success">Active</span>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>Created At:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $user->created_at->format('M d, Y H:i') }}
                                </div>
                            </div>

                            @if($user->updated_at != $user->created_at)
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Last Updated:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        {{ $user->updated_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            @endif

                            @if($user->trashed())
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Deleted At:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        {{ $user->deleted_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <div class="card border-left-primary">
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-user-circle fa-4x text-primary mb-3"></i>
                                        <h5>{{ $user->nama }}</h5>
                                        <p class="text-muted">{{ ucfirst($user->role) }}</p>
                                    </div>
                                </div>
                            </div>

                            @if(!$user->trashed())
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('web-users.destroy', $user->id) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="fas fa-trash mr-2"></i>Delete User
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('web-users.restore', $user->id) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-undo mr-2"></i>Restore User
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('web-users.force-delete', $user->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to permanently delete this user? This action cannot be undone.')">
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
