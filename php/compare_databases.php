<?php
    include "db.php";

    $db1name = 'fire5_test';   // Fabio
    $db2name = 'fire5_vito_new';     // Heureka

    $conn1 = get_db_connection($db1name);
    $conn2 = get_db_connection($db2name);

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
        HAVING count_small_vitomed BETWEEN 0 AND 50 AND count_big_vitomed BETWEEN 0 AND 50
        ORDER BY birth_year ASC, sex ASC;
    ";


    $get_patient_ids = $conn1->prepare($query);
    $get_patient_ids->execute();

    $patient_ids = $get_patient_ids->fetchAll(PDO::FETCH_ASSOC);


    $tableTimeNames = [
        "a_vital" => ["db1_columns" => ["vital_dtime", "bmi", "bp_diast", "bp_syst"], "db2_columns" => ["vital_dtime", "bmi", "bp_diast", "bp_syst"]],
        /*"a_labor" => ["db1_columns" => ["measure_dtime", "lab_label", "lab_value"], "db2_columns" => ["lab_dtime", "lab_label", "lab_value"]],*/
        "a_medi" => ["db1_columns" => ["start_dtime", "gtin"], "db2_columns" => ["start_dtime", "gtin"]],
        /*"a_pdlist" => ["db1_columns" => ["pd_start_dtime", "description"], "db2_columns" => ["pd_start_dtime", "description"]]*/
    ];


    $total_matches = 0;
    $total_patients = 0;
    $patients_small = 0;
    $patients_big = 0;
    $match_threshold = 0.1;
    $matching_pairs = [];

    //foreach ($tableTimeNames as $tableName => $dateColumnName) {
        //echo "$tableName\n";
        //echo "-------------------------------------------------------------------------------------\n";
        foreach ($patient_ids as $entry) {
            echo "Sex: " . $entry["sex"] . "          Birth year: " . $entry["birth_year"] . "\n";
            echo "-------------------------------------------------------------------------------------\n";
            compareTable($entry);
            echo "-------------------------------------------------------------------------------------\n";
        }
        //echo "\n\n\n";
    //}



    echo "\n\n";
    echo "TOTAL MATCHES: " . $total_matches . "\n";
    echo "PATIENTS 1: " . $patients_small . "\n";
    echo "PATIENTS 2: " . $patients_big . "\n";
    echo "TOTAL PATIENTS: " . $total_patients . "\n";


    /*echo "\n\nMATCHES:\n";
    foreach ($matching_pairs as $ids) {
        $id1 = $ids[0];
        $id2 = $ids[1];

        echo $id1 . " <--> " . $id2 . "\n"; 
    }*/
    

    echo "\n\n";




    function compareTable($entry) {
        global $conn1, $conn2;
        global $total_matches, $total_patients, $patients_small, $patients_big, $match_threshold, $matching_pairs;
        global $tableTimeNames;
        global $db1name, $db2name;
    
        $pat_sw_ids_small_vitomed = explode(',', $entry['pat_sw_ids_small_vitomed']);
        $pat_sw_ids_big_vitomed = explode(',', $entry['pat_sw_ids_big_vitomed']);
        $patients_small += count($pat_sw_ids_small_vitomed);
        $patients_big += count($pat_sw_ids_big_vitomed);
        $total_patients += max(count($pat_sw_ids_small_vitomed), count($pat_sw_ids_big_vitomed));
    
        $results_db1 = [];
        $similarity_table = [];
    
        foreach ($tableTimeNames as $tableName => $dateColumnName) {
            echo "Processing Table: $tableName\n";
    
            echo "FABIO\n";
            foreach ($pat_sw_ids_big_vitomed as $pat_sw_id) {
                echo "$pat_sw_id\n";
                $similarity_table[$pat_sw_id] = 0;
    
                $columns = $dateColumnName["db1_columns"];
                
                $big_query = "
                    SELECT " . implode(", ", $columns) . " FROM $db1name.$tableName WHERE pat_sw_id = :pat_sw_id;
                ";
                
                $stmt_small = $conn1->prepare($big_query);
                $stmt_small->execute(['pat_sw_id' => $pat_sw_id]);
                $results = $stmt_small->fetchAll(PDO::FETCH_ASSOC);
    
                if ($results) {
                    foreach ($results as $result) {

                        $results_db1[$pat_sw_id][$columns[0]][] = $result[$columns[0]];
    
                        if (isset($columns[1]) && $tableName == 'a_medi') {
                            $result[$columns[1]] = explode(" ", $result[$columns[1]])[0];
                            //$results_db1[$pat_sw_id][$columns[1]][] = $result[$columns[1]];
                        }
                        $results_db1[$pat_sw_id][$columns[1]][] = $result[$columns[1]];
                        if ($tableName == 'a_vital') {
                            $results_db1[$pat_sw_id][$columns[2]][] = $result[$columns[2]];
                            $results_db1[$pat_sw_id][$columns[3]][] = $result[$columns[3]];
                        }
                        
    
                        //echo implode(": ", array_map(fn($col) => "$col: " . $result[$col], $columns)) . "\n";
                    }
                }
            }


            //var_dump($results_db1);
            echo "\nHEUREKA\n";
    
            foreach ($pat_sw_ids_small_vitomed as $pat_sw_id) {
                echo "$pat_sw_id\n";
    
                foreach ($similarity_table as $name => $value) {
                    $similarity_table[$name] = 0;
                }
    
                $columns = $dateColumnName["db2_columns"];
    
                $small_query = "
                    SELECT " . implode(", ", $columns) . " FROM $db2name.$tableName WHERE pat_sw_id = :pat_sw_id;
                ";
    
                $stmt_big = $conn2->prepare($small_query);
                $stmt_big->execute(['pat_sw_id' => $pat_sw_id]);
                $results = $stmt_big->fetchAll(PDO::FETCH_ASSOC);
    
                if ($results) {
                    $tot_entries = count($results);
                    foreach ($results as $result) {
                        foreach ($columns as $column) {
                            if ($tableName == 'a_medi') {
                                if ($column == $columns[1] && isset($result[$column])) {
                                    $result[$column] = explode(" ", $result[$column])[0];
                                }
                            }
                        }
    
                        //echo implode(": ", array_map(fn($col) => "$col: " . $result[$col], $columns)) . "\n";
    
                        foreach ($results_db1 as $other_pat_sw_id => $infos) {

                            /*if ($tableName == 'a_vital') {
                                var_dump($infos);
                            }*/
                            if (isset($infos[$columns[0]])) {
                                $dates = $infos[$columns[0]];
                            } else {
                                $dates = [];
                            }

                            if (isset($columns[1])) {
                                $medi_labels = isset($infos[$columns[1]]) ? $infos[$columns[1]] : [];
                                $medi_label = isset($result[$columns[1]]) ? $result[$columns[1]] : null;
                            } else {
                                $medi_labels = [];
                                $medi_label = null;
                            }

                            if ($tableName == 'a_vital') {
                                if (isset($columns[2])) {
                                    $medi_labels2 = isset($infos[$columns[2]]) ? $infos[$columns[2]] : [];
                                    $medi_label2 = isset($result[$columns[2]]) ? $result[$columns[2]] : null;
                                } else {
                                    $medi_labels2 = [];
                                    $medi_label2 = null;
                                }

                                if (isset($columns[3])) {
                                    $medi_labels3 = isset($infos[$columns[3]]) ? $infos[$columns[3]] : [];
                                    $medi_label3 = isset($result[$columns[3]]) ? $result[$columns[3]] : null;
                                } else {
                                    $medi_labels3 = [];
                                    $medi_label3 = null;
                                }
                            }


                        
                            $datetime = new DateTime($result[$columns[0]]);
                            $formatted_datetime = $datetime->format('Y-m-d H:i:s');
                        
                            $datetime_minus_1 = clone $datetime;
                            $datetime_minus_1->modify('-1 hour');
                            $formatted_datetime_minus_1 = $datetime_minus_1->format('Y-m-d H:i:s');
                        
                            $datetime_minus_2 = clone $datetime;
                            $datetime_minus_2->modify('-2 hours');
                            $formatted_datetime_minus_2 = $datetime_minus_2->format('Y-m-d H:i:s');
                        
                            if (count($columns) == 1) {
                                
                                for ($i = 0; $i < count($dates); $i++) {
                                    if ($formatted_datetime == $dates[$i] || $formatted_datetime_minus_1 == $dates[$i] || $formatted_datetime_minus_2 == $dates[$i]) {
                                        $similarity_table[$other_pat_sw_id] += 1;
                                        break;
                                    }
                                }
                            } else if (count($columns) > 1) {

                                for ($i = 0; $i < count($dates); $i++) {
                                    if (($formatted_datetime == $dates[$i] || $formatted_datetime_minus_1 == $dates[$i] || $formatted_datetime_minus_2 == $dates[$i]) &&
                                        $medi_label == $medi_labels[$i]) {
                                        $similarity_table[$other_pat_sw_id] += 1;
                                        break;
                                    }
                                }
                            }
                        }
                    }
    
                    echo "\n\n";
    
                    foreach ($results_db1 as $other_pat_sw_id => $infos) {
    
                        if ($tot_entries == 0) {
                            $similarity_probability = 0;
                        } else {
                            $similarity_probability = ($similarity_table[$other_pat_sw_id] / $tot_entries);
                        }
                        
                        echo $similarity_table[$other_pat_sw_id] . " --> " . $tot_entries . "\n";
                        echo "Similarity [" . $pat_sw_id . "] <--> [" . $other_pat_sw_id . "]: " . $similarity_probability . "\n";
    
                        if (count($dates) == 0) {
                            $coverage_rate = 0;
                        } else {
                            $coverage_rate = ($similarity_table[$other_pat_sw_id] / count($dates));
                        }
                        
                        echo $similarity_table[$other_pat_sw_id] . " --> " . count($dates) . "\n";
                        echo "Coverage [" . $pat_sw_id . "] <--> [" . $other_pat_sw_id . "]: " . $coverage_rate . "\n";
    
                        if ($similarity_probability >= $match_threshold) {
                            $total_matches += 1;
                            $matching_pairs[] = [$pat_sw_id, $other_pat_sw_id];
                        }
                    }
                }
            }
            echo "\n\n\n";
        }
    }
    