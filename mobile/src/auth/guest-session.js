import { apiRequest } from './api-client.js';
import { secureTokenStorage } from './secure-token-storage.js';

const supportedLocales = new Set(['en', 'ru', 'tj']);

function preferredLocale() {
    const locale = navigator.language?.slice(0, 2).toLowerCase();

    return supportedLocales.has(locale) ? locale : 'ru';
}

export async function loadPlayer(token) {
    return apiRequest('/me', { token });
}

export async function loadProgress(token) {
    return apiRequest('/progress?limit=1', { token });
}

export async function loadCategories(token, locale) {
    return apiRequest(`/categories?limit=50&locale=${encodeURIComponent(locale)}`, { token });
}

export async function loadCategoryLevels(token, categoryId, locale) {
    return apiRequest(`/levels?category_id=${encodeURIComponent(categoryId)}&limit=50&locale=${encodeURIComponent(locale)}`, { token });
}

export async function loadLevel(token, levelId, locale) {
    return apiRequest(`/levels/${levelId}?locale=${encodeURIComponent(locale)}`, { token });
}

export async function submitLevelAnswer(token, levelId, locale, answer, idempotencyKey) {
    return apiRequest(`/levels/${levelId}/attempts?locale=${encodeURIComponent(locale)}`, {
        token,
        method: 'POST',
        body: { answer },
        idempotencyKey,
    });
}

export async function requestLevelHint(token, levelId, locale, type, idempotencyKey) {
    return apiRequest(`/levels/${levelId}/hints?locale=${encodeURIComponent(locale)}`, {
        token,
        method: 'POST',
        body: { type },
        idempotencyKey,
    });
}

export async function savePlayerSettings(token, settings) {
    return apiRequest('/me/settings', { token, method: 'PATCH', body: settings });
}

export async function createGuestSession() {
    const session = await apiRequest('/auth/guest', {
        method: 'POST',
        body: {
            locale: preferredLocale(),
            device_name: 'Four Pictures One Word',
        },
    });

    if (typeof session.token !== 'string' || session.token.length === 0) {
        throw new Error('The game server did not return a valid session token.');
    }

    try {
        await secureTokenStorage.set(session.token);
    } catch {
        await apiRequest('/auth/session', { method: 'DELETE', token: session.token }).catch(() => {});

        throw new Error('Secure token storage is unavailable. Your guest session could not be saved.');
    }

    return loadPlayer(session.token);
}

export async function endGuestSession(token) {
    await apiRequest('/auth/session', { method: 'DELETE', token });
    await secureTokenStorage.remove();
}

export async function clearStoredSession() {
    await secureTokenStorage.remove();
}
