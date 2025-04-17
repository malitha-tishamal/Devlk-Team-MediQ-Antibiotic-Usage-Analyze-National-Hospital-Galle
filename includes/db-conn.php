<?php
    $servername = "mysql-malitha.alwaysdata.net";
    $username = "malitha";
    $password = "Ybb@K2ab#2t7#_q";
    $dbname = "malitha_mediq";
    
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
?>