import { apiBaseUrl } from '../config/api.js';

export class ApiError extends Error {
    constructor(message, status, code = null, meta = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.meta = meta;
    }
}

export async function apiRequest(path, { token, body, idempotencyKey, method = 'GET' } = {}) {
    if (!apiBaseUrl) {
        throw new ApiError('The game server is not configured.', 0);
    }

    let response;

    try {
        response = await fetch(`${apiBaseUrl}${path}`, {
            method,
            headers: {
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
                ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}),
            },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });
    } catch {
        throw new ApiError('Could not reach the game server. Check your connection and try again.', 0);
    }

    if (response.status === 204) {
        return null;
    }

    let payload;

    try {
        payload = await response.json();
    } catch {
        throw new ApiError('The game server returned an invalid response.', response.status);
    }

    if (!response.ok) {
        throw new ApiError(payload.message ?? 'The request could not be completed.', response.status, payload.code ?? null, payload.meta ?? {});
    }

    return payload.data;
}
