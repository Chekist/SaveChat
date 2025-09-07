let currentUser = '';
let crypto = new P2PCrypto();
let peerConnection = null;
let localStream = null;
let pollingInterval = null;
let incomingCall = null;
let callTimeout = null;

const config = {
    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
};

async function register() {
    currentUser = document.getElementById('username').value;
    if (!currentUser) return;
    
    let publicKey;
    try {
        await crypto.generateKeyPair();
        publicKey = await crypto.exportPublicKey();
    } catch (error) {
        console.error('Crypto error:', error.message);
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'register');
    formData.append('username', currentUser);
    formData.append('public_key', publicKey);
    
    const response = await fetch('signal.php', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        document.getElementById('login').style.display = 'none';
        document.getElementById('app').style.display = 'block';
        startPolling();
        loadUsers();
    }
}

async function loadUsers() {
    try {
        const response = await fetch(`signal.php?action=get_users`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const users = await response.json();
        
        const usersDiv = document.getElementById('users');
        // amazonq-ignore-next-line
        usersDiv.innerHTML = '';
        
        users.forEach(user => {
            if (user.username !== currentUser) {
                const button = document.createElement('button');
                button.textContent = `Позвонить ${user.username}`;
                button.onclick = () => startCall(user.username, user.public_key);
                usersDiv.appendChild(button);
            }
        });
    } catch (error) {
        console.error('Failed to load users:', error);
    }
}

async function startCall(targetUser, targetPublicKey) {
    try {
        await crypto.importPublicKey(targetPublicKey);
        
        peerConnection = new RTCPeerConnection(config);
        
        peerConnection.ontrack = event => {
            document.getElementById('remoteAudio').srcObject = event.streams[0];
        };
        
        peerConnection.onicecandidate = async event => {
            if (event.candidate) {
                const encrypted = await crypto.encrypt(JSON.stringify(event.candidate));
                sendSignal(targetUser, 'ice-candidate', encrypted);
            }
        };
        
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        document.getElementById('localAudio').srcObject = localStream;
        localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
        
        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);
        
        const encryptedOffer = await crypto.encrypt(JSON.stringify(offer));
        sendSignal(targetUser, 'offer', encryptedOffer);
        
        document.getElementById('hangup').style.display = 'block';
        
        callTimeout = setTimeout(() => {
            console.log('Абонент не отвечает');
            hangup();
        }, 30000);
    } catch (error) {
        console.error('Call failed:', error);
        let errorMsg = 'Ошибка звонка: ';
        if (error.name === 'NotAllowedError') {
            errorMsg += 'Нет доступа к микрофону';
        } else if (error.name === 'NotFoundError') {
            errorMsg += 'Микрофон не найден';
        } else if (error.name === 'NotSupportedError') {
            errorMsg += 'Браузер не поддерживает WebRTC';
        } else if (error.name === 'OperationError') {
            errorMsg += 'Ошибка WebRTC соединения. Попробуйте перезагрузить страницу';
        } else {
            errorMsg += error.message;
        }
        console.error(errorMsg);
        hangup();
    }
}

async function sendSignal(to, type, data) {
    const formData = new FormData();
    formData.append('action', 'send_signal');
    formData.append('from', currentUser);
    formData.append('to', to);
    formData.append('type', type);
    formData.append('data', data);
    
    await fetch('signal.php', { method: 'POST', body: formData });
}

async function handleSignal(signal) {
    const senderPublicKey = await getUserPublicKey(signal.from_user);
    await crypto.importPublicKey(senderPublicKey);
    
    const decryptedData = await crypto.decrypt(signal.signal_data);
    const data = JSON.parse(decryptedData);
    
    if (signal.signal_type === 'offer') {
        incomingCall = { from: signal.from_user, data: data };
        showIncomingCall(signal.from_user);
        startRinging();
    } else if (signal.signal_type === 'answer') {
        try {
            await peerConnection.setRemoteDescription(data);
        } catch (error) {
            console.error('Failed to set remote description:', error);
        }
    } else if (signal.signal_type === 'ice-candidate') {
        try {
            await peerConnection.addIceCandidate(data);
        } catch (error) {
            console.error('Failed to add ICE candidate:', error);
        }
    } else if (signal.signal_type === 'reject') {
        console.log('Звонок отклонен');
        hangup();
    } else if (signal.signal_type === 'hangup') {
        hangup();
    }
}

async function getUserPublicKey(username) {
    const response = await fetch(`signal.php?action=get_users`);
    const users = await response.json();
    const user = users.find(u => u.username === username);
    return user ? user.public_key : null;
}

function startPolling() {
    pollingInterval = setInterval(async () => {
        try {
            const response = await fetch(`signal.php?action=get_signals&username=${encodeURIComponent(currentUser)}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const signals = await response.json();
            
            for (const signal of signals) {
                await handleSignal(signal);
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 1000);
}

function showIncomingCall(caller) {
    document.getElementById('incomingCall').style.display = 'block';
    document.getElementById('callerName').textContent = caller;
}

function hideIncomingCall() {
    document.getElementById('incomingCall').style.display = 'none';
    stopRinging();
}

let ringtoneElement = null;

function startRinging() {
    if (!ringtoneElement) {
        ringtoneElement = document.getElementById('ringtone');
    }
    if (ringtoneElement) {
        ringtoneElement.loop = true;
        // amazonq-ignore-next-line
        ringtoneElement.play();
    }
    
    callTimeout = setTimeout(() => {
        rejectCall();
    }, 30000);
}

function stopRinging() {
    if (ringtoneElement) {
        ringtoneElement.pause();
        ringtoneElement.currentTime = 0;
    }
    
    if (callTimeout) {
        clearTimeout(callTimeout);
        callTimeout = null;
    }
}

async function acceptCall() {
    if (!incomingCall) return;
    
    try {
        hideIncomingCall();
        
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        document.getElementById('localAudio').srcObject = localStream;
        
        peerConnection = new RTCPeerConnection(config);
        
        peerConnection.ontrack = event => {
            document.getElementById('remoteAudio').srcObject = event.streams[0];
        };
        
        peerConnection.onicecandidate = async event => {
            if (event.candidate) {
                const encrypted = await crypto.encrypt(JSON.stringify(event.candidate));
                sendSignal(incomingCall.from, 'ice-candidate', encrypted);
            }
        };
        
        localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
        
        await peerConnection.setRemoteDescription(incomingCall.data);
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);
        
        const encryptedAnswer = await crypto.encrypt(JSON.stringify(answer));
        sendSignal(incomingCall.from, 'answer', encryptedAnswer);
        
        document.getElementById('hangup').style.display = 'block';
        // amazonq-ignore-next-line
        incomingCall = null;
    } catch (error) {
        console.error('Failed to accept call:', error);
        let errorMsg = 'Ошибка принятия звонка: ';
        if (error.name === 'NotAllowedError') {
            errorMsg += 'Нет доступа к микрофону';
        } else if (error.name === 'NotFoundError') {
            errorMsg += 'Микрофон не найден';
        } else {
            errorMsg += error.message;
        }
        alert(errorMsg);
        rejectCall();
    }
}

function rejectCall() {
    if (incomingCall) {
        sendSignal(incomingCall.from, 'reject', '');
        incomingCall = null;
    }
    hideIncomingCall();
}

function hangup() {
    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }
    
    sendSignal('', 'hangup', '');
    
    document.getElementById('hangup').style.display = 'none';
    document.getElementById('localAudio').srcObject = null;
    document.getElementById('remoteAudio').srcObject = null;
    hideIncomingCall();
}