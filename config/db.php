<?php
$host = "localhost";
$user = "asm_store";
$pass = "Password";
$db_name = "asm_store";

$conn = mysqli_connect($host, $user, $pass, $db_name);

if (!$conn) {
    die("Database Connection Failed!");
}
?>
