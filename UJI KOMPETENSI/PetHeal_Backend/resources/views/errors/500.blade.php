@extends('errors.layout')

@section('title', 'Terjadi kesalahan server')
@section('code', '500')
@section('heading', 'Kami sedang memperbaiki ini')
@section('message', 'Permintaan Anda tidak dapat diproses saat ini. Data yang sudah tersimpan tidak berubah. Coba lagi beberapa saat lagi.')
@section('status', 'Kesalahan tak terduga')
@section('hint', 'Detail teknis sudah dicatat di log server untuk ditindaklanjuti.')
@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Coba lagi</a>
@endsection
