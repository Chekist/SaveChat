<?php
require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/SecurityHelper.php';
require_once __DIR__ . '/LocalDatabase.php';

$session = SessionManager::getInstance();
$session->requireAuth();

$userId = $session->getUserId();
?>
<div class='container reg'>
    <h2>Сообщения</h2>
    
    <div class="mb-3">
        <button class="btn btn-primary" onclick="createGroupChat()">Создать группу</button>
    </div>
    
    <div class="search-container mb-3">
        <input type="text" id="searchInput" class="form-input" placeholder="Поиск пользователей...">
        <div id="searchResults" class="search-results"></div>
    </div>
    
    <div class="chats-list" id="chatsList">
        <!-- Список чатов будет загружен здесь -->
    </div>
</div>

<style>
.search-results {
    position: absolute;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    width: 100%;
    display: none;
}
.search-result-item {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
}
.search-result-item:hover {
    background-color: #f5f5f5;
}
.search-result-item img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
}
.search-container {
    position: relative;
}
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    margin-left: 0.25rem;
}
.modern-chat {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: none;
    border-bottom: 1px solid #f0f0f0;
    margin-bottom: 0;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}

.modern-chat:hover {
    background: #f8f9fa;
}

.chat-avatar {
    position: relative;
    margin-right: 1rem;
}

.chat-avatar img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.chat-info {
    flex: 1;
    min-width: 0;
}

.chat-info h5 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1a1a1a;
}

.chat-preview {
    margin: 0;
    font-size: 0.875rem;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.chat-time {
    font-size: 0.75rem;
    color: #999;
}

.online-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: #10b981;
    border: 2px solid white;
    border-radius: 50%;
}

.offline-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: #6b7280;
    border: 2px solid white;
    border-radius: 50%;
}

.unread-badge {
    display: inline-block;
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border-radius: 10px;
    margin-left: 0.5rem;
    min-width: 18px;
    text-align: center;
}
</style>

<script>
let searchTimeout;
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length < 2) {
        searchResults.style.display = 'none';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        fetch('searchUser.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'search=' + encodeURIComponent(query)
        })
        .then(response => response.json())
        .then(users => {
            displaySearchResults(users);
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResults.style.display = 'none';
        });
    }, 300);
});

function displaySearchResults(users) {
    if (!Array.isArray(users) || users.length === 0) {
        searchResults.style.display = 'none';
        return;
    }
    
    let html = '';
    users.forEach(user => {
        // Безопасное экранирование данных
        const safePhoto = escapeHtml(user.photo || 'img/default-avatar.png');
        const safeLogin = escapeHtml(user.login || '');
        const safeId = parseInt(user.id) || 0;
        
        html += `
            <div class="search-result-item">
                <img src="${safePhoto}" alt="Avatar" onerror="this.src='img/default-avatar.svg'">
                <div style="flex: 1;">
                    <span>${safeLogin}</span>
                </div>
                <div>
                    <button class="btn btn-sm btn-secondary" onclick="viewProfile(${safeId})">Профиль</button>
                    <button class="btn btn-sm btn-primary" onclick="createDialog(${safeId})">Чат</button>
                </div>
            </div>
        `;
    });
    
    searchResults.innerHTML = html;
    searchResults.style.display = 'block';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function createDialog(userId) {
    if (!userId || userId <= 0) {
        alert('Неверный ID пользователя');
        return;
    }
    
    // Просто создаем chat_id и переходим в чат
    const chatId = 'dialog_' + userId + '_' + Date.now();
    
    fetch('setActiveChat.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'chat_id=' + encodeURIComponent(chatId)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.location.href = '/?page=chat';
        } else {
            alert('Ошибка: ' + (result.message || 'Неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Dialog creation error:', error);
        alert('Ошибка создания диалога');
    });
    
    searchResults.style.display = 'none';
    searchInput.value = '';
}

// Скрытие результатов при клике вне области поиска
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-container')) {
        searchResults.style.display = 'none';
    }
});

// Загрузка списка чатов
function loadChats() {
    fetch('getChat.php')
        .then(response => response.json())
        .then(chats => {
            displayChats(chats);
        })
        .catch(error => {
            console.error('Error loading chats:', error);
        });
}

function displayChats(chats) {
    const chatsList = document.getElementById('chatsList');
    
    console.log('Received chats:', chats);
    
    if (!Array.isArray(chats) || chats.length === 0) {
        chatsList.innerHTML = '<p>Нет активных чатов</p>';
        return;
    }
    
    let html = '';
    chats.forEach(chat => {
        const safeName = escapeHtml(chat.name || 'Неизвестный чат');
        const safePreview = escapeHtml(chat.preview || '');
        const safeTime = escapeHtml(chat.time || '');
        const chatId = parseInt(chat.chat_id) || 0;
        
        const onlineStatus = chat.online ? '<span class="online-dot"></span>' : '<span class="offline-dot"></span>';
        const unreadBadge = chat.unread > 0 ? `<span class="unread-badge">${chat.unread}</span>` : '';
        
        const avatarUrl = chat.avatar || 'img/default-avatar.svg';
        
        html += `
            <div class="chat-item modern-chat" onclick="openChat('${chat.chat_id}')">
                <div class="chat-avatar">
                    <img src="${avatarUrl}" alt="Avatar" onerror="this.src='img/default-avatar.svg'">
                    ${onlineStatus}
                </div>
                <div class="chat-info">
                    <h5>${safeName}</h5>
                    <p class="chat-preview">${safePreview}</p>
                </div>
                <div class="chat-meta">
                    <span class="chat-time">${safeTime}</span>
                    ${unreadBadge}
                </div>
            </div>
        `;
    });
    
    chatsList.innerHTML = html;
}

function openChat(chatId) {
    if (!chatId) {
        alert('Неверный ID чата');
        return;
    }
    
    fetch('setActiveChat.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'chat_id=' + encodeURIComponent(chatId)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.location.href = '/?page=chat';
        } else {
            alert('Ошибка открытия чата');
        }
    })
    .catch(error => {
        console.error('Error opening chat:', error);
        alert('Ошибка открытия чата');
    });
}

function viewProfile(userId) {
    window.location.href = '/?page=cabinet&usrid=' + userId;
}

function createGroupChat() {
    const groupName = prompt('Название группы:');
    if (!groupName) return;
    
    fetch('createGroup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'group_name=' + encodeURIComponent(groupName)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            loadChats();
            alert('Группа создана');
        } else {
            alert('Ошибка: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Group creation error:', error);
        alert('Ошибка создания группы');
    });
}

// Загружаем чаты при загрузке страницы
document.addEventListener('DOMContentLoaded', loadChats);
</script>