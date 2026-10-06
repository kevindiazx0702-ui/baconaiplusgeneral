<?php
// manage_users.php - DISEÑO COMMAND CENTER + SEGURIDAD
define('SECURE_ACCESS', true);
require_once 'db.php';
session_start();

// ================================================================
// 🔴 EL GUARDIA DE SEGURIDAD (Esto hace que "Expulsar" funcione)
// ================================================================
if (isset($_SESSION['admin_username'])) {
    $sessionFile = 'storage_sessions';
    $sesionesActivas = loadData($sessionFile);
    
    // Reconstruimos la ID única de este usuario
    $miID = $_SESSION['admin_username'] . '|' . $_SERVER['REMOTE_ADDR'];
    
    // VERIFICACIÓN: ¿Estoy en la lista?
    if (!isset($sesionesActivas[$miID])) {
        // ¡NO ESTOY! Me han expulsado.
        session_destroy(); // Destruir mi cookie
        header('Location: login.php?error=expulsado'); // Sacarme al login
        exit;
    }
    
    // (Opcional) Actualizar mi tiempo para no aparecer "Ausente"
    if (time() - $sesionesActivas[$miID] > 60) {
        $sesionesActivas[$miID] = time();
        saveData($sessionFile, $sesionesActivas);
    }
}
// ARCHIVOS SEGUROS
$usersFile = 'storage_users';
$sessionsFile = 'storage_sessions';
$colorsFile = 'storage_colors';

$COLORES_SISTEMA = [
    '#FF0000', '#00FF00', '#0066FF', '#FFD700', '#FF00FF', 
    '#00FFFF', '#FF4500', '#9400D3', '#00FA9A', '#DC143C'
];

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_username'] !== 'admin') {
    header('Location: login.php'); exit;
}

$activeTab = $_GET['tab'] ?? 'manage-users';

// LÓGICA DE BACKEND (Exactamente la segura que ya tenías)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $users = loadData($usersFile);
    $colors = loadData($colorsFile);
    $sessions = loadData($sessionsFile);

    switch ($action) {
        case 'kill_session_ajax':
            header('Content-Type: application/json');
            $sessId = $_POST['session_id'];
            foreach ($sessions as $key => $time) {
                if (md5($key) === $sessId) { unset($sessions[$key]); break; }
            }
            saveData($sessionsFile, $sessions);
            echo json_encode(['success' => true]); exit;

        case 'add_user':
            $u = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['new_username']);
            $c = $_POST['new_color'];
            if($u && $c && !isset($users[$u])) {
                $users[$u] = 'nopassword'; $colors[$u] = $c;
                saveData($usersFile, $users); saveData($colorsFile, $colors);
                $_SESSION['msg'] = ['ok', "Usuario $u creado"];
            } else { $_SESSION['msg'] = ['err', "Error al crear usuario"]; }
            break;

        case 'delete_user':
            $u = $_POST['username'];
            if ($u !== 'admin' && isset($users[$u])) {
                unset($users[$u], $colors[$u]);
                saveData($usersFile, $users); saveData($colorsFile, $colors);
                $_SESSION['msg'] = ['ok', "Usuario eliminado"];
            }
            break;

        case 'set_password':
            $u = $_POST['username']; $p = $_POST['password'];
            if($u !== 'admin' && $p) {
                $users[$u] = password_hash($p, PASSWORD_DEFAULT);
                saveData($usersFile, $users);
                $_SESSION['msg'] = ['ok', "Contraseña actualizada"];
            }
            break;
            
        case 'change_admin_pass':
            $cur = $_POST['current_password']; $new = $_POST['new_password'];
            if(password_verify($cur, $users['admin'])) {
                $users['admin'] = password_hash($new, PASSWORD_DEFAULT);
                saveData($usersFile, $users);
                $_SESSION['msg'] = ['ok', "Clave de Admin actualizada"];
            } else { $_SESSION['msg'] = ['err', "Clave actual incorrecta"]; }
            break;
    }
    header('Location: manage_users.php?tab=' . $activeTab); exit;
}

function getActivePanelUsers() {
    global $sessionsFile;
    $sessions = loadData($sessionsFile);
    $active = [];
    foreach ($sessions as $key => $ts) {
        if (time() - $ts > 1200) continue;
        $parts = explode('|', $key);
        if(count($parts) > 1) {
            $active[] = [
                'username' => $parts[0], 'ip' => $parts[1],
                'status' => (time() - $ts <= 120) ? 'online' : 'away',
                'id' => md5($key)
            ];
        }
    }
    return $active;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Sistema</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root { --bg: #0f172a; --surface: #1e293b; --border: #334155; --text: #e2e8f0; --accent: #3b82f6; --danger: #ef4444; --success: #10b981; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; padding: 30px; margin: 0; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .title { font-size: 1.5rem; font-weight: 300; letter-spacing: 1px; color: white; display:flex; align-items:center; gap:10px; }
        .btn { padding: 8px 16px; border-radius: 6px; cursor: pointer; border: none; font-weight: 600; color: white; transition: 0.2s; display:inline-flex; align-items:center; gap:8px; text-decoration: none; font-size:0.9rem;}
        .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-back { background: #475569; }
        .btn-primary { background: var(--accent); }
        .btn-danger { background: var(--danger); }
        .btn-success { background: var(--success); }

        /* TABS */
        .tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        .tab { padding: 10px 20px; background: transparent; border: 1px solid transparent; border-radius: 8px; cursor: pointer; color: #94a3b8; transition: 0.2s; }
        .tab:hover { color: white; background: rgba(255,255,255,0.05); }
        .tab.active { background: rgba(59, 130, 246, 0.15); color: var(--accent); border-color: rgba(59, 130, 246, 0.3); font-weight:bold; }
        .tab-content { display: none; animation: fadeIn 0.3s ease-out; }
        .tab-content.active { display: block; }

        /* CARDS */
        .grid { display: grid; grid-template-columns: 350px 1fr; gap: 25px; }
        .card { background: var(--surface); padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .card h4 { margin: 0 0 20px 0; color: white; font-size: 1.1rem; border-bottom: 1px solid var(--border); padding-bottom: 10px; }

        /* FORMULARIOS */
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        input { width: 100%; padding: 12px; background: var(--bg); border: 1px solid var(--border); color: white; border-radius: 6px; box-sizing: border-box; outline: none; transition: 0.2s; }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }

        /* TABLAS */
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { text-align: left; color: #94a3b8; padding: 12px; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        /* COLORS */
        .color-selector { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .c-opt { width: 24px; height: 24px; border-radius: 50%; cursor: pointer; transition: 0.2s; border: 2px solid transparent; }
        .c-opt:hover { transform: scale(1.2); }
        .c-opt.selected { border-color: white; transform: scale(1.1); box-shadow: 0 0 10px white; }
        .c-blocked { opacity: 0.2; pointer-events: none; }

        /* TOAST */
        .toast { position: fixed; top: 20px; right: 20px; padding: 15px 25px; border-radius: 8px; color: white; font-weight: 500; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); transform: translateX(120%); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 100; }
        .toast.show { transform: translateX(0); }
        .t-ok { background: var(--success); }
        .t-err { background: var(--danger); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <div class="header">
        <div class="title"><i class="fas fa-terminal" style="color:var(--accent)"></i> ADMIN CONSOLE</div>
        <a href="admin.php" class="btn btn-back"><i class="fas fa-chevron-left"></i> Volver al Panel</a>
    </div>

    <div class="tabs">
        <div class="tab <?php echo $activeTab=='manage-users'?'active':''; ?>" onclick="switchTab('manage-users')"><i class="fas fa-users"></i> Usuarios</div>
        <div class="tab <?php echo $activeTab=='active-sessions'?'active':''; ?>" onclick="switchTab('active-sessions')"><i class="fas fa-network-wired"></i> Sesiones</div>
        <div class="tab <?php echo $activeTab=='admin-security'?'active':''; ?>" onclick="switchTab('admin-security')"><i class="fas fa-shield-alt"></i> Seguridad</div>
    </div>

    <div id="manage-users" class="tab-content <?php echo $activeTab=='manage-users'?'active':''; ?>">
        <div class="grid">
            <div class="card" style="height: fit-content;">
                <h4><i class="fas fa-user-plus"></i> Crear Usuario</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="new_color" id="selectedColor">
                    <div class="form-group">
                        <label>Nombre de Usuario</label>
                        <input type="text" name="new_username" required placeholder="Ej: operador1" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Color Distintivo</label>
                        <div class="color-selector">
                            <?php 
                            $assigned = loadData($colorsFile); $taken = array_values($assigned);
                            foreach($COLORES_SISTEMA as $hex): 
                                $isTaken = in_array($hex, $taken);
                            ?>
                            <div class="c-opt <?php echo $isTaken?'c-blocked':''; ?>" 
                                 style="background:<?php echo $hex; ?>" 
                                 onclick="selColor(this, '<?php echo $hex; ?>')"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">Crear Usuario</button>
                </form>
            </div>

            <div class="card">
                <h4><i class="fas fa-list"></i> Lista de Usuarios</h4>
                <table>
                    <thead><tr><th>Usuario</th><th>Color</th><th>Contraseña</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php $users=loadData($usersFile); $cols=loadData($colorsFile); 
                        foreach($users as $u=>$p): if($u==='admin') continue; ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="width:10px; height:10px; border-radius:50%; background:<?php echo $cols[$u]??'#fff'; ?>; box-shadow:0 0 5px <?php echo $cols[$u]??'#fff'; ?>"></span>
                                    <?php echo $u; ?>
                                </div>
                            </td>
                            <td><span style="color:#94a3b8; font-family:monospace"><?php echo $cols[$u]??'-'; ?></span></td>
                            <td>
                                <form method="POST" style="display:flex; gap:5px; margin:0;">
                                    <input type="hidden" name="action" value="set_password">
                                    <input type="hidden" name="username" value="<?php echo $u; ?>">
                                    <input type="password" name="password" placeholder="Nueva clave" style="padding:6px; width:120px; font-size:0.8rem;">
                                    <button class="btn btn-success" style="padding:6px 10px;" title="Guardar"><i class="fas fa-save"></i></button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="username" value="<?php echo $u; ?>">
                                    <button class="btn btn-danger" onclick="return confirm('¿Eliminar?')" style="padding:6px 10px;"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="active-sessions" class="tab-content <?php echo $activeTab=='active-sessions'?'active':''; ?>">
        <div class="card">
            <h4><i class="fas fa-plug"></i> Conexiones Activas</h4>
            <table>
                <thead><tr><th>Usuario</th><th>IP</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody id="sessBody">
                    <?php $active = getActivePanelUsers(); 
                    if(!$active): echo '<tr><td colspan="4" style="text-align:center; padding:30px; color:#64748b;">No hay sesiones activas</td></tr>'; 
                    else: foreach($active as $s): ?>
                    <tr id="row-<?php echo $s['id']; ?>">
                        <td><span style="font-weight:bold; color:white"><?php echo $s['username']; ?></span></td>
                        <td><span style="background:#334155; padding:2px 6px; border-radius:4px; font-size:0.85rem;"><?php echo $s['ip']; ?></span></td>
                        <td>
                            <?php if($s['status']=='online'): ?>
                                <span style="color:var(--success)"><i class="fas fa-circle" style="font-size:8px"></i> Online</span>
                            <?php else: ?>
                                <span style="color:#f59e0b"><i class="fas fa-clock" style="font-size:8px"></i> Ausente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($s['username']!=='admin'): ?>
                                <button class="btn btn-danger" onclick="kick('<?php echo $s['id']; ?>')" style="padding:4px 10px; font-size:0.8rem;">Expulsar</button>
                            <?php else: ?>
                                <span style="color:#64748b; font-size:0.8rem;">(Admin)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="admin-security" class="tab-content <?php echo $activeTab=='admin-security'?'active':''; ?>">
        <div class="card" style="max-width:400px; margin:0 auto;">
            <h4><i class="fas fa-key"></i> Cambiar Clave Maestra</h4>
            <form method="POST">
                <input type="hidden" name="action" value="change_admin_pass">
                <div class="form-group">
                    <label>Contraseña Actual</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>Nueva Contraseña</label>
                    <input type="password" name="new_password" required>
                </div>
                <button class="btn btn-primary" style="width:100%">Actualizar Seguridad</button>
            </form>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        // Mensajes PHP
        <?php if(isset($_SESSION['msg'])): ?>
            showToast('<?php echo $_SESSION['msg'][1]; ?>', '<?php echo $_SESSION['msg'][0]=='ok'?'t-ok':'t-err'; ?>');
            <?php unset($_SESSION['msg']); ?>
        <?php endif; ?>

        function switchTab(id) {
            $('.tab').removeClass('active');
            $('.tab-content').removeClass('active');
            $('.tab[onclick="switchTab(\''+id+'\')"]').addClass('active');
            $('#'+id).addClass('active');
            window.history.pushState({}, '', '?tab='+id);
        }

        function selColor(el, c) {
            $('.c-opt').removeClass('selected');
            $(el).addClass('selected');
            $('#selectedColor').val(c);
        }

        function kick(id) {
            if(!confirm('¿Desconectar usuario?')) return;
            $.post('manage_users.php', {action:'kill_session_ajax', session_id:id}, function(r){
                if(r.success) { $('#row-'+id).fadeOut(); showToast('Usuario expulsado', 't-ok'); }
            }, 'json');
        }

        function showToast(msg, cls) {
            const t = $('#toast');
            t.html((cls=='t-ok'?'<i class="fas fa-check"></i> ':'<i class="fas fa-times"></i> ') + msg);
            t.removeClass('t-ok t-err').addClass(cls + ' show');
            setTimeout(() => t.removeClass('show'), 3000);
        }
    </script>
</body>
</html>