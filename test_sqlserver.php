<?php

try {
    $server = "localhost\\SQLEXPRESS";
    $database = "ElectronicsShop";

    $pdo = new PDO(
        "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=true",
        null,
        null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    echo "Connected successfully to SQL Server!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}