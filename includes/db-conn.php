<?php
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "mediq_db2";
    
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
?>