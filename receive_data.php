<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}

$servername = "localhost";
$username   = "jmuro";
$password   = "Monday.03";
$dbname     = "atomon";

$mysqli = @new mysqli($servername, $username, $password, $dbname);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Error de conexión: ".$mysqli->connect_error]);
    exit;
}

// --- Lee payload de forma flexible ---
$raw = file_get_contents("php://input");
$payload = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($ct, 'application/json') !== false && strlen($raw)) {
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = [];
}

// Mezcla con POST/GET por compatibilidad
$all = array_change_key_case(array_merge($_GET, $_POST, $payload), CASE_LOWER);

// Acepta nombres nuevos y legacy
$sensor_id   = $all['sensor_id'] ?? null;
$temperature = $all['temperature'] ?? ($all['temp'] ?? null);
$humidity    = $all['humidity']    ?? ($all['hum']  ?? null);

// Validaciones básicas
if ($sensor_id === null || $temperature === null || $humidity === null) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Datos inválidos o incompletos"]);
    exit;
}

$sensor_id   = (int)$sensor_id;
$temperature = floatval($temperature);
$humidity    = floatval($humidity);

// Inserta
$stmt = $mysqli->prepare("INSERT INTO sensores (sensor_id, temperatura, humedad, timestamp) VALUES (?, ?, ?, NOW())");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Prepare failed: ".$mysqli->error]);
    exit;
}
$stmt->bind_param("idd", $sensor_id, $temperature, $humidity);

if ($stmt->execute()) {
    echo json_encode(["status"=>"success","message"=>"Datos registrados correctamente"]);
} else {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Error al registrar los datos: ".$stmt->error]);
}
$stmt->close();
$mysqli->close();
