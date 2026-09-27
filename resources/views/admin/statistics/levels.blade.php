<div>
    <!-- The best way to take care of the future is to take care of the present moment. - Thich Nhat Hanh -->
</div>
@extends('adminlte::page')

@section('title', 'Статистика прохождений')

@section('content_header')
    <h1>Статистика прохождений</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="info-box"><div class="info-box-content"><span class="info-box-text">Начато прохождений</span><span class="info-box-number">{{ $totals->started_count ?? 0 }}</span></div></div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box"><div class="info-box-content"><span class="info-box-text">Завершено</span><span class="info-box-number">{{ $totals->completed_count ?? 0 }}</span></div></div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box"><div class="info-box-content"><span class="info-box-text">Ответов отправлено</span><span class="info-box-number">{{ $totals->attempts_count ?? 0 }}</span></div></div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box"><div class="info-box-content"><span class="info-box-text">Подсказок использовано</span><span class="info-box-number">{{ $totals->hints_count ?? 0 }}</span></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">По уровням</h2></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Уровень</th>
                        <th>Статус</th>
                        <th>Начато</th>
                        <th>Завершено</th>
                        <th>Доля завершений</th>
                        <th>Ответов</th>
                        <th>Подсказок</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($levels as $level)
                        @php($completionRate = $level->started_count > 0 ? round($level->completed_count / $level->started_count * 100, 1) : 0)
                        <tr>
                            <td>{{ $level->id }}</td>
                            <td>{{ $level->category->translations->firstWhere('locale', 'ru')?->name ?? $level->category->slug }} · #{{ $level->sequence }}</td>
                            <td>{{ $level->status->value }}</td>
                            <td>{{ $level->started_count }}</td>
                            <td>{{ $level->completed_count }}</td>
                            <td>{{ $completionRate }}%</td>
                            <td>{{ $level->attempts_count }}</td>
                            <td>{{ $level->hints_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">Уровни пока не созданы.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $levels->links() }}</div>
    </div>
@stop
