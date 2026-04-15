<?php
// toggle_sensor_mute.php
header('Content-Type: application/json');
$servername = "localhost";
$username   = "jmuro";
$password   = "Monday.03";
$dbname     = "atomon";

// Se espera petición POST con JSON: {"sensor_id": 1, "alerts_muted": 1}
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!isset($input['sensor_id']) || !isset($input['alerts_muted'])) {
    echo json_encode(["success" => false, "message" => "Faltan datos de envio."]);
    exit;
}

$sensorId = (int)$input['sensor_id'];
$muted = (int)$input['alerts_muted'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Error de conexión BD."]));
}

$stmt = $conn->prepare("UPDATE sensor_config SET alerts_muted = ? WHERE sensor_id = ?");
$stmt->bind_param("ii", $muted, $sensorId);
if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
