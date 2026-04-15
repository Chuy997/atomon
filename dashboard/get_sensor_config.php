<?php
// get_sensor_config.php
header('Content-Type: application/json');
date_default_timezone_set('America/Mexico_City');
$servername = "localhost";
$username   = "jmuro";
$password   = "Monday.03";
$dbname     = "atomon";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Conexión fallida"]));
}

$query = "
    SELECT c.sensor_id, c.alerts_muted,
           (SELECT MAX(timestamp) FROM sensores s WHERE s.sensor_id = c.sensor_id) as last_seen
    FROM sensor_config c
";
$result = $conn->query($query);
$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lastSeen = $row['last_seen'];
        $isDown = false;
        if ($lastSeen === null) {
            $isDown = true;
        } else {
            $lastTime = strtotime($lastSeen);
            if ((time() - $lastTime) > (20 * 60)) {
                $isDown = true;
            }
        }
        $row['is_down'] = $isDown;
        $data[] = $row;
    }
}
$conn->close();
echo json_encode($data);
?>
