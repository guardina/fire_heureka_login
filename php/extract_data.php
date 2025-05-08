<?php
    include "db.php";

    require __DIR__ . '/vendor/autoload.php';
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\IOFactory;

    // WORK
    //$db1name = 'fire5_test';   // Fabio
    //$db2name = 'fire5_vito_new';     // Heureka


    $db1name = 'fire5_big_vitomed';   // Fabio
    $db2name = 'fire5_small_vitomed';     // Heureka

    $conn1 = get_db_connection($db1name);
    $conn2 = get_db_connection($db2name);

    writeOnExcel();








    function writeOnExcel() {
        global $conn1, $conn2;

        $startingRow = 51;

        $table = "a_medi"; // Change to the desired table
        $pat_sw_id = "QiB77LVBaTIzzwKw4sRWzg=="; // Replace with the actual patient ID
        $sheet_name = "MediExamplesHeureka";
    
        $patientData = getPatientData($conn2, $table, $pat_sw_id);
    
        $filePath = 'files/matches.xlsx';
    
        try {
            $spreadsheet = IOFactory::load($filePath);
    
            $sheet = $spreadsheet->getSheetByName($sheet_name);
    
            if (!$sheet) {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($sheet_name);
            }

            if (!empty($patientData)) {
                $columns = array_keys($patientData[0]);
    
                $maxRow = $sheet->getHighestRow();
    
                $colIndex = 13;
                foreach ($columns as $col) {
                    $sheet->setCellValue([$colIndex, $startingRow], $col);
                    //$sheet->setCellValueByColumnAndRow($colIndex, $startingRow, $col);
                    $colIndex++;
                }
                $startingRow++;
    
                $rowIndex = $startingRow;
                foreach ($patientData as $row) {
                    $colIndex = 13;
                    foreach ($row as $value) {
                        $sheet->setCellValue([$colIndex, $rowIndex], $value); 
                        $colIndex++;
                    }
                    $rowIndex++;
                }
    
                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);
    
                echo "Excel file has been updated and saved as $filePath\n";
            } else {
                echo "No data found for the provided patient ID.";
            }
        } catch (Exception $e) {
            echo "Error loading Excel file: " . $e->getMessage();
        }
    }
    




    function getPatientData($conn, $table, $pat_sw_id) {
        $tableTimeNames = [
            "a_medi" => ["db1_columns" => ["pat_sw_id", "start_dtime", "gtin", "medi_label", "active", "dose_mo", "dose_mi", "dose_ab", "dose_na", "gln", "pharmacode"], "db2_columns" => ["pat_sw_id", "start_dtime", "gtin", "medi_label", "active", "dose_mo", "dose_mi", "dose_ab", "dose_na", "gln", "pharmacode"]],
            "a_vital" => ["db1_columns" => ["vital_dtime", "bmi", "bp_diast", "bp_syst", "pulse", "height", "weight", "body_temp"], "db2_columns" => ["vital_dtime", "bmi", "bp_diast", "bp_syst", "pulse", "height", "weight", "body_temp"]],
            "a_labor" => ["db1_columns" => ["pat_sw_id", "measure_dtime", "lab_label", "lab_value", "unit_original", "unit_ucum", "ref_min", "ref_max", "gln", "external"], "db2_columns" => ["pat_sw_id", "lab_dtime", "lab_label", "lab_value", "unit_original", "unit_ucum", "ref_min", "ref_max", "gln", "external"]],
        ];

        try {
            $query = "SELECT DISTINCT " . implode(", ", $tableTimeNames[$table]["db2_columns"]) . " FROM $table WHERE pat_sw_id = :pat_sw_id ORDER BY start_dtime";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':pat_sw_id', $pat_sw_id);
            $stmt->execute();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $result; // Returns an array of rows
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return [];
        }
    }
?>