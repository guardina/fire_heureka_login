<?php
session_start();

function heureka_authorize() {
    $random_state = bin2hex(random_bytes(32));

    $redirect_url = 'http://localhost:5000/callback';
    $auth_url = 'https://portal.testing.heureka.health/authorization';
    $client_id = 'your_client_id';

    $_SESSION['mode'] = 'authorize';

    $url = $auth_url . '/grant?client_id=' . urlencode($client_id) . '&state=' . urlencode($random_state) . '&redirect_uri=' . urlencode($redirect_url);

    header('Location: ' . $url);
    exit();
}

heureka_authorize();
?>
