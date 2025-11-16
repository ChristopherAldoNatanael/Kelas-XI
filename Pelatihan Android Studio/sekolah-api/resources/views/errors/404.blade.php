@extends('layouts.app')

@section('title', 'Halaman Tidak Ditemukan')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        Halaman Tidak Ditemukan (404)
                    </h4>
                </div>
                <div class="card-body text-center py-5">
                    <div class="error-icon mb-4">
                        <i class="fas fa-search fa-5x text-muted"></i>
                    </div>

                    <h2 class="mb-3">Oops! Halaman Tidak Ditemukan</h2>

                    <p class="text-muted mb-4">
                        Halaman yang Anda cari tidak ada atau telah dipindahkan.
                        Jika Anda terdaftar secara otomatis ke halaman ini, kemungkinan Anda mencoba mengakses API secara langsung.
                    </p>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Catatan:</strong> Untuk menggunakan API, silakan gunakan aplikasi mobile atau tools seperti Postman.
                    </div>

                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="list-group">
                                <a href="{{ route('dashboard') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-tachometer-alt"></i>
                                    Kembali ke Dashboard
                                </a>
                                <a href="{{ route('web-schedules.index') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-calendar"></i>
                                    Lihat Jadwal
                                </a>
                                <a href="{{ route('web-teachers.index') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-users"></i>
                                    Kelola Guru
                                </a>
                                <a href="{{ route('web-subjects.index') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-book"></i>
                                    Kelola Mata Pelajaran
                                </a>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <p class="text-muted small">
                        Jika masalah ini terus berlanjut, silakan hubungi administrator sistem.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.error-icon {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

.list-group-item-action:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
    transition: all 0.3s ease;
}
</style>
@endsection
