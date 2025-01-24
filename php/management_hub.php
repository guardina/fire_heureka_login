<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $username = $_SESSION['username'];
    include('templates/heureka_connection.html');
} else {
    header("Location: /");
    exit();
}
?>