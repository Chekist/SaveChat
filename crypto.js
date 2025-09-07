class P2PCrypto {
    constructor() {
        this.keyPair = null;
        this.remotePublicKey = null;
        this.isSecureContext = this.checkSecureContext();
    }
    
    checkSecureContext() {
        return window.isSecureContext && window.crypto && window.crypto.subtle;
    }
    
    async generateKeyPair() {
        if (!this.isSecureContext) {
            throw new Error('Web Crypto API requires HTTPS. Please serve this page over HTTPS.');
        }
        this.keyPair = await window.crypto.subtle.generateKey(
            {
                name: "RSA-OAEP",
                modulusLength: 2048,
                publicExponent: new Uint8Array([1, 0, 1]),
                hash: "SHA-256"
            },
            true,
            ["encrypt", "decrypt"]
        );
        return this.keyPair;
    }
    
    async exportPublicKey() {
        if (!this.keyPair || !this.keyPair.publicKey) {
            throw new Error('Key pair not generated');
        }
        const exported = await window.crypto.subtle.exportKey("spki", this.keyPair.publicKey);
        return btoa(String.fromCharCode(...new Uint8Array(exported)));
    }
    
    async importPublicKey(keyData) {
        // amazonq-ignore-next-line
        const keyBuffer = new Uint8Array(atob(keyData).split('').map(c => c.charCodeAt(0)));
        this.remotePublicKey = await window.crypto.subtle.importKey(
            "spki",
            keyBuffer,
            { name: "RSA-OAEP", hash: "SHA-256" },
            false,
            ["encrypt"]
        );
    }
    
    async encrypt(data) {
        if (!this.remotePublicKey) {
            throw new Error('Remote public key not imported');
        }
        const encoded = new TextEncoder().encode(data);
        const encrypted = await window.crypto.subtle.encrypt(
            { name: "RSA-OAEP" },
            this.remotePublicKey,
            encoded
        );
        return btoa(String.fromCharCode(...new Uint8Array(encrypted)));
    }
    
    async decrypt(encryptedData) {
        if (!this.keyPair || !this.keyPair.privateKey) {
            throw new Error('Key pair not generated');
        }
        try {
            const encrypted = new Uint8Array(atob(encryptedData).split('').map(c => c.charCodeAt(0)));
            const decrypted = await window.crypto.subtle.decrypt(
                { name: "RSA-OAEP" },
                this.keyPair.privateKey,
                encrypted
            );
            return new TextDecoder().decode(decrypted);
        } catch (error) {
            throw new Error('Failed to decrypt data: ' + error.message);
        }
    }
}