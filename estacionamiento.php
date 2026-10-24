```php
<?php
require_once "conexion.php";

$mensaje = "";
$tipoMensaje = "";

function obtenerDatos($conexion)
{
    $sql = "SELECT
                r.id_registro,
                v.placa,
                v.tipo,
                v.marca,
                e.codigo AS espacio,
                p.nombre AS parqueadero,
                r.fecha_entrada,
                r.tarifa_hora,
                TIMESTAMPDIFF(MINUTE, r.fecha_entrada, NOW()) AS minutos
            FROM registros_estacionamiento r
            INNER JOIN vehiculos v ON r.id_vehiculo = v.id_vehiculo
            INNER JOIN espacios e ON r.id_espacio = e.id_espacio
            INNER JOIN parqueaderos p ON e.id_parqueadero = p.id_parqueadero
            WHERE r.estado = 'activo'
            ORDER BY r.fecha_entrada ASC";

    return $conexion->query($sql);
}

// Registrar entrada o salida
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    if ($accion === "entrada") {
        $idVehiculo = filter_input(INPUT_POST, "id_vehiculo", FILTER_VALIDATE_INT);
        $idEspacio = filter_input(INPUT_POST, "id_espacio", FILTER_VALIDATE_INT);

        if (!$idVehiculo || !$idEspacio) {
            $mensaje = "Selecciona un vehículo y un espacio disponible.";
            $tipoMensaje = "error";
        } else {
            try {
                $conexion->begin_transaction();

                // Bloquear el espacio para evitar asignaciones simultáneas
                $consulta = $conexion->prepare(
                    "SELECT estado FROM espacios WHERE id_espacio = ? FOR UPDATE"
                );
                $consulta->bind_param("i", $idEspacio);
                $consulta->execute();
                $espacio = $consulta->get_result()->fetch_assoc();
                $consulta->close();

                if (!$espacio || $espacio["estado"] !== "disponible") {
                    throw new Exception("El espacio seleccionado ya no está disponible.");
                }

                // Comprobar que el vehículo no tenga una entrada activa
                $consulta = $conexion->prepare(
                    "SELECT id_registro FROM registros_estacionamiento
                     WHERE id_vehiculo = ? AND estado = 'activo'
                     LIMIT 1"
                );
                $consulta->bind_param("i", $idVehiculo);
                $consulta->execute();
                $activo = $consulta->get_result()->fetch_assoc();
                $consulta->close();

                if ($activo) {
                    throw new Exception("Este vehículo ya tiene un estacionamiento activo.");
                }

                $tarifa = 5000.00;

                $consulta = $conexion->prepare(
                    "INSERT INTO registros_estacionamiento
                     (id_vehiculo, id_espacio, fecha_entrada, tarifa_hora, estado)
                     VALUES (?, ?, NOW(), ?, 'activo')"
                );
                $consulta->bind_param("iid", $idVehiculo, $idEspacio, $tarifa);
                $consulta->execute();
                $consulta->close();

                $consulta = $conexion->prepare(
                    "UPDATE espacios SET estado = 'ocupado' WHERE id_espacio = ?"
                );
                $consulta->bind_param("i", $idEspacio);
                $consulta->execute();
                $consulta->close();

                $conexion->commit();

                $mensaje = "Entrada registrada correctamente.";
                $tipoMensaje = "exito";
            } catch (Throwable $error) {
                $conexion->rollback();
                $mensaje = $error instanceof Exception
                    ? $error->getMessage()
                    : "No fue posible registrar la entrada. Verifica la configuración de la base de datos.";
                $tipoMensaje = "error";
            }
        }
    } elseif ($accion === "salida") {
        $idRegistro = filter_input(INPUT_POST, "id_registro", FILTER_VALIDATE_INT);

        if (!$idRegistro) {
            $mensaje = "No se encontró un registro válido para la salida.";
            $tipoMensaje = "error";
        } else {
            try {
                $conexion->begin_transaction();

                $consulta = $conexion->prepare(
                    "SELECT id_espacio, fecha_entrada, tarifa_hora
                     FROM registros_estacionamiento
                     WHERE id_registro = ? AND estado = 'activo'
                     FOR UPDATE"
                );
                $consulta->bind_param("i", $idRegistro);
                $consulta->execute();
                $registro = $consulta->get_result()->fetch_assoc();
                $consulta->close();

                if (!$registro) {
                    throw new Exception("Este estacionamiento ya fue finalizado o no existe.");
                }

                $entrada = new DateTime($registro["fecha_entrada"]);
                $salida = new DateTime();
                $segundos = max(0, $salida->getTimestamp() - $entrada->getTimestamp());

                // Cobro por hora iniciada, con mínimo de una hora
                $horas = max(1, (int) ceil($segundos / 3600));
                $total = $horas * (float) $registro["tarifa_hora"];
                $idEspacio = (int) $registro["id_espacio"];

                $consulta = $conexion->prepare(
                    "UPDATE registros_estacionamiento
                     SET fecha_salida = NOW(), total_pago = ?, estado = 'finalizado'
                     WHERE id_registro = ? AND estado = 'activo'"
                );
                $consulta->bind_param("di", $total, $idRegistro);
                $consulta->execute();

                if ($consulta->affected_rows !== 1) {
                    $consulta->close();
                    throw new Exception("No se pudo finalizar el estacionamiento.");
                }
                $consulta->close();

                $consulta = $conexion->prepare(
                    "UPDATE espacios SET estado = 'disponible' WHERE id_espacio = ?"
                );
                $consulta->bind_param("i", $idEspacio);
                $consulta->execute();
                $consulta->close();

                $conexion->commit();

                $mensaje = "Salida registrada. Tiempo cobrado: " . $horas .
                    " hora(s). Total a pagar: $" . number_format($total, 0, ",", ".");
                $tipoMensaje = "exito";
            } catch (Throwable $error) {
                $conexion->rollback();
                $mensaje = $error instanceof Exception
                    ? $error->getMessage()
                    : "No fue posible registrar la salida. Verifica la configuración de la base de datos.";
                $tipoMensaje = "error";
            }
        }
    }
}

// Vehículos disponibles para registrar entrada
$vehiculos = $conexion->query(
    "SELECT v.id_vehiculo, v.placa, v.tipo, v.marca
     FROM vehiculos v
     WHERE NOT EXISTS (
         SELECT 1 FROM registros_estacionamiento r
         WHERE r.id_vehiculo = v.id_vehiculo AND r.estado = 'activo'
     )
     ORDER BY v.placa"
);

// Espacios disponibles
$espacios = $conexion->query(
    "SELECT e.id_espacio, e.codigo, e.tipo, p.nombre AS parqueadero
     FROM espacios e
     INNER JOIN parqueaderos p ON e.id_parqueadero = p.id_parqueadero
     WHERE e.estado = 'disponible'
     ORDER BY p.nombre, e.codigo"
);

// Entradas activas
$estacionados = obtenerDatos($conexion);

$totalActivos = $estacionados ? $estacionados->num_rows : 0;

$disponibles = $conexion->query(
    "SELECT COUNT(*) AS total FROM espacios WHERE estado = 'disponible'"
)->fetch_assoc()["total"];

$ocupados = $conexion->query(
    "SELECT COUNT(*) AS total FROM espacios WHERE estado = 'ocupado'"
)->fetch_assoc()["total"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de estacionamiento | Autos Colombia</title>
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
    </nav>
</header>

<main class="contenedor">
    <section class="encabezado">
        <h1>Control de estacionamiento</h1>
        <p>Registra entradas y salidas, consulta los vehículos estacionados y controla la ocupación.</p>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje <?= $tipoMensaje === "exito" ? "mensaje-exito" : "mensaje-error" ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <section class="tarjetas">
        <article class="tarjeta">
            <h3>Vehículos estacionados</h3>
            <p><?= (int) $totalActivos ?></p>
        </article>
        <article class="tarjeta">
            <h3>Espacios disponibles</h3>
            <p><?= (int) $disponibles ?></p>
        </article>
        <article class="tarjeta">
            <h3>Espacios ocupados</h3>
            <p><?= (int) $ocupados ?></p>
        </article>
    </section>

    <section class="panel">
        <h2>Registrar entrada de vehículo</h2>
        <p style="color:#64748b; margin-bottom:20px;">
            Tarifa de prueba: $5.000 por hora iniciada.
        </p>

        <form method="POST" action="estacionamiento.php" class="formulario">
            <input type="hidden" name="accion" value="entrada">

            <div class="campo">
                <label for="id_vehiculo">Vehículo *</label>
                <select name="id_vehiculo" id="id_vehiculo" required>
                    <option value="">Selecciona una placa</option>
                    <?php if ($vehiculos): ?>
                        <?php while ($v = $vehiculos->fetch_assoc()): ?>
                            <option value="<?= (int) $v["id_vehiculo"] ?>">
                                <?= htmlspecialchars($v["placa"]) ?>
                                - <?= htmlspecialchars(ucfirst($v["tipo"])) ?>
                                <?= $v["marca"] ? "- " . htmlspecialchars($v["marca"]) : "" ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="campo">
                <label for="id_espacio">Espacio disponible *</label>
                <select name="id_espacio" id="id_espacio" required>
                    <option value="">Selecciona un espacio</option>
                    <?php if ($espacios): ?>
                        <?php while ($e = $espacios->fetch_assoc()): ?>
                            <option value="<?= (int) $e["id_espacio"] ?>">
                                <?= htmlspecialchars($e["codigo"]) ?>
                                - <?= htmlspecialchars($e["parqueadero"]) ?>
                                (<?= htmlspecialchars(ucfirst($e["tipo"])) ?>)
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="campo campo-boton">
                <button type="submit" class="boton">Registrar entrada</button>
            </div>
        </form>
    </section>

    <section class="panel" style="margin-top:30px;">
        <h2>Vehículos actualmente estacionados</h2>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Placa</th>
                        <th>Vehículo</th>
                        <th>Parqueadero</th>
                        <th>Espacio</th>
                        <th>Entrada</th>
                        <th>Tiempo transcurrido</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($estacionados && $estacionados->num_rows > 0): ?>
                    <?php while ($r = $estacionados->fetch_assoc()): ?>
                        <?php
                        $minutos = max(0, (int) $r["minutos"]);
                        $horasTranscurridas = intdiv($minutos, 60);
                        $minutosRestantes = $minutos % 60;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($r["placa"]) ?></td>
                            <td><?= htmlspecialchars(ucfirst($r["tipo"])) ?>
                                <?= $r["marca"] ? "- " . htmlspecialchars($r["marca"]) : "" ?>
                            </td>
                            <td><?= htmlspecialchars($r["parqueadero"]) ?></td>
                            <td><?= htmlspecialchars($r["espacio"]) ?></td>
                            <td><?= htmlspecialchars($r["fecha_entrada"]) ?></td>
                            <td><?= $horasTranscurridas ?> h <?= $minutosRestantes ?> min</td>
                            <td>
                                <form method="POST" action="estacionamiento.php"
                                      onsubmit="return confirm('¿Confirmas la salida de este vehículo?');">
                                    <input type="hidden" name="accion" value="salida">
                                    <input type="hidden" name="id_registro"
                                           value="<?= (int) $r["id_registro"] ?>">
                                    <button type="submit" class="boton">Registrar salida</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No hay vehículos estacionados actualmente.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<footer>Autos Colombia · Sistema de gestión de parqueaderos</footer>
</body>
</html>
```