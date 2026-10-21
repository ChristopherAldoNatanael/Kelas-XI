@extends('errors.layout')

@section('title', 'Sesi kedaluwarsa')
@section('code', '419')
@section('heading', 'Sesi Anda sudah berakhir')
@section('message', 'Halaman ini terbuka terlalu lama atau formulir dikirim ulang. Muat ulang halaman, lalu isi kembali data yang diperlukan.')
@section('status', 'Token keamanan kedaluwarsa')
@section('hint', 'Demi keamanan, token CSRF tidak berlaku setelah sesi berakhir.')
@section('actions')
    <a class="btn btn-primary" href="{{ url('/admin/login') }}">Masuk kembali</a>
@endsection
