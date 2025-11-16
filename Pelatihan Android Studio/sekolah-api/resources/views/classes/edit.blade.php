@extends('layouts.app')

@section('title', 'Edit Class')

@section('content')
<div class="container-fluid px-4 py-8">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Class: {{ $class->nama_kelas }}</h6>
                    <a href="{{ route('web-classes.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Classes
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('web-classes.update', $class->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama_kelas" class="form-label">Class Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('nama_kelas') is-invalid @enderror"
                                       id="nama_kelas" name="nama_kelas" value="{{ old('nama_kelas', $class->nama_kelas) }}" required>
                                @error('nama_kelas')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="tingkat_kelas" class="form-label">Grade Level <span class="text-danger">*</span></label>
                                <select class="form-select @error('tingkat_kelas') is-invalid @enderror" id="tingkat_kelas" name="tingkat_kelas" required>
                                    <option value="">Select Grade Level</option>
                                    <option value="1" {{ old('tingkat_kelas', $class->tingkat_kelas) == '1' ? 'selected' : '' }}>Grade 1</option>
                                    <option value="2" {{ old('tingkat_kelas', $class->tingkat_kelas) == '2' ? 'selected' : '' }}>Grade 2</option>
                                    <option value="3" {{ old('tingkat_kelas', $class->tingkat_kelas) == '3' ? 'selected' : '' }}>Grade 3</option>
                                    <option value="4" {{ old('tingkat_kelas', $class->tingkat_kelas) == '4' ? 'selected' : '' }}>Grade 4</option>
                                    <option value="5" {{ old('tingkat_kelas', $class->tingkat_kelas) == '5' ? 'selected' : '' }}>Grade 5</option>
                                    <option value="6" {{ old('tingkat_kelas', $class->tingkat_kelas) == '6' ? 'selected' : '' }}>Grade 6</option>
                                    <option value="7" {{ old('tingkat_kelas', $class->tingkat_kelas) == '7' ? 'selected' : '' }}>Grade 7</option>
                                    <option value="8" {{ old('tingkat_kelas', $class->tingkat_kelas) == '8' ? 'selected' : '' }}>Grade 8</option>
                                    <option value="9" {{ old('tingkat_kelas', $class->tingkat_kelas) == '9' ? 'selected' : '' }}>Grade 9</option>
                                    <option value="10" {{ old('tingkat_kelas', $class->tingkat_kelas) == '10' ? 'selected' : '' }}>Grade 10</option>
                                    <option value="11" {{ old('tingkat_kelas', $class->tingkat_kelas) == '11' ? 'selected' : '' }}>Grade 11</option>
                                    <option value="12" {{ old('tingkat_kelas', $class->tingkat_kelas) == '12' ? 'selected' : '' }}>Grade 12</option>
                                </select>
                                @error('tingkat_kelas')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tahun_ajaran" class="form-label">Academic Year <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('tahun_ajaran') is-invalid @enderror"
                                       id="tahun_ajaran" name="tahun_ajaran" value="{{ old('tahun_ajaran', $class->tahun_ajaran) }}" placeholder="e.g., 2024/2025" required>
                                @error('tahun_ajaran')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="active" {{ old('status', $class->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', $class->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Description</label>
                            <textarea class="form-control @error('deskripsi') is-invalid @enderror"
                                      id="deskripsi" name="deskripsi" rows="3">{{ old('deskripsi', $class->deskripsi) }}</textarea>
                            @error('deskripsi')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="{{ route('web-classes.index') }}" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i>Update Class
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
