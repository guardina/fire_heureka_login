<?php
date_default_timezone_set('Europe/Berlin');
session_start();
require_once 'db.php';
require 'vendor/autoload.php';

use Ramsey\Uuid\Guid\Guid;

function download_data($praxis_name, $access_token, $user_id) {
    echo "-------- DOWNLOADING $praxis_name DATA --------\n";

    $ch = curl_init();
    $access_token = get_access_token($user_id, $ch);

    if (!$access_token) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Could not obtain access token"]);
        http_response_code(401);
        exit();
    }

    $start_time = microtime(true);

    configure_heureka($user_id, $ch);

    $heureka_grants = $_SESSION['heurekaGrants'] ?? [];

    if (isset($heureka_grants['PATIENT']) && in_array('READ', $heureka_grants['PATIENT'])) {
        $todayDate = date("Y-m-d");
	    $patients = get_patients_heureka($praxis_name, $user_id, $ch, $todayDate);
        //echo json_encode($patients);
	    $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        echo "Execution time: " . $execution_time . " seconds.";

        curl_close($ch);
        exit();
    } else {
        $alert_message = "You don't have the required permissions";
	    curl_close($ch);
        exit();
    }
}





function get_access_token($user_id, $ch) {

    $conn = get_db_connection('fire_heureka_credentials');

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
                $access_token = get_new_access_token($user_id, $ch);
            }

            return $access_token;
        } else {
            return null;
        }
    }
}




function get_new_access_token($user_id, $other_ch) {

    $conn = get_db_connection('fire_heureka_credentials');

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
            //"client_id" => "173e5603-6107-4521-a465-5b9dc86b2e95",
	    "client_id" => "f49bcad4-cf7b-4fd8-8b4d-aaab9b390cfb",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://token.heureka.health/oauth2/token");
	    //curl_setopt($ch, CURLOPT_URL, "https://token.testing.heureka.health/oauth2/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSLCERT, __DIR__ . "/resources/fire.crt");
        curl_setopt($ch, CURLOPT_SSLKEY, __DIR__ . "/resources/fire.key");
	    //curl_setopt($ch, CURLOPT_SSLCERT, __DIR__ . "/old_cert/fire.crt");
	    //curl_setopt($ch, CURLOPT_SSLKEY, __DIR__ . "/old_cert/fire.key");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded"
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $message = "cURL Error: " . curl_error($ch);
            return json_encode(["error" => "cURL Error occurred"]);
        }

        //curl_close($ch);

        $response_data = json_decode($response, true);

        if (isset($response_data['access_token']) && isset($response_data['refresh_token'])) {
            save_token($response_data['access_token'], $response_data['refresh_token'], $response_data['expires_in'], $user_id, 'update');
	        //echo $response_data['access_token'] . "\n\n";
	        //echo $response_data['refresh_token'] . "\n\n";
	        return json_encode($response_data);
        } else {
            return json_encode(["error" => "Failed to refresh token", "details" => $response_data]);
        }
    } else {
        return json_encode(["error" => "Error preparing query: " . $conn->error]);
    }
}



function configure_heureka($user_id, $ch) {
    //session_start();
    $user_token = get_access_token($user_id, $ch);
    //$configuration_url = "https://api.testing.heureka.health/api-configuration";
    $configuration_url = "https://api.heureka.health/api-configuration";

    //$ch = curl_init();

    curl_setopt($ch, CURLOPT_HTTPHEADER, []);
    curl_setopt($ch, CURLOPT_URL, $configuration_url);
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $user_token"
    ]);
    curl_setopt($ch, CURLOPT_SSLCERT, __DIR__ .  "/resources/fire.crt");
    curl_setopt($ch, CURLOPT_SSLKEY, __DIR__ .  "/resources/fire.key");
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
    //curl_setopt($ch, CURLOPT_SSLCERT, __DIR__ . "/old_cert/fire.crt");
    //curl_setopt($ch, CURLOPT_SSLKEY, __DIR__ . "/old_cert/fire.key");

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        //curl_close($ch);
        return json_encode(["error" => "cURL Error: " . curl_error($ch)]);
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //curl_close($ch);

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




function get_patients_heureka($praxis_name, $user_id, $ch, $todayDate) {

    $todayDate = date("Y-m-d");
    $baseDir = '/home/administrator/fire-heureka/production/full_download/';
    //$filePath = '/home/administrator/fire-heureka/full_download/' . $praxi>

    $folderPath = $baseDir . $todayDate;

    $filePath = $folderPath . '/' . $praxis_name . '.json';

    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0777, true);
    }

    $fileObj = fopen($filePath, 'w');

    if ($fileObj === false) {
        echo "Error: Unable to open the file for writing.";
        exit;
    }

    fwrite($fileObj, '{"resourceType" : "Bundle", "entry": [');


    $cert = [
        "cert" => __DIR__ . "/resources/fire.crt",
        "key"  => __DIR__ . "/resources/fire.key"
        //"cert" => __DIR__ . "/old_cert/fire.crt",
        //"key" => __DIR__ . "/old_cert/fire.key"
    ];
    //$ca_cert = __DIR__ . '/old_cert/heureka-testing.pem';
    $ca_cert = __DIR__ . '/resources/heureka-production.pem';
    $proxies = [
        //'https' => 'http://tunnel.testing.heureka.health:7000'
        'https' => 'http://tunnel.heureka.health:7000'
    ];


    $uuid_v4 = Guid::uuid4()->toString();
    $context_type = "PATIENT_EXPORT";
    $heureka_role = $_SESSION['heureka_role'] ?? 'SYSTEM';


    curl_setopt($ch, CURLOPT_HTTPHEADER, []);
    //curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSLCERT, $cert['cert']);
    curl_setopt($ch, CURLOPT_SSLKEY, $cert['key']);
    curl_setopt($ch, CURLOPT_CAINFO, $ca_cert);
    curl_setopt($ch, CURLOPT_PROXY, $proxies['https']);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);


    $numProcesses = 10;
    $processes = [];

    $hasNextFile = 'has_next.txt';
    $has_next = true;
    file_put_contents($hasNextFile, $has_next ? 'true' : 'false');
    $start_offset = 0;
    $offset = 300;

    while($has_next) {

        $has_next = trim(file_get_contents($hasNextFile));
        if ($has_next === 'false') {
            break;
        }

        $user_token = get_access_token($user_id, $ch);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $user_token",
            /*"Content-Type: application/x-www-form-urlencoded",*/
            "X-HEUREKA-RequestContextId: $uuid_v4",
            "X-HEUREKA-RequestContextType: $context_type",
            "X-HEUREKA-UserRole: $heureka_role"
        ]);

        for ($i = 0; $i < $numProcesses; $i++) {
            $pid = pcntl_fork();
        
            if ($pid == -1) {
                die("Could not fork process $i\n");
            } elseif ($pid === 0) {
		        sleep(1); 

                $fhir_endpoint = $_SESSION['fhirEndpoint'] ?? null;

                if (!$fhir_endpoint) {
                    return json_encode(["error" => "FHIR endpoint not available"]);
                }

                $your_offset = $start_offset + ($offset * $i);

                $url = $fhir_endpoint . '/Patient?_count=300&_offset=' . $your_offset;
                echo "$url\n\n\n";
                
                curl_setopt($ch, CURLOPT_URL, $url);
                

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                //curl_close($ch);

                if (curl_errno($ch)) {
                    return json_encode(["error" => "cURL Error: " . curl_error($ch)]);
                }

            if ($http_code == 200) {
                $bundle = json_decode($response, true);
                $total_patients = $bundle['total'];
                echo "Patient to process: $total_patients\n";
                if ($total_patients < 299) {
                    echo "ENDING SOON\n";
                    $has_next = false;
                    file_put_contents($hasNextFile, 'false');
                }

                if (isset($bundle['entry'])) {
                    $entries = $bundle['entry'];

                    $baseDirTemp = '/home/administrator/fire-heureka/test/full_download/' . $todayDate;
                    $filePathTemp = $baseDirTemp . '/temp' . $i . '.json';

                    $fileObjTemp = fopen($filePathTemp, 'w');

                    foreach ($entries as $i => $patient) {
                        echo "Patient [" . $offset+$i . "]\n";
                        fwrite($fileObjTemp, json_encode($patient));

                        $elements_patient = get_elements_for_patient($patient['resource']['id'], $user_id, $uuid_v4, $context_type, $heureka_role, $ch);
                        if ($elements_patient !== '') {
                            fwrite($fileObjTemp, ",");
                            fwrite($fileObjTemp, $elements_patient);
                        }

                        if ($i < count($entries) - 1 || $total_patients === 299 || $total_patients === 300) {
                            fwrite($fileObjTemp, ",");
                        }
                    }
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

            exit(0);
        } else {
            $processes[] = $pid;
        }

        foreach ($processes as $process) {
            pcntl_waitpid($process, $status);
        }

        for ($i = 0; $i < $numProcesses; $i++) {
            $tempFile = "full_download/$todayDate/temp$i.json";
            if (file_exists($tempFile)) {
                $tempContent = file_get_contents($tempFile);
                fwrite($fileObj, $tempContent);
                unlink($tempFile);
            }
        }

        $start_offset += $offset * $numProcesses;
    }

    fwrite($fileObj, ']}');
    fclose($fileObj);
    }
}




function get_elements_for_patient($patient_id, $user_id, $uuid_v4, $context_type, $heureka_role, $ch) {
    /*putenv('NO_PROXY=api.testing.heureka.health,authorize.testing.heureka.health,token.testing.heureka.health');*/

    $successful_request = false;

    while (!$successful_request) {
        $user_token = get_access_token($user_id, $ch);
        $url_suffixes = [
            "Observation" => ["/Observation?patient=Patient/", $_SESSION['heurekaGrants']['OBSERVATION'], 'OBS'],
            "Condition" => ["/Condition?patient=Patient/", $_SESSION['heurekaGrants']['CONDITION'], 'CON'],
            "MedicationStatement" => ["/MedicationStatement?subject=Patient/", $_SESSION['heurekaGrants']['MEDICATION_STATEMENT'], 'MED']
        ];

        $patient_info = "";

        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $responses = [];

        $cert = [__DIR__ . '/resources/fire.crt', __DIR__ . '/resources/fire.key'];
        //$cert = [__DIR__ . '/old_cert/fire.crt', __DIR__ . '/old_cert/fire.key'];
        $ca_cert = __DIR__ . '/resources/heureka-production.pem';
        //$ca_cert = __DIR__ . '/old_cert/heureka-testing.pem';
        $proxies = [
            //'https' => 'http://tunnel.testing.heureka.health:7000'
            'https' => 'http://tunnel.heureka.health:7000'
        ];

        foreach ($url_suffixes as $suffix_data) {
            $url_suffix = $suffix_data[0];
            $grants = $suffix_data[1];
            $method = $suffix_data[2];

            if (in_array('READ', $grants)) {
                $url = $_SESSION['fhirEndpoint'] . $url_suffix . $patient_id;

                $headers = [
                    "Authorization: Bearer $user_token",
                    "X-HEUREKA-RequestContextId: $uuid_v4",
                    "X-HEUREKA-RequestContextType: $context_type",
                    "X-HEUREKA-UserRole: $heureka_role"
                ];

                $new_ch = curl_init();

                $options = [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSLCERT => $cert[0],
                    CURLOPT_SSLKEY => $cert[1],
                    CURLOPT_CAINFO => $ca_cert,
                    CURLOPT_PROXY => $proxies['https'],
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                    //CURLOPT_VERBOSE => true
                ];

                curl_setopt_array($new_ch, $options);

                curl_multi_add_handle($multiHandle, $new_ch);
                $curlHandles[$key] = $new_ch;
            }

        }

        do {
            $status = curl_multi_exec($multiHandle, $active);
            curl_multi_select($multiHandle);
        } while ($active && $status == CURLM_OK);

        foreach ($curlHandles as $key => $temp_ch) {
            $responses[$key] = curl_multi_getcontent($temp_ch);
            curl_multi_remove_handle($multiHandle, $temp_ch);
            curl_close($temp_ch);
        }

        foreach (["Observation", "Condition", "MedicationStatement"] as $endpoint) {
            if (isset($responses[$endpoint])) {
                $response_data = json_decode($responses[$endpoint], true);
                if (isset($response_data['entry'][0]) && json_last_error() == JSON_ERROR_NONE) {
                    $patient_info .= json_encode($response_data['entry'][0]) . ",";
                }
            }
        }
        
        usleep(10000);
    }

    $patient_info = rtrim($patient_info, ",");
    return $patient_info;
}


$conn = get_db_connection('fire_heureka_credentials');

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

// Currently in DB: 163, 167
$to_download = [167];

foreach ($results as $row) {
    if (empty($to_download) || in_array($row['user_id'], $to_download)) {
        $pid = pcntl_fork();

        if ($pid == -1) {
            die("Failed");
        } else if ($pid) {
            continue;
        } else {
            //print_r($row);
            download_data($row['praxis_name'], $row['access_token'], $row['user_id']);
            exit(0);
        }
        
    }
}

while (pcntl_waitpid(0, $status) != -1);