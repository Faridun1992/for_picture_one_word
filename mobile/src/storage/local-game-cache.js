const databaseName = 'four-pictures-one-word-cache';
const storeName = 'records';
const idempotencyRetentionMs = 30 * 24 * 60 * 60 * 1000;
const memoryRecords = new Map();

function openDatabase() {
    return new Promise((resolve, reject) => {
        if (!('indexedDB' in globalThis)) {
            reject(new Error('IndexedDB is unavailable.'));

            return;
        }

        const request = indexedDB.open(databaseName, 1);

        request.onupgradeneeded = () => {
            request.result.createObjectStore(storeName, { keyPath: 'key' });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function readRecord(key) {
    try {
        const database = await openDatabase();

        return await new Promise((resolve, reject) => {
            const request = database.transaction(storeName, 'readonly').objectStore(storeName).get(key);
            request.onsuccess = () => resolve(request.result?.value ?? null);
            request.onerror = () => reject(request.error);
        });
    } catch {
        return memoryRecords.get(key) ?? null;
    }
}

async function writeRecord(key, value) {
    memoryRecords.set(key, value);

    try {
        const database = await openDatabase();

        await new Promise((resolve, reject) => {
            const request = database.transaction(storeName, 'readwrite').objectStore(storeName).put({ key, value });
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    } catch {
        // The in-memory copy still supports the current app session.
    }
}

async function deleteRecord(key) {
    memoryRecords.delete(key);

    try {
        const database = await openDatabase();

        await new Promise((resolve, reject) => {
            const request = database.transaction(storeName, 'readwrite').objectStore(storeName).delete(key);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    } catch {
        // Cache cleanup must not prevent logout.
    }
}

function playerPrefix(playerId) {
    return `player:${playerId}:`;
}

export const localGameCache = {
    async savePlayer(player, progress) {
        if (!player?.id) {
            return;
        }

        await writeRecord(`${playerPrefix(player.id)}home`, { player, progress });
        await writeRecord('last-player-id', player.id);
    },

    async loadLastHome() {
        const playerId = await readRecord('last-player-id');

        return playerId ? readRecord(`${playerPrefix(playerId)}home`) : null;
    },

    async saveCategories(playerId, locale, categories) {
        await writeRecord(`${playerPrefix(playerId)}categories:${locale}`, categories);
    },

    async loadCategories(playerId, locale) {
        return readRecord(`${playerPrefix(playerId)}categories:${locale}`);
    },

    async saveLevels(playerId, locale, categoryId, levels) {
        await writeRecord(`${playerPrefix(playerId)}levels:${locale}:${categoryId}`, levels);
    },

    async loadLevels(playerId, locale, categoryId) {
        return readRecord(`${playerPrefix(playerId)}levels:${locale}:${categoryId}`);
    },

    async saveLevel(playerId, locale, level) {
        await writeRecord(`${playerPrefix(playerId)}level:${locale}:${level.id}`, level);
    },

    async loadLevel(playerId, locale, levelId) {
        return readRecord(`${playerPrefix(playerId)}level:${locale}:${levelId}`);
    },

    async saveDraft(playerId, locale, level, selectedTileIds, revealedLetters) {
        await writeRecord(`${playerPrefix(playerId)}draft:${locale}:${level.id}`, {
            contentVersion: level.content_version,
            selectedTileIds,
            revealedLetters,
            letterTiles: level.letter_tiles,
        });
    },

    async loadDraft(playerId, locale, level) {
        const key = `${playerPrefix(playerId)}draft:${locale}:${level.id}`;
        const draft = await readRecord(key);

        if (!draft || draft.contentVersion !== level.content_version) {
            await deleteRecord(key);

            return null;
        }

        const validTileIds = new Set(level.letter_tiles.map((_, index) => index));

        return {
            selectedTileIds: Array.from({ length: level.answer_length }, (_, index) => {
                const tileId = draft.selectedTileIds?.[index];

                return Number.isInteger(tileId) && validTileIds.has(tileId) ? tileId : null;
            }),
            revealedLetters: draft.revealedLetters ?? {},
            letterTiles: Array.isArray(draft.letterTiles) ? draft.letterTiles : level.letter_tiles,
        };
    },

    async clearDraft(playerId, locale, levelId) {
        await deleteRecord(`${playerPrefix(playerId)}draft:${locale}:${levelId}`);
    },

    async savePendingOperation(playerId, operation) {
        const key = `${playerPrefix(playerId)}pending-operation`;
        const existing = await readRecord(key);
        const createdAt = existing?.key === operation.key ? existing.createdAt : Date.now();

        await writeRecord(key, { ...operation, createdAt });
    },

    async loadPendingOperation(playerId) {
        const key = `${playerPrefix(playerId)}pending-operation`;
        const operation = await readRecord(key);

        if (!operation) {
            return null;
        }

        if (!Number.isFinite(operation.createdAt) || Date.now() - operation.createdAt > idempotencyRetentionMs) {
            await deleteRecord(key);

            return null;
        }

        return operation;
    },

    async clearPendingOperation(playerId) {
        await deleteRecord(`${playerPrefix(playerId)}pending-operation`);
    },

    async clearPlayer(playerId) {
        if (!playerId) {
            return;
        }

        const prefix = playerPrefix(playerId);

        for (const key of memoryRecords.keys()) {
            if (key.startsWith(prefix)) {
                memoryRecords.delete(key);
            }
        }

        const lastPlayerId = await readRecord('last-player-id');

        if (lastPlayerId === playerId) {
            await deleteRecord('last-player-id');
        }

        try {
            const database = await openDatabase();

            await new Promise((resolve, reject) => {
                const transaction = database.transaction(storeName, 'readwrite');
                const request = transaction.objectStore(storeName).openCursor();

                request.onsuccess = () => {
                    const cursor = request.result;

                    if (cursor) {
                        if (String(cursor.key).startsWith(prefix)) {
                            cursor.delete();
                        }

                        cursor.continue();
                    }
                };
                transaction.oncomplete = () => resolve();
                transaction.onerror = () => reject(transaction.error);
            });
        } catch {
            // Cache cleanup must not prevent logout.
        }
    },
};
