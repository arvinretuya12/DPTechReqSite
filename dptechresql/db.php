<?php
$host = 'localhost';
$db = 'tech_requirements_db';
$user = 'root'; // Update with your DB user
$pass = '';     // Update with your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>