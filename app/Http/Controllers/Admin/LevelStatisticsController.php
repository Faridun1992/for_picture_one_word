<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\PlayerLevelProgressStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class LevelStatisticsController extends Controller
{
    public function __invoke(): View
    {
        $completedStatus = PlayerLevelProgressStatus::Completed->value;
        $progressStats = DB::table('player_level_progress')
            ->select('level_id')
            ->selectRaw('COUNT(*) AS started_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed_count', [$completedStatus])
            ->selectRaw('SUM(attempt_count) AS attempts_count')
            ->selectRaw('SUM(hints_used) AS hints_count')
            ->groupBy('level_id');

        $levels = Level::query()
            ->leftJoinSub($progressStats, 'progress_stats', 'progress_stats.level_id', '=', 'levels.id')
            ->select('levels.*')
            ->selectRaw('COALESCE(progress_stats.started_count, 0) AS started_count')
            ->selectRaw('COALESCE(progress_stats.completed_count, 0) AS completed_count')
            ->selectRaw('COALESCE(progress_stats.attempts_count, 0) AS attempts_count')
            ->selectRaw('COALESCE(progress_stats.hints_count, 0) AS hints_count')
            ->with(['category.translations' => static fn (Relation $query) => $query->where('locale', 'ru')])
            ->orderBy('levels.sequence')
            ->orderBy('levels.id')
            ->paginate(25);

        $totals = DB::table('player_level_progress')
            ->selectRaw('COUNT(*) AS started_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed_count', [$completedStatus])
            ->selectRaw('COALESCE(SUM(attempt_count), 0) AS attempts_count')
            ->selectRaw('COALESCE(SUM(hints_used), 0) AS hints_count')
            ->first();

        return view('admin.statistics.levels', compact('levels', 'totals'));
    }
}
