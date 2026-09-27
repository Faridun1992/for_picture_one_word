import { registerPlugin } from '@capacitor/core';

let browserToken = null;

const SecureTokenStorage = registerPlugin('SecureTokenStorage', {
    web: () => ({
        async getToken() {
            return { value: browserToken };
        },
        async setToken({ value }) {
            browserToken = value;
        },
        async removeToken() {
            browserToken = null;
        },
    }),
});

export const secureTokenStorage = {
    async get() {
        const result = await SecureTokenStorage.getToken();

        return result.value ?? null;
    },
    async set(token) {
        await SecureTokenStorage.setToken({ value: token });
    },
    async remove() {
        await SecureTokenStorage.removeToken();
    },
};
