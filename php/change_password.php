<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $password = $_POST['password'];
    $password_conf = $_POST['password_conf'];

    if ($password === $password_conf) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $conn = get_db_connection();

        $stmt = $conn->prepare("UPDATE user_credentials SET password = :password WHERE id = :user_id");
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':user_id', $user_id);

        if ($stmt->execute()) {
            header("Location: ../templates/connection_successful.html");
            exit();
        } else {
            $_SESSION['error_message'] = "There was an error updating your password.";
        }

        $conn = null;
    } else {
        $_SESSION['error_message'] = "Passwords do not match.";
    }
}

include '../templates/change_password.html';
?>