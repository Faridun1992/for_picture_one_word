import './style.css';
import { ApiError } from './auth/api-client.js';
import { clearStoredSession, createGuestSession, endGuestSession, loadCategories, loadCategoryLevels, loadLevel, loadPlayer, loadProgress, requestLevelHint, savePlayerSettings, submitLevelAnswer } from './auth/guest-session.js';
import { secureTokenStorage } from './auth/secure-token-storage.js';
import { apiBaseUrl } from './config/api.js';
import { localGameCache } from './storage/local-game-cache.js';

document.documentElement.dataset.apiConfigured = String(Boolean(apiBaseUrl));

const app = document.querySelector('#app');
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
};

function focusScreenTitle() {
    app.querySelector('[data-screen-title]')?.focus({ preventScroll: true });
}

function renderWelcome(message = '') {
    app.innerHTML = `
        <section class="welcome" aria-labelledby="app-title">
            <div class="picture-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <h1 id="app-title" data-screen-title tabindex="-1">Four Pictures<br />One Word</h1>
            <p class="intro">Play for free. Your progress is saved to your guest profile.</p>
            <button class="primary-button" type="button" data-action="guest">Continue as guest</button>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('.status').textContent = message;
    focusScreenTitle();
}

function renderHome(player, progress, message = '') {
    const currentLevelId = session.pendingOperation?.levelId ?? player.current_level_id ?? progress?.current_level_id ?? null;
    const completedLevels = Number(progress?.statistics?.completed_levels ?? 0);
    const totalAttempts = Number(progress?.statistics?.total_attempts ?? 0);

    app.innerHTML = `
        <section class="welcome home-screen" aria-labelledby="app-title">
            <div class="picture-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <h1 id="app-title" data-screen-title tabindex="-1">Four Pictures<br />One Word</h1>
            <p class="player-meta">Guest profile · <span data-locale></span></p>
            <p class="balance" aria-label="Coin balance"><span data-balance></span> <span>Coins</span></p>
            <div class="progress-summary" aria-label="Game progress">
                <span><strong data-completed></strong> levels completed</span>
                <span><strong data-attempts></strong> answers submitted</span>
            </div>
            <button class="primary-button" type="button" data-action="continue" ${currentLevelId ? '' : 'disabled'}>Continue playing</button>
            <p class="intro" data-level-status>${currentLevelId ? 'Resume your current level.' : 'There are no available levels yet.'}</p>
            <div class="home-actions">
                <button class="secondary-button" type="button" data-action="categories">Categories</button>
                <button class="secondary-button" type="button" data-action="settings">Settings</button>
            </div>
            <button class="secondary-button" type="button" data-action="logout">End guest session</button>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('[data-locale]').textContent = String(player.locale).toUpperCase();
    app.querySelector('[data-balance]').textContent = Number(player.balance).toLocaleString();
    app.querySelector('[data-completed]').textContent = progress ? completedLevels.toLocaleString() : '—';
    app.querySelector('[data-attempts]').textContent = progress ? totalAttempts.toLocaleString() : '—';
    app.querySelector('.primary-button').dataset.levelId = currentLevelId ?? '';
    app.querySelector('.status').textContent = message;
    focusScreenTitle();
}

function renderLevelPreview(level, preserveHints = false) {
    app.innerHTML = `
        <section class="level-preview" aria-labelledby="level-title">
            <button class="text-button" type="button" data-action="home">← Home</button>
            <p class="player-meta" data-category></p>
            <h1 id="level-title" data-level-title data-screen-title tabindex="-1"></h1>
            <p class="game-wallet" aria-label="Coin balance"><span data-game-balance></span> Coins</p>
            <div class="level-images" aria-label="Puzzle images"></div>
            <p class="intro" data-level-meta></p>
            <div class="answer-slots" role="group" aria-label="Your answer"></div>
            <div class="letter-tiles" role="group" aria-label="Available letters"></div>
            <button class="text-button clear-answer" type="button" data-action="clear-answer">Clear answer</button>
            <button class="primary-button submit-answer" type="button" data-action="submit-answer" disabled>Check answer</button>
            <div class="hint-actions" role="group" aria-label="Hints">
                <button class="hint-button" type="button" data-action="hint" data-hint-type="reveal_letter">Reveal a letter</button>
                <button class="hint-button" type="button" data-action="hint" data-hint-type="remove_wrong_letters">Remove wrong letters</button>
                <button class="hint-button" type="button" data-action="hint" data-hint-type="reveal_answer">Reveal answer</button>
            </div>
            <p class="visually-hidden" aria-live="polite" data-answer-announcement></p>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('[data-category]').textContent = level.category.name;
    app.querySelector('[data-level-title]').textContent = `Level ${level.sequence}`;
    app.querySelector('[data-level-meta]').textContent = `${level.answer_length} letters · Difficulty ${level.difficulty}`;
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
        picture.alt = `Puzzle clue ${image.position}`;
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
        app.querySelector('.status').textContent = 'Offline mode. Your saved answer is available; reconnect to submit it.';
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
            ? `Answer position ${index + 1}, empty`
            : `Answer position ${index + 1}, letter ${tile}. Remove`);
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
        button.setAttribute('aria-label', `Letter ${tile}${isSelected ? ', selected' : ''}`);
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
        balance.textContent = Number(session.player.balance).toLocaleString();
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
        ? `Hint used. ${result.cost} Coins charged. Balance: ${result.balance.toLocaleString()} Coins.`
        : (type === 'reveal_letter' ? 'There are no more letters to reveal.' : 'There are no more wrong letters to remove.');
}

function renderAttemptResult(result) {
    if (!result.correct) {
        renderLevelPreview(session.level, true);
        app.querySelector('.status').textContent = `Not quite. ${result.progress.attempt_count} attempts so far. Try again.`;

        return;
    }

    app.innerHTML = `
        <section class="welcome result-screen" aria-labelledby="result-title">
            <div class="picture-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <h1 id="result-title" data-screen-title tabindex="-1">Correct!</h1>
            <p class="reward-earned" data-reward></p>
            <p class="intro" data-balance></p>
            <button class="primary-button" type="button" data-action="next-level"></button>
        </section>
    `;

    const reward = result.reward;
    app.querySelector('[data-reward]').textContent = `+${reward.coins} Coins earned`;
    app.querySelector('[data-balance]').textContent = `${reward.balance.toLocaleString()} Coins total`;
    app.querySelector('[data-action="next-level"]').textContent = result.next_level_id ? 'Continue to next level' : 'Back to home';
    session.player.balance = reward.balance;
    session.player.current_level_id = result.next_level_id;
    focusScreenTitle();
}

function renderRestore(message) {
    app.innerHTML = `
        <section class="welcome" aria-labelledby="app-title">
            <div class="picture-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <h1 id="app-title" data-screen-title tabindex="-1">Reconnect your profile</h1>
            <p class="intro">Your guest profile is saved securely on this device.</p>
            <button class="primary-button" type="button" data-action="restore">Retry connection</button>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('.status').textContent = message;
    focusScreenTitle();
}

function renderCategories(categories = session.categories) {
    app.innerHTML = `
        <section class="list-screen" aria-labelledby="screen-title">
            <button class="text-button" type="button" data-action="home">← Home</button>
            <h1 id="screen-title" data-screen-title tabindex="-1">Categories</h1>
            <div class="item-list" data-category-list></div>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    const list = app.querySelector('[data-category-list]');

    if (categories.length === 0) {
        list.textContent = 'No categories are available in this language yet.';
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
        count.textContent = `${category.published_levels_count} levels`;
        button.append(label, count);
        button.disabled = category.published_levels_count === 0;
        list.append(button);
    }

    focusScreenTitle();
}

function renderCategoryLevels(category, levels) {
    app.innerHTML = `
        <section class="list-screen" aria-labelledby="screen-title">
            <button class="text-button" type="button" data-action="categories">← Categories</button>
            <h1 id="screen-title" data-screen-title tabindex="-1"></h1>
            <div class="item-list" data-level-list></div>
            <p class="status" role="status" aria-live="polite"></p>
        </section>
    `;

    app.querySelector('#screen-title').textContent = category.name;
    const list = app.querySelector('[data-level-list]');

    if (levels.length === 0) {
        list.textContent = 'No playable levels are available in this category.';
    }

    for (const level of levels) {
        const button = document.createElement('button');
        const title = document.createElement('span');
        const details = document.createElement('small');
        button.className = 'list-item';
        button.type = 'button';
        button.dataset.action = 'open-category-level';
        button.dataset.levelId = String(level.id);
        title.textContent = `Level ${level.sequence}`;
        details.textContent = `${level.answer_length} letters · Difficulty ${level.difficulty}`;
        button.append(title, details);
        list.append(button);
    }

    focusScreenTitle();
}

function renderSettings() {
    const settings = session.player.settings;

    app.innerHTML = `
        <section class="list-screen" aria-labelledby="screen-title">
            <button class="text-button" type="button" data-action="home">← Home</button>
            <h1 id="screen-title" data-screen-title tabindex="-1">Settings</h1>
            <form class="settings-form">
                <label for="locale-setting">Language</label>
                <select id="locale-setting" name="locale">
                    <option value="ru">Русский</option>
                    <option value="tj">Тоҷикӣ</option>
                    <option value="en">English</option>
                </select>
                <label class="toggle-setting"><input id="sound-setting" name="sound_enabled" type="checkbox"> Sound</label>
                <label class="toggle-setting"><input id="haptics-setting" name="haptics_enabled" type="checkbox"> Vibration</label>
                <button class="primary-button" type="button" data-action="save-settings">Save settings</button>
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
    const message = error instanceof ApiError ? error.message : 'Something went wrong. Please try again.';
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
            renderHome(session.player, session.progress, session.progress ? '' : 'Progress summary is temporarily unavailable.');
        }

        if (button.dataset.action === 'restore') {
            const token = await secureTokenStorage.get();

            if (!token) {
                renderWelcome('No saved guest session was found.');

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
                app.querySelector('.status').textContent = 'An action is waiting for confirmation. Retry it to safely resend the same request.';
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
            app.querySelector('.status').textContent = 'Settings saved to your guest profile.';
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
            session.pendingOperation = null;
            await localGameCache.clearPendingOperation(session.player.id);
            session.progress = await loadProgress(session.token).catch(() => session.progress);
            if (type === 'reveal_answer') {
                await localGameCache.clearDraft(session.player.id, session.level.locale ?? session.player.locale, session.level.id);
            }

            await localGameCache.savePlayer(session.player, session.progress);
        }

        if (button.dataset.action === 'home') {
            renderHome(session.player, session.progress);
        }

        if (button.dataset.action === 'next-level') {
            if (session.player.current_level_id) {
                const { level, offline } = await loadCachedLevel(session.player.current_level_id);
                await openLevel(level, offline);
            } else {
                renderHome(session.player, session.progress);
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
            renderWelcome('Your guest session has ended.');
        }
    } catch (error) {
        if (error instanceof ApiError && error.status === 401 && ['logout', 'restore', 'continue', 'submit-answer', 'hint', 'next-level', 'categories', 'select-category', 'open-category-level', 'save-settings'].includes(button.dataset.action)) {
            session.pendingAttempt = null;
            session.attemptInFlight = false;
            session.pendingHint = null;
            session.hintInFlight = false;
            try {
                await clearStoredSession();
                renderWelcome('Your previous guest session expired. Continue to create a new one.');
            } catch {
                renderWelcome('The expired session could not be removed from secure storage. You can continue as a guest.');
            }
        } else if (['continue', 'submit-answer', 'hint', 'next-level', 'categories', 'select-category', 'open-category-level', 'save-settings'].includes(button.dataset.action)) {
            session.attemptInFlight = false;
            session.hintInFlight = false;

            if (button.dataset.action === 'hint' && error instanceof ApiError && error.code === 'insufficient_coins') {
                session.player.balance = Number(error.meta.balance ?? session.player.balance);
                session.pendingHint = null;
                updateVisibleBalance();
                app.querySelector('.status').textContent = `Not enough Coins. This hint costs ${error.meta.required} Coins; your balance is ${session.player.balance.toLocaleString()} Coins.`;
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
                renderRestore(error instanceof ApiError ? error.message : 'The guest profile could not be loaded.');
            } else {
                showError(error);
                button.disabled = false;
            }
        }

        if (button.dataset.action === 'submit-answer' && session.pendingAttempt
            && !(error instanceof ApiError && error.status === 422)) {
            app.querySelector('.status').textContent = `${error instanceof ApiError ? error.message : 'The answer could not be confirmed.'} Retry to safely resend the same attempt.`;
        }

        if (button.dataset.action === 'hint' && session.pendingHint) {
            app.querySelector('.status').textContent = `${error instanceof ApiError ? error.message : 'The hint could not be confirmed.'} Retry the same hint to safely resend it.`;
        }
    }
});

async function initialize() {
    renderWelcome('Checking your guest profile…');
    app.querySelector('button').disabled = true;

    if (!apiBaseUrl) {
        showError(new ApiError('The game server is not configured.', 0));
        app.querySelector('button').disabled = true;

        return;
    }

    let token;

    try {
        token = await secureTokenStorage.get();
    } catch {
        showError(new Error('Secure token storage is unavailable on this device.'));
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
                renderWelcome('Your previous guest session expired. Continue to create a new one.');
            } catch {
                showError(new Error('The expired session could not be removed from secure storage.'));
            }

            return;
        }

        renderRestore(error instanceof ApiError ? error.message : 'The guest profile could not be loaded.');
    }
}

async function restoreHome(token) {
    session.token = token;

    try {
        session.player = await loadPlayer(token);
        session.progress = await loadProgress(token).catch(() => null);
        session.pendingOperation = await localGameCache.loadPendingOperation(session.player.id);
        await localGameCache.savePlayer(session.player, session.progress);
        renderHome(session.player, session.progress, session.progress ? '' : 'Progress summary is temporarily unavailable.');
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
        renderHome(session.player, session.progress, 'Offline mode. Progress shown from the last saved profile.');
    }
}

initialize();
