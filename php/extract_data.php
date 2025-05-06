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
        $sheetName = "a_labor";
        $startingRow = 3237;

        $table = "a_labor"; // Change to the desired table
        $pat_sw_id = "0Ut9KyDRnTgdNCsVlXAOrw=="; // Replace with the actual patient ID
    
        $patientData = getPatientData($conn2, $table, $pat_sw_id);
    
        $filePath = 'files/matches.xlsx';  // Specify the path to the existing Excel file
    
        try {
            // Load the existing Excel file
            $spreadsheet = IOFactory::load($filePath);
    
            // Check if the sheet exists, otherwise create a new one
            $sheet = $spreadsheet->getSheetByName($table);
    
            if (!$sheet) {
                // If the sheet doesn't exist, create a new sheet with the specified name
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($sheetName);
            }
    
            // If there is patient data, proceed to write it to the Excel file
            if (!empty($patientData)) {
                // Get column names from the first data entry
                $columns = array_keys($patientData[0]);  // Get the column names from the first data entry
    
                // Write column headers only if they don't exist in the sheet
                $maxRow = $sheet->getHighestRow();  // Get the highest row (to check where to start)
    
                // If the sheet is empty or headers are missing, add the headers
                $colIndex = 1; // Start with column A (1)
                foreach ($columns as $col) {
                    $sheet->setCellValue([$colIndex, $startingRow], $col);
                    //$sheet->setCellValueByColumnAndRow($colIndex, $startingRow, $col);
                    $colIndex++;
                }
                $startingRow++;  // Move to the next row after header
    
                // Write data rows starting from the specified row
                $rowIndex = $startingRow;  // Start from the row after the header
                foreach ($patientData as $row) {
                    $colIndex = 1; // Reset column index for each row
                    foreach ($row as $value) {
                        $sheet->setCellValue([$colIndex, $rowIndex], $value);
                        //$sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $value); 
                        $colIndex++;
                    }
                    $rowIndex++;
                }
    
                // Save the modified file back
                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);
    
                echo "Excel file has been updated and saved as $filePath";
            } else {
                echo "No data found for the provided patient ID.";
            }
        } catch (Exception $e) {
            echo "Error loading Excel file: " . $e->getMessage();
        }
    }
    
















    function getPatientData($conn, $table, $pat_sw_id) {
        try {
            $query = "SELECT DISTINCT * FROM $table WHERE pat_sw_id = :pat_sw_id ORDER BY lab_dtime";
            echo $query . "\n";
            echo "$pat_sw_id\n";
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