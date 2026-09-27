@extends('adminlte::page')

@section('title', 'Новый уровень')

@section('content_header')
    <h1>Новый черновик уровня</h1>
@stop

@section('content')
    @include('admin.levels.form', ['action' => route('admin.levels.store'), 'method' => 'POST', 'level' => null])
@stop
