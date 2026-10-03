import { catalogs } from './catalogs.js';

let activeLocale = 'ru';

export function setLocale(locale) {
    if (!Object.hasOwn(catalogs, locale)) {
        throw new Error(`Unsupported mobile locale: ${locale}`);
    }

    activeLocale = locale;
    document.documentElement.lang = locale;
    document.title = catalogs[locale]['app.name'];
    document.querySelector('#app')?.setAttribute('aria-label', catalogs[locale]['app.name']);
}

export function getLocale() {
    return activeLocale;
}

export function t(key, values = {}) {
    const message = catalogs[activeLocale][key];

    if (typeof message !== 'string') {
        throw new Error(`Missing translation key "${key}" for locale "${activeLocale}".`);
    }

    return message.replace(/\{([a-zA-Z0-9_]+)\}/g, (placeholder, name) => {
        if (!Object.hasOwn(values, name)) {
            throw new Error(`Missing value "${name}" for translation key "${key}".`);
        }

        return String(values[name]);
    });
}

export function formatNumber(value) {
    return new Intl.NumberFormat(activeLocale).format(Number(value));
}

export function formatCoins(value) {
    const amount = Number(value);
    const displayAmount = formatNumber(amount);

    if (activeLocale === 'ru') {
        const mod100 = Math.abs(amount) % 100;
        const mod10 = Math.abs(amount) % 10;
        const unit = mod100 >= 11 && mod100 <= 14 ? 'монет'
            : (mod10 === 1 ? 'монета' : (mod10 >= 2 && mod10 <= 4 ? 'монеты' : 'монет'));

        return `${displayAmount} ${unit}`;
    }

    return `${displayAmount} ${t('home.coins')}`;
}

export function formatLevelMeta(answerLength, difficulty) {
    return t('game.levelMeta', {
        letters: formatNumber(answerLength),
        difficulty: formatNumber(difficulty),
    });
}

export function translatedApiError(error) {
    if (error?.status === 0) {
        return t('errors.network');
    }

    if (error?.code === 'insufficient_coins') {
        return t('errors.insufficientCoins', {
            required: formatNumber(error.meta?.required ?? 0),
            balance: formatNumber(error.meta?.balance ?? 0),
        });
    }

    return t('errors.generic');
}
