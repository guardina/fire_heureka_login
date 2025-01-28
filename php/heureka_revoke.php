<?php
session_start();
require_once 'db.php';

function remove_permissions() {
    $user_id = $_SESSION['user_id'] ?? null;
    
    $redirect_url = 'https://localhost:5000/callback';
    
    if ($user_id) {
        $conn = get_db_connection();
        
        $stmt = $conn->prepare("SELECT installation_id FROM user_credentials WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $installation_id = $row['installation_id'];
            
            $_SESSION['mode'] = 'revoke';
            
            $auth_url = 'https://portal.testing.heureka.health/authorization';
            $url = $auth_url . '/revoke?installation_id=' . $installation_id . '&redirect_uri=' . urlencode($redirect_url);
            
            header("Location: $url");
            exit();
        } else {
            echo "User credentials not found.";
        }
    } else {
        echo "User not logged in.";
    }
}

remove_permissions();