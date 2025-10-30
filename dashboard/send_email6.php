<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Carga la biblioteca PHPMailer

function sendTemperatureAlert($temperatura) {
    $minTemp = 10; // Temperatura mínima aceptable
    $maxTemp = 30; // Temperatura máxima aceptable

    if ($temperatura < $minTemp || $temperatura > $maxTemp) {
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Cambia si usas otro proveedor
            $mail->SMTPAuth   = true;
            $mail->Username   = 'testxinya@gmail.com'; // Tu correo
            $mail->Password   = 'Test.2025'; // Contraseña del correo
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Remitente y destinatario
            $mail->setFrom('alertservice@zhongli-la.com', 'Servicio de Alertas');
            $mail->addAddress('juan.mata@xinya-la.com', 'Juan Mata');

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = '¡Alerta de Temperatura Fuera de Rango!';
            $mail->Body    = "La temperatura actual es de {$temperatura}°C, lo cual está fuera del rango aceptable ({$minTemp}°C - {$maxTemp}°C).";
            $mail->AltBody = "La temperatura actual es de {$temperatura}°C, lo cual está fuera del rango aceptable ({$minTemp}°C - {$maxTemp}°C).";

            // Envía el correo
            $mail->send();
            echo "Correo enviado correctamente.\n";
        } catch (Exception $e) {
            echo "Error al enviar el correo: {$mail->ErrorInfo}\n";
        }
    }
}
?>