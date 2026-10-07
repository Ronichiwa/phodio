<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config/database.php";

try {
    $conn = new PhodioDbConnection();

    echo json_encode([
        "ok" => true,
        "app" => "Phodio",
        "database" => "connected"
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "app" => "Phodio",
        "database" => "failed",
        "error" => $e->getMessage()
    ]);
}
