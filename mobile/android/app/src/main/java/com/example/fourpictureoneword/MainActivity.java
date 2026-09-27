package com.example.fourpictureoneword;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(android.os.Bundle savedInstanceState) {
        registerPlugin(SecureTokenStoragePlugin.class);
        super.onCreate(savedInstanceState);
    }
}
