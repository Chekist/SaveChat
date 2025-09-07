<?php
// Пример конфигурации для отправки почты через SMTP сервер smtp.beget.com на порт 2525
ini_set('SMTP', 'smtp.beget.com');
ini_set('smtp_port', 2525);
ini_set('sendmail_from', 'nimakhno@nv-prestige.ru');
session_start();
// Проверка наличия данных в POST-запросе
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Подключение к базе данных
    $conn = new mysqli('localhost', 'root', '', 'r9825349_mh');

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Защита от SQL инъекций
    $login = $conn->real_escape_string($_POST['login']);
    $password = $conn->real_escape_string($_POST['password']);
    $email = $conn->real_escape_string($_POST['email']);
    $repeatPassword = $conn->real_escape_string($_POST['repeat_password']);
    $savecode = random_int(1000000000000000, 9999999999999999);

    // Проверка на пустые поля
    $missingFields = array();
    if (empty($login)) {
        $missingFields[] = "Логин";
    }
    if (empty($password)) {
        $missingFields[] = "Пароль";
    }
    if (empty($email)) {
        $missingFields[] = "Email";
    }
    if (empty($repeatPassword)) {
        $missingFields[] = "Повторите пароль";
    }

    if (!empty($missingFields)) {
        $missingFieldsStr = implode(", ", $missingFields);
        echo json_encode(array("success" => false, "message" => "Пожалуйста, заполните следующие поля: $missingFieldsStr"));
    } else {
        // Проверка на уникальность логина и email
        $stmt = $conn->prepare("SELECT login, email FROM user WHERE login = ? OR email = ?");
        $stmt->bind_param("ss", $login, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $existingUser = $result->fetch_assoc();
            if ($existingUser['login'] === $login) {
                echo json_encode(array("success" => false, "message" => "Пользователь с таким логином уже существует"));
            } else {
                echo json_encode(array("success" => false, "message" => "Пользователь с таким email уже зарегистрирован"));
            }
        } else {
            // Ваш текущий код для обработки регистрации пользователя
            if ($password === $repeatPassword) {
                $hashedPassword = password_hash($password . 'NIMAHNO', PASSWORD_DEFAULT);
                $photo = 'https://toplogos.ru/images/logo-anonymous.png';
                $status = 'user';

                // Запрос на добавление нового пользователя
                $stmt = $conn->prepare("INSERT INTO user (login, password, email, savecode, photo, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $login, $hashedPassword, $email, $savecode, $photo, $status);
                $_SESSION['page'] = 'login';

                if ($stmt->execute()) {

                    // PHP код для отправки электронного письма пользователю
                    $to = $email;
                    $subject = "Благодарим за регистрацию на N.I.Makhno";
                    $message = '<!DOCTYPE html>
                    <html>
                    <head>
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Красивое письмо</title>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                color: #333;
                                background-color: #f4f4f4;
                                margin: 0;
                                padding: 0;
                            }
                            .container {
                                max-width: 600px;
                                margin: 0 auto;
                                padding: 20px;
                                background-color: #fff;
                                border-radius: 10px;
                                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                            }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <p>Добрый день '. $login.'!</p>
                            <p>Этим письмом мы хотим напомнить вам ваш код безопасности на случай если вы забыли свой пароль.</p>
                            <div style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <p>Ваш код безопасности:'.$savecode.'</p>
                            </div>
                        </div>
                    </body>
                    </html>';
                    $headers = "From: nimakhno@nv-prestige.ru\r\n";
                    $headers .= "Content-type: text/html; charset=utf-8\r\n";

                    // Отправка письма
                    if (mail($to, $subject, $message, $headers)) {
                        echo json_encode(array("success" => true, "message" => "Регистрация успешна. Добро пожаловать, $login!"));
                    } else {
                        echo json_encode(array("success" => false, "message" => "Ошибка при отправке письма"));
                    }
                } else {
                    echo json_encode(array("success" => false, "message" => "Ошибка при регистрации пользователя: " . $conn->error));
                }
            } else {
                echo json_encode(array("success" => false, "message" => "Пароли не совпадают"));
            }
        }
    }

    // Закрытие соединения с базой данных
    $conn->close();
} else {
    // Если запрос не является POST, возвращается сообщение об ошибке
    echo json_encode(array("success" => false, "message" => "Недопустимый метод запроса"));
}
?>
