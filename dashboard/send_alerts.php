<?php
// send_alerts.php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuración de la base de datos
date_default_timezone_set('America/Mexico_City');
$servername = "localhost";
$username   = "jmuro";
$password   = "Monday.03"; // Ajusta si es necesario
$dbname     = "atomon";

// Conectar a la base de datos
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

/**
 * Función para enviar correos electrónicos utilizando PHPMailer.
 */
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

        // Destinatarios
        foreach ($recipients as $r) {
            $mail->addAddress($r);
        }

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                    background-color: #f1f5f9;
                    margin: 0;
                    padding: 40px 20px;
                }
                .email-wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                    overflow: hidden;
                    border: 1px solid #e2e8f0;
                }
                .header {
                    background-color: #1e293b;
                    padding: 24px 32px;
                    text-align: center;
                    border-bottom: 4px solid #3b82f6;
                }
                .header h1 {
                    color: #f8fafc;
                    font-size: 20px;
                    font-weight: 600;
                    margin: 0;
                    line-height: 1.4;
                }
                .content {
                    padding: 40px 32px;
                    color: #334155;
                }
                .message-box {
                    background-color: #f8fafc;
                    border-left: 4px solid #eab308;
                    padding: 16px 20px;
                    border-radius: 0 6px 6px 0;
                    margin-bottom: 32px;
                    font-size: 16px;
                    line-height: 1.6;
                }
                .button-container {
                    text-align: center;
                }
                .button {
                    display: inline-block;
                    background-color: #3b82f6;
                    color: #ffffff !important;
                    text-decoration: none;
                    padding: 14px 32px;
                    border-radius: 6px;
                    font-size: 16px;
                    font-weight: 600;
                }
                .button:hover {
                    background-color: #2563eb;
                }
                .footer {
                    background-color: #f8fafc;
                    padding: 24px 32px;
                    text-align: center;
                    border-top: 1px solid #e2e8f0;
                    color: #64748b;
                    font-size: 13px;
                    line-height: 1.5;
                }
                .brand {
                    font-weight: 700;
                    color: #94a3b8;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                    margin-bottom: 8px;
                }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='header'>
                    <h1>{$subject}</h1>
                </div>
                <div class='content'>
                    <div class='message-box'>
                        {$body}
                    </div>
                    <div class='button-container'>
                        <a href='http://192.168.1.36/epa_ato/dashboard_exec.html' class='button'>Acceder al Dashboard</a>
                    </div>
                </div>
                <div class='footer'>
                    <div class='brand'>Xinya Monitoreo Automático</div>
                    Este es un correo generado automáticamente por el sistema de sensores.<br>
                    Por favor, no respondas a esta dirección.
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        echo "Correo enviado a: " . implode(", ", $recipients) . "\n";
    } catch (Exception $e) {
        $errorLogFile = __DIR__ . '/logs/error_log.txt';
        $logMessage    = "[" . date("Y-m-d H:i:s") . "] Error al enviar el correo: " 
                       . $e->getMessage() . "\n";
        file_put_contents($errorLogFile, $logMessage, FILE_APPEND);
        echo "Error al enviar el correo. Consulta el archivo de logs en {$errorLogFile}.\n";
    }
}

/**
 * Función que verifica datos de sensores y envía alertas según promedios.
 */
function checkAndSendAlerts(mysqli $conn): void {
    $day = (int)date('w'); // 0 Sunday, 1 Monday, ... 6 Saturday
    $hour = (int)date('H'); // 00 to 23
    
    // Validar horario laboral: Lunes a Sábado (1-6) de 8 AM a 5 PM (08 - 16)
    if ($day < 1 || $day > 6 || $hour < 8 || $hour >= 17) {
        echo "Fuera de horario laboral. No se envían alertas.\n";
        return;
    }

    // Validar control de 1 hora
    $logFile = __DIR__ . '/logs/last_alert.json';
    $lastAlertTime = 0;
    if (file_exists($logFile)) {
        $json = json_decode(file_get_contents($logFile), true);
        if (isset($json['last_alert_time'])) {
            $lastAlertTime = (int)$json['last_alert_time'];
        }
    }

    if (time() - $lastAlertTime < 3600) {
        echo "Aún no ha pasado 1 hora desde la última alerta.\n";
        return;
    }

    $tempMin      = 10;
    $tempMax      = 30;
    $humidityMin  = 30;
    $humidityMax  = 75;

    $alertRecipients = [
       'jesus.muro@xinya-la.com',
       'valeria.arciniega@xinya-la.com',
       'javier.ramirez@xinya-la.com',
       'pedro.dabdoub@xinya-cn.com',
       'cesar.gutierrez@xinya-la.com',
       'abraham.martinez@xinya-la.com',
       'rene.pineda@xinya-la.com'
    ];
    $warningRecipients = [
       'jesus.muro@xinya-la.com',
       'valeria.arciniega@xinya-la.com',
       'javier.ramirez@xinya-la.com',
       'pedro.dabdoub@xinya-cn.com',
       'cesar.gutierrez@xinya-la.com',
       'abraham.martinez@xinya-la.com',
       'rene.pineda@xinya-la.com'    
    ];

    $query  = "SELECT temperatura, humedad, timestamp
               FROM sensores
               WHERE sensor_id BETWEEN 1 AND 5
                 AND timestamp >= NOW() - INTERVAL 3 HOUR";
    $result = $conn->query($query);

    if (!$result) {
        echo "Error en la consulta: " . $conn->error . "\n";
        return;
    }
    if ($result->num_rows === 0) {
        echo "No hay datos en las últimas 2 horas.\n";
        return;
    }

    $temps = $hums = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['temperatura'] !== null) {
            $temps[] = (float)$row['temperatura'];
        }
        if ($row['humedad'] !== null) {
            $hums[] = (float)$row['humedad'];
        }
    }

    $avgTemp = $temps ? round(array_sum($temps) / count($temps), 2) : '--';
    $avgHum  = $hums ? round(array_sum($hums) / count($hums), 2) : '--';

    echo "Promedio de temperatura: {$avgTemp} °C\n";
    echo "Promedio de humedad: {$avgHum} %\n";

    $emailSent = false;

    if ($avgTemp !== '--') {
        if ($avgTemp < $tempMin || $avgTemp > $tempMax) {
            sendEmail(
                'ALERTA: Temperatura Fuera de Rango',
                "El promedio de temperatura de las últimas 2 horas es de {$avgTemp}°C, fuera del rango ({$tempMin}°C - {$tempMax}°C).",
                $alertRecipients
            );
            $emailSent = true;
        } elseif (($avgTemp >= $tempMin && $avgTemp < 12) || ($avgTemp > 28 && $avgTemp <= $tempMax)) {
            sendEmail(
                'Aviso: Temperatura Próxima a Salirse de Rango',
                "Promedio de temperatura (últimas 2 horas) {$avgTemp}°C, cercano a los límites ({$tempMin}°C - {$tempMax}°C).",
                $warningRecipients
            );
            $emailSent = true;
        }
    }

    if ($avgHum !== '--') {
        if ($avgHum < $humidityMin || $avgHum > $humidityMax) {
            sendEmail(
                'ALERTA: Humedad Fuera de Rango',
                "El promedio de humedad de las últimas 2 horas es de {$avgHum}%, fuera del rango ({$humidityMin}% - {$humidityMax}%).",
                $alertRecipients
            );
            $emailSent = true;
        } elseif (($avgHum >= $humidityMin && $avgHum < 34) || ($avgHum >= 71 && $avgHum <= $humidityMax)) {
            sendEmail(
                'Aviso: Humedad Próxima a Salirse de Rango',
                "Promedio de humedad (últimas 2 horas) {$avgHum}%, cercano a los límites ({$humidityMin}% - {$humidityMax}%).",
                $warningRecipients
            );
            $emailSent = true;
        }
    }

    if ($emailSent) {
        file_put_contents($logFile, json_encode(['last_alert_time' => time()]));
    }
}

// Ejecutar
checkAndSendAlerts($conn);
$conn->close();
