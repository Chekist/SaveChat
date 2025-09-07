<div class='container reg'>
 <h2>Регистрация</h2>
 <form class='log' method="post" action="reg.php">
 Логин: </br> <input class="form-control" type="text" name="login" required><br>
 Пароль:</br> <input class="form-control" type="password" name="password" required><br>
 Повторите пароль:</br> <input class="form-control" type="password" name="repeat_password" required><br>
 Email:</br> <input class="form-control" type="email" name="email" required><br>
 <button class='btn nav-link active btnn' type="submit" >Зарегистрироваться</button>
 <div id="error-message" style="color: red;"></div> <!-- Для отображения сообщений об ошибках -->
 </form>
</div>

<script>
const form = document.querySelector('.log');
form.addEventListener('submit', function(event) {
 event.preventDefault();
 const login = document.querySelector('input[name="login"]').value;  
 const password = document.querySelector('input[name="password"]').value;
 const repeatPassword = document.querySelector('input[name="repeat_password"]').value;
 const email = document.querySelector('input[name="email"]').value;

 const data = {
  login: login,
  password: password,
  email: email,
  repeat_password: repeatPassword
 };

 const xhr = new XMLHttpRequest();
 xhr.open('POST', 'reg.php', true);
 xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
 xhr.onreadystatechange = function() {
  if (xhr.readyState === XMLHttpRequest.DONE) {
   if (xhr.status === 200) {
    try {
     const response = JSON.parse(xhr.responseText);
    } catch (e) {
     document.getElementById("error-message").textContent = 'Ошибка обработки ответа сервера';
     return;
    }
    if (response.success) {
     // Если регистрация прошла успешно, перенаправляем на главную страницу
     window.location.href = '/';
    } else {
     // Если есть ошибки, отображаем сообщение об ошибке
     document.getElementById("error-message").textContent = response.message;
    }
   } else {
    console.error('Произошла ошибка при отправке данных на сервер');
   }
  }
 };
 xhr.send('login=' + login + '&password=' + password + '&email=' + email + '&repeat_password=' + repeatPassword);
});
</script>