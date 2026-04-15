<?php
// check_sensor_health.php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

date_default_timezone_set('America/Mexico_City');
$servername = "localhost";
$username   = "jmuro";
$password   = "Monday.03";
$dbname     = "atomon";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

function sendEmail(string $subject, string $body, array $recipients): void {
    $mail = new PHPMailer(true);
    try {
        // --- Configuración SMTP ---
        $mail->isSMTP();
        $mail->Host       = 'smtphz.qiye.163.com';     
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alertservice@xinya-la.com';
        $mail->Password   = 'M4ru4t4.2025!';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('alertservice@xinya-la.com', 'Servicio de Alertas');

        foreach ($recipients as $r) {
            $mail->addAddress($r);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: -apple-system, sans-serif; background-color: #f1f5f9; padding: 40px 20px; margin: 0; }
                .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
                .header { background-color: #1e293b; padding: 24px; text-align: center; border-bottom: 4px solid #ef4444; }
                .header h1 { color: #f8fafc; font-size: 20px; font-weight: 600; margin: 0; }
                .content { padding: 40px 32px; color: #334155; }
                .message-box { background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 16px 20px; margin-bottom: 32px; font-size: 16px; border-radius: 0 6px 6px 0; }
                .button { background-color: #ef4444; color: #ffffff !important; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 600; display: inline-block; }
                .button:hover { background-color: #dc2626; }
                .footer { background-color: #f8fafc; padding: 24px 32px; text-align: center; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 13px; }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='header'>
                    <h1>{$subject}</h1>
                </div>
                <div class='content'>
                    <div class='message-box'>{$body}</div>
                    <div style='text-align: center;'>
                        <a href='http://test/atomon/dashboard/' class='button'>Acceder al Dashboard</a>
                    </div>
                </div>
                <div class='footer'>
                    <b>Xinya Monitoreo Automático</b><br>
                    Alerta de Salud del Sistema (Sensores)
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        echo "Correo enviado a: " . implode(", ", $recipients) . "\n";
    } catch (Exception $e) {
        $errorLogFile = __DIR__ . '/logs/error_log.txt';
        $logMessage = "[" . date("Y-m-d H:i:s") . "] Error en check_sensor_health (mail): " . $e->getMessage() . "\n";
        file_put_contents($errorLogFile, $logMessage, FILE_APPEND);
        echo "Error al enviar correo.\n";
    }
}

function checkSystemHealth(mysqli $conn): void {
    $day = (int)date('w'); // 0 = Domingo, 1 = Lunes, ... 5 = Viernes
    $hour = (int)date('H'); // Formato 24h (00 - 23)
    
    // Solo de Lunes a Viernes de 8:00 AM a 5:00 PM (17h). Excluyendo horas < 8, o >= 17, o si es sab/dom
    if ($day < 1 || $day > 5 || $hour < 8 || $hour >= 17) {
        echo "Fuera de horario laboral configurado (L-V, 8am-5pm). No se envían alertas de caída.\n";
        return;
    }

    // Ruta donde se almacenará el JSON con las horas de los últimos envíos por sensor
    $logFile = __DIR__ . '/logs/health_alerts.json';
    $alertsCooldown = [];
    if (file_exists($logFile)) {
        $fileContent = file_get_contents($logFile);
        if ($fileContent) {
            $alertsCooldown = json_decode($fileContent, true) ?: [];
        }
    }

    $recipients = ['jesus.muro@xinya-la.com'];

    // Obtenemos del 1 al 5
    $query = "
        SELECT c.sensor_id, c.alerts_muted,
               (SELECT MAX(timestamp) FROM sensores s WHERE s.sensor_id = c.sensor_id) as last_seen
        FROM sensor_config c
        WHERE c.sensor_id BETWEEN 1 AND 5
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        die("Error en query BD: " . $conn->error);
    }

    $updatedCooldown = false;

    while ($row = $result->fetch_assoc()) {
        $sensorId = (int)$row['sensor_id'];
        $isMuted = (int)$row['alerts_muted'];
        $lastSeen = $row['last_seen']; 

        // Si el interruptor está encendido (muted = 1), evitamos alertar para este sensor
        if ($isMuted === 1) {
            echo "El Sensor $sensorId está silenciado (MUTE). Se omite reporte.\n";
            continue;
        }

        // Revisar inactividad (si pasó 20 min)
        $isDown = false;
        if ($lastSeen === null) {
            $isDown = true;
        } else {
            $lastTime = strtotime($lastSeen);
            if ((time() - $lastTime) > (20 * 60)) {
                $isDown = true;
            }
        }

        if ($isDown) {
            // Verificar control de 1 hora de espera entre repetidos mensajes al mismo sensor
            $lastAlertForSensor = $alertsCooldown['sensor_' . $sensorId] ?? 0;
            
            if (time() - $lastAlertForSensor >= 3600) {
                // Es hora de reportar
                $lastSeenText = $lastSeen ? $lastSeen : "Nunca ha reportado";
                
                sendEmail(
                    "🔴 CRÍTICO: El Sensor $sensorId dejó de funcionar", 
                    "El sistema detectó que el <b>Sensor $sensorId</b> no ha enviado información en los últimos 20 minutos.<br><br><b>Último reporte visto:</b> $lastSeenText.<br><br>Si vas a realizar la supervisión física, recuerda activar el interruptor de este sensor en el Dashboard para silenciar estas alarmas temporales.", 
                    $recipients
                );
                
                $alertsCooldown['sensor_' . $sensorId] = time();
                $updatedCooldown = true;
            } else {
                echo "Sensor $sensorId caído pero aún en periodo de silencio (1h cooldown) frente a Spam.\n";
            }
        } else {
            echo "Sensor $sensorId en línea.\n";
        }
    }

    if ($updatedCooldown) {
        file_put_contents($logFile, json_encode($alertsCooldown));
    }
}

// Ejecutar revisión
checkSystemHealth($conn);
$conn->close();
?>
