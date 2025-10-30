<?php
// smtp_test.php
declare(strict_types=1);

// Configuración del servidor SMTP a probar
$host    = 'smtphz.qiye.163.com';
$ports   = [587, 465, 25];
$timeout = 5; // segundos

echo "Probando conectividad SMTP a {$host}...\n\n";

foreach ($ports as $port) {
    $start   = microtime(true);
    $errno   = null;
    $errstr  = null;

    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $elapsed = round((microtime(true) - $start) * 1000);

    if ($fp) {
        fclose($fp);
        echo "✔ Puerto {$port} abierto (respuesta en {$elapsed} ms)\n";
    } else {
        echo "✖ Puerto {$port} cerrado o inaccesible ({$errstr} [{$errno}])\n";
    }
}

echo "\nTesteado en " . date('Y-m-d H:i:s') . "\n";
