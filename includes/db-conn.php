<?php
    $servername = "sql203.infinityfree.com";
    $username = "if0_37526384";
    $password = "malitha2003";
    $dbname = "if0_37526384_mediq_db2";
    
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
?>