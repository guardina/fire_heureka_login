<?php

session_start();
require_once "db.php";


if (isset($_SESSION['flash_message'])) {
    echo "<script type='text/javascript'>alert('" . $_SESSION['flash_message'] . "');</script>";

    unset($_SESSION['flash_message']);
}




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $conn = get_db_connection();

    $hashed_password = hash_password($password);

    #$stmt = $conn->prepare("INSERT INTO user_credentials (username, password, role) VALUES (:username, :password, 'DEV')");   
    #$stmt->bindParam(':username', $username);
    #$stmt->bindParam(':password', $hashed_password);
    #$stmt->execute();

    
    $stmt = $conn->prepare("SELECT * FROM user_credentials WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userJson = json_encode($user);

    $stmt_token = $conn->prepare("SELECT user_tokens.access_token 
                                FROM user_credentials 
                                JOIN user_tokens ON user_credentials.id = user_tokens.user_id 
                                WHERE user_credentials.username = ?");
    $stmt_token->execute([$username]);
    $user_token = $stmt_token->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION["flash_message"] = "Error: Wrong credentials";
        header("Location: index");
        exit();
    }

    if (verify_password($password, $user["password"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["user_role"] = "SYSTEM";

        if (!$user_token) {
            header("Location: heureka_authorize");
            exit();
        } else {
            header("Location: ../templates/connection_successful.html");
            exit();
        }
    } else {
        $_SESSION["flash_message"] = "Error: Wrong credentials";
        header("Location: index");
        exit();
    }
}





function hash_password($password) {
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    return $hashed_password;
}

function verify_password($provided_password, $stored_password) {
    return password_verify($provided_password, $stored_password);
}




include '../templates/login.html';
?>
