<?php
// Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Incluye PHPMailer

// Configuración inicial
$response = ['status' => 'error', 'message' => 'Error desconocido'];

try {
    // Obtener datos de la base de datos
    $data = fetchDataFromDatabase();

    // Filtrar datos de los últimos 30 minutos
    $now = new DateTime();
    $thirtyMinutesAgo = (new DateTime())->modify('-30 minutes');
    $recentData = array_filter($data, function ($entry) use ($thirtyMinutesAgo) {
        return new DateTime($entry['timestamp']) >= $thirtyMinutesAgo;
    });

    // Calcular promedios generales
    $tempValues = array_map(function ($entry) {
        return $entry['temperatura'];
    }, array_filter($recentData, function ($entry) {
        return $entry['temperatura'] !== null;
    }));
    $avgTemp = count($tempValues) > 0 ? array_sum($tempValues) / count($tempValues) : null;

    $humidityValues = array_map(function ($entry) {
        return $entry['humedad'];
    }, array_filter($recentData, function ($entry) {
        return $entry['humedad'] !== null;
    }));
    $avgHumidity = count($humidityValues) > 0 ? array_sum($humidityValues) / count($humidityValues) : null;

    // Definir rangos permitidos
    $tempRange = [10, 30];
    $humidityRange = [30, 75];

    // Verificar condiciones y enviar correos
    checkAndSendAlerts($avgTemp, $avgHumidity, $tempRange, $humidityRange);

    $response = ['status' => 'success', 'message' => 'Verificación completada.'];
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'Error: ' . $e->getMessage()];
}

// Devolver respuesta JSON
header('Content-Type: application/json');
echo json_encode($response);

/**
 * Función para obtener datos de la base de datos
 */
function fetchDataFromDatabase() {
    // Simula la obtención de datos desde la base de datos
    // Reemplaza esto con tu consulta SQL real
    $pdo = new PDO('mysql:host=localhost;dbname=tu_base_de_datos', 'usuario', 'contraseña');
    $stmt = $pdo->query("SELECT sensor_id, temperatura, humedad, timestamp FROM lecturas ORDER BY timestamp DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Función para verificar promedios y enviar correos
 */
function checkAndSendAlerts($avgTemp, $avgHumidity, $tempRange, $humidityRange) {
    // Validar temperatura
    if ($avgTemp !== null) {
        $tempMargin = ($tempRange[1] - $tempRange[0]) * 0.1; // 10% del rango
        if ($avgTemp < $tempRange[0] || $avgTemp > $tempRange[1]) {
            sendEmail(
                'ALERTA: Temperatura fuera de rango',
                "La temperatura promedio es $avgTemp C. Rango permitido: {$tempRange[0]}°C - {$tempRange[1]}°C."
            );
        } elseif ($avgTemp < $tempRange[0] + $tempMargin || $avgTemp > $tempRange[1] - $tempMargin) {
            sendEmail(
                'AVISO: Temperatura cerca del límite',
                "La temperatura promedio es $avgTemp C. Está cerca del rango permitido: {$tempRange[0]}°C - {$tempRange[1]}°C."
            );
        }
    }

    // Validar humedad
    if ($avgHumidity !== null) {
        $humidityMargin = ($humidityRange[1] - $humidityRange[0]) * 0.1; // 10% del rango
        if ($avgHumidity < $humidityRange[0] || $avgHumidity > $humidityRange[1]) {
            sendEmail(
                'ALERTA: Humedad fuera de rango',
                "La humedad promedio es $avgHumidity%. Rango permitido: {$humidityRange[0]}% - {$humidityRange[1]}%."
            );
        } elseif ($avgHumidity < $humidityRange[0] + $humidityMargin || $avgHumidity > $humidityRange[1] - $humidityMargin) {
            sendEmail(
                'AVISO: Humedad cerca del límite',
                "La humedad promedio es $avgHumidity%. Está cerca del rango permitido: {$humidityRange[0]}% - {$humidityRange[1]}%."
            );
        }
    }
}

/**
 * Función para enviar correos usando PHPMailer
 */
function sendEmail($subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.tu-servidor.com'; // Cambia esto por tu servidor SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'alertservice@zhongli-la.com'; // Tu correo electrónico
        $mail->Password = 'Xinya.123'; // Tu contraseña
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // O 'ssl' si es necesario
        $mail->Port = 587; // Puerto SMTP (587 para TLS, 465 para SSL)
        $mail->CharSet = 'UTF-8';

        // Configuración del correo
        $mail->setFrom('alertservice@zhongli-la.com', 'Sistema de Monitoreo'); // Remitente
        $mail->addAddress('jesus.muro@zhongli-la.com'); // Destinatario
        $mail->isHTML(true); // Habilitar HTML

        // Asunto y cuerpo del correo
        $mail->Subject = $subject;
        $mail->Body = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>$subject</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f9f9f9;
                    margin: 0;
                    padding: 0;
                }
                .email-container {
                    max-width: 600px;
                    margin: 20px auto;
                    background-color: #ffffff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                }
                .email-header {
                    background-color: #007bff;
                    color: #ffffff;
                    padding: 20px;
                    text-align: center;
                }
                .email-body {
                    padding: 20px;
                    font-size: 14px;
                    line-height: 1.6;
                }
                .email-footer {
                    background-color: #f4f4f4;
                    text-align: center;
                    padding: 10px;
                    font-size: 12px;
                    color: #666;
                }
                .alert {
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .alert-success {
                    background-color: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                .alert-danger {
                    background-color: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                .alert-warning {
                    background-color: #fff3cd;
                    color: #856404;
                    border: 1px solid #ffeeba;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1>Sistema de Monitoreo</h1>
                </div>
                <div class='email-body'>
                    <div class='" . ($subject === 'ALERTA' ? 'alert-danger' : 'alert-warning') . "'>
                        <strong>$subject</strong>
                        <p>$body</p>
                    </div>
                </div>
                <div class='email-footer'>
                    &copy; 2023 Sistema de Monitoreo. Todos los derechos reservados.
                </div>
            </div>
        </body>
        </html>";

        // Enviar el correo
        $mail->send();
    } catch (Exception $e) {
        throw new Exception('Error al enviar el correo: ' . $mail->ErrorInfo);
    }
}