<?php

session_start();
require_once "db.php";



$language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);

if (isset($_SESSION['flash_message'])) {
    echo "<script type='text/javascript'>alert('" . $_SESSION['flash_message'] . "');</script>";

    unset($_SESSION['flash_message']);
}




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $conn = get_db_connection();

    $hashed_password = hash_password($password);

    //$stmt = $conn->prepare("UPDATE user_credentials SET role = 'USER' WHERE username = 'mock_user'");
    //$stmt = $conn->prepare("INSERT INTO user_credentials (username, password, role) VALUES (:username, :password, 'DEV')");   
    //$stmt->bindParam(':username', $username);
    //$stmt->bindParam(':password', $hashed_password);
    //$stmt->execute();

    
    $stmt = $conn->prepare("SELECT * FROM user_credentials WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userJson = json_encode($user);

    if (!$user) {
        $_SESSION["flash_message"] = get_translation('wrong_credentials', $language);
        header("Location: index.php");
        exit();
    }

    if (verify_password($password, $user["password"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["heureka_role"] = "SYSTEM";
        $_SESSION["role"] = $user["role"];

        if (!check_access_token()) {
            header("Location: heureka_authorize.php");
            exit();
        } else {
            if (check_temp_pw()) {
                header("Location: change_password.php");
                exit();
            } else {
                header("Location: templates/connection_successful.html");
                exit();
            }
        }
    } else {
        $_SESSION["flash_message"] = get_translation('wrong_credentials', $language);
        header("Location: index.php");
        exit();
    }
}





function get_translation($key, $lang) {
    $translations = [
        'en' => [
            'wrong_credentials' => 'Error: You have entered an invalid username or password',
        ],
        'fr' => [
            'wrong_credentials' => 'Erreur : Vous avez saisi un nom d\'utilisateur ou un mot de passe non valide',
        ],
        'de' => [
            'wrong_credentials' => 'Error: Sie haben einen ungültigen Benutzernamen oder ein ungültiges Passwort eingegeben',
        ],
        'it' => [
            'wrong_credentials' => 'Errore: È stato inserito un nome utente o una password non validi',
        ],
    ];

    return $translations[$lang][$key] ?? $translations['en'][$key];
}


function hash_password($password) {
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    return $hashed_password;
}

function verify_password($provided_password, $stored_password) {
    return password_verify($provided_password, $stored_password);
}




include 'templates/login.html';
?>
