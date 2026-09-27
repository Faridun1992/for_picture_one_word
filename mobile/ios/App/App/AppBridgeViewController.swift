import Capacitor

class AppBridgeViewController: CAPBridgeViewController {
    override func capacitorDidLoad() {
        bridge?.registerPluginType(SecureTokenStoragePlugin.self)
    }
}
