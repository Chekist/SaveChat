<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>💬 Мои чаты</title>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Google-шрифт -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #f2f4f7;
            --card: #ffffff;
            --accent: #0066ff;
            --text: #121212;
            --muted: #8a8a8a;
        }
        body {
            background: var(--bg);
            font-family: 'Inter', sans-serif;
            color: var(--text);
        }
        /* Контейнер карточек */
        .chat-list {
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }
        /* Карточка диалога */
        .dialog-card {
            background: var(--card);
            border-radius: 1rem;
            padding: .75rem 1rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            cursor: pointer;
            transition: .2s;
            box-shadow: 0 2px 6px rgba(0,0,0,.05);
        }
        .dialog-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,.08);
            transform: translateY(-2px);
        }
        .dialog-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .dialog-info {
            flex: 1;
            min-width: 0;
        }
        .dialog-name {
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.2;
            margin-bottom: .15rem;
        }
        .dialog-preview {
            color: var(--muted);
            font-size: .875rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dialog-meta {
            flex-shrink: 0;
            text-align: right;
            font-size: .75rem;
            color: var(--muted);
            line-height: 1.2;
        }
    </style>
</head>

<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-semibold mb-0">💬 Мои чаты</h1>
        <button class="btn btn-primary rounded-pill" onclick="createGroup()">👥 Создать группу</button>
    </div>

    <!-- Поиск -->
    <div class="mb-4">
        <input type="text" id="searchUser" class="form-control rounded-pill" placeholder="Введите логин пользователя…">
        <div id="searchResult" class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3 mt-3"></div>
    </div>

    <!-- Список диалогов -->
    <div class="chat-list" id="chatList"></div>
</div>

<!-- Модальное окно создания группы -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">👥 Создать группу</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="createGroupForm">
          <div class="mb-3">
            <label class="form-label">Название группы</label>
            <input type="text" class="form-control" name="group_name" placeholder="Моя группа" required>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-primary" onclick="submitCreateGroup()">Создать</button>
      </div>
    </div>
  </div>
</div>

<script>
/* ---------- поиск ---------- */
$('#searchUser').on('input', function () {
    // amazonq-ignore-next-line
    const q = $(this).val().trim();
    if (q.length < 2) { $('#searchResult').empty(); return; }

    $.getJSON('searchUser.php', {q: q}, data => {
        $('#searchResult').empty();
        data.forEach(u => {
            $('#searchResult').append(`
                <div class="col">
                    <div class="card chat-card h-100 border-0 shadow-sm">
                        <img src="${u.photo}" class="card-img-top" style="height:140px;object-fit:cover;border-top-left-radius:1rem;border-top-right-radius:1rem;">
                        <div class="card-body text-center">
                            <h6 class="fw-semibold mb-2">${u.login}</h6>
                            <button class="btn btn-primary btn-sm rounded-pill w-100"
                                    onclick="startChat(${u.id})">Написать</button>
                        </div>
                    </div>
                </div>
            `);
        });
    });
});

/* ---------- создание / открытие чата ---------- */
function startChat(userId) {
    $.post('createChat.php', {user_id: userId}, r => {
        if (r.success) {
            location.href = 'page.php?pg=chat&chat=' + r.chat_id;
        } else {
            alert(r.message);
        }
    }, 'json');
}

/* ---------- загрузка диалогов ---------- */
function loadChats() {
    $.getJSON('getChat.php', data => {
        let html = '';
        data.forEach(chat => {
            const onlineIndicator = chat.online ? '<span style="color: #22c55e;">•</span>' : '';
            const chatIcon = chat.type === 'group' ? '👥' : '💬';
            
            html += `
                <div class="dialog-card" onclick="openChat('${chat.chat_id}')">
                    <img src="${chat.avatar}" class="dialog-avatar" onerror="this.src='img/default-avatar.svg'">
                    <div class="dialog-info">
                        <div class="dialog-name">${chatIcon} ${chat.name} ${onlineIndicator}</div>
                        <div class="dialog-preview">${chat.preview}</div>
                    </div>
                    <div class="dialog-meta">
                        <div>${chat.time}</div>
                        ${chat.unread > 0 ? `<div class="badge bg-primary rounded-pill">${chat.unread}</div>` : ''}
                    </div>
                </div>
            `;
        });
        $('#chatList').html(html || '<p class="text-center text-muted">Нет чатов</p>');
    }).fail(() => {
        $('#chatList').html('<p class="text-center text-danger">Ошибка загрузки чатов</p>');
    });
}

function openChat(chatId) {
    $.post('setActiveChat.php', {chat_id: chatId}, r => {
        if (r.success) {
            location.href = '/?page=chat_modern';
        } else {
            alert('Ошибка: ' + r.message);
        }
    }, 'json');
}

function createGroup() {
    const modal = new bootstrap.Modal(document.getElementById('createGroupModal'));
    modal.show();
}

function submitCreateGroup() {
    const form = document.getElementById('createGroupForm');
    const formData = new FormData(form);
    
    $.post('createGroup.php', Object.fromEntries(formData), r => {
        if (r.success) {
            alert('✅ Группа создана!');
            bootstrap.Modal.getInstance(document.getElementById('createGroupModal')).hide();
            form.reset();
            loadChats();
        } else {
            alert('❌ Ошибка: ' + r.message);
        }
    }, 'json');
}

loadChats();
setInterval(loadChats, 10_000);
</script>
</body>
</html>