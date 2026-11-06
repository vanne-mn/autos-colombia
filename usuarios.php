
<?php
require_once "conexion.php";

$mensaje = "";
$tipoMensaje = "";

// REGISTRAR USUARIO
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["registrar"])) {
    $nombre = trim($_POST["nombre"] ?? "");
    $documento = trim($_POST["documento"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $rol = $_POST["rol"] ?? "cliente";

    $rolesPermitidos = ["administrador", "operador", "cliente"];

    if (
        $nombre === "" ||
        $documento === "" ||
        $contrasena === "" ||
        !in_array($rol, $rolesPermitidos, true)
    ) {
        $mensaje = "Completa los campos obligatorios y verifica el rol.";
        $tipoMensaje = "error";
    } elseif (strlen($contrasena) < 8) {
        $mensaje = "La contraseña debe tener mínimo 8 caracteres.";
        $tipoMensaje = "error";
    } else {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuarios
                (nombre, documento, telefono, correo, contrasena, rol)
                VALUES (?, ?, ?, ?, ?, ?)";

        $consulta = $conexion->prepare($sql);
        $consulta->bind_param(
            "ssssss",
            $nombre,
            $documento,
            $telefono,
            $correo,
            $hash,
            $rol
        );

        if ($consulta->execute()) {
            $mensaje = "Usuario registrado correctamente.";
            $tipoMensaje = "exito";
        } elseif ($consulta->errno === 1062) {
            $mensaje = "El documento o correo ya se encuentra registrado.";
            $tipoMensaje = "error";
        } else {
            $mensaje = "No fue posible registrar el usuario.";
            $tipoMensaje = "error";
        }

        $consulta->close();
    }
}

// ACTUALIZAR USUARIO
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    $id = filter_input(INPUT_POST, "id_usuario", FILTER_VALIDATE_INT);
    $nombre = trim($_POST["nombre"] ?? "");
    $documento = trim($_POST["documento"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $rol = $_POST["rol"] ?? "";

    $rolesPermitidos = ["administrador", "operador", "cliente"];

    if (
        !$id || $nombre === "" || $documento === "" ||
        !in_array($rol, $rolesPermitidos, true)
    ) {
        $mensaje = "Verifica los datos del usuario.";
        $tipoMensaje = "error";
    } else {
        $sql = "UPDATE usuarios
                SET nombre = ?, documento = ?, telefono = ?, correo = ?, rol = ?
                WHERE id_usuario = ?";

        $consulta = $conexion->prepare($sql);
        $consulta->bind_param(
            "sssssi",
            $nombre,
            $documento,
            $telefono,
            $correo,
            $rol,
            $id
        );

        if ($consulta->execute()) {
            $mensaje = "Usuario actualizado correctamente.";
            $tipoMensaje = "exito";
        } elseif ($consulta->errno === 1062) {
            $mensaje = "El documento o correo ya pertenece a otro usuario.";
            $tipoMensaje = "error";
        } else {
            $mensaje = "No fue posible actualizar el usuario.";
            $tipoMensaje = "error";
        }

        $consulta->close();
    }
}

// ELIMINAR USUARIO
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar"])) {
    $id = filter_input(INPUT_POST, "id_usuario", FILTER_VALIDATE_INT);

    if ($id) {
        $consulta = $conexion->prepare(
            "DELETE FROM usuarios WHERE id_usuario = ?"
        );
        $consulta->bind_param("i", $id);

        if ($consulta->execute()) {
            if ($consulta->affected_rows > 0) {
                $mensaje = "Usuario eliminado correctamente.";
                $tipoMensaje = "exito";
            } else {
                $mensaje = "No se encontró el usuario.";
                $tipoMensaje = "error";
            }
        } else {
            $mensaje = "No se puede eliminar: el usuario puede tener registros relacionados.";
            $tipoMensaje = "error";
        }

        $consulta->close();
    }
}

// CONSULTAR USUARIO PARA EDITAR
$usuarioEditar = null;

if (isset($_GET["editar"])) {
    $idEditar = filter_input(INPUT_GET, "editar", FILTER_VALIDATE_INT);

    if ($idEditar) {
        $consulta = $conexion->prepare(
            "SELECT id_usuario, nombre, documento, telefono, correo, rol
             FROM usuarios WHERE id_usuario = ?"
        );

        $consulta->bind_param("i", $idEditar);
        $consulta->execute();

        $usuarioEditar = $consulta->get_result()->fetch_assoc();

        $consulta->close();
    }
}

// CONSULTAR USUARIOS
$resultado = $conexion->query(
    "SELECT id_usuario, nombre, documento, telefono, correo, rol, fecha_registro
     FROM usuarios ORDER BY id_usuario DESC"
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | Autos Colombia</title>
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
    </nav>
</header>

<main class="contenedor">

    <section class="encabezado">
        <h1>Gestión de usuarios</h1>
        <p>Registra, consulta, modifica y administra los usuarios del sistema.</p>
    </section>

    <section class="panel">

        <h2>
            <?= $usuarioEditar ? "Modificar usuario" : "Registrar usuario" ?>
        </h2>

        <?php if ($mensaje !== ""): ?>
            <div class="<?= $tipoMensaje === "exito" ? "mensaje mensaje-exito" : "mensaje mensaje-error" ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="usuarios.php" class="formulario">

            <?php if ($usuarioEditar): ?>
                <input type="hidden" name="id_usuario"
                       value="<?= (int)$usuarioEditar["id_usuario"] ?>">
            <?php endif; ?>

            <div class="campo">
                <label for="nombre">Nombre completo *</label>
                <input type="text" id="nombre" name="nombre"
                       maxlength="100" required
                       value="<?= htmlspecialchars($usuarioEditar["nombre"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="documento">Documento *</label>
                <input type="text" id="documento" name="documento"
                       maxlength="20" required
                       value="<?= htmlspecialchars($usuarioEditar["documento"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono"
                       maxlength="20"
                       value="<?= htmlspecialchars($usuarioEditar["telefono"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo"
                       maxlength="100"
                       value="<?= htmlspecialchars($usuarioEditar["correo"] ?? "") ?>">
            </div>

            <?php if (!$usuarioEditar): ?>
                <div class="campo">
                    <label for="contrasena">Contraseña *</label>
                    <input type="password" id="contrasena" name="contrasena"
                           minlength="8" required>
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="rol">Rol *</label>

                <?php $rolActual = $usuarioEditar["rol"] ?? "cliente"; ?>

                <select id="rol" name="rol" required>
                    <option value="administrador"
                        <?= $rolActual === "administrador" ? "selected" : "" ?>>
                        Administrador
                    </option>

                    <option value="operador"
                        <?= $rolActual === "operador" ? "selected" : "" ?>>
                        Operador
                    </option>

                    <option value="cliente"
                        <?= $rolActual === "cliente" ? "selected" : "" ?>>
                        Cliente
                    </option>
                </select>
            </div>

            <div class="campo campo-boton">
                <?php if ($usuarioEditar): ?>
                    <button type="submit" name="actualizar" class="boton">
                        Guardar cambios
                    </button>

                    <a href="usuarios.php" class="boton">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="registrar" class="boton">
                        Registrar usuario
                    </button>
                <?php endif; ?>
            </div>

        </form>
    </section>

    <section class="panel" style="margin-top: 30px;">

        <h2>Usuarios registrados</h2>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Documento</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Fecha de registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>

                        <?php while ($fila = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($fila["nombre"]) ?></td>
                                <td><?= htmlspecialchars($fila["documento"]) ?></td>
                                <td><?= htmlspecialchars($fila["telefono"] ?? "") ?></td>
                                <td><?= htmlspecialchars($fila["correo"] ?? "") ?></td>
                                <td><?= htmlspecialchars(ucfirst($fila["rol"])) ?></td>
                                <td><?= htmlspecialchars($fila["fecha_registro"] ?? "") ?></td>

                                <td>
                                    <a class="boton"
                                       href="usuarios.php?editar=<?= (int)$fila["id_usuario"] ?>">
                                        Editar
                                    </a>

                                    <form method="POST" action="usuarios.php"
                                          onsubmit="return confirm('¿Deseas eliminar este usuario?');">

                                        <input type="hidden" name="id_usuario"
                                               value="<?= (int)$fila["id_usuario"] ?>">

                                        <button type="submit" name="eliminar" class="boton">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="7">Todavía no hay usuarios registrados.</td>
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
