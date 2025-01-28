<?php
function myWeeklyTask() {
    $logFile = '/home/debian/Desktop/json_fire5_parser/fire_heureka_login/php/weekly_log.txt';
    file_put_contents($logFile, "Task run at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}
myWeeklyTask();
?>
