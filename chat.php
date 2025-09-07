<?php
session_start();
if (empty($_SESSION['chat']) && empty($_SESSION['user'])) {
    header('Location: /page.php?pg=msg');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>💬 Диалог</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
    :root {
        --primary:#0084ff;
        --bubble-me:#daf7c1;
        --bubble-them:#fff;
        --text-me:#000;
        --text-them:#000;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f0f2f5;color:#111;padding-bottom:70px}
    .chat-header{background:var(--primary);color:#fff;position:sticky;top:0;z-index:1030}
    .chat-wrapper{flex:1;display:flex;flex-direction:column}
    .chat-messages{flex:1;overflow-y:auto;padding:0 .5rem .5rem;display:flex;flex-direction:column}
    .msg{max-width:85%;padding:.5rem .75rem 22px .75rem;margin:.15rem 0;border-radius:18px;word-break:break-word;position:relative;font-size:.95rem;line-height:1.3}
    .msg.me{background:var(--bubble-me);color:var(--text-me);align-self:flex-end;border-bottom-right-radius:4px}
    .msg.them{background:var(--bubble-them);color:var(--text-them);align-self:flex-start;border-bottom-left-radius:4px;box-shadow:0 1px 2px rgba(0,0,0,.08)}
    .msg .time{position:absolute;right:8px;bottom:3px;font-size:.6rem;color:rgba(0,0,0,.5)}
    .msg.me .time{color:rgba(0,0,0,.5)}
    .chat-footer{position:fixed;bottom:0;left:0;right:0;z-index:1030;background:#fff;border-top:1px solid #dee2e6;padding:.4rem .5rem;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
    #msgInput{flex:1;border-radius:50px;border:1px solid #ced4da;padding:.55rem .9rem;font-size:1rem;resize:none;max-height:120px;overflow:hidden}
    #previewPanel{display:flex;gap:.25rem;flex-wrap:wrap;margin:.25rem 0;width:100%}
    .preview-box{position:relative;width:60px;height:60px}
    .preview-box img{width:100%;height:100%;object-fit:cover;border-radius:6px}
    .preview-box .del{position:absolute;top:-6px;right:-6px;background:#ff4757;color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:12px;line-height:1;cursor:pointer}
    #videoPreview{display:none;width:100px;height:100px;border-radius:8px;margin-right:4px;object-fit:cover}
    #voiceTimer{display:none;font-size:.75rem;color:#666;margin-left:4px}
    #galleryModal{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center}
    #galleryModal img,#galleryModal video{max-width:95vw;max-height:95vh;border-radius:4px}
    #galleryModal .nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;font-size:2.5rem;color:#fff;cursor:pointer;padding:0 .5rem}
    #galleryModal .prev{left:20px}#galleryModal .next{right:20px}
    #galleryModal .close{position:absolute;top:15px;right:20px;background:none;border:none;font-size:2rem;color:#fff;cursor:pointer}
    @media (max-width:480px){
      #galleryModal .nav{font-size:1.8rem;padding:0 .3rem}
      #galleryModal .close{font-size:1.8rem}
    }
    /* PDF-иконка */
.msg-pdf{display:flex;align-items:center;gap:8px;color:#000;text-decoration:none;font-size:.9rem}
.msg-pdf i{flex-shrink:0}

/* модальное окно уже есть (#galleryModal), просто дополняем */
@media (max-width:480px){
  #galleryModal iframe{height:70vh}
}
.footer-07{
    display: none !important;
}
    </style>
</head>
<body class="d-flex flex-column">

<!-- 🔐 Key Modal (ключ необязателен) -->
<div class="modal fade" id="keyModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-key-fill text-primary"></i> Ключ шифрования (по желанию)</h5>
      </div>
      <form id="keyForm">
        <div class="modal-body">
          <input type="password" class="form-control mb-2" name="passPhrase" placeholder="Ключ-фраза (можно пропустить)">
          <input type="file" class="form-control" name="keyFile" accept="image/*">
          <small class="form-text text-muted">Можно оставить пустыми — сообщения не будут шифроваться.</small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary w-100">Открыть чат</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Header -->
<div class="chat-header p-2 d-flex justify-content-between align-items-center">
  <a href="page.php?pg=msg" id="backBtn" class="btn btn-light btn-sm">
    <i class="bi bi-arrow-left"></i> Назад
  </a>
  <span class="fw-bold">💬 Диалог</span>
</div>

<!-- Messages -->
<div class="chat-wrapper">
    <div class="chat-messages" id="chatBox"></div>
</div>

<!-- Footer -->
<div class="chat-footer">
    <textarea id="msgInput" placeholder="Сообщение…" maxlength="1000" rows="1"></textarea>
    <input type="file" id="fileInput" multiple hidden>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#fileInput').click()" title="Файл">
      <i class="bi bi-paperclip"></i>
    </button>
    <button class="btn btn-sm btn-primary" id="sendBtn"><i class="bi bi-send-fill"></i></button>
    <div id="previewPanel"></div>
</div>

<!-- Галерея -->
<div id="galleryModal">
  <button class="close">&times;</button>
  <button class="nav prev">&#10094;</button>
  <img id="galleryImg" src="" alt="">
  <video id="galleryVideo" controls style="display:none;"></video>
  <button class="nav next">&#10095;</button>
  <iframe id="galleryPdf" style="display:none;width:100%;height:80vh;border:none;" title="PDF"></iframe>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ---------- Key ---------- */
const keyModal = new bootstrap.Modal(document.getElementById('keyModal'));
keyModal.show();

$('#keyForm').on('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('chat', <?= json_encode($_SESSION['chat']) ?>);
    $.ajax({
        url: 'saveKey.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false
    }).done(r => {
        if (r.ok) {
            keyModal.hide();
        } else {
            alert('Ошибка: ' + JSON.stringify(r));
        }
    }).fail(xhr => alert('AJAX ' + xhr.status));
});


/* ---------- textarea auto-resize ---------- */
const tx = document.getElementById('msgInput');
tx.addEventListener('input', ()=>{
  tx.style.height='auto';
  tx.style.height = tx.scrollHeight + 'px';
});

/* ---------- любые файлы / drag-n-drop ---------- */
let filesArray = [];
function addFilePreview(file) {
    if (filesArray.length >= 20) { alert('Максимум 20 файлов'); return; }
    filesArray.push(file);
    const reader = new FileReader();
    reader.onload = e => {
        const id = Date.now() + Math.random();
        $('#previewPanel').append(`
            <div class="preview-box" data-id="${id}">
                <img src="${e.target.result}" alt="">
                <button class="del">×</button>
            </div>
        `);
        $(`.preview-box[data-id="${id}"] .del`).on('click', function () {
            $(this).parent().remove();
            const idx = filesArray.indexOf(file);
            if (idx > -1) filesArray.splice(idx, 1);
        });
    };
    reader.readAsDataURL(file);
}
document.getElementById('fileInput').addEventListener('change', e => {
    [...e.target.files].forEach(addFilePreview);
    e.target.value = '';
});

/* ---------- отправка ---------- */
function sendMessage() {
    
    const msg = $('#msgInput').val().trim();
    if (!msg && !filesArray.length) return;   // пусто — не отправляем

    const fd = new FormData();
    if (msg) fd.append('message', msg);       // текст всегда добавляем

    filesArray.forEach(f => {
        if (f.type.startsWith('image/'))        fd.append('photos[]', f);
        else if (f.type.startsWith('audio/'))   fd.append('voice', f);
        else if (f.type.startsWith('video/'))   fd.append('video', f);
        else                                    fd.append('file', f);
    });

    $('#sendBtn').prop('disabled', true).html('⏳');
    $.ajax({
        url: 'sendMessage.php',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false
    })
    .done(res => {
        if (res.ok) {
            $('#msgInput').val('');
            $('#previewPanel').empty();
            filesArray = [];
            loadMessages();
        } else {
            alert('Ошибка: ' + (res.error || 'unknown'));
        }
    })
    .fail(xhr => alert('HTTP ' + xhr.status))
    .always(() => $('#sendBtn').prop('disabled', false).html('<i class="bi bi-send-fill"></i>'));
}

/* ---------- загрузка сообщений ---------- */
let lastPoll = 0;
function loadMessages(first = false) {
    if (!first && Date.now() - lastPoll < 5000) return;
    lastPoll = Date.now();
    $.get('getMessages.php', html => {
        const $box = $('#chatBox');
        if (first || $box.html() !== html) {
            $box.html(html);
            $box.animate({scrollTop: 0}, 150);
        }
    });
}
loadMessages(true);

/* ---------- галерея ---------- */
(() => {
    const modal   = document.getElementById('galleryModal');
    const imgEl   = document.getElementById('galleryImg');
    const videoEl = document.getElementById('galleryVideo');
    let current = 0;
    let items   = [];

    function show(idx){
        if(!items[idx]) return;
        current = idx;
        const {src, type} = items[idx];
        if(type === 'image'){
            imgEl.src = src;
            imgEl.style.display = 'block';
            videoEl.style.display = 'none';
        }else{
            videoEl.src = src;
            videoEl.style.display = 'block';
            imgEl.style.display = 'none';
        }
        modal.style.display = 'flex';
    }
    function hide(){
        modal.style.display = 'none';
        videoEl.pause();
    }

    $(document).on('click', '.msg-thumb, .msg-video', function () {
        const all = document.querySelectorAll('.msg-thumb, .msg-video');
        items = Array.from(all).map(el=>{
            const src = el.tagName==='VIDEO' ? el.querySelector('source').src : el.src;
            const type = el.tagName==='VIDEO' ? 'video' : 'image';
            return {el, src, type};
        });
        const idx = items.findIndex(item => item.el === this);
        show(idx);
    });
    $('.close').on('click', hide);
    $('.prev').on('click', () => show((current - 1 + items.length) % items.length));
    $('.next').on('click', () => show((current + 1) % items.length));
    $(window).on('keydown', e => {
        if(modal.style.display !== 'flex') return;
        if(e.key === 'ArrowLeft') $('.prev').click();
        if(e.key === 'ArrowRight') $('.next').click();
        if(e.key === 'Escape') hide();
    });
})();

/* ---------- выход из чата ---------- */
$('#backBtn').on('click', function (e) {
    e.preventDefault();
    $.post('leaveChat.php').always(() => location.href = 'page.php?pg=msg');
});

/* Enter без Shift = отправка */
$('#sendBtn').on('click', sendMessage);   // клик
$('#msgInput').on('keydown', e => {       // Enter
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
/* открываем PDF в том же модальном окне */
$(document).on('click', '.msg-pdf', function (e) {
    e.preventDefault();
    const pdfPath = $(this).data('pdf');
    $('#galleryImg, #galleryVideo').hide();
    $('#galleryPdf')
        .attr('src', 'viewPdf.php?f=' + encodeURIComponent(pdfPath))
        .show();
    $('#galleryModal').css('display','flex');
});
</script>
</body>
</html>