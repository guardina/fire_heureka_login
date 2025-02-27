<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function generate_password($length = 12) {
    $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $characters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
    
    $password = $letters[random_int(0, strlen($letters) - 1)];
    for ($i = 1; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $password;
}

function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

$num_users = 0;

$data = [];
foreach (range(1000, 1000+$num_users) as $i) {
    $username = "praxis$i";
    $password = generate_password();
    $hashed_password = hash_password($password);

    $data[] = [
        'username' => $username,
        'password' => $password,
        'hashed_password' => $hashed_password
    ];
}

$pw = 'axe_pw';
$h_pw = hash_password($pw);
echo "pw: $h_pw\n";


echo "Generated SQL Queries:\n\n";
foreach ($data as $entry) {
    $username = $entry['username'];
    $hashed_password = $entry['hashed_password'];

    $sql = "INSERT INTO user_credentials (username, password) VALUES ('$username', '$hashed_password');";
    echo $sql . "\n";
}


$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Heureka Praxis password');

$sheet->setCellValue('A1', 'Username');
$sheet->setCellValue('B1', 'Praxis');
$sheet->setCellValue('C1', 'Password');
$sheet->setCellValue('D1', 'Hashed Password');

$row = 2;
foreach ($data as $entry) {
    $sheet->setCellValue("A$row", $entry['username']);
    $sheet->setCellValue("B$row", '');
    $sheet->setCellValue("C$row", $entry['password']);
    $sheet->setCellValue("D$row", $entry['hashed_password']);
    $row++;
}

$excel_file = 'heureka_praxis_passwords.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($excel_file);

echo "\nExcel file '$excel_file' generated successfully.\n";
?>
