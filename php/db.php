<?php
    function get_db_connection() {
        $host = 'localhost';
        //$host = 'ihmzclin.mysql.db.internal';
        $dbname = 'fire_heureka_credentials';
        //$dbname = 'ihmzclin_fireHeurekaCredentials';
        $username = 'alex';
        //$username = 'ihmzclin';
        $password = 'password';
        //$password = 'M1jTjJTXgnE?bFQW-cmz';
        try {
            $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }



    function save_token($access_token, $refresh_token, $token_expiry, $mode) {
        $conn = get_db_connection();
    
        $new_token_expiry = date('Y-m-d H:i:s', time() + $token_expiry);
        $user_id = $_SESSION['user_id'];
    
        if ($mode === 'insert') {
            $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, access_token, refresh_token, token_expiry)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                                access_token = VALUES(access_token),
                                refresh_token = VALUES(refresh_token),
                                token_expiry = VALUES(token_expiry),
                                updated_at = CURRENT_TIMESTAMP");
    
            $stmt->execute([$user_id, $access_token, $refresh_token, $new_token_expiry]);
    
        } else if ($mode === 'update') {
            $stmt = $conn->prepare("UPDATE user_tokens
                SET access_token = ?, 
                    refresh_token = ?, 
                    token_expiry = ?
                WHERE user_id = ?");
    
            $stmt->execute([$access_token, $refresh_token, $new_token_expiry, $user_id]);
        }
    }
?>