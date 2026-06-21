<?php
$host = "localhost";
$db_name = "clothing_db";
$username = "root"; 
$password = "";     

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(array("success" => false, "message" => "Database connection error: " . $exception->getMessage()));
    exit();
}
?>