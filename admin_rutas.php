<?php
/*
 * admin_rutas.php
 * ---------------
 * Panel de administrador para CRUD de rutas.
 * Solo lo puede abrir un usuario con rol=admin.
 */

require 'admin_only.php';
require 'Conexion.php';

$msgOk = "";
$msgErr = "";

/* ====== Helpers ====== */
function paradasTextareaToDB($texto) {
    $lineas = preg_split("/\r\n|\n|\r/", trim($texto));
    $limpias = [];
    foreach ($lineas as $l) {
        $l = trim($l);
        if ($l !== "") $limpias[] = $l;
    }
    return implode("|", $limpias);
}

function paradasDBToTextarea($texto) {
    $arr = explode("|", $texto ?? "");
    $arr = array_map('trim', $arr);
    $arr = array_filter($arr, fn($x) => $x !== "");
    return implode("\n", $arr);
}

/* ====== ELIMINAR ====== */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM rutas WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) $msgOk = "Ruta eliminada correctamente.";
    else $msgErr = "No se pudo eliminar la ruta.";

    $stmt->close();
}

/* ====== GUARDAR (INSERT/UPDATE) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_ruta'])) {
    $id          = (int)($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre_ruta'] ?? "");
    $descripcion = trim($_POST['descripcion'] ?? "");
    $conductor   = trim($_POST['conductor'] ?? "");
    $paradasTxt  = trim($_POST['paradas'] ?? "");

    if ($nombre === "" || $descripcion === "" || $conductor === "" || $paradasTxt === "") {
        $msgErr = "Completa todos los campos.";
    } else {
        $paradasDB = paradasTextareaToDB($paradasTxt);

        if ($id > 0) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE rutas SET nombre_ruta=?, descripcion=?, conductor=?, paradas=? WHERE id=?");
            $stmt->bind_param("ssssi", $nombre, $descripcion, $conductor, $paradasDB, $id);

            if ($stmt->execute()) $msgOk = "Ruta actualizada correctamente.";
            else $msgErr = "No se pudo actualizar la ruta.";

            $stmt->close();
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO rutas (nombre_ruta, descripcion, conductor, paradas) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $nombre, $descripcion, $conductor, $paradasDB);

            if ($stmt->execute()) $msgOk = "Ruta agregada correctamente.";
            else $msgErr = "No se pudo agregar la ruta.";

            $stmt->close();
        }
    }
}

/* ====== CARGAR PARA EDITAR ====== */
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM rutas WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit = $res->fetch_assoc();
    $stmt->close();

    if ($edit) {
        $edit['paradas_textarea'] = paradasDBToTextarea($edit['paradas']);
    }
}

/* ====== LISTAR RUTAS ====== */
$rutas = [];
$res = $conn->query("SELECT id, nombre_ruta, conductor FROM rutas ORDER BY id DESC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) $rutas[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Rutas (TuBus)</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- HEADER (mismo estilo que tibus-app.php) -->
<div class="top-header">
    <div class="top-header-left">
        <strong>TuBus</strong>
    </div>
    <div class="top-header-right">
        <a href="tibus-app.php" class="top-link">Inicio</a>
        <a href="admin_rutas.php" class="top-link">Panel Admin</a>
        <span class="top-user"><?= htmlspecialchars($_SESSION['usuario']['nombre']) ?></span>
        <a href="logout.php" class="top-link logout">Cerrar sesión</a>
    </div>
</div>

<div class="app-container" style="margin-top: 16px;">
    <div class="glass-card">

        <h1 style="margin-bottom:10px;">Administrar Rutas</h1>

        <?php if ($msgOk): ?><div class="msg success"><?= htmlspecialchars($msgOk) ?></div><?php endif; ?>
        <?php if ($msgErr): ?><div class="msg error"><?= htmlspecialchars($msgErr) ?></div><?php endif; ?>

        <h2 style="margin:16px 0 8px;">
            <?= $edit ? "Editar ruta #".(int)$edit['id'] : "Agregar nueva ruta" ?>
        </h2>

        <form method="POST" class="contact-form">
            <input type="hidden" name="guardar_ruta" value="1">
            <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">

            <input
                type="text"
                name="nombre_ruta"
                placeholder="Nombre de la ruta"
                value="<?= $edit ? htmlspecialchars($edit['nombre_ruta']) : '' ?>"
                required
            >

            <input
                type="text"
                name="conductor"
                placeholder="Conductor"
                value="<?= $edit ? htmlspecialchars($edit['conductor']) : '' ?>"
                required
            >

            <textarea name="descripcion" placeholder="Descripción" required><?= $edit ? htmlspecialchars($edit['descripcion']) : '' ?></textarea>

            <textarea name="paradas" placeholder="Paradas (una por línea)" required><?= $edit ? htmlspecialchars($edit['paradas_textarea']) : '' ?></textarea>

            <button type="submit" class="view-route-btn">
                <?= $edit ? "Guardar cambios" : "Agregar ruta" ?>
            </button>

            <?php if ($edit): ?>
                <a href="admin_rutas.php" style="margin-left:10px; color:#2563eb; font-weight:800; text-decoration:none;">
                    Cancelar edición
                </a>
            <?php endif; ?>
        </form>

        <div style="margin-top:22px;">
            <h2 style="margin-bottom:10px;">Rutas registradas</h2>

            <?php if (count($rutas) === 0): ?>
                <div class="empty-state">No hay rutas registradas.</div>
            <?php else: ?>
                <?php foreach ($rutas as $r): ?>
                    <div class="bus-card" style="cursor:default;">
                        <div class="bus-info">
                            <h3>#<?= (int)$r['id'] ?> - <?= htmlspecialchars($r['nombre_ruta']) ?></h3>
                            <p>Conductor: <?= htmlspecialchars($r['conductor']) ?></p>
                        </div>

                        <div style="display:flex; gap:8px;">
                            <a class="view-route-btn" href="admin_rutas.php?edit=<?= (int)$r['id'] ?>" style="text-decoration:none;">
                                Editar
                            </a>

                            <a class="view-route-btn"
                               href="admin_rutas.php?delete=<?= (int)$r['id'] ?>"
                               style="text-decoration:none; background:#ef4444;"
                               onclick="return confirm('¿Seguro que quieres eliminar esta ruta?');">
                                Eliminar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>