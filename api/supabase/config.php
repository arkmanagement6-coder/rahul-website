<?php
// api/supabase/config.php

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'smmpa5f7_db_userssm'); // Pre-configured cPanel database user
define('DB_PASS', 'Rahul@2709@');          // Pre-configured cPanel database password
define('DB_NAME', 'smmpa5f7_smmpaynow');      // Pre-configured cPanel database name

// Load Environment Variables (.env) from root directory
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
loadEnv(__DIR__ . '/../../.env');

// CORS headers for local/cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type, apikey, X-Client-Info, X-Supabase-Api-Version");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database Connection
function getDbConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
    }
}

// Helper to get JSON request body
function getRequestBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// Helper to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit(0);
}

// Helper to generate UUID v4
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Simple Token-based Auth Helpers (Simulating JWT)
function generateToken($userId) {
    // Return a base64 encoded string representing userId and timestamp
    return base64_encode($userId . '.' . time() . '.' . bin2hex(random_bytes(16)));
}

function verifyToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $decoded = base64_decode($token);
        if ($decoded) {
            $parts = explode('.', $decoded);
            if (count($parts) >= 2) {
                $userId = $parts[0];
                $timestamp = intval($parts[1]);
                // Session valid for 30 days
                if (time() - $timestamp < 30 * 24 * 3600) {
                    return $userId;
                }
            }
        }
    }
    
    sendJsonResponse(['error' => 'Unauthorized: Invalid or missing token'], 401);
}
?>
