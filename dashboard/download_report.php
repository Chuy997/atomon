<?php
// Configuración de la base de datos
$servername = "localhost";
$username = "jmuro";
$password = "Monday.03";
$dbname = "atomon";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Obtener las fechas seleccionadas del formulario
$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];

// Asegurarse de que las fechas estén en el formato correcto (YYYY-MM-DD)
$start_date = date('Y-m-d', strtotime($start_date));
$end_date = date('Y-m-d', strtotime($end_date));

// Consultar los datos de la tabla `sensores` dentro del rango de fechas, comparando solo la fecha sin la hora
$sql = "SELECT id, sensor_id, temperatura, humedad, timestamp 
        FROM sensores 
        WHERE DATE(timestamp) BETWEEN '$start_date' AND '$end_date' 
        ORDER BY timestamp ASC";

$result = $conn->query($sql);

// Verificar si hay resultados
if ($result->num_rows > 0) {
    // Crear un nombre dinámico para el archivo CSV con las fechas
    $filename = "report_{$start_date}_{$end_date}.csv";
    
    // Crear un archivo CSV en la salida
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    // Abrir el archivo de salida
    $output = fopen('php://output', 'w');
    
    // Escribir la cabecera del CSV
    fputcsv($output, ['ID', 'Sensor ID', 'Temperatura', 'Humedad', 'Fecha y Hora']);
    
    // Escribir los datos de la base de datos en el CSV
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    // Cerrar el archivo de salida
    fclose($output);
} else {
    echo "No se encontraron datos para el rango de fechas seleccionado.";
}

// Cerrar la conexión
$conn->close();
?>
