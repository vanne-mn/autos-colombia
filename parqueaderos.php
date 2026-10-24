
<?php
require_once "conexion.php";

$sql = "SELECT * FROM parqueaderos";
$resultado = $conexion->query($sql);

$totalParqueaderos = $resultado ? $resultado->num_rows : 0;
$capacidadTotal = 0;
$parqueaderos = [];

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $parqueaderos[] = $fila;
        $capacidadTotal += (int) $fila["capacidad"];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parqueaderos | Autos Colombia</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<header class="navbar">
    <div class="logo">AUTOS <span>COLOMBIA</span></div>

    <nav class="nav-links">
        <a href="index.php">Inicio</a>
        <a href="parqueaderos.php">Parqueaderos</a>
    </nav>
</header>

<main class="contenedor">

    <section class="encabezado">
        <h1>Gestión de parqueaderos</h1>
        <p>Consulta la información y la capacidad de los parqueaderos registrados.</p>
    </section>

    <section class="tarjetas">
        <article class="tarjeta">
            <h3>Parqueaderos registrados</h3>
            <p><?= $totalParqueaderos ?></p>
        </article>

        <article class="tarjeta">
            <h3>Capacidad total</h3>
            <p><?= $capacidadTotal ?> espacios</p>
        </article>
    </section>

    <section class="panel">
        <h2>Listado de parqueaderos</h2>

        <?php if (!empty($parqueaderos)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Dirección</th>
                        <th>Capacidad</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($parqueaderos as $fila): ?>
                        <tr>
                            <td><?= htmlspecialchars($fila["nombre"]) ?></td>
                            <td><?= htmlspecialchars($fila["direccion"]) ?></td>
                            <td><?= (int) $fila["capacidad"] ?> espacios</td>
                            <td>
                                <span class="estado">
                                    <?= htmlspecialchars($fila["estado"]) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay parqueaderos registrados todavía.</p>
        <?php endif; ?>
    </section>

</main>

<footer>
    Autos Colombia · Sistema de gestión de parqueaderos
</footer>

</body>
</html>
