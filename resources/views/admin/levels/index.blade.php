@extends('adminlte::page')

@section('title', 'Уровни')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Уровни</h1>
        <a class="btn btn-primary" href="{{ route('admin.levels.create') }}">Создать черновик</a>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Порядок</th>
                        <th>Категория</th>
                        <th>Переводы</th>
                        <th>Сложность</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($levels as $level)
                        <tr>
                            <td>{{ $level->id }}</td>
                            <td>{{ $level->sequence }}</td>
                            <td>{{ $level->category->translations->firstWhere('locale', 'ru')?->name ?? $level->category->slug }}</td>
                            <td>{{ $level->translations->pluck('locale')->sort()->implode(', ') ?: 'Нет' }}</td>
                            <td>{{ $level->difficulty }}</td>
                            <td>{{ $level->status->value }}</td>
                            <td>
                                @if ($level->status->value === 'published')
                                    <form method="POST" action="{{ route('admin.levels.move', $level) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="btn btn-link p-0" aria-label="Переместить уровень вверх">↑</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.levels.move', $level) }}" class="d-inline ml-1">
                                        @csrf
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="btn btn-link p-0" aria-label="Переместить уровень вниз">↓</button>
                                    </form>
                                @endif
                                @if ($level->status->value === 'draft')
                                    <a href="{{ route('admin.levels.edit', $level) }}">Изменить</a>
                                @endif
                                @if ($level->status->value !== 'archived')
                                    <form method="POST" action="{{ route('admin.levels.archive', $level) }}" class="d-inline" onsubmit="return confirm('Архивировать уровень?');">
                                        @csrf
                                        <button type="submit" class="btn btn-link text-danger p-0 ml-2">Архивировать</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">Уровней пока нет.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $levels->links() }}</div>
    </div>
@stop
