<?php

session_start();

header('Content-Type: text/plain; charset=utf-8');

echo "SESSION TEST\n";
echo "============\n\n";

echo "Session ID: " . session_id() . "\n\n";

echo "client_id: ";
var_dump($_SESSION['client_id'] ?? null);

echo "client: ";
var_dump($_SESSION['client'] ?? null);

echo "client_name: ";
var_dump($_SESSION['client_name'] ?? null);

echo "\nAll session data:\n";
var_dump($_SESSION);
