<?php
    include "db.php";

    $db1name = 'fire5_test_new';
    $db2name = 'fire5_test';

    $conn1 = get_db_connection($db1name);
    $conn2 = get_db_connection($db2name);

    $all_changed_data = [];


    //compare_patients();
    //compare_labors();
    //compare_consultations();
    //compare_vitals();
    compare_pdlists();


    function compare_patients() {
        global $all_changed_data, $conn1, $conn2, $db1name, $db2name;

        $check_patient_query = 
        " SELECT a_patient1.pat_sw_id,
        COUNT(DISTINCT a_patient1.pat_sw_id, a_patient1.practice_sw_id, a_patient1.pms_name, a_patient1.practice_id) AS total_patient,
        SUM(a_patient1.sex = a_patient2.sex
        AND a_patient1.birth_year = a_patient2.birth_year
        AND a_patient1.death_year = a_patient2.death_year
        ) AS matching_patients
        
        FROM $db1name.a_patient a_patient1
        INNER JOIN $db2name.a_patient a_patient2
            ON a_patient1.pat_sw_id = a_patient2.pat_sw_id
            AND a_patient1.pms_name = a_patient2.pms_name
        GROUP BY a_patient1.pat_sw_id
        HAVING matching_patients < total_patient
        ;";
    }

   




    function compare_labors() {
        global $all_changed_data, $conn1, $conn2, $db1name, $db2name;  

        $check_labor_query = 
        " SELECT a_labor1.pat_sw_id,
        COUNT(DISTINCT a_labor1.pat_sw_id, a_labor1.practice_sw_id, a_labor1.pms_name, a_labor1.practice_id, a_labor1.insert_dtime, a_labor1.change_dtime) AS total_labors,
        SUM(a_labor1.lab_dtime = a_labor2.lab_dtime
        AND a_labor1.lab_label = a_labor2.lab_label
        AND a_labor1.lab_value = a_labor2.lab_value
        AND a_labor1.unit_original = a_labor2.unit_original
        AND a_labor1.ref_min = a_labor2.ref_min
        AND a_labor1.ref_max = a_labor2.ref_max
        AND a_labor1.abnormal_flag = a_labor2.abnormal_flag
        AND a_labor1.loinc = a_labor2.loinc
        ) AS matching_labors

        FROM $db1name.a_labor a_labor1
        INNER JOIN $db2name.a_labor a_labor2
            ON a_labor1.pat_sw_id = a_labor2.pat_sw_id
            AND a_labor1.pms_name = a_labor2.pms_name
        GROUP BY a_labor1.pat_sw_id
        HAVING matching_labors < total_labors
        ;";

        $result = $conn1->query($check_labor_query);
        echo "LABOR:\n";
        $count = 0;
        foreach ($result as $row) {
            $all_changed_data[] = $row['pat_sw_id'];
            //echo "User ID: " . $row['pat_sw_id'] . "\n";
            $count++;
        }

        echo "$count\n";
    }



    function compare_consultations() {
        global $all_changed_data, $conn1, $conn2, $db1name, $db2name;

        $check_consultation_query = 
        " SELECT a_consultation1.pat_sw_id,
        COUNT(DISTINCT a_consultation1.pat_sw_id, a_consultation1.practice_sw_id, a_consultation1.pms_name, a_consultation1.practice_id, a_consultation1.insert_dtime, a_consultation1.change_dtime) AS total_consultations,
        SUM(a_consultation1.cons_dtime = a_consultation2.cons_dtime
        AND a_consultation1.cons_type = a_consultation2.cons_type
        AND a_consultation1.insurer_name = a_consultation2.insurer_name
        AND a_consultation1.gln_insurer = a_consultation2.gln_insurer
        AND a_consultation1.gln_insurer = a_consultation2.gln_insurer
        AND a_consultation1.insurance_model = a_consultation2.insurance_model
        AND a_consultation1.gln_executor = a_consultation2.gln_executor
        ) AS matching_consultations

        FROM $db1name.a_consultation a_consultation1
        INNER JOIN $db2name.a_consultation a_consultation2
            ON a_consultation1.pat_sw_id = a_consultation2.pat_sw_id
            AND a_consultation1.pms_name = a_consultation2.pms_name
        GROUP BY a_consultation1.pat_sw_id
        HAVING matching_consultations < total_consultations
        ;";


        $result = $conn1->query($check_consultation_query);
        echo "CONSULTATION:\n";
        $count = 0;
        foreach ($result as $row) {
            $all_changed_data[] = $row['pat_sw_id'];
            //echo "User ID: " . $row['pat_sw_id'] . "\n";
            $count++;
        }

        echo "$count\n";
    }





    function compare_vitals() {
        global $all_changed_data, $conn1, $conn2, $db1name, $db2name;

        $check_vital_query = 
        " SELECT a_vital1.pat_sw_id,
        COUNT(DISTINCT a_vital1.pat_sw_id, a_vital1.practice_sw_id, a_vital1.pms_name, a_vital1.practice_id, a_vital1.insert_dtime, a_vital1.change_dtime) AS total_vitals,
        SUM(a_vital1.vital_dtime = a_vital2.vital_dtime
        AND a_vital1.bp_type = a_vital2.bp_type
        AND a_vital1.bp_syst = a_vital2.bp_syst
        AND a_vital1.bp_diast = a_vital2.bp_diast
        AND a_vital1.pulse = a_vital2.pulse
        AND a_vital1.pulse_quality = a_vital2.pulse_quality
        AND a_vital1.weight = a_vital2.weight
        AND a_vital1.height = a_vital2.height
        AND a_vital1.bmi = a_vital2.bmi
        AND a_vital1.waist_circum = a_vital2.waist_circum
        AND a_vital1.hip_circum = a_vital2.hip_circum
        AND a_vital1.head_circum = a_vital2.head_circum
        AND a_vital1.body_temp = a_vital2.body_temp
        AND a_vital1.oxygen_saturation = a_vital2.oxygen_saturation
        AND a_vital1.body_fat    = a_vital2.body_fat
        AND a_vital1.bone_age = a_vital2.bone_age
        ) AS matching_vitals

        FROM $db1name.a_vital a_vital1
        INNER JOIN $db2name.a_vital a_vital2
            ON a_vital1.pat_sw_id = a_vital2.pat_sw_id
            AND a_vital1.pms_name = a_vital2.pms_name
        GROUP BY a_vital1.pat_sw_id
        HAVING matching_vitals < total_vitals
        ;";


        $result = $conn1->query($check_vital_query);
        echo "VITAL:\n";
        $count = 0;
        foreach ($result as $row) {
            $all_changed_data[] = $row['pat_sw_id'];
            //echo "User ID: " . $row['pat_sw_id'] . "\n";
            $count++;
        }

        echo "$count\n";
    }

    


    function compare_pdlists() {
        global $all_changed_data, $conn1, $conn2, $db1name, $db2name;

        $check_pdlist_query = 
        " SELECT a_pdlist1.pat_sw_id,
        COUNT(DISTINCT a_pdlist1.pat_sw_id, a_pdlist1.practice_sw_id, a_pdlist1.pms_name, a_pdlist1.practice_id, a_pdlist1.insert_dtime, a_pdlist1.change_dtime) AS total_pdlists,
        SUM(a_pdlist1.pd_start_dtime = a_pdlist2.pd_start_dtime
        AND a_pdlist1.pd_stop_dtime = a_pdlist2.pd_stop_dtime
        AND a_pdlist1.classification = a_pdlist2.classification
        AND a_pdlist1.classification_code = a_pdlist2.classification_code
        AND a_pdlist1.classification_desc = a_pdlist2.classification_desc
        AND a_pdlist1.active = a_pdlist2.active
        ) AS matching_pdlists

        FROM $db1name.a_pdlist a_pdlist1
        INNER JOIN $db2name.a_pdlist a_pdlist2
            ON a_pdlist1.pat_sw_id = a_pdlist2.pat_sw_id
            AND a_pdlist1.pms_name = a_pdlist2.pms_name
        GROUP BY a_pdlist1.pat_sw_id
        HAVING matching_pdlists < total_pdlists
        ;";


        $result = $conn1->query($check_pdlist_query);
        echo "PDLIST:\n";
        $count = 0;
        foreach ($result as $row) {
            $all_changed_data[] = $row['pat_sw_id'];
            //echo "User ID: " . $row['pat_sw_id'] . "\n";
            $count++;
        }

        echo "$count\n";
    }




    echo "UNIQUE CHANGED:\n";
    $unique_changed_data = array_unique($all_changed_data);
    $count=0;
    foreach ($unique_changed_data as $data) {
        //echo "User ID: " . $data . "\n";
        $count++;
    }

    echo "$count\n";    
?>