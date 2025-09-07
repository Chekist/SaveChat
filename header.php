<!doctype html>
<?php
session_start();
$user = $_SESSION['user'];

echo '
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    ';

    echo '<title>Союз вольных хлеборобов имени Махно</title>';
echo '        

		<link rel="stylesheet" href="css/ionicons.min.css">
		<link rel="stylesheet" href="css/style.css">
		<link rel="stylesheet" href="css/styles.css">
		<style>
		body{overflow-wrap: break-word
		    text-align:center;
		}
		h2,a,p{
		    text-align:center;
		}
		</style>
  </head>
  <body>
  ';
echo '<nav class="navbar navbar-expand-lg bg-success ">
    <div class="container-fluid">
        <h4 class="navbar-brand" >N.I.Makhno</h4>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Переключатель навигации">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">';

    echo '       
            <li class="nav-item">
                <form method="post" action="page.php">
                    <input type="hidden" name="pg" value="home">
                    <button class="nav-link active" aria-current="page" type="submit" name="home">Главная</button>
                </form>
            </li>
          ';
    echo '       
          <li class="nav-item">
              <form method="post" action="page.php">
                  <input type="hidden" name="pg" value="art">
                  <button class="nav-link active" aria-current="page" type="submit" name="art">Галлерея</button>
              </form>
          </li>
        ';

    echo '       
        <li class="nav-item">
            <form method="post" action="page.php">
                <input type="hidden" name="pg" value="news">
                <button class="nav-link active" aria-current="page" type="submit" name="news">Новости</button>
            </form>
        </li>

      ';
      if ($user == 0 || empty($user)) {
        echo '       
                <li class="nav-item">
                    <form method="post" action="page.php">
                        <input type="hidden" name="pg" value="login">
                        <button class="nav-link active" aria-current="page" type="submit" name="login">Вход</button>
                    </form>
                </li>
              ';
        echo '       
              <li class="nav-item">
                  <form method="post" action="page.php">
                      <input type="hidden" name="pg" value="register">
                      <button class="nav-link active" aria-current="page" type="submit" name="register">Регистрация</button>
                  </form>
              </li>
</ul>
</div>
</div>
</nav>';
      }elseif ($user == "admin" || !empty($user)) {
        echo '       
                <li class="nav-item">
                    <form method="post" action="page.php">
                        <input type="hidden" name="pg" value="cabinet">
                        <button class="nav-link active" aria-current="page" type="submit" name="cabinet">Личный кабинет</button>
                    </form>
                </li>
                <li class="nav-item">
                    <form method="post" action="page.php">
                        <input type="hidden" name="pg" value="msg">
                        <button class="nav-link active" aria-current="page" type="submit" name="msg">Сообщения</button>
                    </form>
                </li>
              ';
        echo '       
              <li class="nav-item">
                  <form method="post" action="page.php">
                      <input type="hidden" name="pg" value="logout">
                      <button class="nav-link active" aria-current="page" type="submit" name="logout">Выйти</button>
                  </form>
              </li>
                      </ul>
        </div>
      </div>
    </nav>';
    }


?>

