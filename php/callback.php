<?php

session_start();
require_once "db.php";

$conn = get_db_connection();

function redirected_page() {
    global $conn;
    if (isset($_SESSION['mode'])) {
        if ($_SESSION['mode'] === 'authorize') {
            $auth_code = $_GET['code'] ?? null;

            if (!$auth_code) {
                $username = $_SESSION['username'] ?? null;
                echo render_template('heureka_connection.html', ['username' => $username]);
                return;
            }

            $redirect_uri = 'https://ihamz.ch/callback';

            $payload = [
                "grant_type" => "authorization_code",
                "client_id" => '173e5603-6107-4521-a465-5b9dc86b2e95',
                "redirect_uri" => $redirect_uri,
                "code" => $auth_code
            ];

            $cert_path = __DIR__ . '/resources/fire.crt';
            $key_path = __DIR__ . '/resources/fire.key';
            $token_url = 'https://token.testing.heureka.health/oauth2/token';

            try {
                $ch = curl_init($token_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
                curl_setopt($ch, CURLOPT_SSLCERT, $cert_path);
                curl_setopt($ch, CURLOPT_SSLKEY, $key_path);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code === 200) {
                    $token_data = json_decode($response, true);
                    $access_token = $token_data['access_token'] ?? null;
                    $refresh_token = $token_data['refresh_token'] ?? null;
                    $expires_in = $token_data['expires_in'] ?? null;

                    $jwt_parts = explode('.', $access_token);
                    $payload_data = json_decode(base64_decode($jwt_parts[1]), true);
                    $installation_id = $payload_data['sub'] ?? null;


                    save_token($access_token, $refresh_token, $expires_in, 'insert');

                    $user_id = $_SESSION['user_id'] ?? null;

                    $query = "UPDATE user_credentials SET installation_id = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $success = $stmt->execute([$installation_id, $user_id]);

                    header('Location: management_hub');
                    exit();
                } else {
                    echo "Failed to retrieve token. Status code: $http_code, Error: $response";
                    http_response_code($http_code);
                }
            } catch (Exception $e) {
                echo "An error occurred while requesting the token: " . $e->getMessage();
                http_response_code(500);
            }
        } elseif ($_SESSION['mode'] === 'update') {
            $username = $_SESSION['username'] ?? null;
            echo render_template('heureka_connection.html', ['username' => $username]);
        } elseif ($_SESSION['mode'] === 'revoke') {
            $username = $_SESSION['username'] ?? null;
            echo render_template('heureka_connection.html', ['username' => $username]);
        }
    }
}


function render_template($template, $data = []) {
    extract($data);
    ob_start();
    include $template;
    return ob_get_clean();
}




redirected_page();
//include "templates/heureka_connection.html";
?>