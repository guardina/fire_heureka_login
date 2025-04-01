<?php
    function readFileToList($filename) {
        // Check if the file exists
        if (!file_exists($filename)) {
            die("Error: File not found.");
        }
    
        // Read file into an array (each line becomes an array element)
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
        return $lines;
    }
    
    // Example usage
    $filename = "files/matches1.txt";
    $elements1 = readFileToList($filename);
    print_r($elements1);

    $filename = "files/matches2.txt";
    $elements2 = readFileToList($filename);
    print_r($elements2);
?>