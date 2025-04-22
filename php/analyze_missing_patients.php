<?php

    include "db.php";

    require __DIR__ . '/vendor/autoload.php';
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\IOFactory;




    
    $filename = "files/notmatches1.txt";
    $elements1 = readFileToList($filename);
    //print_r($elements1);

    $filename = "files/notmatches2.txt";
    $elements2 = readFileToList($filename);
    //print_r($elements2);




    // WORK
    //$db1name = 'fire5_test';   // Fabio
    //$db2name = 'fire5_vito_new';     // Heureka

    // HOME
    $db1name = 'fire5_big_vitomed';   // Fabio
    $db2name = 'fire5_small_vitomed';     // Heureka

    $conn1 = get_db_connection($db1name);
    $conn2 = get_db_connection($db2name);

    $conn1->query("SET SESSION group_concat_max_len = 1000000;");



    $query =
    " SELECT 
            birth_year, 
            LOWER(sex) AS sex,
            SUM(CASE WHEN source = '$db2name' THEN patient_count ELSE 0 END) AS count_small_vitomed,
            SUM(CASE WHEN source = '$db1name' THEN patient_count ELSE 0 END) AS count_big_vitomed
        FROM (
            SELECT '$db2name' AS source, 
                birth_year, 
                LOWER(sex) AS sex, 
                COUNT(*) AS patient_count
            FROM $db2name.a_patient
            WHERE birth_year IS NOT NULL AND sex IS NOT NULL AND pms_name = 'heureka_vitomed'
            GROUP BY birth_year, LOWER(sex)

            UNION ALL

            SELECT '$db1name' AS source, 
                birth_year, 
                LOWER(sex) AS sex, 
                COUNT(DISTINCT pat_sw_id) AS patient_count
            FROM $db1name.a_patient
            WHERE birth_year IS NOT NULL AND sex IS NOT NULL AND pms_name = 'vitomed'
            GROUP BY birth_year, LOWER(sex)
        ) AS combined
        GROUP BY birth_year, sex
        ORDER BY birth_year ASC, sex ASC;
    ";


    $stmt = $conn1->prepare($query);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $birth_year = $row['birth_year'];
        $sex = $row['sex'];
    
        if (!isset($new_results[$birth_year])) {
            $new_results[$birth_year] = [];
        }
    
        $new_results[$birth_year][$sex] = [
            'count_small_vitomed' => $row['count_small_vitomed'],
            'count_big_vitomed' => $row['count_big_vitomed']
        ];
    }



    $inputFile = 'files/matches.xlsx'; 
    $spreadsheet = IOFactory::load($inputFile);

    $newSheet = $spreadsheet->createSheet();
    $newSheet->setTitle('Unmatched patients');



    $newSheet->setCellValue('A1', 'Fire5');
    $newSheet->setCellValue('A2', 'Year');
    $newSheet->setCellValue('B2', 'Count Male');
    $newSheet->setCellValue('C2', 'Count Female');



    $query = "SELECT DISTINCT pat_sw_id, birth_year, LOWER(sex) AS sex 
          FROM fire5_big_vitomed.a_patient 
          WHERE pat_sw_id IN ('" . implode("','", array_map('addslashes', $elements1)) . "')
          ORDER BY birth_year ASC";

    $stmt = $conn1->prepare($query);
    $stmt->execute();
    $patients_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $big_counts = [];

    foreach ($patients_data as $patient) {
        $birth_year = $patient['birth_year'];
        $sex = $patient['sex'];

        if (!isset($big_counts[$birth_year])) {
            $big_counts[$birth_year] = ['male' => 0, 'female' => 0];
        }

        $big_counts[$birth_year][$sex]++;
    }


    $row = 3;
    foreach ($big_counts as $year => $sex_count) {
        $newSheet->setCellValue("A$row", $year);
        $newSheet->setCellValue("B$row", $sex_count['male']);
        $newSheet->setCellValue("C$row", $sex_count['female']);
        echo "\n";
        $row++;
    }


    $newSheet->setCellValue('E1', 'Heureka');
    $newSheet->setCellValue('E2', 'Year');
    $newSheet->setCellValue('F2', 'Count Male');
    $newSheet->setCellValue('G2', 'Count Female');

    $query = "SELECT DISTINCT pat_sw_id, birth_year, LOWER(sex) AS sex 
          FROM fire5_small_vitomed.a_patient 
          WHERE pat_sw_id IN ('" . implode("','", array_map('addslashes', $elements2)) . "')
          ORDER BY birth_year ASC";
    $stmt = $conn2->prepare($query);
    $stmt->execute();
    $patients_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $small_counts = [];

    foreach ($patients_data as $patient) {
        $birth_year = $patient['birth_year'];
        $sex = $patient['sex'];

        if (!isset($small_counts[$birth_year])) {
            $small_counts[$birth_year] = ['male' => 0, 'female' => 0];
        }

        $small_counts[$birth_year][$sex]++;
    }


    $row = 3;
    foreach ($small_counts as $year => $sex_count) {
        $newSheet->setCellValue("E$row", $year);
        $newSheet->setCellValue("F$row", $sex_count['male']);
        $newSheet->setCellValue("G$row", $sex_count['female']);
        echo "\n";
        $row++;
    }



    $newSheet->setCellValue("I1", "Difference");
    $newSheet->setCellValue("I2", "Year");
    $newSheet->setCellValue("J2", "Count Male");
    $newSheet->setCellValue("K2", "Count Female");

    $all_years = array_unique(array_merge(array_keys($big_counts), array_keys($small_counts)));


    $tot_missing_male = $tot_missing_female = $tot_extra_male = $tot_extra_female = 0;
    $row = 3;
    foreach ($all_years as $year) {
        $big_male = isset($big_counts[$year]['male']) ? $big_counts[$year]['male'] : 0;
        $big_female = isset($big_counts[$year]['female']) ? $big_counts[$year]['female'] : 0;
    
        $small_male = isset($small_counts[$year]['male']) ? $small_counts[$year]['male'] : 0;
        $small_female = isset($small_counts[$year]['female']) ? $small_counts[$year]['female'] : 0;
    
        $male_difference = $small_male - $big_male;
        $female_difference = $small_female - $big_female;
    
        echo "Year: " . $year . "\n";
        $newSheet->setCellValue("I$row", $year);
 
        echo "Male difference: " . abs($male_difference);
        $newSheet->setCellValue("J$row", $male_difference);
        if ($male_difference > 0) {
            $tot_extra_male += abs($male_difference);
            echo " extra\n";
        } else {
            $tot_missing_male += abs($male_difference);
            echo " missing\n";
        }

        echo "Female difference: " . abs($female_difference);
        $newSheet->setCellValue("K$row", $female_difference);
        if ($female_difference > 0) {
            $tot_extra_female += abs($female_difference);
            echo " extra\n";
        } else {
            $tot_missing_female += abs($female_difference);
            echo " missing\n";
        }

        $row++;
    }

    $row += 2;

    $newSheet->setCellValue("I$row", "Tot. extra");
    $newSheet->setCellValue("J$row", $tot_extra_male);
    $newSheet->setCellValue("K$row", $tot_extra_female);

    $row += 1;

    $newSheet->setCellValue("I$row", "Tot. missing");
    $newSheet->setCellValue("J$row", $tot_missing_male);
    $newSheet->setCellValue("K$row", $tot_missing_female);

    $row += 2;

    $newSheet->setCellValue("I$row", "Extra = Heureka has, fire5 doesn't");
    $row++;
    $newSheet->setCellValue("I$row", "Missing = Fire5 has, Heureka doesn't");








    $count_prob_match_hk = 0;
    $count_outliers_hk = 0;


    foreach ($elements2 as $id2) {
        // Query the details for patient id2
        $query2 = "SELECT birth_year, LOWER(sex) as sex FROM a_patient WHERE pat_sw_id = :id2";
        $stmt2 = $conn2->prepare($query2);
        $stmt2->bindParam(':id2', $id2);
        $stmt2->execute();
        $patient2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    
        if ($patient2) {
            $birth_year2 = $patient2['birth_year'];
            $sex2 = $patient2['sex'];
            $count = 0;
            $matchable_count = $small_counts[$birth_year2][$sex2];
            $could_match = false;
    
            echo $birth_year2 . "  " . $sex2 . "\n";
    
            // Loop through elements in the first list (elements1)
            foreach ($elements1 as $id1) {
                // Query the details for patient id1
                $query1 = "SELECT birth_year, LOWER(sex) as sex 
                       FROM a_patient 
                       WHERE pat_sw_id = :id1 
                         AND birth_year = :birth_year2 
                         AND sex = :sex2";
    
                $stmt1 = $conn1->prepare($query1);
                $stmt1->bindParam(':id1', $id1);
                $stmt1->bindParam(':birth_year2', $birth_year2);
                $stmt1->bindParam(':sex2', $sex2);
                $stmt1->execute();
                $patient1 = $stmt1->fetch(PDO::FETCH_ASSOC);
    
                if ($patient1) {
                    $birth_year1 = $patient1['birth_year'];
                    $sex1 = $patient1['sex'];
    
                    // Compare birth year and sex
                    $birth_year_match = ($birth_year1 == $birth_year2);
                    $sex_match = ($sex1 == $sex2);
    
                    // Get the number of entries for related tables a_medi, a_vital, a_labor, a_pdlist for both patients
                    $medi_entries2 = getEntriesCount($id2, 'a_medi', $conn2);
                    $vital_entries2 = getEntriesCount($id2, 'a_vital', $conn2);
                    $labor_entries2 = getEntriesCount($id2, 'a_labor', $conn2);
                    $pdlist_entries2 = getEntriesCount($id2, 'a_pdlist', $conn2);

                    $medi_entries1 = getEntriesCount($id1, 'a_medi', $conn1);
                    $vital_entries1 = getEntriesCount($id1, 'a_vital', $conn1);
                    $labor_entries1 = getEntriesCount($id1, 'a_labor', $conn1);
                    $pdlist_entries1 = getEntriesCount($id1, 'a_pdlist', $conn1);

    
                    // Compare the number of entries in the related tables
                    $medi_match = ($medi_entries1 == $medi_entries2);
                    $vital_match = ($vital_entries1 == $vital_entries2);
                    $labor_match = ($labor_entries1 == $labor_entries2);
                    $pdlist_match = ($pdlist_entries1 == $pdlist_entries2);
    
                    // Check if all conditions match
                    $all_conditions_match = $birth_year_match && $sex_match && $medi_match && $vital_match && $labor_match && $pdlist_match;
    
                    // Only print the results if all conditions match
                    if ($all_conditions_match) {
                        if ($small_counts[$birth_year1][$sex1] > 0) {
                            $could_match = true;
                            $small_counts[$birth_year1][$sex1] -= 1;
                            break;
                        }
                    }
                }
            }
            if ($could_match) {
                $count_prob_match_hk++;
            } else {
                $count_outliers_hk++;
            }
        }
        echo "\n\n";
    }
    

    echo "PROB MATCHES: " . $count_prob_match_hk . "\n";
    echo "OUTLIERS: " . $count_outliers_hk . "\n";


    /*$newSheet->setCellValue("M1", "After Prob match");
    $newSheet->setCellValue("M2", "Year");
    $newSheet->setCellValue("N2", "Count Male");
    $newSheet->setCellValue("O2", "Count Female");


    $row = 3;
    foreach ($small_counts as $year => $sex_count) {
        $newSheet->setCellValue("M$row", $year);
        $newSheet->setCellValue("N$row", $sex_count['male']);
        $newSheet->setCellValue("O$row", $sex_count['female']);
        $row++;
    }*/



    $lastSheetIndex = count($spreadsheet->getAllSheets()) - 2;
    $spreadsheet->removeSheetByIndex($lastSheetIndex);

    $writer = new Xlsx($spreadsheet);
    $writer->save($inputFile); 







    function getEntriesCount($id, $table, $conn) {
        $query = "SELECT COUNT(*) FROM $table WHERE pat_sw_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetchColumn();
    }










    function readFileToList($filename) {
        if (!file_exists($filename)) {
            die("Error: File not found.");
        }
    
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
        return $lines;
    }
?>