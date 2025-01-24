<?php
session_start();
require_once 'db.php';


$language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);



if (isset($_SESSION['flash_message'])) {
    echo "<script type='text/javascript'>alert('" . $_SESSION['flash_message'] . "');</script>";

    unset($_SESSION['flash_message']);
}




if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (check_access_token()) {
    if (!check_temp_pw()) {
        header("Location: /templates/connection_successful.html");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $password = $_POST['password'];
    $password_conf = $_POST['password_conf'];

    if ($password === $password_conf) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $conn = get_db_connection();

        $stmt = $conn->prepare("UPDATE user_credentials SET password = :password, temp_pw = 0 WHERE id = :user_id");
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':user_id', $user_id);

        if ($stmt->execute()) {
            header("Location: management_hub");
            exit();
        } else {
            $_SESSION['flash_message'] = get_translation('database_error', $language);
            header("Location: change_password.php");
            exit();
        }

        $conn = null;
    } else {
        $_SESSION['flash_message'] = get_translation('password_mismatch', $language);
        header("Location: change_password.php");
        exit();
    }
}




function get_translation($key, $lang) {
    $translations = [
        'en' => [
            'password_mismatch' => 'Error: The inserted passwords don\'t match',
            'database_error' => 'Error: Failed to update data in database',
        ],
        'fr' => [
            'password_mismatch' => 'Erreur : Les mots de passe insérés ne correspondent pas',
            'database_error' => 'Erreur: Échec de la mise à jour des données dans la base de données',
        ],
        'de' => [
            'password_mismatch' => 'Error: Die eingegebenen Passwörter stimmen nicht überein',
            'database_error' => 'Error: Daten in der Datenbank konnten nicht aktualisiert werden',
        ],
        'it' => [
            'password_mismatch' => 'Errore: Le password inserite non corrispondono',
            'database_error' => 'Errore : Impossibile aggiornare i dati nel database',
        ],
    ];

    return $translations[$lang][$key] ?? $translations['en'][$key];
}




include 'templates/change_password.html';
?>