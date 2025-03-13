<?php
    include "db.php";

    // WORK
    //$db1name = 'fire5_test';   // Fabio
    //$db2name = 'fire5_vito_new';     // Heureka

    // HOME
    $db1name = 'fire5_big_vitomed';   // Fabio
    $db2name = 'fire5_small_vitomed';     // Heureka

    $conn1 = get_db_connection($db1name);
    $conn2 = get_db_connection($db2name);

    $conn1->query("SET SESSION group_concat_max_len = 1000000;");

    $threshold = 100;



    $query = 
    "   SELECT 
        birth_year, 
        LOWER(sex) AS sex,
        SUM(CASE WHEN source = '$db2name' THEN patient_count ELSE 0 END) AS count_small_vitomed,
        SUM(CASE WHEN source = '$db1name' THEN patient_count ELSE 0 END) AS count_big_vitomed,
        GROUP_CONCAT(DISTINCT CASE WHEN source = '$db2name' THEN pat_sw_id ELSE NULL END) AS pat_sw_ids_small_vitomed,
        GROUP_CONCAT(DISTINCT CASE WHEN source = '$db1name' THEN pat_sw_id ELSE NULL END) AS pat_sw_ids_big_vitomed
        FROM (
            SELECT '$db2name' AS source, 
                birth_year, 
                LOWER(sex) AS sex, 
                COUNT(*) AS patient_count,
                pat_sw_id
            FROM $db2name.a_patient
            WHERE birth_year IS NOT NULL AND sex IS NOT NULL AND pms_name = 'vitomed'
            GROUP BY birth_year, LOWER(sex), pat_sw_id

            UNION ALL

            SELECT '$db1name' AS source, 
                birth_year, 
                LOWER(sex) AS sex, 
                COUNT(DISTINCT pat_sw_id) AS patient_count,
                pat_sw_id
            FROM $db1name.a_patient
            WHERE birth_year IS NOT NULL AND sex IS NOT NULL
            GROUP BY birth_year, LOWER(sex), pat_sw_id
        ) AS combined
        GROUP BY birth_year, sex
        HAVING count_small_vitomed <= $threshold AND count_big_vitomed <= $threshold
        ORDER BY birth_year ASC, sex ASC;
    ";


    $get_patient_ids = $conn1->prepare($query);
    $get_patient_ids->execute();

    $patient_ids = $get_patient_ids->fetchAll(PDO::FETCH_ASSOC);


    $total_matches = 0;
    $total_patients = 0;
    $patients_small = 0;
    $patients_big = 0;
    $match_threshold = 0.1;
    $matching_pairs = [];
    $matching_results = ["a_labor" => [], "a_medi" => [], "a_vital" => []];

    //foreach ($tableTimeNames as $tableName => $dateColumnName) {
        //echo "$tableName\n";
        //echo "-------------------------------------------------------------------------------------\n";
        foreach ($patient_ids as $entry) {
            echo "Sex: " . $entry["sex"] . "          Birth year: " . $entry["birth_year"] . "\n";
            echo "-------------------------------------------------------------------------------------\n";
            compareTableGeneral($entry);
            echo "-------------------------------------------------------------------------------------\n";
        }
        //echo "\n\n\n";
    //}


    $vital_matches = 0;
    $medi_matches = 0;
    $labor_matches = 0;


    echo "\n\nMATCHES:\n";
    foreach ($matching_results as $table => $results) {
        echo "$table\n";
        foreach ($results as $ids) {
            $id1 = $ids[0];
            $id2 = $ids[1];
    
            $pair_key = $id1 < $id2 ? "$id1-$id2" : "$id2-$id1";
    
            if (!isset($unique_pairs[$pair_key])) {
                if ($table == 'a_vital') {
                    $vital_matches += 1; 
                } else if ($table == 'a_medi') {
                    $medi_matches += 1;
                } else if ($table == 'a_labor') {
                    $labor_matches += 1;
                }
                $unique_pairs[$pair_key] = true;
                $total_matches++;

                echo "$id1 <--> $id2\n";
            }
        } 
    }

    echo "\n\n";




    echo "\n\n";
    echo "TOTAL MATCHES: " . $total_matches . "\n";
    echo "VITAL MATCHES: " . $vital_matches . "\n";
    echo "MEDI MATCHES: " . $medi_matches . "\n";
    echo "LABOR MATCHES: " . $labor_matches . "\n";
    echo "PATIENTS Fabio: " . $patients_big . "\n";
    echo "PATIENTS Heureka: " . $patients_small . "\n";
    echo "TOTAL PATIENTS: " . $total_patients . "\n";




    function compareTableGeneral($entry) {
        global $conn1, $conn2;
        global $total_matches, $total_patients, $patients_small, $patients_big, $match_threshold, $matching_pairs;
        global $db1name, $db2name;
        global $matching_results;
    
        $tableTimeNames = [
            /*"a_medi" => ["db1_columns" => ["start_dtime", "gtin"], "db2_columns" => ["start_dtime", "gtin"]],
            "a_vital" => ["db1_columns" => ["vital_dtime", "bmi", "bp_diast", "bp_syst"], "db2_columns" => ["vital_dtime", "bmi", "bp_diast", "bp_syst"]],*/
            "a_labor" => ["db1_columns" => ["measure_dtime", "lab_label", "lab_value"], "db2_columns" => ["lab_dtime", "lab_label", "lab_value"]],
        ];
    
        $pat_sw_ids_small_vitomed = explode(',', $entry['pat_sw_ids_small_vitomed']);
        $pat_sw_ids_big_vitomed = explode(',', $entry['pat_sw_ids_big_vitomed']);
        $patients_small += count($pat_sw_ids_small_vitomed);
        $patients_big += count($pat_sw_ids_big_vitomed);
        $total_patients += max(count($pat_sw_ids_small_vitomed), count($pat_sw_ids_big_vitomed));
         
    
        foreach ($tableTimeNames as $tableName => $columns) {
            $results_db1 = [];
            $similarity_table = [];
            foreach ($pat_sw_ids_big_vitomed as $pat_sw_id) {
                $similarity_table[$pat_sw_id] = 0;
    
                $big_query = "SELECT " . implode(", ", $columns["db1_columns"]) . " FROM $db1name.$tableName WHERE pat_sw_id = :pat_sw_id;";
                $stmt_big = $conn1->prepare($big_query);
                $stmt_big->execute(['pat_sw_id' => $pat_sw_id]);
                $results = $stmt_big->fetchAll(PDO::FETCH_ASSOC);
    
                if ($results) {
                    foreach ($results as $result) {
                        foreach ($columns["db1_columns"] as $col) {
                            $results_db1[$pat_sw_id][$col][] = $result[$col] ?? null;
                        }
                    }
                }
            }

            /*if ($tableName == 'a_labor') {
                echo "START\n\n";
                var_dump($results_db1);
                echo "\n\n";
            }*/

            foreach ($pat_sw_ids_small_vitomed as $pat_sw_id) {
                foreach ($similarity_table as $name => $value) {
                    $similarity_table[$name] = 0;
                }
    
                $small_query = "SELECT " . implode(", ", $columns["db2_columns"]) . " FROM $db2name.$tableName WHERE pat_sw_id = :pat_sw_id;";
                $stmt_small = $conn2->prepare($small_query);
                $stmt_small->execute(['pat_sw_id' => $pat_sw_id]);
                $results = $stmt_small->fetchAll(PDO::FETCH_ASSOC);
    
                if ($results) {
                    $tot_entries = count($results);
                    foreach ($results as $result) {
                        foreach ($results_db1 as $other_pat_sw_id => $infos) {
                            $datetime = new DateTime($result[$columns["db2_columns"][0]]);
                            $formatted_datetime = $datetime->format('Y-m-d H:i:s');
                            $formatted_datetime_minus_1 = $datetime->modify('+1 hour')->format('Y-m-d H:i:s');
                            $formatted_datetime_minus_2 = $datetime->modify('+2 hours')->format('Y-m-d H:i:s');
    
                            $dates = $infos[$columns["db1_columns"][0]] ?? [];
                            $match_found = false;
    
                            foreach ($dates as $i => $date) {
                                if ($formatted_datetime == $date || $formatted_datetime_minus_1 == $date || $formatted_datetime_minus_2 == $date) {
                                    if ($tableName == "a_medi") {
                                        if ($result[$columns["db2_columns"][1]] == ($infos[$columns["db1_columns"][1]][$i] ?? null)) {
                                            $similarity_table[$other_pat_sw_id] += 1;
                                            $match_found = true;
                                        }
                                    } elseif ($tableName == "a_vital") {
                                        $bmi_match = ($result["bmi"] ?? null) == ($infos["bmi"][$i] ?? null) && $result["bmi"] !== null;
                                        $bp_diast_match = ($result["bp_diast"] ?? null) == ($infos["bp_diast"][$i] ?? null) && $result["bp_diast"] !== null;
                                        $bp_syst_match = ($result["bp_syst"] ?? null) == ($infos["bp_syst"][$i] ?? null) && $result["bp_syst"] !== null;

                                        if ($bmi_match || $bp_diast_match || $bp_syst_match) {
                                            $similarity_table[$other_pat_sw_id] += 1;
                                            $match_found = true;
                                        }
                                    } elseif ($tableName == "a_labor") {
                                        if ($result["lab_label"] != 'P-LCC' && $infos["lab_label"][$i] != 'P-LCR') {
                                            echo $result["lab_label"] . " ------> " . $infos["lab_label"][$i] . "\n";
                                            echo $result["lab_value"] . " ------> " . $infos["lab_value"][$i] . "\n";
                                            echo "\n";
                                        }
                                        $label_match = ($result["lab_label"] ?? null) == ($infos["lab_label"][$i] ?? null) && $result["lab_label"] !== null;
                                        $value_match = ($result["lab_value"] ?? null) == ($infos["lab_value"][$i] ?? null) && $result["lab_value"] !== null;

                                        if ($label_match && $value_match) {
                                            $similarity_table[$other_pat_sw_id] += 1;
                                            $match_found = true;
                                        }

                                    }
                                    if ($match_found) break;
                                }
                            }
                        }
                    }
    
                    foreach ($results_db1 as $other_pat_sw_id => $infos) {
                        $similarity_probability = $tot_entries == 0 ? 0 : ($similarity_table[$other_pat_sw_id] / $tot_entries);
                        $coverage_rate = count($infos[$columns["db1_columns"][0]] ?? []) == 0 ? 0 : ($similarity_table[$other_pat_sw_id] / count($infos[$columns["db1_columns"][0]]));
    
                        if ($similarity_probability >= $match_threshold) {
                            $matching_pairs[] = [$pat_sw_id, $other_pat_sw_id];
                            if ($tableName == 'a_vital') {
                                $matching_results['a_vital'][] = [$pat_sw_id, $other_pat_sw_id];
                            } else if ($tableName == 'a_medi') {
                                $matching_results['a_medi'][] = [$pat_sw_id, $other_pat_sw_id];
                            } else if ($tableName == 'a_labor') {
                                $matching_results['a_labor'][] = [$pat_sw_id, $other_pat_sw_id];
                            }
                        }
                    }
                }
            }
        }
    }
    
    