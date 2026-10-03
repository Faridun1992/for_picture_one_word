<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="form-group">
                <label for="category_id">Категория</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">Выберите категорию</option>
                    @foreach ($categories as $category)
                        @php
                            $categoryName = $category->translations->firstWhere('locale', 'ru')?->name ?? $category->slug;
                        @endphp
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $level?->category_id) === (string) $category->id)>
                            {{ $categoryName }} ({{ $category->slug }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="difficulty">Сложность (1–5)</label>
                <input id="difficulty" name="difficulty" type="number" min="1" max="5" class="form-control" value="{{ old('difficulty', $level?->difficulty ?? 1) }}" required>
            </div>
        </div>
    </div>

    @foreach ($locales as $locale)
        @php
            $savedTranslation = $level?->translations->firstWhere('locale', $locale);
            $translationInput = old("translations.{$locale}", []);
            $savedTiles = $savedTranslation?->letter_tiles ?? [];
            $tilesInput = data_get($translationInput, 'letter_tiles', $savedTiles);
            if (! is_array($tilesInput)) {
                $tilesInput = [];
            }
        @endphp

        <div class="card card-default">
            <div class="card-header"><h3 class="card-title">Перевод: {{ strtoupper($locale) }}</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label for="answer-{{ $locale }}">Правильный ответ</label>
                    <input id="answer-{{ $locale }}" name="translations[{{ $locale }}][answer_display]" class="form-control" maxlength="100" aria-describedby="answer-help-{{ $locale }}" value="{{ data_get($translationInput, 'answer_display', $savedTranslation?->answer_display ?? '') }}">
                    <small id="answer-help-{{ $locale }}" class="form-text text-muted">Не более {{ config('game.max_answer_graphemes') }} Unicode-графем.</small>
                </div>
                <div class="form-group mb-0">
                    <label for="tiles-{{ $locale }}">12 буквенных плиток</label>
                    <textarea id="tiles-{{ $locale }}" name="translations[{{ $locale }}][letter_tiles]" class="form-control" rows="6" aria-describedby="tiles-help-{{ $locale }}">{{ implode("\n", $tilesInput) }}</textarea>
                    <small id="tiles-help-{{ $locale }}" class="form-text text-muted">Укажите ровно 12 плиток, включая все буквы ответа и дополнительные буквы — по одной Unicode-графеме на строку.</small>
                </div>
            </div>
        </div>
    @endforeach

    <button type="submit" class="btn btn-primary mb-4">Сохранить черновик</button>
</form>

@if ($level)
    <div class="card card-info card-outline">
        <div class="card-header"><h3 class="card-title">Изображения уровня</h3></div>
        <div class="card-body">
            <p>Загрузите все четыре изображения. Загрузка заменит текущий комплект целиком.</p>
            @if ($level->images->isNotEmpty())
                <div class="row mb-3">
                    @foreach ($level->images->sortBy('position') as $image)
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <figure class="figure">
                                @php($preview = data_get($image->variants, 'display'))
                                @php($previewKey = $preview['storage_key'] ?? $image->storage_key)
                                <img class="figure-img img-fluid rounded" src="{{ \Illuminate\Support\Facades\Storage::disk($image->storage_disk)->temporaryUrl($previewKey, now()->addMinutes(15)) }}" alt="Изображение {{ $image->position }}">
                                <figcaption class="figure-caption">Позиция {{ $image->position }} · {{ $preview['width'] ?? $image->width }}×{{ $preview['height'] ?? $image->height }}</figcaption>
                            </figure>
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.levels.images.store', $level) }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    @foreach ([1, 2, 3, 4] as $position)
                        <div class="col-md-6 form-group">
                            <label for="image-{{ $position }}">Изображение {{ $position }}</label>
                            <input id="image-{{ $position }}" name="images[{{ $position }}]" type="file" class="form-control-file" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-info">Загрузить комплект</button>
            </form>
        </div>
    </div>
@endif
