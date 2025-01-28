<?php

session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (check_access_token()) {
    if (!check_temp_pw()) {
        header("Location: /templates/connection_successful.html");
        exit();
    } else {
        header("Location: change_password.php");
        exit();
    }
}



heureka_authorize();



function heureka_authorize() {
    $random_state = bin2hex(random_bytes(32));

    $redirect_url = 'https://localhost:5000/callback';
    $auth_url = 'https://portal.testing.heureka.health/authorization';
    $client_id = '173e5603-6107-4521-a465-5b9dc86b2e95';

    $_SESSION['mode'] = 'authorize';

    $url = $auth_url . '/grant?client_id=' . urlencode($client_id) . '&state=' . urlencode($random_state) . '&redirect_uri=' . urlencode($redirect_url);

    header('Location: ' . $url);
    exit();
}

?>
