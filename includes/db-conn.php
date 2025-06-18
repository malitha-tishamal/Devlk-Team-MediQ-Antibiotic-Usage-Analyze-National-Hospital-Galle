<?php
    $servername = "sql113.infinityfree.com";
    $username = "if0_38329700";
    $password = "3FOTawAx0nH";
    $dbname = "if0_38329700_mediq";
    
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
?>