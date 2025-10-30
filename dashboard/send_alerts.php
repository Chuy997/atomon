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
        <html>
        <head>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background-color: #f9f9f9;
                    color: #333;
                    line-height: 1.6;
                    margin: 0;
                    padding: 20px;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    overflow: hidden;
                    border: 1px solid #ddd;
                }
                .header {
                    background-color: #007bff;
                    color: #ffffff;
                    text-align: center;
                    padding: 20px;
                }
                .header h1 {
                    font-size: 20px;
                    margin: 0;
                }
                .content {
                    padding: 20px;
                }
                .content p {
                    font-size: 14px;
                    margin-bottom: 10px;
                }
                .button {
                    display: inline-block;
                    background-color: #007bff;
                    color: #ffffff;
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    font-size: 14px;
                    margin-top: 20px;
                    text-align: center;
                }
                .button:hover {
                    background-color: #0056b3;
                }
                .footer {
                    text-align: center;
                    padding: 10px;
                    background-color: #f8f9fa;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'><h1>{$subject}</h1></div>
                <div class='content'>
                    <p>{$body}</p>
                    <a href='http://test/atomon/dashboard/' class='button'>Ir al Dashboard</a>
                </div>
                <div class='footer'>
                    <p>Este es un mensaje automático generado por el sistema de monitoreo.</p>
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
    $tempMin      = 10;
    $tempMax      = 30;
    $humidityMin  = 30;
    $humidityMax  = 75;

    $alertRecipients = [
       'jesus.muro@xinya-la.com',
       /*  'valeria.arciniega@xinya-la.com',
        'javier.ramirez@xinya-la.com',
        'pedro.dabdoub@xinya-cn.com',
        'cesar.gutierrez@xinya-la.com',
        'abraham.martinez@xinya-la.com',
        'rene.pineda@xinya-la.com',
        'sara.margarita.constantino@huawei.com'*/
    ];
    $warningRecipients = [
        'jesus.muro@xinya-la.com'
    ];

    $query  = "SELECT temperatura, humedad, timestamp
               FROM sensores
               WHERE sensor_id BETWEEN 1 AND 5
                 AND timestamp >= NOW() - INTERVAL 15 MINUTE";
    $result = $conn->query($query);

    if (!$result) {
        echo "Error en la consulta: " . $conn->error . "\n";
        return;
    }
    if ($result->num_rows === 0) {
        echo "No hay datos en los últimos 15 minutos.\n";
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

    if ($avgTemp !== '--') {
        if ($avgTemp < $tempMin || $avgTemp > $tempMax) {
            sendEmail(
                'ALERTA: Temperatura Fuera de Rango',
                "El promedio de temperatura de los últimos 15 minutos es de {$avgTemp}°C, fuera del rango ({$tempMin}°C - {$tempMax}°C).",
                $alertRecipients
            );
        } elseif (($avgTemp >= $tempMin && $avgTemp < 12) || ($avgTemp > 28 && $avgTemp <= $tempMax)) {
            sendEmail(
                'Aviso: Temperatura Próxima a Salirse de Rango',
                "Promedio de temperatura {$avgTemp}°C, cercano a los límites ({$tempMin}°C - {$tempMax}°C).",
                $warningRecipients
            );
        }
    }

    if ($avgHum !== '--') {
        if ($avgHum < $humidityMin || $avgHum > $humidityMax) {
            sendEmail(
                'ALERTA: Humedad Fuera de Rango',
                "El promedio de humedad de los últimos 15 minutos es de {$avgHum}%, fuera del rango ({$humidityMin}% - {$humidityMax}%).",
                $alertRecipients
            );
        } elseif (($avgHum >= $humidityMin && $avgHum < 34.5) || ($avgHum > 70.5 && $avgHum <= $humidityMax)) {
            sendEmail(
                'Aviso: Humedad Próxima a Salirse de Rango',
                "Promedio de humedad {$avgHum}%, cercano a los límites ({$humidityMin}% - {$humidityMax}%).",
                $warningRecipients
            );
        }
    }
}

// Ejecutar
checkAndSendAlerts($conn);
$conn->close();
