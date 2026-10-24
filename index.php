```php
<?php
require_once "conexion.php";

// Total de parqueaderos registrados
$sqlParqueaderos = "SELECT COUNT(*) AS total FROM parqueaderos";
$resultadoParqueaderos = $conexion->query($sqlParqueaderos);
$totalParqueaderos = $resultadoParqueaderos
    ? (int) $resultadoParqueaderos->fetch_assoc()["total"]
    : 0;

// Total de vehículos registrados
$sqlVehiculos = "SELECT COUNT(*) AS total FROM vehiculos";
$resultadoVehiculos = $conexion->query($sqlVehiculos);
$totalVehiculos = $resultadoVehiculos
    ? (int) $resultadoVehiculos->fetch_assoc()["total"]
    : 0;

// Consultar espacios disponibles y ocupados
$sqlEspacios = "SELECT estado, COUNT(*) AS total
                FROM espacios
                GROUP BY estado";
$resultadoEspacios = $conexion->query($sqlEspacios);

$disponibles = 0;
$ocupados = 0;

if ($resultadoEspacios) {
    while ($fila = $resultadoEspacios->fetch_assoc()) {
        if ($fila["estado"] === "disponible") {
            $disponibles = (int) $fila["total"];
        }

        if ($fila["estado"] === "ocupado") {
            $ocupados = (int) $fila["total"];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Inicio | Autos Colombia</title>

    <link rel="stylesheet" href="estilos.css">
</head>

<body>

    <!-- Barra de navegación -->
    <header class="navbar">
        <div class="logo">
            AUTOS <span>COLOMBIA</span>
        </div>

        <nav class="nav-links">
            <a href="index.php">Inicio</a>
            <a href="parqueaderos.php">Parqueaderos</a>
            <a href="vehiculos.php">Vehículos</a>
            <a href="estacionamiento.php">Estacionamiento</a>
        </nav>
    </header>

    <main class="contenedor">

        <!-- Bienvenida -->
        <section class="encabezado">
            <h1>Bienvenido a Autos Colombia</h1>

            <p>
                Administra tus parqueaderos, consulta los vehículos
                y supervisa la ocupación desde un solo lugar.
            </p>
        </section>

        <!-- Resumen del sistema -->
        <section class="tarjetas">

            <article class="tarjeta">
                <h3>Parqueaderos registrados</h3>
                <p><?= $totalParqueaderos ?></p>
            </article>

            <article class="tarjeta">
                <h3>Vehículos registrados</h3>
                <p><?= $totalVehiculos ?></p>
            </article>

            <article class="tarjeta">
                <h3>Espacios disponibles</h3>
                <p><?= $disponibles ?></p>
            </article>

            <article class="tarjeta">
                <h3>Espacios ocupados</h3>
                <p><?= $ocupados ?></p>
            </article>

        </section>

        <!-- Accesos rápidos -->
        <section class="panel">
            <h2>Accesos rápidos</h2>

            <p style="color: #64748b; margin-bottom: 20px;">
                Selecciona una opción para continuar con la gestión.
            </p>

            <div class="tarjetas">

                <!-- Módulo de parqueaderos -->
                <article class="tarjeta">
                    <h3>Gestión de parqueaderos</h3>

                    <p style="font-size: 16px; font-weight: normal; margin-bottom: 20px;">
                        Consulta los parqueaderos registrados y su capacidad.
                    </p>

                    <a class="boton" href="parqueaderos.php">
                        Ver parqueaderos
                    </a>
                </article>

                <!-- Módulo de vehículos -->
                <article class="tarjeta">
                    <h3>Gestión de vehículos</h3>

                    <p style="font-size: 16px; font-weight: normal; margin-bottom: 20px;">
                        Registra nuevos vehículos y consulta los que ya están guardados.
                    </p>

                    <a class="boton" href="vehiculos.php">
                        Gestionar vehículos
                    </a>
                </article>

                <!-- Módulo de estacionamiento -->
                <article class="tarjeta">
                    <h3>Control de estacionamiento</h3>

                    <p style="font-size: 16px; font-weight: normal; margin-bottom: 20px;">
                        Registra las entradas y salidas de vehículos,
                        consulta los espacios ocupados y calcula el valor
                        del servicio.
                    </p>

                    <a class="boton" href="estacionamiento.php">
                        Gestionar estacionamiento
                    </a>
                </article>

            </div>
        </section>

    </main>

    <!-- Pie de página -->
    <footer>
        Autos Colombia · Sistema de gestión de parqueaderos
    </footer>

</body>
</html>
```