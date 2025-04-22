<?php
    include "db.php";

    require __DIR__ . '/vendor/autoload.php';
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
            WHERE birth_year IS NOT NULL AND sex IS NOT NULL AND pms_name = 'heureka_vitomed'
            GROUP BY birth_year, LOWER(sex), pat_sw_id

            UNION ALL

            SELECT '$db1name' AS source, 
                birth_year, 
                LOWER(sex) AS sex, 
                COUNT(DISTINCT pat_sw_id) AS patient_count,
                pat_sw_id
            FROM $db1name.a_patient
            WHERE birth_year IS NOT NULL AND sex IS NOT NULL AND pms_name = 'vitomed'
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

    $vital_match_count = 0;
    $medi_match_count = 0;
    $labor_match_count = 0;

    $match_counts = ["a_vital" => ["matches" => 0, "total_entries" => 0], "a_medi" => ["matches" => 0, "total_entries" => 0], "a_labor" => ["matches" => 0, "total_entries" => 0]];

    $spreadsheet = new Spreadsheet();


    echo "\n\nMATCHES:\n";
    foreach ($matching_results as $table => $results) {

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($table);
        $sheet->setCellValue('A1', 'Fire5 ID');
        $sheet->setCellValue('B1', 'Heureka ID');
        $sheet->setCellValue('C1', 'Tot. entries Fire5');
        $sheet->setCellValue('D1', 'Tot. entries Heureka');
        $sheet->setCellValue('E1', 'Matches');
        $sheet->setCellValue('F1', 'Missing');
        $sheet->setCellValue('G1', 'Extra');

        $present_matches = [];

        $sql = "SELECT COUNT(*) FROM $table";

        $stmt = $conn2->prepare($sql);
        $stmt->execute();
        $total_entries_db2 = $stmt->fetchColumn();

        $stmt = $conn1->prepare($sql);
        $stmt->execute();
        $total_entries_db1 = $stmt->fetchColumn();

        $match_counts[$table]['total_entries'] += (int)$total_entries_db1;

        echo "$table\n";
        $row = 2;
        $total_match_count = ['a_vital' => 0, 'a_medi' => 0, 'a_labor' => 0];

        $total_diff_fire5 = 0;
        $total_diff_heureka = 0;
        $total_entries_db1 = 0;
        $total_entries_db2 = 0;
        $total_match = 0;

        echo count($results) . "\n";
        foreach ($results as $ids) {
            $id1 = $ids[0];
            $id2 = $ids[1];
            $sim_prob = $ids[2];
            $coverage_rate = $ids[3];
            $match_count = $ids[4];
            $tot_entries = $ids[5];
            $other_tot_entries = $ids[6];

            $match_counts[$table]['matches'] += $match_count;
            $total_match_count[$table] += $match_count;
            $total_entries_db1 += $other_tot_entries;
            $total_entries_db2 += $tot_entries;
            $total_match += $match_count;
    
            //$pair_key = $id1 < $id2 ? "$id1-$id2" : "$id2-$id1";
            $key = $id1;

            if ($table == 'a_vital') {
                $vital_matches += 1; 
            } else if ($table == 'a_medi') {
                $medi_matches += 1;
            } else if ($table == 'a_labor') {
                $labor_matches += 1;
            }
    
            if (!isset($unique_pairs_db2[$key])) {
                $unique_pairs_db2[$key] = true;
                $unique_pairs_db1[$id2] = true;
                $total_matches++;

                //echo "$id1 <--> $id2 SIMILARITY: $sim_prob, COVERAGE: $coverage_rate\n";
                $present_matches[] = $id1;
            }

            $sheet->setCellValue("A$row", $id2);
            $sheet->setCellValue("B$row", $id1);
            $sheet->setCellValue("C$row", $other_tot_entries);
            $sheet->setCellValue("D$row", $tot_entries);
            $sheet->setCellValue("E$row", $match_count);

            $diff_fire5 = $other_tot_entries - $match_count;
            $diff_heureka = $tot_entries - $match_count;

            if ($diff_fire5 > 0) {
                $sheet->setCellValue("F$row", $diff_fire5);
            }
            $total_diff_fire5 += $diff_fire5;

            if ($diff_heureka > 0) {
                $sheet->setCellValue("G$row", $diff_heureka);
            }
            $total_diff_heureka += $diff_heureka;

            $row++;
        } 

        $sheet->setCellValue("C$row", $total_entries_db1);
        $sheet->setCellValue("D$row", $total_entries_db2);
        $sheet->setCellValue("E$row", $total_match);
        $sheet->setCellValue("F$row", $total_diff_fire5);
        $sheet->setCellValue("G$row", $total_diff_heureka);
    }




    echo "\n\n";

    echo "PATIENTS Fire5: " . $patients_big . "\n";
    echo "PATIENTS Heureka: " . $patients_small . "\n";
    echo "TOTAL MATCHES: " . $total_matches . "\n";
    echo "PATIENTS FOUND THANKS TO VITAL: " . $vital_matches . "\n";
    echo "PATIENTS FOUND THANKS TO MEDI: " . $medi_matches . "\n";
    echo "PATIENTS FOUND THANKS TO LABOR: " . $labor_matches . "\n";
    

    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle("info");

    $data = [
        ["PATIENTS Fire5", $patients_big],
        ["PATIENTS Heureka", $patients_small],
        ["TOTAL MATCHES", $total_matches],
    ];

    foreach ($match_counts as $table => $numbers) {
        $data[] = ["MATCHES $table", $numbers['matches'] . " / " . $numbers['total_entries']]; 
        echo "MATCHES $table: " . $numbers['matches'] . " / " . $numbers['total_entries'] . "\n";
    }

    
    
    $row = 1;
    foreach ($data as $entry) {
        $sheet->setCellValue("A$row", $entry[0]);
        $sheet->setCellValue("B$row", $entry[1]);
        $row++;
    }

    $spreadsheet->removeSheetByIndex(0);

    $writer = new Xlsx($spreadsheet);
    $writer->save('files/matches.xlsx');


    $sql = "SELECT DISTINCT pat_sw_id, birth_year, sex FROM a_patient ORDER BY birth_year ASC, sex ASC";
    $stmt = $conn2->prepare($sql);
    $stmt->execute();
    $all_patients2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $matched_ids_db2 = array_unique(array_keys($unique_pairs_db2));
    $matched_ids_db1 = array_unique(array_keys($unique_pairs_db1));

    $unmatched_ids2 = [];

    foreach ($all_patients2 as $patient) {
        if (!in_array($patient['pat_sw_id'], $matched_ids_db2)) {
            $unmatched_ids2[] = $patient;
        }
    }

    echo "\n\nUNMATCHED IDS Heureka:\n";
    $count = count($unmatched_ids2);

    $missing_patients2 = [];

    foreach ($unmatched_ids2 as $row) {
        //echo $row['birth_year'] . "   " . $row['sex'] . "   " . $row['pat_sw_id'] . "\n";
        $missing_patients2[] = $row['pat_sw_id'];
    }

    echo "Total unmatched: $count\n";

    $matched_ids2 = array_unique($missing_patients2);
    writeToFile("files/notmatches2.txt", $matched_ids2);



    $sql = "SELECT DISTINCT pat_sw_id, birth_year, sex FROM a_patient ORDER BY birth_year ASC, sex ASC";
    $stmt = $conn1->prepare($sql);
    $stmt->execute();
    $all_patients1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unmatched_ids1 = [];

    foreach ($all_patients1 as $patient) {
        if (!in_array($patient['pat_sw_id'], $matched_ids_db1)) {
            $unmatched_ids1[] = $patient;
        }
    }

    echo "\n\nUNMATCHED IDS fire5:\n";
    $count = count($unmatched_ids1);

    $missing_patients1 = [];

    foreach ($unmatched_ids1 as $row) {
        //echo $row['birth_year'] . "   " . $row['sex'] . "   " . $row['pat_sw_id'] . "\n";
        $missing_patients1[] = $row['pat_sw_id'];
    }

    echo "Total unmatched: $count\n";
    
    $matched_ids1 = array_unique($missing_patients1);
    writeToFile("files/notmatches1.txt", $matched_ids1);

    











    function writeToFile($filename, $elements) {
        $file = fopen($filename, "w");
    
        if ($file === false) {
            die("Error: Unable to open the file.");
        }

        foreach ($elements as $element) {
            fwrite($file, $element . PHP_EOL);
        }
    
        fclose($file);
    
        echo "Data written to $filename successfully.\n";
    }










    function compareTableGeneral($entry) {
        global $conn1, $conn2;
        global $total_matches, $patients_small, $patients_big, $match_threshold, $matching_pairs;
        global $db1name, $db2name;
        global $matching_results;
    
            $tableTimeNames = [
                "a_medi" => ["db1_columns" => ["start_dtime", "gtin"], "db2_columns" => ["start_dtime", "gtin"]],
                "a_vital" => ["db1_columns" => ["vital_dtime", "bmi", "bp_diast", "bp_syst", "pulse", "height", "weight", "body_temp"], "db2_columns" => ["vital_dtime", "bmi", "bp_diast", "bp_syst", "pulse", "height", "weight", "body_temp"]],
                "a_labor" => ["db1_columns" => ["measure_dtime", "lab_label", "lab_value"], "db2_columns" => ["lab_dtime", "lab_label", "lab_value"]],
            ];
    
        $pat_sw_ids_small_vitomed = explode(',', $entry['pat_sw_ids_small_vitomed']);
        $pat_sw_ids_big_vitomed = explode(',', $entry['pat_sw_ids_big_vitomed']);
        $patients_small += count($pat_sw_ids_small_vitomed);
        $patients_big += count($pat_sw_ids_big_vitomed);
         
    
        foreach ($tableTimeNames as $tableName => $columns) {
            $results_db1 = [];
            $similarity_table = [];
            foreach ($pat_sw_ids_big_vitomed as $pat_sw_id) {
                $similarity_table[$pat_sw_id] = 0;
    
                $big_query = "SELECT DISTINCT " . implode(", ", $columns["db1_columns"]) . " FROM $db1name.$tableName WHERE pat_sw_id = :pat_sw_id;";
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
                    foreach ($results_db1 as $other_pat_sw_id => $infos) {
                        foreach ($results as $result) {
                            $datetime = new DateTime($result[$columns["db2_columns"][0]]);
                            $formatted_datetime = $datetime->format('Y-m-d H:i:s');
                            $formatted_datetime_minus_1 = $datetime->modify('-1 hour')->format('Y-m-d H:i:s');
                            $formatted_datetime_minus_2 = $datetime->modify('-2 hours')->format('Y-m-d H:i:s');
    
                            $dates = $infos[$columns["db1_columns"][0]] ?? [];
                            $match_found = false;
                            $match_count = 0;
                            foreach ($dates as $i => $date) {
                                if ($formatted_datetime == $date || $formatted_datetime_minus_1 == $date || $formatted_datetime_minus_2 == $date) {
                                    if ($tableName == "a_medi") {
                                        if ($result[$columns["db2_columns"][1]] == ($infos[$columns["db1_columns"][1]][$i] ?? null)) {
                                            $similarity_table[$other_pat_sw_id] += 1;
                                            $match_found = true;
                                        }
                                    } elseif ($tableName == "a_vital") {
                                        $fieldsToCheck = ["bmi", "bp_diast", "bp_syst", "pulse", "height", "weight", "body_temp"];
                                        $match_found = false;
                                        foreach ($fieldsToCheck as $field) {
                                            $match = ($result[$field] ?? null) == ($infos[$field][$i] ?? null) && $result[$field] !== null;
                                            if ($match) {
                                                $similarity_table[$other_pat_sw_id] += 1;
                                                $match_found = true;
                                                break;
                                            }
                                        }
                                    } elseif ($tableName == "a_labor") {
                                        $label_match = ($result["lab_label"] ?? null) == ($infos["lab_label"][$i] ?? null) && $result["lab_label"] !== null;
                                        $value_match = ($result["lab_value"] ?? null) == ($infos["lab_value"][$i] ?? null) && $result["lab_value"] !== null;
                                        if ($label_match && strpos($result["lab_label"], "Nicht zuordenbare") !== false) {
                                            $similarity_table[$other_pat_sw_id] += 1;
                                            $match_found = true;
                                        } else if ($label_match && $value_match) {
                                            $similarity_table[$other_pat_sw_id] += 1;
                                            $match_found = true;
                                        }
                                    }
                                    if ($match_found) break;
                                }
                            }
                        }
                    }

                    $best_match = array_keys($similarity_table, max($similarity_table))[0];

                    foreach ($results_db1 as $other_pat_sw_id => $infos) {
                        if ($other_pat_sw_id == $best_match) {
                            $similarity_probability = $tot_entries == 0 ? 0 : ($similarity_table[$other_pat_sw_id] / $tot_entries);
                            $coverage_rate = count($infos[$columns["db1_columns"][0]] ?? []) == 0 ? 0 : ($similarity_table[$other_pat_sw_id] / count($infos[$columns["db1_columns"][0]]));

                            $matching_entries = $similarity_table[$other_pat_sw_id];
                            $other_tot_entries = count($infos[$columns["db1_columns"][0]]);
        
                            if ($similarity_probability >= $match_threshold) {
                                $matching_pairs[] = [$pat_sw_id, $other_pat_sw_id];
                                $matching_results[$tableName][] = [$pat_sw_id, $other_pat_sw_id, $similarity_probability, $coverage_rate, $matching_entries, $tot_entries, $other_tot_entries];
                            }
                        }
                    }
                }
            }
        }
    }
    
?>