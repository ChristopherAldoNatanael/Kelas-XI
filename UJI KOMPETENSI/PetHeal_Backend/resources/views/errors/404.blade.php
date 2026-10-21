@extends('errors.layout')

@section('title', 'Halaman tidak ditemukan')
@section('code', '404')
@section('heading', 'Halaman yang Anda cari tidak ada')
@section('message', 'Tautan mungkin sudah berubah, atau halaman ini tidak tersedia untuk akun Anda. Periksa kembali alamatnya, lalu coba dari menu utama.')
@section('status', 'Rute tidak ditemukan')
@section('hint', 'Jika ini tautan dari email atau notifikasi, buka ulang dari aplikasi.')
@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Kembali ke beranda</a>
@endsection
