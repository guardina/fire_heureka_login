<?php
    include "db.php";

    $db1name = 'fire5_test_new';
    $db2name = 'fire5_test';

    $conn1 = get_db_connection($db1name);
    $conn2 = get_db_connection($db2name);

    $query = "SELECT pat_sw_id FROM a_patient";
    $get_patient_ids = $conn1->prepare($query);
    $get_patient_ids->execute();

    $patient_ids = $get_patient_ids->fetch(PDO::FETCH_ASSOC);
    var_dump($patient_ids);


    foreach ($patient_ids as $patient_id) {
        $check_patient_table_query_1 = $conn1->prepare("SELECT * FROM a_patient WHERE pat_sw_id = ?");
        $check_patient_table_query_2 = $conn2->prepare("SELECT * FROM a_patient WHERE pat_sw_id = ?");

        if ($check_patient_table_query_1 && $check_patient_table_query_2) {
            $check_patient_table_query_1->execute([$patient_id]);
            $check_patient_table_query_2->execute([$patient_id]);

            if ($check_patient_table_query_1->rowCount() > 0 && $check_patient_table_query_2->rowCount() > 0) {
                while ($result1 = $check_patient_table_query_1->fetch(PDO::FETCH_ASSOC) && $result2 = $check_patient_table_query_2->fetch(PDO::FETCH_ASSOC)) {
                    foreach ($result1 as $key1 => $val1) {
                        foreach ($result2 as $key2 => $val2) {
                            if ($key1 == $key2) {
                                if ($val1 == $val2) {
                                    continue;
                                } else {
                                    echo "Difference in $key1 --- Value 1 : $val1 | Value 2 : $val2\n";
                                }
                            }
                        }
                    }
                }
            }
        }

 
        
        /*if ($check_labor_table_query = $conn1->prepare("SELECT * FROM a_labor WHERE pat_sw_id = ?")) {
            $check_labor_table_query->execute([$patient_id]);

            while ($result = $check_labor_table_query->fetch(PDO::FETCH_ASSOC)) {
                print_r($result);
            }
        }*/

        //echo "\n\n\n\n";
    }

    
?>