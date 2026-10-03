import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { catalogs } from '../src/i18n/catalogs.js';
import { formatCoins, getLocale, setLocale, t, translatedApiError } from '../src/i18n/index.js';

globalThis.document = {
    documentElement: {},
    title: '',
    querySelector: () => null,
};

test('each supported locale contains the same complete set of UI messages', () => {
    const locales = Object.keys(catalogs);
    const expectedKeys = Object.keys(catalogs.en).sort();

    assert.deepEqual(locales.sort(), ['en', 'ru', 'tj']);

    for (const locale of locales) {
        assert.deepEqual(Object.keys(catalogs[locale]).sort(), expectedKeys, `catalog ${locale} must be complete`);

        for (const [key, message] of Object.entries(catalogs[locale])) {
            assert.equal(typeof message, 'string', `${locale}.${key} must be a string`);
            assert.notEqual(message.trim(), '', `${locale}.${key} must not be empty`);
        }
    }
});

test('mobile code references every catalog key and does not leave unused translations', () => {
    const sourcePaths = [
        new URL('../src/main.js', import.meta.url),
        new URL('../src/i18n/index.js', import.meta.url),
    ];
    const source = sourcePaths.map((path) => readFileSync(fileURLToPath(path), 'utf8')).join('\n');
    const referencedKeys = [...source.matchAll(/['"]([A-Za-z][\w]*(?:\.[A-Za-z][\w]*)+)['"]/g)]
        .map((match) => match[1]);

    assert.deepEqual([...new Set(referencedKeys)].sort(), Object.keys(catalogs.en).sort());

    const mainSource = readFileSync(fileURLToPath(sourcePaths[0]), 'utf8');
    const visibleLiterals = [...mainSource.matchAll(/>([^<{]+)</g)]
        .map((match) => match[1].trim())
        .filter(Boolean);
    const allowedNativeLabelsAndIcons = new Set(['⚙', '●', 'A', '60', 'Русский', 'Тоҷикӣ', 'English']);

    assert.ok(visibleLiterals.every((text) => allowedNativeLabelsAndIcons.has(text)), 'visible UI text must come from locale catalogs');
});

test('every locale uses the same interpolation placeholders for each message', () => {
    const keys = Object.keys(catalogs.en);

    for (const key of keys) {
        const placeholdersByLocale = Object.values(catalogs).map((catalog) => [
            ...catalog[key].matchAll(/\{([a-zA-Z0-9_]+)\}/g),
        ].map((match) => match[1]).sort());

        assert.ok(placeholdersByLocale.every((placeholders) => (
            JSON.stringify(placeholders) === JSON.stringify(placeholdersByLocale[0])
        )), `placeholder mismatch for ${key}`);
    }
});

test('locale selection updates document language and title without a fallback', () => {
    setLocale('tj');

    assert.equal(getLocale(), 'tj');
    assert.equal(document.documentElement.lang, 'tj');
    assert.equal(document.title, catalogs.tj['app.name']);
    assert.match(t('settings.title'), /Танзимот/);
    assert.throws(() => setLocale('fr'), /Unsupported mobile locale/);
    assert.throws(() => t('missing.key'), /Missing translation key/);
});

test('localized messages interpolate values and show the server hint price in each locale', () => {
    for (const locale of Object.keys(catalogs)) {
        setLocale(locale);
        assert.match(t('game.revealLetterAria', { price: 60 }), /60/);
        assert.equal(translatedApiError({ status: 0 }), t('errors.network'));
        assert.notEqual(formatCoins(1234), '1234');
    }

    setLocale('ru');
    assert.match(formatCoins(1), /1 монета$/);
    assert.match(formatCoins(2), /2 монеты$/);
    assert.match(formatCoins(5), /5 монет$/);
});
