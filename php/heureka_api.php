<?php
session_start();
require_once 'db.php';

function heureka_api() {
    $access_token = get_access_token();
    
    if (!$access_token) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Could not obtain access token"]);
        http_response_code(401);
        exit();
    }
    
    configure_heureka();

    $heureka_grants = $_SESSION['heurekaGrants'] ?? [];
    if (isset($heureka_grants['PATIENT']) && in_array('READ', $heureka_grants['PATIENT'])) {
        $patients = get_patients_heureka();
        echo json_encode($patients);
        exit();
    } else {
        $alert_message = "You don't have the required permissions";
        include 'templates/heureka_connection';
        exit();
    }
}





function get_access_token() {
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        return null;
    }

    $conn = get_db_connection();

    $query = "SELECT user_tokens.access_token, user_tokens.token_expiry
        FROM user_credentials
        JOIN user_tokens ON user_credentials.id = user_tokens.user_id
        WHERE user_credentials.id = ?";

    if ($stmt = $conn->prepare($query)) {
        $stmt->execute([$user_id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $access_token = $result['access_token'];
            $token_expiry = $result['token_expiry'];

            $current_time = new DateTime();
            $token_expiry_time = new DateTime($token_expiry);

            if (!$access_token || $current_time > $token_expiry_time) {
                $access_token = get_new_access_token();
            }

            return $access_token;
        } else {
            return null;
        }
    }
}




function get_new_access_token() {
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        return json_encode(["error" => "User ID not found in session"]);
    }

    $conn = get_db_connection();

    $query = "
        SELECT user_tokens.refresh_token
        FROM user_credentials
        JOIN user_tokens ON user_credentials.id = user_tokens.user_id
        WHERE user_credentials.id = ?
    ";

    if ($stmt = $conn->prepare($query)) {
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['refresh_token'])) {
            $refresh_token = $result['refresh_token'];
        }

        if (!$refresh_token) {
            return json_encode(["error" => "No refresh token available"]);
        }


        $data = [
            "grant_type" => "refresh_token",
            "refresh_token" => $refresh_token,
            "client_id" => "173e5603-6107-4521-a465-5b9dc86b2e95",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://token.testing.heureka.health/oauth2/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSLCERT, "resources/fire.crt");
        curl_setopt($ch, CURLOPT_SSLKEY, "resources/fire.key");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded"
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "cURL Error: " . curl_error($ch);
            return json_encode(["error" => "cURL Error occurred"]);
        }

        curl_close($ch);

        $response_data = json_decode($response, true);

        if (isset($response_data['access_token']) && isset($response_data['refresh_token'])) {
            save_token($response_data['access_token'], $response_data['refresh_token'], $response_data['expires_in'], 'update');
            return json_encode($response_data);
        } else {
            return json_encode(["error" => "Failed to refresh token", "details" => $response_data]);
        }
    } else {
        return json_encode(["error" => "Error preparing query: " . $conn->error]);
    }
}





function configure_heureka() {
    session_start();
    $user_token = get_access_token();

    $configuration_url = "https://api.testing.heureka.health/api-configuration";

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $configuration_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $user_token"
    ]);
    curl_setopt($ch, CURLOPT_SSLCERT, "resources/fire.crt");
    curl_setopt($ch, CURLOPT_SSLKEY, "resources/fire.key");

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return json_encode(["error" => "cURL Error: " . curl_error($ch)]);
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_json = json_decode($response, true);

        $_SESSION['fhirEndpoint'] = $response_json['fhirEndpoint'] ?? null;
        $_SESSION['heurekaProxy'] = $response_json['proxy'] ?? null;
        $_SESSION['healthcareProviderId'] = $response_json['healthcareProviderId'] ?? null;
        $_SESSION['heurekaGrants'] = $response_json['grants'] ?? null;

        return json_encode($response_json);
    } else {
        return json_encode([
            "error" => "Failed to fetch data",
            "status_code" => $http_code,
            "details" => $response
        ]);
    }
}




function get_patients_heureka() {
    session_start();
    putenv('NO_PROXY=api.testing.heureka.health,authorize.testing.heureka.health,token.testing.heureka.health');

    $user_token = get_access_token();

    $fhir_endpoint = $_SESSION['fhirEndpoint'] ?? null;

    if (!$fhir_endpoint) {
        return json_encode(["error" => "FHIR endpoint not available"]);
    }

    $url = $fhir_endpoint . '/Patient';
    echo "$url<br>";
    $cert = [
        "cert" => __DIR__ . "/resources/fire.crt",
        "key"  => __DIR__ . "/resources/fire.key"
    ];
    $ca_cert = __DIR__ . '/resources/heureka-testing.pem';
    $proxies = [
        'https' => 'http://tunnel.testing.heureka.health:7000'
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $user_token",
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_SSLCERT, $cert['cert']);
    curl_setopt($ch, CURLOPT_SSLKEY, $cert['key']);
    curl_setopt($ch, CURLOPT_CAINFO, $ca_cert);
    curl_setopt($ch, CURLOPT_PROXY, $proxies['https']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (curl_errno($ch)) {
        return json_encode(["error" => "cURL Error: " . curl_error($ch)]);
    }

    if ($http_code == 200) {
        $bundle = json_decode($response, true);
        if (isset($bundle['entry'])) {
            $entries = $bundle['entry'];

            $fileObj = fopen('php://temp', 'r+');
            fwrite($fileObj, '{"resourceType" : "Bundle", "entry": [');

            foreach ($entries as $i => $patient) {
                fwrite($fileObj, json_encode($patient));
                fwrite($fileObj, ",");
                
                $elements_patient = get_elements_for_patient($patient['resource']['id']);
                fwrite($fileObj, $elements_patient);

                if ($i < count($entries) - 1) {
                    fwrite($fileObj, ",");
                }
            }

            fwrite($fileObj, ']}');
            rewind($fileObj);
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="download.json"');
            fpassthru($fileObj);
            fclose($fileObj);
            exit;

        } else {
            return json_encode(["error" => "No patients found in response"]);
        }
    } else {
        return json_encode([
            "error" => "Request failed",
            "status_code" => $http_code,
            "response" => $response
        ]);
    }
}

heureka_api();