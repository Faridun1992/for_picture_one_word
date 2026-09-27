@extends('adminlte::page')

@section('title', 'Панель управления')

@section('content_header')
    <h1>Панель управления</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <p>Управление игровым каталогом.</p>
            <a class="btn btn-primary" href="{{ route('admin.levels.index') }}">Редактор уровней</a>
            <a class="btn btn-info" href="{{ route('admin.statistics.levels') }}">Статистика игры</a>
        </div>
    </div>
@stop
