<?php
    include "db.php";

    // WORK
    $db1name = 'fire5_vito_new';
    $db2name = 'fire5_test';

    // HOME
    //$db1name = 'fire5_small_vitomed';
    //$db2name = 'fire5_big_vitomed';

    $conn1 = get_db_connection($db1name);
    $conn2 = get_db_connection($db2name);



    $firstValues = [];
    $secondValues = [];
    
    $lines = file('files/lab_matches', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) == 2) {
            $firstValues[] = $parts[0];
            $secondValues[] = $parts[1];
        }
    }
    
    
    analyse_labor($db1name, $db2name);
    //analyse_labor($db2name, $db1name);







    function analyse_labor($db1name, $db2name) {

        global $conn1, $conn2;
        global $firstValues, $secondValues;


        $tot_count = 0;
        $sniper_count = 0;
        $missing_per_year = ['2020' => 0, '2021' => 0, '2022' => 0, '2023' => 0, '2024' => 0, '2025' => 0];

        for ($i = 0; $i < count($firstValues); $i++) {

            if ($db1name == 'fire5_vito_new') {
                $db1_query = "SELECT pat_sw_id, lab_label, lab_value, lab_dtime FROM $db1name.a_labor WHERE pat_sw_id = :pat_sw_id";
                $db2_query = "SELECT pat_sw_id, lab_label, lab_value, measure_dtime FROM $db2name.a_labor WHERE pat_sw_id = :pat_sw_id";

                $db1_time_name = "lab_dtime";
                $db2_time_name = "measure_dtime";

                $pat_sw_id_1 = $firstValues[$i];
                $pat_sw_id_2 = $secondValues[$i];
            } else {
                $db1_query = "SELECT pat_sw_id, lab_label, lab_value, measure_dtime FROM $db1name.a_labor WHERE pat_sw_id = :pat_sw_id";
                $db2_query = "SELECT pat_sw_id, lab_label, lab_value, lab_dtime FROM $db2name.a_labor WHERE pat_sw_id = :pat_sw_id";

                $db1_time_name = "measure_dtime";
                $db2_time_name = "lab_dtime";

                $pat_sw_id_1 = $secondValues[$i];
                $pat_sw_id_2 = $firstValues[$i];
            }
            

            $getDb1Lab = $conn1->prepare($db1_query);
            $getDb1Lab->execute(['pat_sw_id' => $pat_sw_id_1]);

            $lab_values_1 = $getDb1Lab->fetchAll(PDO::FETCH_ASSOC);

            $getDb2Lab = $conn2->prepare($db2_query);
            $getDb2Lab->execute(['pat_sw_id' => $pat_sw_id_2]);

            $lab_values_2 = $getDb2Lab->fetchAll(PDO::FETCH_ASSOC);

            echo $pat_sw_id_1 . "  ----  " . $pat_sw_id_2 . "\n";

            $count_missing = 0;
            foreach ($lab_values_1 as $value_1) {
                $found_match = false;
                foreach ($lab_values_2 as $value_2) {
                    $cmp_date = new DateTime("2025-01-31 21:00:20");
                    $db_date = new DateTime($value_2[$db2_time_name]);

                    if ($cmp_date > $db_date) {
                        $lab_label_match = $value_1['lab_label'] == $value_2['lab_label'];
                        $lab_value_match = $value_1['lab_value'] == $value_2['lab_value'];
                        $lab_dtime_match = $value_1[$db1_time_name] == $value_2[$db2_time_name];

                        if ($lab_label_match && $lab_value_match && $lab_dtime_match) {
                            $found_match = true;
                            break;
                        }
                    }
                }

                $threshold_dt = new DateTime('2025-01-31 21:00:20');
                $db1_dt = new DateTime($value_1[$db1_time_name]);
                if ($found_match == false && $db1_dt < $threshold_dt) {
                    echo "NO MATCH FOR: " . $value_1['lab_label'] . "  " . $value_1['lab_value'] . "   " . $value_1[$db1_time_name] . "\n";
                    $count_missing++;
                    $tot_count++;
                    $year = $db1_dt->format('Y');
                    $missing_per_year[$year]++;

                    if (str_contains($value_1['lab_label'], "Laborbefund")) {
                        $sniper_count++;
                    }
                }
            }
            if ($count_missing > 0){
                echo "COUNT " . $count_missing . "\n";
            }
            echo "\n\n";
        }
        echo "TOTAL COUNT: " . $tot_count . "\n";

        foreach ($missing_per_year as $year => $missing) {
            echo "MISSING YEAR " . $year . ": " . $missing . "\n";
        }

        echo "MISSING C-reaktives Protein: " . $sniper_count . "\n";
    }
    
    
?>