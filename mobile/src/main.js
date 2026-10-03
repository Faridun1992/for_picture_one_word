import './style.css';
import { ApiError } from './auth/api-client.js';
import { clearStoredSession, createGuestSession, endGuestSession, loadCategories, loadCategoryLevels, loadLevel, loadPlayer, loadProgress, requestLevelHint, savePlayerSettings, submitLevelAnswer } from './auth/guest-session.js';
import { secureTokenStorage } from './auth/secure-token-storage.js';
import { apiBaseUrl } from './config/api.js';
import { formatCoins, formatLevelMeta, formatNumber, setLocale, t, translatedApiError } from './i18n/index.js';
import { localGameCache } from './storage/local-game-cache.js';

document.documentElement.dataset.apiConfigured = String(Boolean(apiBaseUrl));

const app = document.querySelector('#app');
setLocale('ru');
const session = {
    token: null,
    player: null,
    progress: null,
    categories: [],
    category: null,
    level: null,
    selectedTileIds: [],
    revealedLetters: {},
    pendingAttempt: null,
    attemptInFlight: false,
    pendingHint: null,
    hintInFlight: false,
    pendingOperation: null,
    homeRenderId: 0,
};

function focusScreenTitle() {
    app.querySelector('[data-screen-title]')?.focus({ preventScroll: true });
}

function renderWelcome(message = '') {
    app.innerHTML = `
        <section class="welcome" aria-labelledby="app-title">
            <div class="picture-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <h1 id="app-title" data-screen-title tabindex="-1">${t('app.name')}</h1>
            <p class="intro">${t('welcome.intro')}</p>
            <button class="primary-button" type="button" data-action="guest">${t('welcome.guest')}</button>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('.status').textContent = message;
    focusScreenTitle();
}

async function renderHome(player, progress, message = '') {
    setLocale(player.locale);
    const currentLevelId = session.pendingOperation?.levelId ?? player.current_level_id ?? progress?.current_level_id ?? null;
    const currentLevelLocale = session.pendingOperation?.locale ?? player.locale;
    const completedLevels = Number(progress?.statistics?.completed_levels ?? 0);
    const totalAttempts = Number(progress?.statistics?.total_attempts ?? 0);
    const homeRenderId = ++session.homeRenderId;
    const hasUnfinishedLevel = session.pendingOperation?.levelId === currentLevelId
        || player.current_level_status === 'in_progress';

    app.innerHTML = `
        <section class="home-screen" aria-labelledby="app-title">
            <header class="home-header">
                <div class="home-brand" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                <span class="home-locale" data-locale></span>
                <button class="home-settings" type="button" data-action="settings" aria-label="${t('home.settings')}">⚙</button>
            </header>
            <p class="home-eyebrow">${t('home.eyebrow')}</p>
            <h1 id="app-title" data-screen-title tabindex="-1">${t('home.title')}</h1>
            <div class="home-wallet" aria-label="${t('game.balance')}">
                <span class="coin-icon" aria-hidden="true">●</span>
                <span><small>${t('home.balance')}</small><strong data-balance></strong></span>
            </div>
            <article class="home-level-card" aria-label="${t('home.currentLevel')}">
                <div class="home-level-images" data-home-images aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                <div class="home-level-copy">
                    <span class="home-level-kicker" data-home-level-label>${currentLevelId ? t('game.level', { level: currentLevelId }) : t('home.yourNext')}</span>
                    <strong data-home-category>${currentLevelId ? t('home.loading') : t('home.noLevels')}</strong>
                    <small data-home-level-meta>${currentLevelId ? t('home.puzzlePreview') : t('home.noLevelHint')}</small>
                </div>
            </article>
            <div class="progress-summary" aria-label="${t('home.progress')}">
                <span><strong data-completed></strong> ${t('home.completed')}</span>
                <span><strong data-attempts></strong> ${t('home.attempts')}</span>
            </div>
            <button class="primary-button home-play-button" type="button" data-action="continue" ${currentLevelId ? '' : 'disabled'}>${hasUnfinishedLevel ? t('home.continue') : t('home.play')}</button>
            <p class="intro" data-level-status>${currentLevelId ? (hasUnfinishedLevel ? t('home.resumeHint') : t('home.readyHint')) : t('home.emptyHint')}</p>
            <div class="home-actions">
                <button class="secondary-button" type="button" data-action="categories">${t('home.categories')}</button>
            </div>
            <button class="secondary-button" type="button" data-action="logout">${t('home.endSession')}</button>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('[data-locale]').textContent = String(player.locale).toUpperCase();
    app.querySelector('[data-balance]').textContent = formatCoins(player.balance);
    app.querySelector('[data-completed]').textContent = progress ? formatNumber(completedLevels) : '—';
    app.querySelector('[data-attempts]').textContent = progress ? formatNumber(totalAttempts) : '—';
    app.querySelector('.primary-button').dataset.levelId = currentLevelId ?? '';
    app.querySelector('.status').textContent = message;
    focusScreenTitle();

    if (!currentLevelId) {
        return;
    }

    try {
        const { level } = await loadCachedLevel(currentLevelId, currentLevelLocale);

        if (homeRenderId !== session.homeRenderId || !app.querySelector('[data-home-images]')) {
            return;
        }

        const images = app.querySelector('[data-home-images]');
        images.replaceChildren();

        for (const image of level.images) {
            const picture = document.createElement('img');
            picture.src = image.thumbnail_url ?? image.url;
            picture.alt = '';
            picture.loading = 'lazy';
            images.append(picture);
        }

        app.querySelector('[data-home-level-label]').textContent = t('game.level', { level: formatNumber(level.sequence) });
        app.querySelector('[data-home-category]').textContent = level.category.name;
        app.querySelector('[data-home-level-meta]').textContent = formatLevelMeta(level.answer_length, level.difficulty);

        const draft = await localGameCache.loadDraft(player.id, level.locale ?? player.locale, level);
        const hasSavedInput = draft?.selectedTileIds?.some((tileId) => tileId !== null)
            || Object.keys(draft?.revealedLetters ?? {}).length > 0;
        const shouldContinue = hasUnfinishedLevel || hasSavedInput;

        app.querySelector('.home-play-button').textContent = shouldContinue ? t('home.continue') : t('home.play');
        app.querySelector('[data-level-status]').textContent = shouldContinue
            ? t('home.resumeHint')
            : t('home.readyHint');
    } catch {
        if (homeRenderId === session.homeRenderId && app.querySelector('[data-home-images]')) {
            app.querySelector('[data-home-category]').textContent = t('home.previewUnavailable');
            app.querySelector('[data-home-level-meta]').textContent = t('home.reconnect');
            app.querySelector('[data-level-status]').textContent = t('home.savedSafe');
        }
    }
}

function renderLevelPreview(level, preserveHints = false) {
    app.innerHTML = `
        <section class="level-preview" aria-labelledby="level-title">
            <header class="game-topbar">
                <button class="text-button" type="button" data-action="home">${t('game.home')}</button>
                <p class="game-wallet" aria-label="${t('game.balance')}" data-game-balance></p>
            </header>
            <p class="player-meta game-category" data-category></p>
            <h1 id="level-title" data-level-title data-screen-title tabindex="-1"></h1>
            <div class="level-images" aria-label="${t('game.images')}"></div>
            <p class="intro" data-level-meta></p>
            <div class="answer-slots" role="group" aria-label="${t('game.answer')}"></div>
            <div class="tile-hint-layout">
                <div class="letter-tiles" role="group" aria-label="${t('game.letters')}"></div>
                <button class="hint-button letter-hint-button" type="button" data-action="hint" data-hint-type="reveal_letter" aria-label="${t('game.revealLetterAria', { price: 60 })}">
                    <span class="letter-hint-mark" aria-hidden="true">A</span>
                    <span class="letter-hint-price">60</span>
                    <span class="letter-hint-coin" aria-hidden="true">●</span>
                </button>
            </div>
            <button class="text-button clear-answer" type="button" data-action="clear-answer">${t('game.clear')}</button>
            <button class="primary-button submit-answer" type="button" data-action="submit-answer" disabled>${t('game.check')}</button>
            <div class="hint-actions" role="group" aria-label="${t('game.hints')}">
                <button class="hint-button" type="button" data-action="hint" data-hint-type="remove_wrong_letters">${t('game.removeWrong')}</button>
                <button class="hint-button" type="button" data-action="hint" data-hint-type="reveal_answer">${t('game.revealAnswer')}</button>
            </div>
            <p class="visually-hidden" aria-live="polite" data-answer-announcement></p>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('[data-category]').textContent = level.category.name;
    app.querySelector('[data-level-title]').textContent = t('game.level', { level: formatNumber(level.sequence) });
    app.querySelector('[data-level-meta]').textContent = formatLevelMeta(level.answer_length, level.difficulty);
    session.level = level;
    session.selectedTileIds = Array.from({ length: level.answer_length }, () => null);

    if (!preserveHints) {
        session.revealedLetters = {};
        session.pendingAttempt = null;
        session.attemptInFlight = false;
        session.pendingHint = null;
        session.hintInFlight = false;
    }

    updateVisibleBalance();

    const images = app.querySelector('.level-images');

    for (const image of level.images) {
        const picture = document.createElement('img');
        picture.src = image.thumbnail_url ?? image.url;
        picture.alt = t('game.imageClue', { position: formatNumber(image.position) });
        picture.loading = 'lazy';
        images.append(picture);
    }

    renderPuzzleControls();
    focusScreenTitle();
}

async function openLevel(level, offline = false) {
    renderLevelPreview(level);
    const draft = await localGameCache.loadDraft(session.player.id, level.locale ?? session.player.locale, level);

    if (draft) {
        session.selectedTileIds = draft.selectedTileIds;
        session.revealedLetters = draft.revealedLetters;
        session.level.letter_tiles = draft.letterTiles;
        renderPuzzleControls();
    }

    if (offline) {
        app.querySelector('.status').textContent = t('game.offline');
    }
}

async function loadCachedLevel(levelId, locale = session.player.locale) {

    try {
        const level = await loadLevel(session.token, levelId, locale);
        await localGameCache.saveLevel(session.player.id, locale, level);

        return { level, offline: false };
    } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 0) {
            throw error;
        }

        const level = await localGameCache.loadLevel(session.player.id, locale, levelId);

        if (!level) {
            throw error;
        }

        return { level, offline: true };
    }
}

function persistPuzzleDraft() {
    if (session.player?.id && session.level) {
        void localGameCache.saveDraft(
            session.player.id,
            session.level.locale ?? session.player.locale,
            session.level,
            session.selectedTileIds,
            session.revealedLetters,
        );
    }
}

function renderPuzzleControls() {
    const answerSlots = app.querySelector('.answer-slots');
    const letterTiles = app.querySelector('.letter-tiles');

    if (!answerSlots || !letterTiles || !session.level) {
        return;
    }

    answerSlots.replaceChildren();

    session.selectedTileIds.forEach((tileId, index) => {
        const slot = document.createElement('button');
        const revealedLetter = session.revealedLetters[index + 1] ?? null;
        const tile = revealedLetter ?? (tileId === null ? null : session.level.letter_tiles[tileId]);
        slot.className = `answer-slot${tile === null ? ' is-empty' : ''}${revealedLetter ? ' is-revealed' : ''}`;
        slot.type = 'button';
        slot.dataset.action = 'remove-answer-letter';
        slot.dataset.slotIndex = String(index);
        slot.textContent = tile ?? '';
        slot.setAttribute('aria-label', tile === null
            ? t('game.emptySlot', { position: formatNumber(index + 1) })
            : t('game.filledSlot', { position: formatNumber(index + 1), letter: tile }));
        slot.disabled = tile === null || revealedLetter !== null || Boolean(session.pendingAttempt) || Boolean(session.pendingHint);
        answerSlots.append(slot);
    });

    letterTiles.replaceChildren();
    const answerIsFull = session.selectedTileIds.every((tileId, index) => tileId !== null || session.revealedLetters[index + 1]);

    session.level.letter_tiles.forEach((tile, index) => {
        const button = document.createElement('button');
        const isSelected = session.selectedTileIds.includes(index);
        button.className = 'letter-tile';
        button.type = 'button';
        button.dataset.action = 'select-answer-letter';
        button.dataset.tileIndex = String(index);
        button.textContent = tile;
        button.disabled = isSelected
            || answerIsFull
            || Boolean(session.pendingAttempt)
            || Boolean(session.pendingHint);
        button.setAttribute('aria-label', t('game.tile', { letter: tile, selected: isSelected ? t('game.selected') : '' }));
        button.setAttribute('aria-pressed', String(isSelected));
        letterTiles.append(button);
    });

    app.querySelector('[data-answer-announcement]').textContent = session.selectedTileIds
        .map((tileId, index) => session.revealedLetters[index + 1]
            ?? (tileId === null ? '' : session.level.letter_tiles[tileId]))
        .join('');
    app.querySelector('.clear-answer').disabled = session.selectedTileIds.every((tileId) => tileId === null)
        || Boolean(session.pendingAttempt)
        || Boolean(session.pendingHint);
    app.querySelector('.submit-answer').disabled = !session.pendingAttempt
        && session.selectedTileIds.some((tileId, index) => tileId === null && !session.revealedLetters[index + 1])
        || session.attemptInFlight
        || Boolean(session.pendingHint);
    app.querySelector('[data-action="home"]').disabled = Boolean(session.pendingAttempt) || Boolean(session.pendingHint);

    for (const hintButton of app.querySelectorAll('.hint-button')) {
        const isPendingHint = session.pendingHint?.type === hintButton.dataset.hintType;
        hintButton.disabled = Boolean(session.pendingAttempt)
            || (Boolean(session.pendingHint) && !isPendingHint)
            || Boolean(session.attemptInFlight)
            || Boolean(session.hintInFlight);
    }

}

function updateVisibleBalance() {
    const balance = app.querySelector('[data-game-balance]');

    if (balance) {
        balance.textContent = formatCoins(session.player.balance);
    }
}

function applyHintResult(type, result) {
    session.player.balance = result.balance;
    session.pendingHint = null;
    session.hintInFlight = false;

    if (type === 'reveal_answer') {
        renderAttemptResult({ correct: true, reward: result.reward, next_level_id: result.next_level_id });

        return;
    }

    session.level.letter_tiles = result.letter_tiles;
    session.revealedLetters = result.revealed ?? {};
    session.selectedTileIds.fill(null);
    persistPuzzleDraft();
    renderPuzzleControls();
    updateVisibleBalance();

    app.querySelector('.status').textContent = result.charged
        ? t('game.hintUsed', { cost: formatNumber(result.cost), balance: formatNumber(result.balance) })
        : (type === 'reveal_letter' ? t('game.noLetters') : t('game.noWrongTiles'));
}

function renderAttemptResult(result) {
    if (!result.correct) {
        renderLevelPreview(session.level, true);
        app.querySelector('.status').textContent = t('game.incorrect', { attempts: formatNumber(result.progress.attempt_count) });

        return;
    }

    app.innerHTML = `
        <section class="welcome result-screen" aria-labelledby="result-title">
            <div class="picture-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <h1 id="result-title" data-screen-title tabindex="-1">${t('game.correct')}</h1>
            <p class="reward-earned" data-reward></p>
            <p class="intro" data-balance></p>
            <button class="primary-button" type="button" data-action="next-level"></button>
        </section>
    `;

    const reward = result.reward;
    app.querySelector('[data-reward]').textContent = t('game.reward', { coins: formatNumber(reward.coins) });
    app.querySelector('[data-balance]').textContent = t('game.totalBalance', { balance: formatCoins(reward.balance) });
    app.querySelector('[data-action="next-level"]').textContent = result.next_level_id ? t('game.nextLevel') : t('game.backHome');
    session.player.balance = reward.balance;
    session.player.current_level_id = result.next_level_id;
    session.player.current_level_status = result.next_level_id ? 'available' : null;
    focusScreenTitle();
}

function renderRestore(message) {
    app.innerHTML = `
        <section class="welcome" aria-labelledby="app-title">
            <div class="picture-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <h1 id="app-title" data-screen-title tabindex="-1">${t('restore.title')}</h1>
            <p class="intro">${t('restore.intro')}</p>
            <button class="primary-button" type="button" data-action="restore">${t('restore.retry')}</button>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('.status').textContent = message;
    focusScreenTitle();
}

function renderCategories(categories = session.categories) {
    app.innerHTML = `
        <section class="list-screen" aria-labelledby="screen-title">
            <button class="text-button" type="button" data-action="home">${t('game.home')}</button>
            <h1 id="screen-title" data-screen-title tabindex="-1">${t('categories.title')}</h1>
            <div class="item-list" data-category-list></div>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    const list = app.querySelector('[data-category-list]');

    if (categories.length === 0) {
        list.textContent = t('categories.empty');
    }

    for (const category of categories) {
        const button = document.createElement('button');
        const label = document.createElement('span');
        const count = document.createElement('small');
        button.className = 'list-item';
        button.type = 'button';
        button.dataset.action = 'select-category';
        button.dataset.categoryId = String(category.id);
        label.textContent = category.name;
        count.textContent = t('categories.count', { count: formatNumber(category.published_levels_count) });
        button.append(label, count);
        button.disabled = category.published_levels_count === 0;
        list.append(button);
    }

    focusScreenTitle();
}

function renderCategoryLevels(category, levels) {
    app.innerHTML = `
        <section class="list-screen" aria-labelledby="screen-title">
            <button class="text-button" type="button" data-action="categories">${t('levels.back')}</button>
            <h1 id="screen-title" data-screen-title tabindex="-1"></h1>
            <div class="item-list" data-level-list></div>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('#screen-title').textContent = category.name;
    const list = app.querySelector('[data-level-list]');

    if (levels.length === 0) {
        list.textContent = t('levels.empty');
    }

    for (const level of levels) {
        const button = document.createElement('button');
        const title = document.createElement('span');
        const details = document.createElement('small');
        button.className = 'list-item';
        button.type = 'button';
        button.dataset.action = 'open-category-level';
        button.dataset.levelId = String(level.id);
        title.textContent = t('game.level', { level: formatNumber(level.sequence) });
        details.textContent = formatLevelMeta(level.answer_length, level.difficulty);
        button.append(title, details);
        list.append(button);
    }

    focusScreenTitle();
}

function renderSettings() {
    const settings = session.player.settings;
    setLocale(settings.locale);

    app.innerHTML = `
        <section class="list-screen" aria-labelledby="screen-title">
            <button class="text-button" type="button" data-action="home">${t('game.home')}</button>
            <h1 id="screen-title" data-screen-title tabindex="-1">${t('settings.title')}</h1>
            <form class="settings-form">
                <label for="locale-setting">${t('settings.language')}</label>
                <select id="locale-setting" name="locale">
                    <option value="ru">Русский</option>
                    <option value="tj">Тоҷикӣ</option>
                    <option value="en">English</option>
                </select>
                <label class="toggle-setting"><input id="sound-setting" name="sound_enabled" type="checkbox"> ${t('settings.sound')}</label>
                <label class="toggle-setting"><input id="haptics-setting" name="haptics_enabled" type="checkbox"> ${t('settings.vibration')}</label>
                <button class="primary-button" type="button" data-action="save-settings">${t('settings.save')}</button>
            </form>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('#locale-setting').value = settings.locale;
    app.querySelector('#sound-setting').checked = settings.sound_enabled;
    app.querySelector('#haptics-setting').checked = settings.haptics_enabled;
    focusScreenTitle();
}

function showError(error) {
    const message = translatedApiError(error);
    const status = app.querySelector('.status');

    if (status) {
        status.textContent = message;
    }
}

function newIdempotencyKey() {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
}

app.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');

    if (!button) {
        return;
    }

    button.disabled = true;

    try {
        if (button.dataset.action === 'guest') {
            const player = await createGuestSession();
            session.token = await secureTokenStorage.get();
            session.player = player;
            session.progress = await loadProgress(session.token).catch(() => null);
            await renderHome(session.player, session.progress, session.progress ? '' : t('settings.progressUnavailable'));
        }

        if (button.dataset.action === 'restore') {
            const token = await secureTokenStorage.get();

            if (!token) {
                renderWelcome(t('restore.missing'));

                return;
            }

            await restoreHome(token);
        }

        if (button.dataset.action === 'continue') {
            const { level, offline } = await loadCachedLevel(
                button.dataset.levelId,
                session.pendingOperation?.locale ?? session.player.locale,
            );
            await openLevel(level, offline);

            if (session.pendingOperation?.levelId === level.id) {
                if (session.pendingOperation.kind === 'attempt') {
                    session.pendingAttempt = session.pendingOperation;
                    const restoredAnswer = Array.from({ length: level.answer_length }, (_, index) => {
                        const tileId = session.selectedTileIds[index];

                        return session.revealedLetters[index + 1]
                            ?? (tileId === null || tileId === undefined ? '' : level.letter_tiles[tileId]);
                    }).join('');

                    if (restoredAnswer !== session.pendingOperation.answer) {
                        session.selectedTileIds = Array.from({ length: level.answer_length }, () => null);
                        session.revealedLetters = {};
                    }
                } else {
                    session.pendingHint = {
                        type: session.pendingOperation.hintType,
                        key: session.pendingOperation.key,
                        levelId: session.pendingOperation.levelId,
                        locale: session.pendingOperation.locale,
                    };
                }

                renderPuzzleControls();
                app.querySelector('.status').textContent = t('game.pending');
            }
        }

        if (button.dataset.action === 'categories') {
            try {
                session.categories = await loadCategories(session.token, session.player.locale);
                await localGameCache.saveCategories(session.player.id, session.player.locale, session.categories);
            } catch (error) {
                if (!(error instanceof ApiError) || error.status !== 0) {
                    throw error;
                }

                session.categories = await localGameCache.loadCategories(session.player.id, session.player.locale) ?? [];
            }

            renderCategories();
        }

        if (button.dataset.action === 'select-category') {
            session.category = session.categories.find((category) => category.id === Number(button.dataset.categoryId));
            let levels;

            try {
                levels = await loadCategoryLevels(session.token, session.category.id, session.player.locale);
                await localGameCache.saveLevels(session.player.id, session.player.locale, session.category.id, levels);
            } catch (error) {
                if (!(error instanceof ApiError) || error.status !== 0) {
                    throw error;
                }

                levels = await localGameCache.loadLevels(session.player.id, session.player.locale, session.category.id) ?? [];
            }

            renderCategoryLevels(session.category, levels);
        }

        if (button.dataset.action === 'open-category-level') {
            const { level, offline } = await loadCachedLevel(button.dataset.levelId);
            await openLevel(level, offline);
        }

        if (button.dataset.action === 'settings') {
            renderSettings();
        }

        if (button.dataset.action === 'save-settings') {
            await savePlayerSettings(session.token, {
                locale: app.querySelector('#locale-setting').value,
                sound_enabled: app.querySelector('#sound-setting').checked,
                haptics_enabled: app.querySelector('#haptics-setting').checked,
            });

            session.player = await loadPlayer(session.token);
            session.categories = [];
            session.category = null;
            session.progress = await loadProgress(session.token).catch(() => session.progress);
            await localGameCache.savePlayer(session.player, session.progress);
            renderSettings();
            app.querySelector('.status').textContent = t('settings.saved');
        }

        if (button.dataset.action === 'submit-answer') {
            const composedAnswer = session.selectedTileIds
                .map((tileId, index) => session.revealedLetters[index + 1]
                    ?? (tileId === null ? '' : session.level.letter_tiles[tileId]))
                .join('');
            const isSamePendingAttempt = session.pendingAttempt?.levelId === session.level.id;
            const answer = isSamePendingAttempt ? session.pendingAttempt.answer : composedAnswer;
            const isSamePendingAnswer = isSamePendingAttempt && session.pendingAttempt.answer === answer;

            if (!isSamePendingAnswer) {
                session.pendingAttempt = {
                    levelId: session.level.id,
                    locale: session.player.locale,
                    answer,
                    key: newIdempotencyKey(),
                };
            }

            session.pendingOperation = { kind: 'attempt', ...session.pendingAttempt };
            await localGameCache.savePendingOperation(session.player.id, session.pendingOperation);
            session.attemptInFlight = true;
            renderPuzzleControls();

            const result = await submitLevelAnswer(
                session.token,
                session.level.id,
                session.pendingAttempt.locale ?? session.player.locale,
                answer,
                session.pendingAttempt.key,
            );

            session.pendingAttempt = null;
            session.pendingOperation = null;
            await localGameCache.clearPendingOperation(session.player.id);
            session.attemptInFlight = false;

            if (!result.correct) {
                session.player = await loadPlayer(session.token).catch(() => ({
                    ...session.player,
                    current_level_id: session.level.id,
                    current_level_status: 'in_progress',
                }));
            }

            renderAttemptResult(result);
            session.progress = await loadProgress(session.token).catch(() => session.progress);
            await localGameCache.clearDraft(session.player.id, session.level.locale ?? session.player.locale, session.level.id);
            await localGameCache.savePlayer(session.player, session.progress);
        }

        if (button.dataset.action === 'hint') {
            const type = button.dataset.hintType;

            if (session.pendingHint?.type !== type) {
                session.pendingHint = { type, key: newIdempotencyKey(), levelId: session.level.id, locale: session.player.locale };
            }

            session.pendingOperation = {
                kind: 'hint',
                hintType: session.pendingHint.type,
                key: session.pendingHint.key,
                levelId: session.pendingHint.levelId,
                locale: session.pendingHint.locale,
            };
            await localGameCache.savePendingOperation(session.player.id, session.pendingOperation);
            session.hintInFlight = true;
            renderPuzzleControls();

            const result = await requestLevelHint(
                session.token,
                session.level.id,
                session.pendingHint.locale ?? session.player.locale,
                type,
                session.pendingHint.key,
            );

            applyHintResult(type, result);

            if (type !== 'reveal_answer') {
                session.player = await loadPlayer(session.token).catch(() => ({
                    ...session.player,
                    current_level_id: session.level.id,
                    current_level_status: 'in_progress',
                }));
            }

            session.pendingOperation = null;
            await localGameCache.clearPendingOperation(session.player.id);
            session.progress = await loadProgress(session.token).catch(() => session.progress);
            if (type === 'reveal_answer') {
                await localGameCache.clearDraft(session.player.id, session.level.locale ?? session.player.locale, session.level.id);
            }

            await localGameCache.savePlayer(session.player, session.progress);
        }

        if (button.dataset.action === 'home') {
            await renderHome(session.player, session.progress);
        }

        if (button.dataset.action === 'next-level') {
            if (session.player.current_level_id) {
                const { level, offline } = await loadCachedLevel(session.player.current_level_id);
                await openLevel(level, offline);
            } else {
                await renderHome(session.player, session.progress);
            }
        }

        if (button.dataset.action === 'select-answer-letter') {
            const emptySlot = session.selectedTileIds.findIndex((tileId, index) => tileId === null && !session.revealedLetters[index + 1]);

            if (emptySlot !== -1) {
                session.selectedTileIds[emptySlot] = Number(button.dataset.tileIndex);
                persistPuzzleDraft();
                renderPuzzleControls();
            }
        }

        if (button.dataset.action === 'remove-answer-letter') {
            session.selectedTileIds.splice(Number(button.dataset.slotIndex), 1);
            session.selectedTileIds.push(null);
            persistPuzzleDraft();
            renderPuzzleControls();
        }

        if (button.dataset.action === 'clear-answer') {
            session.selectedTileIds.fill(null);
            persistPuzzleDraft();
            renderPuzzleControls();
        }

        if (button.dataset.action === 'logout') {
            const playerId = session.player?.id;
            const token = await secureTokenStorage.get();

            if (token) {
                await endGuestSession(token);
            }

            await localGameCache.clearPlayer(playerId);
            renderWelcome(t('welcome.ended'));
        }
    } catch (error) {
        if (error instanceof ApiError && error.status === 401 && ['logout', 'restore', 'continue', 'submit-answer', 'hint', 'next-level', 'categories', 'select-category', 'open-category-level', 'save-settings'].includes(button.dataset.action)) {
            session.pendingAttempt = null;
            session.attemptInFlight = false;
            session.pendingHint = null;
            session.hintInFlight = false;
            try {
                await clearStoredSession();
                renderWelcome(t('restore.expired'));
            } catch {
                renderWelcome(t('restore.removeFailed'));
            }
        } else if (['continue', 'submit-answer', 'hint', 'next-level', 'categories', 'select-category', 'open-category-level', 'save-settings'].includes(button.dataset.action)) {
            session.attemptInFlight = false;
            session.hintInFlight = false;

            if (button.dataset.action === 'hint' && error instanceof ApiError && error.code === 'insufficient_coins') {
                session.player.balance = Number(error.meta.balance ?? session.player.balance);
                session.pendingHint = null;
                updateVisibleBalance();
                app.querySelector('.status').textContent = t('errors.insufficientCoins', {
                    required: formatNumber(error.meta.required),
                    balance: formatNumber(session.player.balance),
                });
            } else {
                if (button.dataset.action === 'hint' && error instanceof ApiError && error.status === 422) {
                    session.pendingHint = null;
                    session.pendingOperation = null;
                    await localGameCache.clearPendingOperation(session.player.id);
                }

                showError(error);
            }

            button.disabled = false;

            if (button.dataset.action === 'submit-answer' && error instanceof ApiError && error.status === 422) {
                session.pendingAttempt = null;
                session.pendingOperation = null;
                await localGameCache.clearPendingOperation(session.player.id);
            }

            if (button.dataset.action === 'submit-answer' || button.dataset.action === 'hint') {
                renderPuzzleControls();
            }
        } else {
            const storedToken = await secureTokenStorage.get().catch(() => null);

            if (button.dataset.action === 'restore' || storedToken) {
                renderRestore(translatedApiError(error));
            } else {
                showError(error);
                button.disabled = false;
            }
        }

        if (button.dataset.action === 'submit-answer' && session.pendingAttempt
            && !(error instanceof ApiError && error.status === 422)) {
            app.querySelector('.status').textContent = t('game.retryAttempt', { message: translatedApiError(error) });
        }

        if (button.dataset.action === 'hint' && session.pendingHint) {
            app.querySelector('.status').textContent = t('game.retryHint', { message: translatedApiError(error) });
        }
    }
});

async function initialize() {
    renderWelcome(t('welcome.checking'));
    app.querySelector('button').disabled = true;

    if (!apiBaseUrl) {
        app.querySelector('.status').textContent = t('errors.apiNotConfigured');
        app.querySelector('button').disabled = true;

        return;
    }

    let token;

    try {
        token = await secureTokenStorage.get();
    } catch {
        app.querySelector('.status').textContent = t('errors.secureStorage');
        app.querySelector('button').disabled = true;

        return;
    }

    if (!token) {
        app.querySelector('button').disabled = false;
        app.querySelector('.status').textContent = '';

        return;
    }

    try {
        await restoreHome(token);
    } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
            try {
                await clearStoredSession();
                renderWelcome(t('restore.expired'));
            } catch {
                app.querySelector('.status').textContent = t('errors.expiredCleanup');
            }

            return;
        }

        renderRestore(translatedApiError(error));
    }
}

async function restoreHome(token) {
    session.token = token;

    try {
        session.player = await loadPlayer(token);
        session.progress = await loadProgress(token).catch(() => null);
        session.pendingOperation = await localGameCache.loadPendingOperation(session.player.id);
        await localGameCache.savePlayer(session.player, session.progress);
        await renderHome(session.player, session.progress, session.progress ? '' : t('settings.progressUnavailable'));
    } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 0) {
            throw error;
        }

        const cachedHome = await localGameCache.loadLastHome();

        if (!cachedHome) {
            throw error;
        }

        session.player = cachedHome.player;
        session.progress = cachedHome.progress;
        session.pendingOperation = await localGameCache.loadPendingOperation(session.player.id);
        await renderHome(session.player, session.progress, t('home.offlineProgress'));
    }
}

initialize();
