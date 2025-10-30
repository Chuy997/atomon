<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

date_default_timezone_set('America/Mexico_City'); 

// Configuración de la base de datos
$servername = "localhost";
$username = "jmuro";
$password = "Monday.03"; // 
$dbname = "atomon";

// Conectar a la base de datos
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Error de conexión: " . $conn->connect_error, "status" => "error"]);
    exit();
}

// Consultar las últimas mediciones (3 horas)
$query = "SELECT sensor_id, temperatura, humedad, timestamp 
          FROM sensores 
          WHERE timestamp >= NOW() - INTERVAL 3 HOUR 
          ORDER BY timestamp DESC";

$result = $conn->query($query);

if (!$result) {
    http_response_code(500);
    echo json_encode(["message" => "Error en la consulta: " . $conn->error, "status" => "error"]);
    exit();
}

$data = [];
$promedios = ["temperatura" => 0, "humedad" => 0, "conteo" => 0];

while ($row = $result->fetch_assoc()) {
    $row["temperatura"] = $row["temperatura"] !== null ? (float)$row["temperatura"] : 0;
    $row["humedad"] = $row["humedad"] !== null ? (float)$row["humedad"] : 0;
    
    $data[] = $row;

    // Calcular promedios
    $promedios["temperatura"] += $row["temperatura"];
    $promedios["humedad"] += $row["humedad"];
    $promedios["conteo"]++;
}

// Evitar división por cero
$promedios["temperatura"] = $promedios["conteo"] > 0 ? round($promedios["temperatura"] / $promedios["conteo"], 2) : 0;
$promedios["humedad"] = $promedios["conteo"] > 0 ? round($promedios["humedad"] / $promedios["conteo"], 2) : 0;

$conn->close();

// Respuesta JSON
echo json_encode([
    "status" => "success",
    "data" => $data,
    "promedios" => [
        "temperatura" => $promedios["temperatura"],
        "humedad" => $promedios["humedad"]
    ]
]);
?>
