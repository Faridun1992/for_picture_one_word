@extends('adminlte::page')

@section('title', 'Редактировать уровень '.$level->id)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Черновик уровня #{{ $level->id }}</h1>
        <div>
            <form method="POST" action="{{ route('admin.levels.publish', $level) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success">Опубликовать</button>
            </form>
            <a class="btn btn-default" href="{{ route('admin.levels.index') }}">К списку</a>
        </div>
    </div>
@stop

@section('content')
    @include('admin.levels.form', ['action' => route('admin.levels.update', $level), 'method' => 'PUT', 'level' => $level])
@stop
