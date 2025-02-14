<?php
    include "db.php";

    $db1name = 'fire5_test_new';
    $db2name = 'fire5_test';

    $conn1 = get_db_connection($db1name);
    $conn2 = get_db_connection($db2name);

    $all_changed_data = [];



    $find_new_patients_query = 
    " SELECT pat_sw_id 
    FROM $db1name.a_patient a_patient1
    WHERE NOT EXISTS(
        SELECT 1
        FROM $db2name.a_patient a_patient2
        WHERE a_patient1.pat_sw_id = a_patient2.pat_sw_id
    )";

    $result = $conn1->query($find_new_patients_query);
    echo "NEW PATIENTS FOUND:\n";
    $count = 0;
    foreach ($result as $row) {
        $count++;
    }

    echo "$count\n";

    //compare_patients();
    //compare_labors();
    //compare_consultations();
    //compare_vitals();
    //compare_pdlists();
    //compare_medications();


    get_few_full_patients();



    function get_few_full_patients() {
        global $conn1;

        $sql = "
            SELECT DISTINCT a_patient.pat_sw_id 
            FROM a_patient
            LEFT JOIN a_labor ON a_patient.pat_sw_id = a_labor.pat_sw_id
            LEFT JOIN a_vital ON a_patient.pat_sw_id = a_vital.pat_sw_id
            LEFT JOIN a_medi ON a_patient.pat_sw_id = a_medi.pat_sw_id
            WHERE a_labor.pat_sw_id IS NOT NULL 
            OR a_vital.pat_sw_id IS NOT NULL 
            OR a_medi.pat_sw_id IS NOT NULL
            LIMIT 1000;
        ";

        $stmt = $conn1->prepare($sql);
        $stmt->execute();
        $patientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Step 2: Build the IN clause for the WHERE statement
        $patientIdsString = implode(", ", array_map(function($id) {
            return "'$id'";  // Add single quotes around each patient_id
        }, $patientIds));

        // Step 3: Fetch the data from a_patient and other tables (a_labor, a_medi, etc.)
        $tables = ['a_patient', 'a_labor', 'a_medi', 'a_vital'];

        foreach ($tables as $table) {
            // Step 3a: Prepare the SQL to get data for patients that have entries in any of the tables
            $sql = "SELECT * FROM $table WHERE pat_sw_id IN ($patientIdsString)";
            $stmt = $conn1->prepare($sql);
            $stmt->execute();
        
            // Step 3b: Write the data to an export file
            $exportFile = fopen("patients_data_dump.sql", "a");
        
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Prepare the insert query for each row
                $columns = [];
                $values = [];
        
                foreach ($row as $column => $value) {
                    // If the value is not NULL, add it to the query
                    if ($value !== null) {
                        $columns[] = $column;
                        $values[] = is_string($value) ? "'$value'" : $value;
                    }
                }
        
                // Only generate the INSERT query if we have columns to insert
                if (count($columns) > 0) {
                    $columnsList = implode(", ", $columns);
                    $valuesList = implode(", ", $values);
                    $insertQuery = "INSERT INTO $table ($columnsList) VALUES ($valuesList);\n";
                    fwrite($exportFile, $insertQuery);
                }
            }
        
            fclose($exportFile);
        }
    }


    function compare_patients() {
        global $all_changed_data, $conn1, $conn2, $db1name, $db2name;

        /*$check_patient_query = 
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
        ;";*/

        $check_patient_query =
        " SELECT *
        FROM $db1name.a_patient a_patient1
        WHERE EXISTS (
            SELECT 1 
            FROM $db2name.a_patient a_patient2
            WHERE a_patient1.sex = a_patient2.sex
            AND a_patient1.birth_year = a_patient2.birth_year
            AND COALESCE(a_patient1.death_year, 'NULL') = COALESCE(a_patient2.death_year, 'NULL')
            AND a_patient1.pat_sw_id = a_patient2.pat_sw_id
        )
        ;";


        $result = $conn1->query($check_patient_query);
        echo "PATIENTS FOUND:\n";
        $count = 0;
        foreach ($result as $row) {
            //$all_changed_data[] = $row['pat_sw_id'];
            //echo "User ID: " . $row['pat_sw_id'] . "\n";
            $count++;
        }

        echo "$count\n";
    }

   




    function compare_labors() {
        global $all_changed_data, $conn1, $conn2, $db1name, $db2name;  

        $check_labor_query = 
        " SELECT a_labor1.pat_sw_id,
        COUNT(DISTINCT a_labor1.pat_sw_id, a_labor1.practice_sw_id, a_labor1.pms_name, a_labor1.practice_id, a_labor1.insert_dtime, a_labor1.change_dtime) AS total_labors,
        SUM(COALESCE(a_labor1.lab_dtime, 'NULL') = COALESCE(a_labor2.lab_dtime, 'NULL')
        AND COALESCE(a_labor1.lab_label, 'NULL') = COALESCE(a_labor2.lab_label, 'NULL')
        AND COALESCE(a_labor1.lab_value, 'NULL') = COALESCE(a_labor2.lab_value, 'NULL')
        AND COALESCE(a_labor1.unit_original, 'NULL') = COALESCE(a_labor2.unit_original, 'NULL')
        AND COALESCE(a_labor1.ref_min, 'NULL') = COALESCE(a_labor2.ref_min, 'NULL')
        AND COALESCE(a_labor1.ref_max, 'NULL') = COALESCE(a_labor2.ref_max, 'NULL')
        AND COALESCE(a_labor1.abnormal_flag, 'NULL') = COALESCE(a_labor2.abnormal_flag, 'NULL')
        AND COALESCE(a_labor1.loinc, 'NULL') = COALESCE(a_labor2.loinc, 'NULL')
        ) AS matching_labors

        FROM $db1name.a_labor a_labor1
        INNER JOIN $db2name.a_labor a_labor2
            ON a_labor1.pat_sw_id = a_labor2.pat_sw_id
            AND a_labor1.pms_name = a_labor2.pms_name
        GROUP BY a_labor1.pat_sw_id
        HAVING matching_labors < total_labors
        ;";



        $check_labor_query = 
        " SELECT *
        FROM $db1name.a_labor a_labor1
        WHERE EXISTS (
            SELECT 1
            FROM $db2name.a_labor a_labor2
            WHERE COALESCE(a_labor1.lab_dtime, 'NULL') = COALESCE(a_labor2.lab_dtime, 'NULL')
            AND COALESCE(a_labor1.lab_label, 'NULL') = COALESCE(a_labor2.lab_label, 'NULL')
            AND COALESCE(a_labor1.lab_value, 'NULL') = COALESCE(a_labor2.lab_value, 'NULL')
            AND COALESCE(a_labor1.unit_original, 'NULL') = COALESCE(a_labor2.unit_original, 'NULL')
            AND COALESCE(a_labor1.ref_min, 'NULL') = COALESCE(a_labor2.ref_min, 'NULL')
            AND COALESCE(a_labor1.ref_max, 'NULL') = COALESCE(a_labor2.ref_max, 'NULL')
            AND COALESCE(a_labor1.abnormal_flag, 'NULL') = COALESCE(a_labor2.abnormal_flag, 'NULL')
            AND COALESCE(a_labor1.loinc, 'NULL') = COALESCE(a_labor2.loinc, 'NULL')
        )
        ";

        $result = $conn1->query($check_labor_query);
        echo "LABORS FOUND:\n";
        $count = 0;
        foreach ($result as $row) {
            //$all_changed_data[] = $row['pat_sw_id'];
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




    function compare_medications() {
        global $all_changed_data, $conn1, $conn2, $db1name, $db2name;

        /*$check_medi_query = 
        " SELECT a_medi1.pat_sw_id,
        COUNT(DISTINCT a_medi1.pat_sw_id, a_medi1.practice_sw_id, a_medi1.pms_name, a_medi1.practice_id, a_medi1.insert_dtime, a_medi1.change_dtime) AS total_medi,
        SUM(
            COALESCE(a_medi1.start_dtime, 'NULL') = COALESCE(a_medi2.start_dtime, 'NULL')
            AND COALESCE(a_medi1.stop_dtime, 'NULL') = COALESCE(a_medi2.stop_dtime, 'NULL')
            AND COALESCE(a_medi1.stop_reason, 'NULL') = COALESCE(a_medi2.stop_reason, 'NULL')
            AND COALESCE(a_medi1.active, 'NULL') = COALESCE(a_medi2.active, 'NULL')
            AND COALESCE(a_medi1.medi_label, 'NULL') = COALESCE(a_medi2.medi_label, 'NULL')
            AND COALESCE(a_medi1.dose, 'NULL') = COALESCE(a_medi2.dose, 'NULL')
            AND COALESCE(a_medi1.dose_unit, 'NULL') = COALESCE(a_medi2.dose_unit, 'NULL')
            AND COALESCE(a_medi1.administration_form, 'NULL') = COALESCE(a_medi2.administration_form, 'NULL')
            AND COALESCE(a_medi1.package_quantity, 'NULL') = COALESCE(a_medi2.package_quantity, 'NULL')
            AND COALESCE(a_medi1.dose_mo, 'NULL') = COALESCE(a_medi2.dose_mo, 'NULL')
            AND COALESCE(a_medi1.dose_mi, 'NULL') = COALESCE(a_medi2.dose_mi, 'NULL')
            AND COALESCE(a_medi1.dose_ab, 'NULL') = COALESCE(a_medi2.dose_ab, 'NULL')
            AND COALESCE(a_medi1.dose_na, 'NULL') = COALESCE(a_medi2.dose_na, 'NULL')
            AND COALESCE(a_medi1.dose_frequency, 'NULL') = COALESCE(a_medi2.dose_frequency, 'NULL')
            AND COALESCE(a_medi1.application, 'NULL') = COALESCE(a_medi2.application, 'NULL')
            AND COALESCE(a_medi1.acute, 'NULL') = COALESCE(a_medi2.acute, 'NULL')
            AND COALESCE(a_medi1.repetitive, 'NULL') = COALESCE(a_medi2.repetitive, 'NULL')
            AND COALESCE(a_medi1.reserve, 'NULL') = COALESCE(a_medi2.reserve, 'NULL')
            AND COALESCE(a_medi1.packages, 'NULL') = COALESCE(a_medi2.packages, 'NULL')
            AND COALESCE(a_medi1.dispensation, 'NULL') = COALESCE(a_medi2.dispensation, 'NULL')
            AND COALESCE(a_medi1.indication, 'NULL') = COALESCE(a_medi2.indication, 'NULL')
            AND COALESCE(a_medi1.intolerance, 'NULL') = COALESCE(a_medi2.intolerance, 'NULL')
            AND COALESCE(a_medi1.gtin, 'NULL') = COALESCE(a_medi2.gtin, 'NULL')
            AND COALESCE(a_medi1.pharmacode, 'NULL') = COALESCE(a_medi2.pharmacode, 'NULL')
            AND COALESCE(a_medi1.product_id, 'NULL') = COALESCE(a_medi2.product_id, 'NULL')
            AND COALESCE(a_medi1.atc, 'NULL') = COALESCE(a_medi2.atc, 'NULL')
        ) AS matching_medi

        FROM $db1name.a_medi a_medi1
        INNER JOIN $db2name.a_medi a_medi2
            ON a_medi1.pat_sw_id = a_medi2.pat_sw_id
            AND a_medi1.pms_name = a_medi2.pms_name
        GROUP BY a_medi1.pat_sw_id
        HAVING matching_medi < total_medi
        ;";*/



        $check_medi_query =
        " SELECT *
        FROM $db1name.a_medi a_medi1
        WHERE EXISTS (
            SELECT 1 
            FROM $db2name.a_medi a_medi2
            WHERE COALESCE(a_medi1.start_dtime, 'NULL') = COALESCE(a_medi2.start_dtime, 'NULL')
            AND COALESCE(a_medi1.stop_dtime, 'NULL') = COALESCE(a_medi2.stop_dtime, 'NULL')
            AND COALESCE(a_medi1.stop_reason, 'NULL') = COALESCE(a_medi2.stop_reason, 'NULL')
            AND COALESCE(a_medi1.active, 'NULL') = COALESCE(a_medi2.active, 'NULL')
            AND COALESCE(a_medi1.medi_label, 'NULL') = COALESCE(a_medi2.medi_label, 'NULL')
            AND COALESCE(a_medi1.dose, 'NULL') = COALESCE(a_medi2.dose, 'NULL')
            AND COALESCE(a_medi1.dose_unit, 'NULL') = COALESCE(a_medi2.dose_unit, 'NULL')
            AND COALESCE(a_medi1.administration_form, 'NULL') = COALESCE(a_medi2.administration_form, 'NULL')
            AND COALESCE(a_medi1.package_quantity, 'NULL') = COALESCE(a_medi2.package_quantity, 'NULL')
            AND COALESCE(a_medi1.dose_mo, 'NULL') = COALESCE(a_medi2.dose_mo, 'NULL')
            AND COALESCE(a_medi1.dose_mi, 'NULL') = COALESCE(a_medi2.dose_mi, 'NULL')
            AND COALESCE(a_medi1.dose_ab, 'NULL') = COALESCE(a_medi2.dose_ab, 'NULL')
            AND COALESCE(a_medi1.dose_na, 'NULL') = COALESCE(a_medi2.dose_na, 'NULL')
            AND COALESCE(a_medi1.dose_frequency, 'NULL') = COALESCE(a_medi2.dose_frequency, 'NULL')
            AND COALESCE(a_medi1.application, 'NULL') = COALESCE(a_medi2.application, 'NULL')
            AND COALESCE(a_medi1.acute, 'NULL') = COALESCE(a_medi2.acute, 'NULL')
            AND COALESCE(a_medi1.repetitive, 'NULL') = COALESCE(a_medi2.repetitive, 'NULL')
            AND COALESCE(a_medi1.reserve, 'NULL') = COALESCE(a_medi2.reserve, 'NULL')
            AND COALESCE(a_medi1.packages, 'NULL') = COALESCE(a_medi2.packages, 'NULL')
            AND COALESCE(a_medi1.dispensation, 'NULL') = COALESCE(a_medi2.dispensation, 'NULL')
            AND COALESCE(a_medi1.indication, 'NULL') = COALESCE(a_medi2.indication, 'NULL')
            AND COALESCE(a_medi1.intolerance, 'NULL') = COALESCE(a_medi2.intolerance, 'NULL')
            AND COALESCE(a_medi1.gtin, 'NULL') = COALESCE(a_medi2.gtin, 'NULL')
            AND COALESCE(a_medi1.pharmacode, 'NULL') = COALESCE(a_medi2.pharmacode, 'NULL')
            AND COALESCE(a_medi1.product_id, 'NULL') = COALESCE(a_medi2.product_id, 'NULL')
            AND COALESCE(a_medi1.atc, 'NULL') = COALESCE(a_medi2.atc, 'NULL')
            AND a_medi1.pat_sw_id = a_medi2.pat_sw_id
            AND a_medi1.pms_name = a_medi2.pms_name
        )
        ;";


        $result = $conn1->query($check_medi_query);
        echo "MEDI:\n";
        $count = 0;
        foreach ($result as $row) {
            $all_changed_data[] = $row['pat_sw_id'];
            //echo "User ID: " . $row['pat_sw_id'] . "\n";
            $count++;
        }

        echo "$count\n";
    }




    echo "UNIQUE pat_sw_id\n";
    $unique_changed_data = array_unique($all_changed_data);
    $count=0;
    foreach ($unique_changed_data as $data) {
        //echo "User ID: " . $data . "\n";
        $count++;
    }

    echo "$count\n";    
?>