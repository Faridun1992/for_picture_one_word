<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveLevelRequest;
use App\Http\Requests\Admin\SaveLevelRequest;
use App\Http\Requests\Admin\StoreLevelImagesRequest;
use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use App\Services\Admin\LevelImageUploadService;
use App\Services\Admin\LevelPublicationService;
use App\Services\Admin\LevelReorderingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class LevelController extends Controller
{
    public function index(): View
    {
        $levels = Level::query()
            ->with(['category.translations', 'translations'])
            ->orderBy('sequence')
            ->orderBy('id')
            ->paginate(25);

        return view('admin.levels.index', compact('levels'));
    }

    public function create(): View
    {
        return view('admin.levels.create', [
            'categories' => $this->categories(),
            'locales' => config('game.supported_locales'),
        ]);
    }

    public function store(SaveLevelRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $level = DB::transaction(function () use ($data): Level {
            $level = Level::query()->create([
                'category_id' => $data['category_id'],
                'sequence' => ((int) Level::query()->max('sequence')) + 1,
                'difficulty' => $data['difficulty'],
                'status' => LevelStatus::Draft,
            ]);

            $this->saveTranslations($level, $data['translations'] ?? []);

            return $level;
        });

        return redirect()->route('admin.levels.edit', $level)->with('status', 'Черновик уровня создан.');
    }

    public function edit(Level $level): View
    {
        $this->ensureDraft($level);
        $level->load(['translations', 'images']);

        return view('admin.levels.edit', [
            'level' => $level,
            'categories' => $this->categories(),
            'locales' => config('game.supported_locales'),
        ]);
    }

    public function update(SaveLevelRequest $request, Level $level): RedirectResponse
    {
        $this->ensureDraft($level);
        $data = $request->validated();

        DB::transaction(function () use ($level, $data): void {
            $lockedLevel = Level::query()->whereKey($level->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraft($lockedLevel);
            $lockedLevel->update([
                'category_id' => $data['category_id'],
                'difficulty' => $data['difficulty'],
            ]);

            $this->saveTranslations($lockedLevel, $data['translations'] ?? []);
        });

        return redirect()->route('admin.levels.edit', $level)->with('status', 'Черновик уровня сохранён.');
    }

    public function storeImages(
        StoreLevelImagesRequest $request,
        Level $level,
        LevelImageUploadService $imageUploadService,
    ): RedirectResponse {
        $imageUploadService->replace($level, $request->validated('images'));

        return redirect()->route('admin.levels.edit', $level)->with('status', 'Четыре изображения сохранены.');
    }

    public function publish(Level $level, LevelPublicationService $publicationService): RedirectResponse
    {
        $publicationService->publish($level);

        return redirect()->route('admin.levels.index')->with('status', 'Уровень опубликован.');
    }

    public function archive(Level $level, LevelPublicationService $publicationService): RedirectResponse
    {
        $publicationService->archive($level);

        return redirect()->route('admin.levels.index')->with('status', 'Уровень архивирован.');
    }

    public function move(MoveLevelRequest $request, Level $level, LevelReorderingService $reorderingService): RedirectResponse
    {
        $reorderingService->move($level, $request->validated('direction'));

        return redirect()->route('admin.levels.index')->with('status', 'Порядок уровней обновлён.');
    }

    /** @return Collection<int, Category> */
    private function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('slug')
            ->get();
    }

    /** @param array<string, array{answer_display?: ?string, letter_tiles?: list<string>}> $translations */
    private function saveTranslations(Level $level, array $translations): void
    {
        foreach (config('game.supported_locales') as $locale) {
            $translation = $translations[$locale] ?? [];
            $answer = trim((string) ($translation['answer_display'] ?? ''));
            $tiles = $translation['letter_tiles'] ?? [];

            if ($answer === '' && $tiles === []) {
                $level->translations()->where('locale', $locale)->delete();

                continue;
            }

            $level->translations()->updateOrCreate(
                ['locale' => $locale],
                ['answer_display' => $answer, 'letter_tiles' => $tiles],
            );
        }
    }

    private function ensureDraft(Level $level): void
    {
        abort_unless($level->status === LevelStatus::Draft, 403);
    }
}
