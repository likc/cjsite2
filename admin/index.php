<?php
// ============================================================================
// admin/index.php - VERSÃO FINAL, UNIFICADA E COMPLETA
// ============================================================================

session_start();
// error_reporting(E_ALL); // Descomente para depurar se necessário
// ini_set('display_errors', 1); // Descomente para depurar se necessário

// Configurações do banco
$db_host = 'localhost';
$db_name = 'minec761_comprejogos';
$db_user = 'minec761_comprejogos';
$db_pass = 'pr9n0xz5zxk2';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

// Sistema de login simples do admin_painel.php
$admin_password = 'admin123';
$is_logged_in = isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;

if (!$is_logged_in) {
    if (isset($_POST['password'])) {
        if ($_POST['password'] === $admin_password) {
            $_SESSION['admin_logged'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else { $login_error = "Senha incorreta!"; }
    }
    // Página de login HTML
    ?>
    <!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Admin Login</title>
    <style>
        :root { --primary: #667eea; --primary-dark: #5a6fd8; --secondary: #764ba2; --danger: #dc2626; --success: #16a34a; --warning: #d97706; --info: #2563eb; --bg-light: #f8fafc; --bg-dark: #f1f5f9; --text-light: #64748b; --text-dark: #1e293b; --purple: #7c3aed; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", system-ui, sans-serif; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); min-height: 100vh; color: var(--text-dark); }
        .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 1.5rem 2rem; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); }
        .header-content { max-width: 1600px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { color: var(--primary); font-size: 2rem; font-weight: 700; }
        .header .subtitle { color: var(--text-light); font-size: 1rem; margin-top: 0.25rem; }
        .logout-btn { background: var(--danger); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 0.5rem; }
        .logout-btn:hover { background: #b91c1c; transform: translateY(-2px); }
        .container { padding: 0 2rem 2rem; max-width: 1600px; margin: auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-number { font-size: 2.5rem; font-weight: 800; color: var(--primary); }
        .stat-label { color: var(--text-light); font-weight: 600; margin-top: 0.5rem; }
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .card-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--bg-dark); font-size: 1.25rem; font-weight: 700; background: linear-gradient(45deg, var(--primary), var(--secondary)); color: white; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 0; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--bg-dark); vertical-align: middle; white-space: nowrap; }
        th { font-size: 0.875rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; background: var(--bg-dark); }
        .actions-cell { display: flex; flex-wrap: wrap; gap: 0.5rem; min-width: 600px; }
        .action-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border: none; border-radius: 8px; cursor: pointer; color: white; font-weight: 600; font-size: 0.875rem; transition: all 0.3s; text-decoration: none; white-space: nowrap; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        .action-btn i { font-size: 1rem; }
        .btn-library { background: var(--info); }
        .btn-status { background: var(--warning); color: var(--text-dark); }
        .btn-client { background: var(--purple); }
        .btn-admin { background: var(--danger); }
        .btn-password { background: #64748b; }
        .btn-unlink { background: #0f766e; }
        .btn-delete { background: var(--text-dark); }
        .status { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-active { background: #dcfce7; color: #15803d; } 
        .status-inactive { background: #fee2e2; color: #b91c1c; }
        .role-badge { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .role-admin { background: #fecaca; color: #991b1b; } 
        .role-client { background: #dbeafe; color: #1e40af; } 
        .role-user { background: #e5e7eb; color: #4b5563; }
        .user-info { display: flex; flex-direction: column; }
        .user-email { color: var(--text-light); font-size: 0.875rem; }
        .games-count { display: inline-flex; align-items: center; gap: 0.25rem; background: var(--info); color: white; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .mac-address { font-family: 'Courier New', monospace; background: var(--bg-dark); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; color: var(--text-dark); }
        .mac-none { color: var(--text-light); font-style: italic; }
        .search-container { position: relative; display: flex; align-items: center; }
        .search-input { padding: 0.75rem 1rem 0.75rem 2.5rem; border: 2px solid var(--bg-dark); border-radius: 25px; font-size: 0.875rem; width: 300px; transition: all 0.3s; background: white; }
        .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); width: 350px; }
        .search-icon { position: absolute; left: 1rem; color: var(--text-light); z-index: 2; }
        .search-results { position: absolute; top: 100%; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); padding: 0.5rem; font-size: 0.75rem; color: var(--text-light); min-width: 200px; display: none; }
        .table-row-hidden { display: none !important; }
        .game-item-hidden { display: none !important; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--bg-dark); }
        .modal-header h2 { color: var(--primary); font-size: 1.5rem; }
        .modal-close { background: none; border: none; font-size: 2rem; cursor: pointer; color: #aaa; transition: color 0.3s; }
        .modal-close:hover { color: var(--danger); }
        .library-controls { margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
        .library-search-container { display: flex; justify-content: center; }
        .bulk-actions { padding: 1rem; background: var(--bg-light); border-radius: 8px; display: flex; gap: 1rem; justify-content: center; }
        .bulk-btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 0.5rem; }
        .bulk-select-all { background: var(--success); color: white; }
        .bulk-deselect-all { background: var(--warning); color: white; }
        .bulk-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        .game-list { max-height: 400px; overflow-y: auto; }
        .game-list label { display: flex; align-items: center; padding: 1rem; border-radius: 8px; transition: all 0.3s; cursor: pointer; margin-bottom: 0.5rem; border: 2px solid transparent; }
        .game-list label:hover { background: var(--bg-light); border-color: var(--primary); }
        .game-list input[type="checkbox"] { transform: scale(1.5); margin-right: 1rem; accent-color: var(--primary); }
        .game-info { flex: 1; }
        .game-name { font-weight: 600; color: var(--text-dark); }
        .game-appid { color: var(--text-light); font-size: 0.875rem; margin-top: 0.25rem; }
        .no-results { color: var(--text-light); text-align: center; padding: 2rem; font-style: italic; }
    </style>
	</head>
	<body>
	<div class="login-container"><h1>Painel Admin</h1><form method="POST"><input type="password" name="password" placeholder="Senha" required><button type="submit">Entrar</button><?php if(isset($login_error)): ?><p class="error"><?php echo $login_error; ?></p><?php endif; ?></form></div></body></html>
    <?php
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ============================================================================
// ROTEADOR DE AÇÕES (AJAX) - UNIFICADO
// ============================================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Ação inválida ou dados ausentes.'];
    
    try {
        if ($action === 'reset_password') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id) {
                $new_password = bin2hex(random_bytes(6));
                $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$new_password, $user_id]);
                $response = ['success' => true, 'new_password' => $new_password];
            }
        } elseif ($action === 'get_user_games') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id) {
                $todos_jogos = $pdo->query("SELECT appid, nome FROM jogos WHERE ativo=1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
                $stmt_owned = $pdo->prepare("SELECT jogo_appid FROM usuario_jogos WHERE usuario_id = ?");
                $stmt_owned->execute([$user_id]);
                $jogos_do_usuario_ids = $stmt_owned->fetchAll(PDO::FETCH_COLUMN, 0);
                foreach ($todos_jogos as &$jogo) { $jogo['possui'] = in_array($jogo['appid'], $jogos_do_usuario_ids); }
                $response = ['success' => true, 'games' => $todos_jogos];
            }
        } elseif ($action === 'toggle_game_access') {
             $user_id = (int)($_POST['user_id'] ?? 0);
             $jogo_appid = (int)($_POST['jogo_appid'] ?? 0);
             $status = $_POST['status'] ?? '';
             if($user_id && $jogo_appid){
                if ($status === 'grant') { $pdo->prepare("INSERT IGNORE INTO usuario_jogos (usuario_id, jogo_appid) VALUES (?, ?)")->execute([$user_id, $jogo_appid]); }
                elseif ($status === 'revoke') { $pdo->prepare("DELETE FROM usuario_jogos WHERE usuario_id = ? AND jogo_appid = ?")->execute([$user_id, $jogo_appid]); }
                $response = ['success' => true];
             }
        } elseif ($action === 'bulk_toggle_games') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $grant_all = $_POST['grant_all'] === 'true';
            if($user_id){
                if ($grant_all) { $pdo->prepare("INSERT IGNORE INTO usuario_jogos (usuario_id, jogo_appid) SELECT ?, appid FROM jogos WHERE ativo=1")->execute([$user_id]); }
                else { $pdo->prepare("DELETE FROM usuario_jogos WHERE usuario_id = ?")->execute([$user_id]); }
                $response = ['success' => true];
            }
        }
        elseif ($action === 'catalog_game') {
            $log = [];
            $game_name = trim($_POST['nome_jogo'] ?? '');
            if (empty($game_name) || !isset($_FILES['arquivo_zip']) || $_FILES['arquivo_zip']['error'] !== UPLOAD_ERR_OK) {
                $response = ['success' => false, 'log' => ["❌ ERRO: Nome do jogo e arquivo .zip são obrigatórios."]];
            } else {
                $log[] = "✅ Arquivo recebido...";
                $base_jogos_path = dirname(__DIR__) . '/jogos/';
                if (!is_dir($base_jogos_path)) mkdir($base_jogos_path, 0755, true);
                $game_folder_name = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $game_name);
                $game_folder_name = preg_replace('/\s+/', '_', $game_folder_name);
                $extract_path = $base_jogos_path . $game_folder_name;
                $log[] = "⚙️ Descompactando...";
                $zip = new ZipArchive;
                if ($zip->open($_FILES['arquivo_zip']['tmp_name']) === TRUE) {
                    $zip->extractTo($extract_path); $zip->close();
                    $log[] = "✅ Jogo descompactado.";
                    $log[] = "🔎 Analisando arquivos...";
                    $manifest_files = []; $lua_files = []; $appid = null;
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extract_path, FilesystemIterator::SKIP_DOTS));
                    foreach ($iterator as $file) {
                        if (!$file->isFile()) continue;
                        $filename = $file->getFilename();
                        if (pathinfo($filename, PATHINFO_EXTENSION) == 'manifest') { $manifest_files[] = $filename; }
                        elseif (pathinfo($filename, PATHINFO_EXTENSION) == 'lua') { $lua_files[] = $filename; }
                    }
                    $source_file = !empty($manifest_files) ? $manifest_files[0] : (!empty($lua_files) ? $lua_files[0] : null);
                    if ($source_file && preg_match('/^(\d+)/', $source_file, $matches)) { $appid = $matches[1]; }
                    if (!$appid) {
                        $log[] = "❌ ERRO FATAL: AppID não encontrado.";
                        $response = ['success' => false, 'log' => $log];
                    } else {
                        $log[] = "➡️ AppID encontrado: $appid";
                        $pdo->beginTransaction();
                        try {
                            $stmt = $pdo->prepare("INSERT INTO jogos (appid, nome, nome_pasta, arquivo_lua, ativo) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE nome=VALUES(nome), nome_pasta=VALUES(nome_pasta), arquivo_lua=VALUES(arquivo_lua)");
                            $stmt->execute([$appid, $game_name, $game_folder_name, !empty($lua_files) ? $lua_files[0] : null]);
                            $log[] = "✅ Jogo principal inserido/atualizado.";
                            if (!empty($manifest_files)) {
                                $pdo->prepare("DELETE FROM manifests_jogos WHERE jogo_appid = ?")->execute([$appid]);
                                $stmt_manifest = $pdo->prepare("INSERT INTO manifests_jogos (jogo_appid, nome_arquivo) VALUES (?, ?)");
                                foreach ($manifest_files as $manifest) { $stmt_manifest->execute([$appid, $manifest]); }
                                $log[] = "✅ " . count($manifest_files) . " manifest(s) catalogado(s).";
                            }
                            $pdo->commit();
                            $log[] = "🎉 PROCESSO CONCLUÍDO!";
                            $response = ['success' => true, 'log' => $log];
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $log[] = "❌ ERRO DE BANCO DE DADOS: " . $e->getMessage();
                            $response = ['success' => false, 'log' => $log];
                        }
                    }
                } else {
                    $log[] = "❌ ERRO: Falha ao abrir o .zip.";
                    $response = ['success' => false, 'log' => $log];
                }
            }
        }
    } catch (Exception $e) {
        // Se a resposta ainda não foi definida, preenche com o erro
        if (!isset($response['log'])) {
            $response['message'] = 'Erro: ' . $e->getMessage();
        }
    }
    echo json_encode($response);
    exit;
}

// Ações de formulário POST que recarregam a página
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id) {
        $action = $_POST['form_action'];
        try {
            if ($action === 'toggle_user_status') { $pdo->prepare("UPDATE usuarios SET ativo = NOT ativo WHERE id = ?")->execute([$user_id]); }
            if ($action === 'toggle_client_status') { $pdo->prepare("UPDATE usuarios SET is_client = NOT COALESCE(is_client, 0) WHERE id = ?")->execute([$user_id]); }
            if ($action === 'toggle_admin_status') { $pdo->prepare("UPDATE usuarios SET is_admin = NOT COALESCE(is_admin, 0) WHERE id = ?")->execute([$user_id]); }
            if ($action === 'unlink_mac') { $pdo->prepare("DELETE FROM user_sessions WHERE usuario_id = ?")->execute([$user_id]); }
            if ($action === 'delete_user') { $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$user_id]); }
        } catch (Exception $e) { /* Erro silencioso */ }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '#users'); 
    exit;
}

// Buscar dados para renderizar o painel
try {
    $stats = [
        'ganhos_totais' => $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE status = 'aprovado'")->fetchColumn(),
        'vendas_realizadas' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status = 'aprovado'")->fetchColumn(),
        'total_de_jogos' => $pdo->query("SELECT COUNT(*) FROM jogos")->fetchColumn(),
        'total_de_usuarios' => $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
        'clientes_ativos' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1 AND is_client = 1")->fetchColumn(),
        'jogos_adquiridos' => $pdo->query("SELECT COUNT(*) FROM usuario_jogos")->fetchColumn(),
    ];
    $users = $pdo->query("SELECT u.id, u.login, u.email, u.ativo, u.is_client, u.is_admin, us.mac_address, (SELECT COUNT(*) FROM usuario_jogos uj WHERE uj.usuario_id = u.id) as total_games FROM usuarios u LEFT JOIN user_sessions us ON u.id = us.usuario_id ORDER BY u.id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erro fatal ao carregar dados do painel: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COMPREJOGOS - Painel Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --danger: #dc2626; --success: #16a34a; --warning: #d97706; --info: #2563eb; --bg-light: #f8fafc; --bg-dark: #f1f5f9; --text-light: #64748b; --text-dark: #1e293b; --purple: #7c3aed; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", system-ui, sans-serif; background: var(--bg-dark); color: var(--text-dark); }
        .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .sidebar { background: var(--dark); color: #e2e8f0; display:flex; flex-direction:column; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid #334155; }
        .sidebar-header h2 { font-size: 1.5rem; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 1rem 1.5rem; color: #cbd5e1; text-decoration: none; transition: all 0.2s; border-left: 4px solid transparent; }
        .sidebar-nav a:hover { background: #334155; color: white; }
        .sidebar-nav a.active { background: var(--primary); color: white; border-left-color: var(--warning); }
        .main-content { padding: 2rem; overflow-y: auto; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); font-size: 1.25rem; font-weight: 600; }
        .card-body { padding: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .actions-cell { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .action-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border: none; border-radius: 8px; cursor: pointer; color: white; font-weight: 600; font-size: 0.875rem; transition: all 0.3s; }
        .action-btn:hover { transform: translateY(-2px); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 2px solid var(--border); border-radius: 8px; }
        #catalogerLog { background: var(--dark); color: #e5e7eb; font-family: monospace; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; height: 300px; overflow-y: auto; white-space: pre-wrap; display: none; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header"><h2><i class="fas fa-user-shield"></i> Admin</h2></div>
            <nav><ul class="sidebar-nav">
                <li><a href="#dashboard" class="nav-link active" data-tab="dashboard"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="#users" class="nav-link" data-tab="users"><i class="fas fa-users-cog"></i> Usuários</a></li>
                <li><a href="#cataloger" class="nav-link" data-tab="cataloger"><i class="fas fa-upload"></i> Catalogador</a></li>
                <li><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
            </ul></nav>
        </aside>

        <main class="main-content">
            <div id="dashboard" class="tab-content active">
                <div class="stats-grid">
                    <?php foreach($stats as $key => $value): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo ($key == 'ganhos_totais' ? 'R$ ' : '') . number_format($value, $key == 'ganhos_totais' ? 2 : 0, ',', '.'); ?></div>
                            <div><?php echo ucwords(str_replace('_', ' ', $key)); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="users" class="tab-content">
                <div class="card">
                    <div class="card-header">Gerenciamento de Usuários</div>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead><tr><th>Usuário</th><th>Status</th><th>Funções</th><th>Ações</th></tr></thead>
<tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <div><?php echo htmlspecialchars($user['login']); ?></div>
                                        <small style="color: #64748b;"><?php echo htmlspecialchars($user['email']); ?></small>
                                    </td>
                                    <td>
                                        <span style="padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background-color: <?php echo $user['ativo'] ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $user['ativo'] ? '#15803d' : '#b91c1c'; ?>;">
                                            <?php echo $user['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($user['is_admin']): ?>
                                            <span style="padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background-color: #fecaca; color: #991b1b;">Admin</span>
                                        <?php endif; ?>
                                        <?php if($user['is_client']): ?>
                                            <span style="padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background-color: #dbeafe; color: #1e40af;">Cliente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="action-btn" style="background:var(--info);" onclick="openLibraryModal(this)" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['login']); ?>"><i class="fas fa-gamepad"></i> Biblioteca</button>
                                        
                                        <form method="POST" style="display:inline;"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>"><button type="submit" name="form_action" value="toggle_user_status" class="action-btn" style="background:var(--warning); color:var(--dark);"><i class="fas fa-power-off"></i> <?php echo $user['ativo'] ? 'Inativar' : 'Ativar'; ?></button></form>
                                        
                                        <form method="POST" style="display:inline;"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>"><button type="submit" name="form_action" value="toggle_client_status" class="action-btn" style="background:var(--success);"><i class="fas fa-crown"></i> <?php echo $user['is_client'] ? 'Remover Cliente' : 'Tornar Cliente'; ?></button></form>
                                        
                                        <form method="POST" style="display:inline;"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>"><button type="submit" name="form_action" value="toggle_admin_status" class="action-btn" style="background:var(--danger);"><i class="fas fa-user-shield"></i> <?php echo $user['is_admin'] ? 'Rebaixar' : 'Promover Admin'; ?></button></form>

                                        <button class="action-btn reset-password-btn" style="background:var(--text-light); color:var(--dark);" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['login']); ?>"><i class="fas fa-key"></i> Resetar Senha</button>
                                        
                                        <form method="POST" style="display:inline;"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>"><button type="submit" name="form_action" value="unlink_mac" class="action-btn" style="background:#0f766e;"><i class="fas fa-unlink"></i> Desvincular</button></form>
                                        
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('EXCLUIR USUÁRIO PERMANENTEMENTE?')"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>"><button type="submit" name="form_action" value="delete_user" class="action-btn" style="background:var(--dark);"><i class="fas fa-trash"></i> Excluir</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="cataloger" class="tab-content">
                <div class="card">
                    <div class="card-header">Catalogador de Jogos</div>
                    <div class="card-body">
                        <form id="catalogerForm" enctype="multipart/form-data">
                            <div class="form-group"><label>Nome do Jogo</label><input type="text" name="nome_jogo" class="form-control" required></div>
                            <div class="form-group"><label>Arquivo .zip</label><input type="file" name="arquivo_zip" class="form-control" accept=".zip" required></div>
                            <button type="submit" id="catalogerSubmitBtn" class="btn btn-primary"><i class="fas fa-upload"></i> Enviar e Catalogar</button>
                        </form>
                        <div id="catalogerLog"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <div id="gamesModal" style="display:none; position:fixed; z-index:1001; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; display:none;">
        <div style="background:white; padding:2rem; border-radius:12px; width:90%; max-width:700px; max-height:80vh; display:flex; flex-direction:column;">
            <h3 id="modalUserName">Biblioteca</h3><hr style="margin:1rem 0;">
            <div id="modalGameList" style="flex-grow:1; overflow-y:auto;"></div>
            <button class="btn" onclick="document.getElementById('gamesModal').style.display='none'" style="margin-top:1rem; background:var(--dark); color:white;">Fechar</button>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lógica de navegação por abas
        const navLinks = document.querySelectorAll('.nav-link');
        const tabContents = document.querySelectorAll('.tab-content');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('?')) return;
                e.preventDefault();
                const targetId = this.getAttribute('href');
                navLinks.forEach(l => l.classList.remove('active'));
                tabContents.forEach(t => t.style.display = 'none');
                this.classList.add('active');
                document.querySelector(targetId).style.display = 'block';
                window.location.hash = targetId;
            });
        });
        const currentHash = window.location.hash || '#dashboard';
        const activeLink = document.querySelector(`.nav-link[href="${currentHash}"]`);
        if(activeLink) activeLink.click();

        // Lógica da Biblioteca
        const gamesModal = document.getElementById('gamesModal');
        let currentUserId = null;
        window.openLibraryModal = function(button) {
            currentUserId = button.dataset.userid;
            document.getElementById('modalUserName').textContent = `Biblioteca de ${button.dataset.username}`;
            const gameList = document.getElementById('modalGameList');
            gameList.innerHTML = 'Carregando...';
            gamesModal.style.display = 'flex';
            
            const formData = new FormData();
            formData.append('action', 'get_user_games');
            formData.append('user_id', currentUserId);

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                gameList.innerHTML = '';
                if(data.success && data.games) {
                    data.games.forEach(game => {
                        gameList.innerHTML += `<label style="display:block; margin:0.5rem 0;"><input type="checkbox" onchange="toggleGameAccess(${game.appid}, this.checked)" ${game.possui ? 'checked' : ''}> ${game.nome}</label>`;
                    });
                } else { gameList.innerHTML = 'Erro ao carregar jogos.'; }
            }).catch(err => { gameList.innerHTML = 'Erro de comunicação.'; });
        }
        window.toggleGameAccess = function(gameAppId, hasAccess) {
            const formData = new FormData();
            formData.append('action', 'toggle_game_access');
            formData.append('user_id', currentUserId);
            formData.append('jogo_appid', gameAppId);
            formData.append('status', hasAccess ? 'grant' : 'revoke');
            fetch('', { method: 'POST', body: formData });
        }
        
        // Lógica do Catalogador
        const catalogerForm = document.getElementById('catalogerForm');
        const catalogerLog = document.getElementById('catalogerLog');
        const catalogerSubmitBtn = document.getElementById('catalogerSubmitBtn');
        if (catalogerForm) {
            catalogerForm.addEventListener('submit', function(e) {
                e.preventDefault();
                catalogerLog.innerHTML = '';
                catalogerLog.style.display = 'block';
                catalogerSubmitBtn.disabled = true;
                catalogerSubmitBtn.innerHTML = 'Processando...';
                const formData = new FormData(this);
                formData.append('action', 'catalog_game');
                fetch('', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.log) { data.log.forEach(line => { catalogerLog.innerHTML += `<div>${line}</div>`; }); }
                    if (!data.success && data.message) { catalogerLog.innerHTML += `<div>❌ ${data.message}</div>`; }
                }).catch(err => {
                    catalogerLog.innerHTML = `<div>❌ Erro de comunicação.</div>`;
                }).finally(() => {
                    catalogerSubmitBtn.disabled = false;
                    catalogerSubmitBtn.innerHTML = 'Enviar e Catalogar';
                });
            });
        }
        
        // Lógica do Reset de Senha
        document.querySelectorAll('.reset-password-btn').forEach(btn => {
            btn.addEventListener('click', function(){
                if(confirm(`Resetar a senha de ${this.dataset.username}?`)){
                    const formData = new FormData();
                    formData.append('action', 'reset_password');
                    formData.append('user_id', this.dataset.userid);
                    fetch('', { method: 'POST', body: formData })
                    .then(res => res.json()).then(data => {
                        if(data.success){ alert(`Nova senha: ${data.new_password}`); }
                        else { alert('Erro ao resetar senha.'); }
                    });
                }
            });
        });

    });
</script>
</body>
</html>