package com.example.fourpictureoneword;

import android.content.SharedPreferences;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.util.Base64;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.nio.ByteBuffer;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

@CapacitorPlugin(name = "SecureTokenStorage")
public class SecureTokenStoragePlugin extends Plugin {
    private static final String KEY_ALIAS = "four-picture-one-word-token";
    private static final String PREFERENCES_NAME = "secure_guest_session";
    private static final String TOKEN_KEY = "encrypted_token";
    private static final Object STORAGE_LOCK = new Object();

    @PluginMethod
    public void getToken(PluginCall call) {
        synchronized (STORAGE_LOCK) {
            try {
                String encryptedToken = preferences().getString(TOKEN_KEY, null);
                JSObject result = new JSObject();

                result.put("value", encryptedToken == null ? null : decrypt(encryptedToken));
                call.resolve(result);
            } catch (Exception exception) {
                call.reject("Could not read the secure guest token.", "secure_storage_read_failed", exception);
            }
        }
    }

    @PluginMethod
    public void setToken(PluginCall call) {
        String token = call.getString("value");

        if (token == null || token.isEmpty()) {
            call.reject("A non-empty guest token is required.", "invalid_token");

            return;
        }

        synchronized (STORAGE_LOCK) {
            try {
                if (!preferences().edit().putString(TOKEN_KEY, encrypt(token)).commit()) {
                    throw new IllegalStateException("Secure token write did not complete.");
                }
                call.resolve();
            } catch (Exception exception) {
                call.reject("Could not save the secure guest token.", "secure_storage_write_failed", exception);
            }
        }
    }

    @PluginMethod
    public void removeToken(PluginCall call) {
        synchronized (STORAGE_LOCK) {
            try {
                if (!preferences().edit().remove(TOKEN_KEY).commit()) {
                    throw new IllegalStateException("Secure token removal did not complete.");
                }
                call.resolve();
            } catch (Exception exception) {
                call.reject("Could not remove the secure guest token.", "secure_storage_remove_failed", exception);
            }
        }
    }

    private SharedPreferences preferences() {
        return getContext().getSharedPreferences(PREFERENCES_NAME, android.content.Context.MODE_PRIVATE);
    }

    private String encrypt(String token) throws Exception {
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, secretKey());

        byte[] iv = cipher.getIV();
        byte[] encrypted = cipher.doFinal(token.getBytes(StandardCharsets.UTF_8));
        ByteBuffer payload = ByteBuffer.allocate(iv.length + encrypted.length);
        payload.put(iv);
        payload.put(encrypted);

        return Base64.encodeToString(payload.array(), Base64.NO_WRAP);
    }

    private String decrypt(String encryptedToken) throws Exception {
        byte[] payload = Base64.decode(encryptedToken, Base64.NO_WRAP);
        ByteBuffer buffer = ByteBuffer.wrap(payload);
        byte[] iv = new byte[12];
        buffer.get(iv);
        byte[] encrypted = new byte[buffer.remaining()];
        buffer.get(encrypted);

        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.DECRYPT_MODE, secretKey(), new GCMParameterSpec(128, iv));

        return new String(cipher.doFinal(encrypted), StandardCharsets.UTF_8);
    }

    private SecretKey secretKey() throws Exception {
        KeyStore keyStore = KeyStore.getInstance("AndroidKeyStore");
        keyStore.load(null);

        if (keyStore.containsAlias(KEY_ALIAS)) {
            return (SecretKey) keyStore.getKey(KEY_ALIAS, null);
        }

        KeyGenerator keyGenerator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
        keyGenerator.init(new KeyGenParameterSpec.Builder(
                KEY_ALIAS,
                KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT
        )
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setRandomizedEncryptionRequired(true)
                .build());

        return keyGenerator.generateKey();
    }
}
