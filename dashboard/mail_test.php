<?php
// mail_test.php
declare(strict_types=1);

$to      = 'jesus.muro@xinya-la.com';      // reemplaza con un destinatario válido
$subject = 'Prueba de PHP mail()';
$message = "Este es un correo de prueba usando la función mail() de PHP.\n\n";
$headers = [
    'From'    => 'alertservice@xinya-la.com',
    'Reply-To'=> 'alertservice@xinya-la.com',
    'X-Mailer'=> 'PHP/' . phpversion()
];

$ok = mail($to, $subject, $message, implode("\r\n", array_map(
    fn($k, $v) => "$k: $v",
    array_keys($headers),
    $headers
)));

if ($ok) {
    echo "✔ mail() se ejecutó sin errores. Revisa tu bandeja de entrada.\n";
} else {
    echo "✖ mail() falló. Probablemente no hay un MTA configurado.\n";
}
