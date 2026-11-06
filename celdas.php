
<?php
require_once "conexion.php";

$mensaje = "";
$tipoMensaje = "";

// REGISTRAR CELDA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["registrar"])) {
    $codigo = strtoupper(trim($_POST["codigo"] ?? ""));
    $tipo = $_POST["tipo"] ?? "";
    $estado = $_POST["estado"] ?? "disponible";
    $idParqueadero = filter_input(INPUT_POST, "id_parqueadero", FILTER_VALIDATE_INT);

    $tiposPermitidos = ["carro", "moto", "bicicleta", "otro"];
    $estadosPermitidos = ["disponible", "ocupado", "mantenimiento"];

    if (
        $codigo === "" ||
        strlen($codigo) > 10 ||
        !in_array($tipo, $tiposPermitidos, true) ||
        !in_array($estado, $estadosPermitidos, true) ||
        !$idParqueadero
    ) {
        $mensaje = "Completa los campos obligatorios y verifica los datos.";
        $tipoMensaje = "error";
    } else {
        $sql = "INSERT INTO espacios (codigo, tipo, estado, id_parqueadero)
                VALUES (?, ?, ?, ?)";

        $consulta = $conexion->prepare($sql);
        $consulta->bind_param("sssi", $codigo, $tipo, $estado, $idParqueadero);

        if ($consulta->execute()) {
            $mensaje = "Celda registrada correctamente.";
            $tipoMensaje = "exito";
        } elseif ($consulta->errno === 1062) {
            $mensaje = "Ya existe una celda con ese código.";
            $tipoMensaje = "error";
        } else {
            $mensaje = "No fue posible registrar la celda. Verifica el parqueadero seleccionado.";
            $tipoMensaje = "error";
        }

        $consulta->close();
    }
}

// ACTUALIZAR CELDA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    $id = filter_input(INPUT_POST, "id_espacio", FILTER_VALIDATE_INT);
    $codigo = strtoupper(trim($_POST["codigo"] ?? ""));
    $tipo = $_POST["tipo"] ?? "";
    $idParqueadero = filter_input(INPUT_POST, "id_parqueadero", FILTER_VALIDATE_INT);

    $tiposPermitidos = ["carro", "moto", "bicicleta", "otro"];

    if (
        !$id ||
        $codigo === "" ||
        strlen($codigo) > 10 ||
        !in_array($tipo, $tiposPermitidos, true) ||
        !$idParqueadero
    ) {
        $mensaje = "Verifica los datos de la celda.";
        $tipoMensaje = "error";
    } else {
        $sql = "UPDATE espacios
                SET codigo = ?, tipo = ?, id_parqueadero = ?
                WHERE id_espacio = ?";

        $consulta = $conexion->prepare($sql);
        $consulta->bind_param("ssii", $codigo, $tipo, $idParqueadero, $id);

        if ($consulta->execute()) {
            $mensaje = "Celda actualizada correctamente.";
            $tipoMensaje = "exito";
        } elseif ($consulta->errno === 1062) {
            $mensaje = "Ya existe una celda con ese código.";
            $tipoMensaje = "error";
        } else {
            $mensaje = "No fue posible actualizar la celda.";
            $tipoMensaje = "error";
        }

        $consulta->close();
    }
}

// CAMBIAR ESTADO DE CELDA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cambiar_estado"])) {
    $id = filter_input(INPUT_POST, "id_espacio", FILTER_VALIDATE_INT);
    $estado = $_POST["estado"] ?? "";

    $estadosPermitidos = ["disponible", "ocupado", "mantenimiento"];

    if (!$id || !in_array($estado, $estadosPermitidos, true)) {
        $mensaje = "Selecciona un estado válido.";
        $tipoMensaje = "error";
    } else {
        $consulta = $conexion->prepare(
            "UPDATE espacios SET estado = ? WHERE id_espacio = ?"
        );

        $consulta->bind_param("si", $estado, $id);

        if ($consulta->execute()) {
            $mensaje = "Estado de la celda actualizado correctamente.";
            $tipoMensaje = "exito";
        } else {
            $mensaje = "No fue posible cambiar el estado.";
            $tipoMensaje = "error";
        }

        $consulta->close();
    }
}

// ELIMINAR CELDA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar"])) {
    $id = filter_input(INPUT_POST, "id_espacio", FILTER_VALIDATE_INT);

    if ($id) {
        try {
            $consulta = $conexion->prepare(
                "DELETE FROM espacios WHERE id_espacio = ?"
            );

            $consulta->bind_param("i", $id);
            $consulta->execute();

            if ($consulta->affected_rows > 0) {
                $mensaje = "Celda eliminada correctamente.";
                $tipoMensaje = "exito";
            } else {
                $mensaje = "No se encontró la celda.";
                $tipoMensaje = "error";
            }

            $consulta->close();

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1451) {
                $mensaje = "No se puede eliminar esta celda porque tiene registros relacionados.";
            } else {
                $mensaje = "Ocurrió un error al eliminar la celda.";
            }

            $tipoMensaje = "error";
        }
    }
}

// CONSULTAR CELDA PARA EDITAR
$celdaEditar = null;

if (isset($_GET["editar"])) {
    $idEditar = filter_input(INPUT_GET, "editar", FILTER_VALIDATE_INT);

    if ($idEditar) {
        $consulta = $conexion->prepare(
            "SELECT id_espacio, codigo, tipo, estado, id_parqueadero
             FROM espacios WHERE id_espacio = ?"
        );

        $consulta->bind_param("i", $idEditar);
        $consulta->execute();

        $celdaEditar = $consulta->get_result()->fetch_assoc();

        $consulta->close();
    }
}

// CONSULTAR PARQUEADEROS PARA EL FORMULARIO
$parqueaderos = $conexion->query(
    "SELECT id_parqueadero, nombre FROM parqueaderos
     WHERE estado = 'activo' ORDER BY nombre"
);

// CONSULTAR CELDAS
$sql = "SELECT e.id_espacio, e.codigo, e.tipo, e.estado,
               p.nombre AS parqueadero
        FROM espacios e
        INNER JOIN parqueaderos p
        ON e.id_parqueadero = p.id_parqueadero
        ORDER BY e.id_espacio DESC";

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de celdas | Autos Colombia</title>
    <link rel="stylesheet" href="estilos.css">
</head>

<body>

<header class="navbar">
    <div class="logo">AUTOS <span>COLOMBIA</span></div>

    <nav class="nav-links">
        <a href="index.php">Inicio</a>
        <a href="parqueaderos.php">Parqueaderos</a>
        <a href="vehiculos.php">Vehículos</a>
        <a href="estacionamiento.php">Estacionamiento</a>
        <a href="usuarios.php">Usuarios</a>
        <a href="celdas.php">Celdas</a>
    </nav>
</header>

<main class="contenedor">

    <section class="encabezado">
        <h1>Gestión de celdas</h1>
        <p>Administra las celdas y consulta su disponibilidad en los parqueaderos.</p>
    </section>

    <section class="panel">

        <h2><?= $celdaEditar ? "Modificar celda" : "Registrar celda" ?></h2>

        <?php if ($mensaje !== ""): ?>
            <div class="<?= $tipoMensaje === "exito" ? "mensaje mensaje-exito" : "mensaje mensaje-error" ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="celdas.php" class="formulario">

            <?php if ($celdaEditar): ?>
                <input type="hidden" name="id_espacio"
                       value="<?= (int)$celdaEditar["id_espacio"] ?>">
            <?php endif; ?>

            <div class="campo">
                <label for="codigo">Código de la celda *</label>
                <input type="text" id="codigo" name="codigo"
                       maxlength="10" required
                       placeholder="Ej. A01"
                       style="text-transform: uppercase;"
                       value="<?= htmlspecialchars($celdaEditar["codigo"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="tipo">Tipo de vehículo *</label>
                <select id="tipo" name="tipo" required>
                    <?php $tipoActual = $celdaEditar["tipo"] ?? "carro"; ?>

                    <option value="carro" <?= $tipoActual === "carro" ? "selected" : "" ?>>Carro</option>
                    <option value="moto" <?= $tipoActual === "moto" ? "selected" : "" ?>>Moto</option>
                    <option value="bicicleta" <?= $tipoActual === "bicicleta" ? "selected" : "" ?>>Bicicleta</option>
                    <option value="otro" <?= $tipoActual === "otro" ? "selected" : "" ?>>Otro</option>
                </select>
            </div>

            <?php if (!$celdaEditar): ?>
                <div class="campo">
                    <label for="estado">Estado inicial *</label>
                    <select id="estado" name="estado" required>
                        <option value="disponible">Disponible</option>
                        <option value="ocupado">Ocupado</option>
                        <option value="mantenimiento">Mantenimiento</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="id_parqueadero">Parqueadero *</label>
                <select id="id_parqueadero" name="id_parqueadero" required>
                    <option value="">Selecciona un parqueadero</option>

                    <?php if ($parqueaderos): ?>
                        <?php while ($p = $parqueaderos->fetch_assoc()): ?>
                            <option value="<?= (int)$p["id_parqueadero"] ?>"
                                <?= isset($celdaEditar["id_parqueadero"]) &&
                                    (int)$celdaEditar["id_parqueadero"] === (int)$p["id_parqueadero"]
                                    ? "selected" : "" ?>>
                                <?= htmlspecialchars($p["nombre"]) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="campo campo-boton">
                <?php if ($celdaEditar): ?>
                    <button type="submit" name="actualizar" class="boton">
                        Guardar cambios
                    </button>
                    <a href="celdas.php" class="boton">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="registrar" class="boton">
                        Registrar celda
                    </button>
                <?php endif; ?>
            </div>

        </form>
    </section>

    <section class="panel" style="margin-top: 30px;">

        <h2>Celdas registradas</h2>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Parqueadero</th>
                        <th>Modificar</th>
                        <th>Cambiar estado</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>

                        <?php while ($fila = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($fila["codigo"]) ?></td>
                                <td><?= htmlspecialchars(ucfirst($fila["tipo"])) ?></td>
                                <td><?= htmlspecialchars(ucfirst($fila["estado"])) ?></td>
                                <td><?= htmlspecialchars($fila["parqueadero"]) ?></td>

                                <td>
                                    <a class="boton"
                                       href="celdas.php?editar=<?= (int)$fila["id_espacio"] ?>">
                                        Editar
                                    </a>
                                </td>

                                <td>
                                    <form method="POST" action="celdas.php">
                                        <input type="hidden" name="id_espacio"
                                               value="<?= (int)$fila["id_espacio"] ?>">

                                        <select name="estado" required>
                                            <option value="disponible"
                                                <?= $fila["estado"] === "disponible" ? "selected" : "" ?>>
                                                Disponible
                                            </option>

                                            <option value="ocupado"
                                                <?= $fila["estado"] === "ocupado" ? "selected" : "" ?>>
                                                Ocupado
                                            </option>

                                            <option value="mantenimiento"
                                                <?= $fila["estado"] === "mantenimiento" ? "selected" : "" ?>>
                                                Mantenimiento
                                            </option>
                                        </select>

                                        <button type="submit" name="cambiar_estado" class="boton">
                                            Actualizar
                                        </button>
                                    </form>
                                </td>

                                <td>
                                    <form method="POST" action="celdas.php"
                                          onsubmit="return confirm('¿Deseas eliminar esta celda?');">

                                        <input type="hidden" name="id_espacio"
                                               value="<?= (int)$fila["id_espacio"] ?>">

                                        <button type="submit" name="eliminar" class="boton">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="7">Todavía no hay celdas registradas.</td>
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
