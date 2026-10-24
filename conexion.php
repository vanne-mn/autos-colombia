
<?php
$host = "localhost";
$usuario = "root";
$contrasena = "DulceMaria2025";
$base_datos = "autos_colombia";

$conexion = new mysqli(
    $host,
    $usuario,
    $contrasena,
    $base_datos
);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");


?>
