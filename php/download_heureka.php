<?php
date_default_timezone_set('Europe/Berlin');
session_start();
require_once 'db.php';
require 'vendor/autoload.php';

use Ramsey\Uuid\Guid\Guid;

function download_data($praxis_name, $access_token, $user_id) {
    $access_token = get_access_token($user_id);
    
    if (!$access_token) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Could not obtain access token"]);
        http_response_code(401);
        exit();
    }
    
    configure_heureka($user_id);

    $heureka_grants = $_SESSION['heurekaGrants'] ?? [];
    if (isset($heureka_grants['PATIENT']) && in_array('READ', $heureka_grants['PATIENT'])) {
        $patients = get_patients_heureka($praxis_name, $user_id);
        echo json_encode($patients);
        exit();
    } else {
        $alert_message = "You don't have the required permissions";
        //include 'templates/heureka_connection';
        exit();
    }
}





function get_access_token($user_id) {
    //$user_id = $_SESSION['user_id'] ?? null;

    //if (!$user_id) {
    //    return null;
    //}

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
                $access_token = get_new_access_token($user_id);
            }

            return $access_token;
        } else {
            return null;
        }
    }
}




function get_new_access_token($user_id) {
    //$user_id = $_SESSION['user_id'] ?? null;

    //if (!$user_id) {
    //    return json_encode(["error" => "User ID not found in session"]);
    //}

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
        curl_setopt($ch, CURLOPT_SSLCERT, __DIR__ . "/resources/fire.crt");
        curl_setopt($ch, CURLOPT_SSLKEY, __DIR__ . "/resources/fire.key");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded"
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
                 "cURL Error: " . curl_error($ch);
            return json_encode(["error" => "cURL Error occurred"]);
        }

        curl_close($ch);

        $response_data = json_decode($response, true);

        if (isset($response_data['access_token']) && isset($response_data['refresh_token'])) {
            save_token($response_data['access_token'], $response_data['refresh_token'], $response_data['expires_in'], $user_id, 'update');
            return json_encode($response_data);
        } else {
            return json_encode(["error" => "Failed to refresh token", "details" => $response_data]);
        }
    } else {
        return json_encode(["error" => "Error preparing query: " . $conn->error]);
    }
}





function configure_heureka($user_id) {
    session_start();
    $user_token = get_access_token($user_id);

    $configuration_url = "https://api.testing.heureka.health/api-configuration";

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $configuration_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $user_token"
    ]);
    curl_setopt($ch, CURLOPT_SSLCERT, __DIR__ .  "/resources/fire.crt");
    curl_setopt($ch, CURLOPT_SSLKEY, __DIR__ .  "/resources/fire.key");

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




function get_patients_heureka($praxis_name, $user_id) {
    session_start();
    /*putenv('NO_PROXY=api.testing.heureka.health,authorize.testing.heureka.health,token.testing.heureka.health');*/

    $user_token = get_access_token($user_id);

    $fhir_endpoint = $_SESSION['fhirEndpoint'] ?? null;

    if (!$fhir_endpoint) {
        return json_encode(["error" => "FHIR endpoint not available"]);
    }

    $url = $fhir_endpoint . '/Patient';
    $cert = [
        "cert" => __DIR__ . "/resources/fire.crt",
        "key"  => __DIR__ . "/resources/fire.key"
    ];
    $ca_cert = __DIR__ . '/resources/heureka-testing.pem';
    $proxies = [
        'https' => 'http://tunnel.testing.heureka.health:7000'
    ];

    $ch = curl_init();

    $uuid_v4 = Guid::uuid4()->toString();
    $context_type = "PATIENT_EXPORT";
    $heureka_role = $_SESSION['heureka_role'] ?? 'SYSTEM';

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $user_token",
        "Content-Type: application/x-www-form-urlencoded",
        "X-HEUREKA-RequestContextId: $uuid_v4",
        "X-HEUREKA-RequestContextType: $context_type",
        "X-HEUREKA-UserRole: $heureka_role"
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

            $filePath = '/home/debian/Desktop/json_fire5_parser/json_parser/src/main/resources/files/heureka/full_download/' . $praxis_name . '.json';

            echo "$filePath\n";

            $fileObj = fopen($filePath, 'w');

            if ($fileObj === false) {
                // Handle error: unable to open the file for writing
                echo "Error: Unable to open the file for writing.";
                exit;
            }

            fwrite($fileObj, '{"resourceType" : "Bundle", "entry": [');

            foreach ($entries as $i => $patient) {
                $uuid_v4 = Guid::uuid4()->toString();
                $context_type = "PATIENT_EXPORT";
                $heureka_role = $_SESSION['heureka_role'] ?? 'SYSTEM';
                fwrite($fileObj, json_encode($patient));
                
                $elements_patient = get_elements_for_patient($patient['resource']['id'], $user_id);
                fwrite($fileObj, ",");
                fwrite($fileObj, $elements_patient);

                if ($i < count($entries) - 1) {
                    fwrite($fileObj, ",");
                }
            }

            fwrite($fileObj, ']}');
            //rewind($fileObj);
            //header('Content-Type: application/json');
            //header('Content-Disposition: attachment; filename="download.json"');
            //fpassthru($fileObj);
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




function get_elements_for_patient($patient_id, $user_id/*, $uuid_v4, $context_type, $heureka_role*/) {
    /*putenv('NO_PROXY=api.testing.heureka.health,authorize.testing.heureka.health,token.testing.heureka.health');*/
    
    $user_token = get_access_token($user_id);
    $url_suffixes = [
        ["/Observation?patient=Patient/", $_SESSION['heurekaGrants']['OBSERVATION']],
        ["/Condition?patient=Patient/", $_SESSION['heurekaGrants']['CONDITION']],
        ["/MedicationStatement?subject=Patient/", $_SESSION['heurekaGrants']['MEDICATION_STATEMENT']]
    ];

    $patient_info = "";

    foreach ($url_suffixes as $suffix_data) {
        $url_suffix = $suffix_data[0];
        $grants = $suffix_data[1];

        if (in_array('READ', $grants)) {
            $url = $_SESSION['fhirEndpoint'] . $url_suffix . $patient_id;
            $cert = [__DIR__ . '/resources/fire.crt', __DIR__ . '/resources/fire.key'];
            $ca_cert = __DIR__ . '/resources/heureka-testing.pem';
            $proxies = [
                'https' => 'http://tunnel.testing.heureka.health:7000'
            ];

            $headers = [
                "Authorization: Bearer $user_token"/*,
                "X-HEUREKA-RequestContextId: $uuid_v4",
                "X-HEUREKA-RequestContextType: $context_type",
                "X-HEUREKA-UserRole: $heureka_role"*/
            ];

            try {
                $options = [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSLCERT => $cert[0],
                    CURLOPT_SSLKEY => $cert[1],
                    CURLOPT_CAINFO => $ca_cert,
                    CURLOPT_PROXY => $proxies['https'],
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_VERBOSE => true
                ];

                $ch = curl_init();
                curl_setopt_array($ch, $options);
                $response = curl_exec($ch);

                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($http_code == 200) {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['entry'][0])) {
                        $patient_info .= json_encode($response_data['entry'][0]) . ",";
                    }
                } else {
                    $patient_info .= "Request failed with status code: $http_code\n";
                    echo "Request failed with status code: $http_code\n";
                    echo "Response: $response\n";
                    curl_close($ch);
                    return $response;
                }

                curl_close($ch);
            } catch (Exception $e) {
                echo "An error occurred: " . $e->getMessage();
            }
        }
    } 

    $patient_info = rtrim($patient_info, ",");
    return $patient_info;
}


$conn = get_db_connection();

$sql = "
    SELECT 
        uc.username AS praxis_name,   
        ut.user_id,               
        ut.access_token           
    FROM 
        user_credentials uc
    JOIN 
        user_tokens ut
    ON 
        uc.id = ut.user_id;
";

$stmt = $conn->prepare($sql);
$stmt->execute();

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    download_data($row['praxis_name'], $row['access_token'], $row['user_id']);
}
