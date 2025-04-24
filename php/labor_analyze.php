<?php
    include "db.php";

    $db1name = 'fire5_small_vitomed';
    $db2name = 'fire5_big_vitomed';

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
    
    // Now you have:
    // $firstValues and $secondValues arrays
    
    for ($i = 0; $i < count($firstValues); $i++) {
        $pat_sw_id_1 = $firstValues[$i];
        $pat_sw_id_2 = $secondValues[$i];

        $db1_query = "SELECT lab_label, lab_value, lab_dtime FROM $db1name.a_labor WHERE pat_sw_id = :pat_sw_id";
        $db2_query = "SELECT lab_label, lab_value, measure_dtime FROM $db2name.a_labor WHERE pat_sw_id = :pat_sw_id";

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
                $lab_label_match = $value_1['lab_label'] == $value_2['lab_label'];
                $lab_value_match = $value_1['lab_value'] == $value_2['lab_value'];
                $lab_dtime_match = $value_1['lab_dtime'] == $value_2['measure_dtime'];
                if ($lab_label_match && $lab_value_match && $lab_dtime_match) {
                    $found_match = true;
                    break;
                }
            }

            if ($found_match == false) {
                echo "NO MATCH FOR: " . $value_1['lab_label'] . "  " . $value_1['lab_value'] . "   " . $value_1['lab_dtime'] . "\n";
                $count_missing++;
            }
        }
        if ($count_missing > 0){
            echo "COUNT " . $count_missing . "\n";
        }
        echo "\n\n";
    }
?>