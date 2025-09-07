<div class='container reg'>
 <h2>Вход</h2>
 <form class='log' method="post" action="log.php">
 Логин: </br> <input class="form-control" type="text" name="login" required><br>
 Пароль или код безопасности:</br> <input class="form-control" type="password" name="password" required><br>
 <button class='btn nav-link active btnn' type="submit" >Войти</button>
 <div id="error-message" style="color: red;"></div> <!-- Для отображения сообщений об ошибках -->
 </form>
</div>

<script>
const form = document.querySelector('.log');
form.addEventListener('submit', function(event) {
 event.preventDefault();
 const login = document.querySelector('input[name="login"]').value;  
 const password = document.querySelector('input[name="password"]').value;

 const data = {
 login: login,
 password: password
 };

 const xhr = new XMLHttpRequest();
 xhr.open('POST', 'log.php', true);
 xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
 xhr.onreadystatechange = function() {
 if (xhr.readyState === XMLHttpRequest.DONE) {
 if (xhr.status === 200) {
 const response = JSON.parse(xhr.responseText);
 if (response.success) {
  // Если вход выполнен успешно, перенаправляем на главную страницу
  window.location.href = '/';
 } else {
  // Если есть ошибки, отображаем сообщение об ошибке
  document.getElementById("error-message").textContent = response.message;

  if (response.message === "Неверный логин или пароль. На вашу почту поступил ваш код безопасности") {
    // Отправка данных в repair.php
    const repairData = {
      login: login,
      password: password
    };

    const repairXhr = new XMLHttpRequest();
    repairXhr.open('POST', 'repair.php', true);
    repairXhr.setRequestHeader('Content-Type', 'application/json');
    repairXhr.send(JSON.stringify(repairData));
  }
 }
 } else {
 console.error('Произошла ошибка при отправке данных на сервер');
 }
 }
 };
 xhr.send('login=' + login + '&password=' + password);
});
</script>