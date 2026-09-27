import Capacitor
import Foundation
import Security

@objc(SecureTokenStoragePlugin)
public class SecureTokenStoragePlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "SecureTokenStoragePlugin"
    public let jsName = "SecureTokenStorage"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "getToken", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "setToken", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "removeToken", returnType: CAPPluginReturnPromise)
    ]

    private var service: String {
        "\(Bundle.main.bundleIdentifier ?? "com.example.fourpictureoneword").guest-token"
    }

    @objc func getToken(_ call: CAPPluginCall) {
        var query = keychainQuery
        query[kSecReturnData as String] = true
        query[kSecMatchLimit as String] = kSecMatchLimitOne

        var result: CFTypeRef?
        let status = SecItemCopyMatching(query as CFDictionary, &result)

        if status == errSecItemNotFound {
            call.resolve(["value": NSNull()])
        } else if status == errSecSuccess,
                  let data = result as? Data,
                  let token = String(data: data, encoding: .utf8) {
            call.resolve(["value": token])
        } else {
            call.reject("Could not read the secure guest token.", "secure_storage_read_failed")
        }
    }

    @objc func setToken(_ call: CAPPluginCall) {
        guard let token = call.getString("value"), !token.isEmpty else {
            call.reject("A non-empty guest token is required.", "invalid_token")

            return
        }

        let data = Data(token.utf8)
        let query = keychainQuery
        let attributes: [String: Any] = [
            kSecValueData as String: data,
            kSecAttrAccessible as String: kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
        ]
        let updateStatus = SecItemUpdate(query as CFDictionary, attributes as CFDictionary)
        let status = updateStatus == errSecItemNotFound
            ? SecItemAdd((query.merging(attributes) { _, new in new }) as CFDictionary, nil)
            : updateStatus

        if status == errSecSuccess {
            call.resolve()
        } else {
            call.reject("Could not save the secure guest token.", "secure_storage_write_failed")
        }
    }

    @objc func removeToken(_ call: CAPPluginCall) {
        let status = SecItemDelete(keychainQuery as CFDictionary)

        if status == errSecSuccess || status == errSecItemNotFound {
            call.resolve()
        } else {
            call.reject("Could not remove the secure guest token.", "secure_storage_remove_failed")
        }
    }

    private var keychainQuery: [String: Any] {
        [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: "current"
        ]
    }
}
