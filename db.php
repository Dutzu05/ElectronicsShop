<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $server = "localhost\\SQLEXPRESS";
        $database = "ElectronicsShop";

        $pdo = new PDO(
            "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=true",
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    return $pdo;
}