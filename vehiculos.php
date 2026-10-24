
<?php
require_once "conexion.php";

$mensaje = "";
$tipoMensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $placa = strtoupper(trim($_POST["placa"] ?? ""));
    $tipo = $_POST["tipo"] ?? "";
    $marca = trim($_POST["marca"] ?? "");
    $color = trim($_POST["color"] ?? "");
    $idUsuario = filter_input(INPUT_POST, "id_usuario", FILTER_VALIDATE_INT);

    $tiposPermitidos = ["carro", "moto", "bicicleta", "otro"];

    if (
        $placa === "" ||
        !in_array($tipo, $tiposPermitidos, true) ||
        strlen($placa) > 10
    ) {
        $mensaje = "Verifica la placa y el tipo de vehículo.";
        $tipoMensaje = "error";
    } else {
        $sql = "INSERT INTO vehiculos
                (placa, tipo, marca, color, id_usuario)
                VALUES (?, ?, ?, ?, ?)";

        $consulta = $conexion->prepare($sql);
        $consulta->bind_param(
            "ssssi",
            $placa,
            $tipo,
            $marca,
            $color,
            $idUsuario
        );

        if ($consulta->execute()) {
            $mensaje = "Vehículo registrado correctamente.";
            $tipoMensaje = "exito";
        } elseif ($conexion->errno === 1062 || $consulta->errno === 1062) {
            $mensaje = "Ya existe un vehículo registrado con esa placa.";
            $tipoMensaje = "error";
        } else {
            $mensaje = "No fue posible registrar el vehículo. Verifica los datos.";
            $tipoMensaje = "error";
        }

        $consulta->close();
    }
}

$sql = "SELECT v.id_vehiculo, v.placa, v.tipo, v.marca, v.color,
               u.nombre AS propietario
        FROM vehiculos v
        LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
        ORDER BY v.id_vehiculo DESC";

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehículos | Autos Colombia</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<header class="navbar">
    <div class="logo">AUTOS <span>COLOMBIA</span></div>

    <nav class="nav-links">
        <a href="index.php">Inicio</a>
        <a href="parqueaderos.php">Parqueaderos</a>
        <a href="vehiculos.php">Vehículos</a>
    </nav>
</header>

<main class="contenedor">

    <section class="encabezado">
        <h1>Gestión de vehículos</h1>
        <p>Registra y consulta los vehículos del sistema de parqueaderos.</p>
    </section>

    <section class="panel">
        <h2>Registrar un vehículo</h2>

        <?php if ($mensaje !== ""): ?>
            <div class="mensaje <?= $tipoMensaje === "exito" ? "mensaje-exito" : "mensaje-error" ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="vehiculos.php" class="formulario">
            <div class="campo">
                <label for="placa">Placa *</label>
                <input
                    type="text"
                    id="placa"
                    name="placa"
                    maxlength="10"
                    required
                    placeholder="Ej. ABC123"
                    style="text-transform: uppercase;"
                >
            </div>

            <div class="campo">
                <label for="tipo">Tipo de vehículo *</label>
                <select id="tipo" name="tipo" required>
                    <option value="">Selecciona un tipo</option>
                    <option value="carro">Carro</option>
                    <option value="moto">Moto</option>
                    <option value="bicicleta">Bicicleta</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <div class="campo">
                <label for="marca">Marca</label>
                <input
                    type="text"
                    id="marca"
                    name="marca"
                    maxlength="50"
                    placeholder="Ej. Chevrolet"
                >
            </div>

            <div class="campo">
                <label for="color">Color</label>
                <input
                    type="text"
                    id="color"
                    name="color"
                    maxlength="30"
                    placeholder="Ej. Gris"
                >
            </div>

            <div class="campo">
                <label for="id_usuario">Propietario</label>
                <select id="id_usuario" name="id_usuario">
                    <option value="">Sin propietario asignado</option>

                    <?php
                    $usuarios = $conexion->query(
                        "SELECT id_usuario, nombre, documento
                         FROM usuarios ORDER BY nombre"
                    );

                    if ($usuarios):
                        while ($usuario = $usuarios->fetch_assoc()):
                    ?>
                        <option value="<?= (int) $usuario["id_usuario"] ?>">
                            <?= htmlspecialchars($usuario["nombre"]) ?>
                            - <?= htmlspecialchars($usuario["documento"]) ?>
                        </option>
                    <?php
                        endwhile;
                    endif;
                    ?>
                </select>
            </div>

            <div class="campo campo-boton">
                <button type="submit" class="boton">
                    Registrar vehículo
                </button>
            </div>
        </form>
    </section>

    <section class="panel" style="margin-top: 30px;">
        <h2>Vehículos registrados</h2>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Placa</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Color</th>
                        <th>Propietario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while ($fila = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($fila["placa"]) ?></td>
                                <td><?= htmlspecialchars(ucfirst($fila["tipo"])) ?></td>
                                <td><?= htmlspecialchars($fila["marca"] ?? "") ?></td>
                                <td><?= htmlspecialchars($fila["color"] ?? "") ?></td>
                                <td><?= htmlspecialchars($fila["propietario"] ?? "Sin asignar") ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">Todavía no hay vehículos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<footer>
    Autos Colombia · Sistema de gestión de parqueaderos
</footer>

</body>
</html>
