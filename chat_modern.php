<?php
require_once __DIR__ . '/SessionManager.php';
$session = SessionManager::getInstance();
if (!$session->isLoggedIn() || empty($session->get('active_chat'))) {
    header('Location: /?page=msg');
    exit;
}
?>

<div class="container">
  <div class="chat-container">
    <div class="chat-sidebar">
      <div class="p-2">
        <h3>Чат</h3>
        <button id="backBtn" class="btn btn-secondary" style="width: 100%; margin-top: 1rem;">← Назад к списку</button>
      </div>
    </div>
    
    <div class="chat-main">
      <div class="chat-header" style="padding: 0.5rem 1rem; background: var(--light); border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div style="min-width: 0; flex: 1;">
          <h5 id="chatTitle" style="margin: 0; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">💬 Диалог</h5>
          <small id="encryptionStatus" style="color: var(--success); font-size: 0.7rem;">🔒 <span id="keyStatus">не задан</span></small>
        </div>
        <div class="d-flex flex-wrap gap-1">
          <button class="btn btn-secondary btn-sm" onclick="changeKey()">🔑</button>
          <button class="btn btn-secondary btn-sm" id="groupSettingsBtn" onclick="editGroup()" style="display: none;">⚙️</button>
          <button class="btn btn-secondary btn-sm" id="viewMembersBtn" onclick="viewMembers()" style="display: none;">👥</button>
          <button class="btn btn-secondary btn-sm" id="addMemberBtn" onclick="addMember()" style="display: none;">+</button>
          <button class="btn btn-secondary btn-sm" onclick="startAudioCall()">📞</button>
          <button class="btn btn-secondary btn-sm" onclick="startVideoCall()">📹</button>
        </div>
      </div>
      
      <div class="chat-messages" id="chatBox">
        <!-- Сообщения загружаются здесь -->
      </div>
      
      <div class="chat-input-modern">
        <input type="file" id="fileInput" multiple hidden>
        <button type="button" class="attach-btn" onclick="document.getElementById('fileInput').click()">📎</button>
        <input type="text" id="msgInput" class="message-input" placeholder="Напишите сообщение..." maxlength="1000">
        <button class="send-btn" id="sendBtn">➤</button>
      </div>
      
      <div id="unsafeWarning" style="display: none; padding: 0.5rem; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: var(--radius); margin: 0.5rem; font-size: 0.8rem; color: #dc2626;">
        ⚠️ Ключ шифрования не задан. Сообщения будут отправляться без шифрования!
      </div>
      
      <div id="previewPanel" style="padding: 1rem; display: none; background: var(--gray-50); border-top: 1px solid var(--gray-200);"></div>
    </div>
  </div>
</div>

<!-- Key Modal -->
<div id="keyModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;">
  <div class="card" style="width: 400px;">
    <div class="card-body">
      <h3 class="card-title">🔐 Ключ шифрования</h3>
      <form id="keyForm">
        <div class="form-group">
          <label class="form-label">Ключ-фраза (необязательно)</label>
          <input type="password" class="form-input" name="passPhrase" placeholder="Оставьте пустым для обычного чата">
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Открыть чат</button>
      </form>
    </div>
  </div>
</div>

<!-- Group Settings Modal -->
<div id="groupModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
  <div class="card" style="width: 100%; max-width: 400px;">
    <div class="card-body">
      <h3 class="card-title">⚙️ Настройки группы</h3>
      <form id="groupForm">
        <div class="form-group" style="margin-bottom: 1rem;">
          <label class="form-label">Название</label>
          <input type="text" class="form-control" name="groupName" placeholder="Новое название">
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
          <label class="form-label">Аватар</label>
          <input type="file" class="form-control" name="avatar" accept="image/*">
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">Сохранить</button>
          <button type="button" class="btn btn-secondary flex-fill" onclick="closeGroupModal()">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Members Modal -->
<div id="viewMembersModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
  <div class="card" style="width: 100%; max-width: 400px;">
    <div class="card-body">
      <h3 class="card-title">👥 Участники</h3>
      <div id="membersList" style="max-height: 50vh; overflow-y: auto; margin: 1rem 0;"></div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-secondary flex-fill" onclick="closeViewMembersModal()">Закрыть</button>
        <button type="button" class="btn btn-danger flex-fill" onclick="leaveGroup()">Покинуть</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Member Modal -->
<div id="memberModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000;">
  <div class="card" style="width: 400px;">
    <div class="card-body">
      <h3 class="card-title">👥 Добавить участника</h3>
      <input type="text" id="memberSearch" class="form-input" placeholder="Поиск пользователей...">
      <div id="memberResults" style="max-height: 200px; overflow-y: auto; margin: 1rem 0;"></div>
      <button type="button" class="btn btn-secondary" onclick="closeMemberModal()">Закрыть</button>
    </div>
  </div>
</div>

<script>
let filesArray = [];

// Key Modal
document.getElementById('keyForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('chat', <?= json_encode($session->get('active_chat')) ?>);
  
  fetch('saveKey.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(result => {
    if (result.ok) {
      document.getElementById('keyModal').style.display = 'none';
      loadMessages(true);
    } else {
      alert('Ошибка: ' + JSON.stringify(result));
    }
  });
});

// File handling
document.getElementById('fileInput').addEventListener('change', function(e) {
  const previewPanel = document.getElementById('previewPanel');
  filesArray = Array.from(e.target.files);
  
  if (filesArray.length > 0) {
    previewPanel.style.display = 'block';
    previewPanel.innerHTML = `<p>Выбрано файлов: ${filesArray.length}</p>`;
  } else {
    previewPanel.style.display = 'none';
  }
});

// Send message
function sendMessage() {
  const msg = document.getElementById('msgInput').value.trim();
  if (!msg && !filesArray.length) return;

  const fd = new FormData();
  if (msg) fd.append('message', msg);
  
  filesArray.forEach(f => {
    if (f.type.startsWith('image/')) fd.append('photos[]', f);
    else if (f.type.startsWith('audio/')) fd.append('voice', f);
    else if (f.type.startsWith('video/')) fd.append('video', f);
    else fd.append('file', f);
  });

  document.getElementById('sendBtn').disabled = true;
  
  fetch('sendMessage.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(result => {
    if (result.ok) {
      document.getElementById('msgInput').value = '';
      document.getElementById('previewPanel').style.display = 'none';
      filesArray = [];
      loadMessages();
    } else {
      alert('Ошибка: ' + (result.error || 'unknown'));
    }
  })
  .finally(() => {
    document.getElementById('sendBtn').disabled = false;
  });
}

// Load messages
function loadMessages(first = false) {
  fetch('getMessages.php')
    .then(r => r.text())
    .then(html => {
      const chatBox = document.getElementById('chatBox');
      if (first || chatBox.innerHTML !== html) {
        chatBox.innerHTML = html;
        chatBox.scrollTop = chatBox.scrollHeight;
        
        // Отмечаем сообщения как прочитанные
        const chatId = <?= json_encode($session->get('active_chat')) ?>;
        if (chatId) {
          fetch('markAsRead.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'chat_id=' + encodeURIComponent(chatId)
          });
        }
      }
    });
}

// Event listeners
document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('msgInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
});

document.getElementById('backBtn').addEventListener('click', function() {
  fetch('leaveChat.php', {method: 'POST'})
    .finally(() => location.href = '/?page=msg');
});

// Auto-refresh messages
setInterval(() => loadMessages(), 10000);

// Проверка входящих звонков
setInterval(() => {
  if (!isCallActive) {
    fetch('checkCalls.php')
      .then(r => r.json())
      .then(data => {
        if (data.hasCall) {
          showIncomingCall(data.type);
        }
      })
      .catch(e => console.log('Call check error:', e));
  }
}, 2000);

function showIncomingCall(type) {
  if (confirm(`${type === 'video' ? '📹 Входящий видеозвонок' : '📞 Входящий аудиозвонок'}\n\nПринять звонок?`)) {
    initCall(type === 'video');
  }
}

// Video/Audio calls
let localStream = null;
let remoteStream = null;
let peerConnection = null;
let isCallActive = false;

function startAudioCall() {
  initCall(false);
}

function startVideoCall() {
  initCall(true);
}

async function initCall(withVideo) {
  if (isCallActive) {
    alert('Звонок уже активен');
    return;
  }
  
  // Полифилл для старых браузеров
  if (!navigator.mediaDevices) {
    navigator.mediaDevices = {};
  }
  if (!navigator.mediaDevices.getUserMedia) {
    navigator.mediaDevices.getUserMedia = function(constraints) {
      const getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
      if (!getUserMedia) {
        return Promise.reject(new Error('Нет доступа к медиа'));
      }
      return new Promise((resolve, reject) => {
        getUserMedia.call(navigator, constraints, resolve, reject);
      });
    };
  }
  
  try {
    // Получаем доступ к микрофону и камере
    localStream = await navigator.mediaDevices.getUserMedia({
      audio: true,
      video: withVideo
    });
    
    // Показываем интерфейс звонка
    showCallInterface(withVideo);
    
    // Создаем WebRTC соединение
    peerConnection = new RTCPeerConnection({
      iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });
    
    // Добавляем локальный поток
    localStream.getTracks().forEach(track => {
      peerConnection.addTrack(track, localStream);
    });
    
    // Обработка удаленного потока
    peerConnection.ontrack = (event) => {
      remoteStream = event.streams[0];
      document.getElementById('remoteVideo').srcObject = remoteStream;
    };
    
    // Отправляем уведомление о звонке
    sendCallNotification(withVideo ? 'video' : 'audio');
    
    isCallActive = true;
    
  } catch (error) {
    console.error('Call error:', error);
    let errorMsg = 'Ошибка доступа к камере/микрофону';
    
    if (error.name === 'NotAllowedError') {
      errorMsg = 'Доступ запрещен!\n\n1. Нажмите на иконку камеры в адресной строке\n2. Выберите "Разрешить"\n3. Попробуйте снова';
    } else if (error.name === 'NotFoundError') {
      errorMsg = 'Камера или микрофон не найдены. Проверьте подключение устройств.';
    } else if (error.name === 'NotSupportedError') {
      errorMsg = 'Ваш браузер не поддерживает видео/аудио звонки.';
    }
    
    alert(errorMsg);
  }
}

function showCallInterface(withVideo) {
  const callModal = document.createElement('div');
  callModal.id = 'callModal';
  callModal.style.cssText = `
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.9); z-index: 2000; display: flex;
    flex-direction: column; align-items: center; justify-content: center;
  `;
  
  callModal.innerHTML = `
    <div style="color: white; text-align: center; margin-bottom: 2rem;">
      <h3>${withVideo ? '📹 Видеозвонок' : '📞 Аудиозвонок'}</h3>
      <p>Ожидание ответа...</p>
    </div>
    
    <div style="display: flex; gap: 2rem; margin-bottom: 2rem;">
      ${withVideo ? '<video id="localVideo" autoplay muted style="width: 300px; height: 200px; border-radius: 8px;"></video>' : ''}
      ${withVideo ? '<video id="remoteVideo" autoplay style="width: 300px; height: 200px; border-radius: 8px;"></video>' : ''}
    </div>
    
    <div style="display: flex; gap: 1rem;">
      <button onclick="toggleMute()" class="btn btn-secondary">🔇 Микрофон</button>
      ${withVideo ? '<button onclick="toggleVideo()" class="btn btn-secondary">📹 Камера</button>' : ''}
      <button onclick="endCall()" class="btn" style="background: #ef4444; color: white;">📞 Завершить</button>
    </div>
  `;
  
  document.body.appendChild(callModal);
  
  if (withVideo && localStream) {
    document.getElementById('localVideo').srcObject = localStream;
  }
}

function toggleMute() {
  if (localStream) {
    const audioTrack = localStream.getAudioTracks()[0];
    if (audioTrack) {
      audioTrack.enabled = !audioTrack.enabled;
    }
  }
}

function toggleVideo() {
  if (localStream) {
    const videoTrack = localStream.getVideoTracks()[0];
    if (videoTrack) {
      videoTrack.enabled = !videoTrack.enabled;
    }
  }
}

function endCall() {
  if (localStream) {
    localStream.getTracks().forEach(track => track.stop());
    localStream = null;
  }
  
  if (peerConnection) {
    peerConnection.close();
    peerConnection = null;
  }
  
  const callModal = document.getElementById('callModal');
  if (callModal) {
    callModal.remove();
  }
  
  isCallActive = false;
  
  // Уведомляем о завершении звонка
  sendCallNotification('end');
}

function sendCallNotification(type) {
  fetch('callNotification.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'type=' + type + '&chat_id=' + encodeURIComponent(<?= json_encode($session->get('active_chat')) ?>)
  });
}

function changeKey() {
  document.getElementById('keyModal').style.display = 'flex';
}

function updateKeyStatus() {
  const keyStatus = document.getElementById('keyStatus');
  const unsafeWarning = document.getElementById('unsafeWarning');
  
  fetch('getKeyStatus.php')
    .then(r => r.json())
    .then(data => {
      keyStatus.textContent = data.hasKey ? 'задан' : 'не задан';
      unsafeWarning.style.display = data.hasKey ? 'none' : 'block';
    });
}

// Обновляем статус ключа при загрузке
setTimeout(updateKeyStatus, 500);

// Проверяем тип чата и обновляем заголовок
const chatId = <?= json_encode($session->get('active_chat')) ?>;
if (chatId && chatId.startsWith('group_')) {
  // Проверяем, является ли пользователь участником группы
  fetch('getGroupMembers.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'chat_id=' + encodeURIComponent(chatId)
  })
  .then(r => r.json())
  .then(members => {
    if (members.length > 0) {
      document.getElementById('groupSettingsBtn').style.display = 'inline-block';
      document.getElementById('viewMembersBtn').style.display = 'inline-block';
      document.getElementById('addMemberBtn').style.display = 'inline-block';
    }
  });
  
  // Обновляем название группы
  fetch('getGroupInfo.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'chat_id=' + encodeURIComponent(chatId)
  })
  .then(r => r.json())
  .then(info => {
    if (info.name) {
      document.getElementById('chatTitle').textContent = '👥 ' + info.name;
    }
  });
}

// Обновляем активность пользователя каждые 30 секунд
setInterval(() => {
  fetch('updateActivity.php');
}, 30000);

// Управление группой
function editGroup() {
  document.getElementById('groupModal').style.display = 'flex';
}

function closeGroupModal() {
  document.getElementById('groupModal').style.display = 'none';
}

function addMember() {
  document.getElementById('memberModal').style.display = 'flex';
}

function closeMemberModal() {
  document.getElementById('memberModal').style.display = 'none';
}

function viewMembers() {
  document.getElementById('viewMembersModal').style.display = 'flex';
  loadGroupMembers();
}

function closeViewMembersModal() {
  document.getElementById('viewMembersModal').style.display = 'none';
}

function loadGroupMembers() {
  fetch('getGroupMembers.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'chat_id=' + encodeURIComponent(<?= json_encode($session->get('active_chat')) ?>)
  })
  .then(r => r.json())
  .then(members => {
    let html = '';
    const currentUserId = <?= json_encode($session->getUserId()) ?>;
    let isCurrentUserAdmin = false;
    
    // Определяем, является ли текущий пользователь админом
    members.forEach(member => {
      if (member.id === currentUserId && member.role === 'admin') {
        isCurrentUserAdmin = true;
      }
    });
    
    members.forEach(member => {
      const onlineStatus = member.online ? '🟢' : '🔴';
      const isCurrentUser = member.id === currentUserId;
      
      html += `
        <div style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e5e7eb; margin: 0.25rem 0; border-radius: 8px;">
          <img src="${member.photo || 'img/default-avatar.svg'}" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 0.75rem;" onerror="this.src='img/default-avatar.svg'">
          <div style="flex: 1;">
            <strong>${member.role_icon} ${member.login}</strong> ${onlineStatus}
            <div style="font-size: 0.8rem; color: #666;">${member.about || 'Описание не указано'}</div>
            <div style="font-size: 0.7rem; color: #999;">Роль: ${member.role}</div>
          </div>
      `;
      
      // Кнопки управления для админов
      if (isCurrentUserAdmin && !isCurrentUser && member.role !== 'admin') {
        html += `
          <div style="display: flex; gap: 0.25rem;">
            <button onclick="removeMember(${member.id})" style="background: #ef4444; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; cursor: pointer; font-size: 0.7rem;" title="Удалить">✖</button>
          </div>
        `;
      }
      
      html += '</div>';
    });
    
    document.getElementById('membersList').innerHTML = html || '<p>Нет участников</p>';
  })
  .catch(e => {
    document.getElementById('membersList').innerHTML = '<p>Ошибка загрузки</p>';
  });
}

// Редактирование сообщений
function editMessage(messageId) {
  const newText = prompt('Редактировать сообщение:');
  if (newText !== null && newText.trim()) {
    fetch('editMessage.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'message_id=' + messageId + '&new_text=' + encodeURIComponent(newText.trim())
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        loadMessages();
      } else {
        alert('Ошибка: ' + result.message);
      }
    });
  }
}

function deleteMessage(messageId) {
  if (confirm('Удалить сообщение?')) {
    fetch('deleteMessage.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'message_id=' + messageId
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        loadMessages();
      } else {
        alert('Ошибка: ' + result.message);
      }
    });
  }
}

// Обработка формы настроек группы
document.getElementById('groupForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('chat_id', <?= json_encode($session->get('active_chat')) ?>);
  
  fetch('updateGroup.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      alert('✅ Настройки сохранены');
      closeGroupModal();
      location.reload();
    } else {
      alert('❌ Ошибка: ' + result.message);
    }
  });
});

// Поиск участников
document.getElementById('memberSearch').addEventListener('input', function() {
  const query = this.value.trim();
  if (query.length < 2) {
    document.getElementById('memberResults').innerHTML = '';
    return;
  }
  
  fetch('searchUser.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'search=' + encodeURIComponent(query)
  })
  .then(r => r.json())
  .then(users => {
    let html = '';
    users.forEach(user => {
      html += `
        <div style="padding: 0.5rem; border: 1px solid #ddd; margin: 0.25rem 0; cursor: pointer;" onclick="addUserToGroup(${user.id})">
          <strong>${user.login}</strong>
        </div>
      `;
    });
    document.getElementById('memberResults').innerHTML = html;
  });
});

function addUserToGroup(userId) {
  fetch('addToGroup.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'chat_id=' + encodeURIComponent(<?= json_encode($session->get('active_chat')) ?>) + '&user_id=' + userId
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      alert('✅ Пользователь добавлен');
      closeMemberModal();
    } else {
      alert('❌ Ошибка: ' + result.message);
    }
  });
}

function removeMember(userId) {
  if (confirm('Удалить участника из группы?')) {
    fetch('removeMember.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'chat_id=' + encodeURIComponent(<?= json_encode($session->get('active_chat')) ?>) + '&user_id=' + userId
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        alert('✅ Участник удален');
        loadGroupMembers(); // Перезагружаем список
      } else {
        alert('❌ Ошибка: ' + result.message);
      }
    });
  }
}

function leaveGroup() {
  if (confirm('Вы уверены, что хотите покинуть группу?')) {
    fetch('leaveGroup.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'chat_id=' + encodeURIComponent(<?= json_encode($session->get('active_chat')) ?>)
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        alert('✅ Вы покинули группу');
        location.href = '/?page=msg';
      } else {
        alert('❌ Ошибка: ' + result.message);
      }
    });
  }
}
</script>

<style>
#chatBox {
  height: 500px;
  overflow-y: auto;
  padding: 1rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  background-attachment: fixed;
}

.message {
  display: flex;
  margin-bottom: 0.75rem;
  max-width: 70%;
  animation: slideIn 0.3s ease;
}

.message.own {
  margin-left: auto;
  flex-direction: row-reverse;
}

.message-bubble {
  background: white;
  padding: 0.75rem 1rem;
  border-radius: 18px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  position: relative;
  word-wrap: break-word;
}

.message.own .message-bubble {
  background: #007bff;
  color: white;
}

.message-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  margin: 0 0.5rem;
  background: #ddd;
  flex-shrink: 0;
}

.message img, .message video {
  max-width: 250px;
  border-radius: 12px;
  margin-top: 0.5rem;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.chat-input-modern {
  display: flex;
  align-items: center;
  padding: 1rem;
  background: white;
  border-top: 1px solid #e5e7eb;
  gap: 0.75rem;
}

.attach-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: #f3f4f6;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  transition: all 0.2s;
}

.attach-btn:hover {
  background: #e5e7eb;
  transform: scale(1.05);
}

.message-input {
  flex: 1;
  padding: 0.75rem 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 25px;
  outline: none;
  font-size: 1rem;
  background: #f9fafb;
  transition: all 0.2s;
}

.message-input:focus {
  border-color: #3b82f6;
  background: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.send-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: #3b82f6;
  color: white;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  transition: all 0.2s;
}

.send-btn:hover {
  background: #2563eb;
  transform: scale(1.05);
}

.send-btn:disabled {
  background: #9ca3af;
  cursor: not-allowed;
  transform: none;
}

.unsafe-message {
  background: rgba(239, 68, 68, 0.1);
  border-left: 3px solid #ef4444;
  padding-left: 0.5rem;
  position: relative;
  cursor: help;
}

.unsafe-icon {
  margin-right: 0.25rem;
  font-size: 0.8rem;
}

.unsafe-message:hover {
  background: rgba(239, 68, 68, 0.15);
}
</style>