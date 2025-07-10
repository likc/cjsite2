<?php
// ============================================================================
// admin/index.php - Painel Administrativo COMPLETO E CORRIGIDO
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

// Verificar se está logado e é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../login.php'); // Assumindo que o login está um nível acima
    exit;
}

$admin = $pdo->query("SELECT * FROM usuarios WHERE id = {$_SESSION['user_id']}")->fetch(PDO::FETCH_ASSOC);
if (!$admin) { session_destroy(); header('Location: ../login.php'); exit; }


// ============================================================================
// ROTEADOR DE AÇÕES (AJAX)
// ============================================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Ação inválida ou dados ausentes.'];
    
    try {
        switch ($action) {
            case 'add_game':
                $nome = trim($_POST['nome'] ?? '');
                $appid = trim($_POST['appid'] ?? '');
                // ... outros campos ...
                if (!empty($nome) && !empty($appid)) {
                    $stmt = $pdo->prepare("INSERT INTO jogos (nome, appid, descricao, preco, categoria, desenvolvedor, imagem, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$nome, $appid, $_POST['descricao'], (float)$_POST['preco'], $_POST['categoria'], $_POST['desenvolvedor'], $_POST['imagem']]);
                    $response = ['success' => true, 'message' => 'Jogo adicionado!'];
                }
                break;
                
            case 'update_game':
                $appid = (int)($_POST['appid'] ?? 0);
                // ... outros campos ...
                if ($appid) {
                    $stmt = $pdo->prepare("UPDATE jogos SET nome = ?, preco = ?, descricao = ?, categoria = ?, desenvolvedor = ?, imagem = ?, ativo = ? WHERE appid = ?");
                    $stmt->execute([$_POST['nome'], (float)$_POST['preco'], $_POST['descricao'], $_POST['categoria'], $_POST['desenvolvedor'], $_POST['imagem'], (int)$_POST['ativo'], $appid]);
                    $response = ['success' => true, 'message' => 'Jogo atualizado!'];
                }
                break;
                
            case 'delete_game':
                $appid = (int)($_POST['appid'] ?? 0);
                if ($appid) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_jogos WHERE jogo_appid = ?");
                    $stmt->execute([$appid]);
                    if ($stmt->fetchColumn() > 0) {
                        $response['message'] = 'Não é possível excluir: jogo está na biblioteca de usuários.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM jogos WHERE appid = ?");
                        $stmt->execute([$appid]);
                        $response = ['success' => true, 'message' => 'Jogo excluído!'];
                    }
                }
                break;
            
            // Lógica do Catalogador de Jogos
            case 'catalog_game':
                $log = [];
                $game_name = trim($_POST['nome_jogo'] ?? '');

                if (empty($game_name) || !isset($_FILES['arquivo_zip']) || $_FILES['arquivo_zip']['error'] !== UPLOAD_ERR_OK) {
                    $response['message'] = "Nome do jogo e arquivo .zip são obrigatórios.";
                    break;
                }

                $log[] = "✅ Arquivo recebido. Iniciando processo...";
                
                $base_jogos_path = dirname(__DIR__) . '/jogos/'; // Caminho relativo ao script
                if (!is_dir($base_jogos_path)) mkdir($base_jogos_path, 0755, true);

                $game_folder_name = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $game_name);
                $game_folder_name = preg_replace('/\s+/', '_', $game_folder_name);
                $extract_path = $base_jogos_path . $game_folder_name;

                $log[] = "⚙️ Descompactando em: " . $extract_path;
                $zip = new ZipArchive;
                if ($zip->open($_FILES['arquivo_zip']['tmp_name']) === TRUE) {
                    if (!is_dir($extract_path)) { mkdir($extract_path, 0755, true); }
                    $zip->extractTo($extract_path);
                    $zip->close();
                    $log[] = "✅ Jogo descompactado.";
                } else {
                    $log[] = "❌ ERRO: Falha ao abrir o .zip.";
                    $response = ['success' => false, 'log' => $log];
                    break;
                }

                $log[] = "🔎 Analisando arquivos...";
                $manifest_files = [];
                $lua_files = [];
                $appid = null;
                
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extract_path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS));
                foreach ($iterator as $file) {
                    if (!$file->isFile()) continue;
                    $filename = $file->getFilename();
                    if (pathinfo($filename, PATHINFO_EXTENSION) == 'manifest') { $manifest_files[] = $filename; }
                    elseif (pathinfo($filename, PATHINFO_EXTENSION) == 'lua') { $lua_files[] = $filename; }
                }
                
                $source_file = !empty($manifest_files) ? $manifest_files[0] : (!empty($lua_files) ? $lua_files[0] : null);
                if ($source_file && preg_match('/^(\d+)/', $source_file, $matches)) {
                    $appid = $matches[1];
                }

                if (!$appid) {
                    $log[] = "❌ ERRO FATAL: AppID não encontrado. Jogo não catalogado.";
                    $response = ['success' => false, 'log' => $log];
                    break;
                }
                $log[] = "➡️ AppID encontrado: $appid";

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO jogos (appid, nome, nome_pasta, arquivo_lua, ativo) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE nome=VALUES(nome), nome_pasta=VALUES(nome_pasta), arquivo_lua=VALUES(arquivo_lua)");
                    $stmt->execute([$appid, $game_name, $game_folder_name, !empty($lua_files) ? $lua_files[0] : null]);
                    $log[] = "✅ Jogo principal (AppID: $appid) inserido/atualizado.";
                    
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
                break;
            
            // ... (outros cases para gerenciar usuários, etc.)
        }
    } catch (Exception $e) {
        $response['message'] = 'Erro: ' . $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

// Buscar dados para o painel
$stats = [];
$users = [];
$games = [];
$recent_orders = [];
try {
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
        'active_users' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1")->fetchColumn(),
        'total_games' => $pdo->query("SELECT COUNT(*) FROM jogos")->fetchColumn(),
        'active_games' => $pdo->query("SELECT COUNT(*) FROM jogos WHERE ativo = 1")->fetchColumn(),
        'total_sales' => $pdo->query("SELECT COUNT(*) FROM usuario_jogos")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE status = 'aprovado'")->fetchColumn(),
    ];
    $users = $pdo->query("SELECT id, login, email, ativo, is_client, is_admin, created_at FROM usuarios ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $games = $pdo->query("SELECT * FROM jogos ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $recent_orders = $pdo->query("SELECT p.*, u.login FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id ORDER BY p.created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Tratar erros de busca se necessário
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - COMPREJOGOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #667eea; --primary-dark: #5a6fd8; --secondary: #764ba2; --success: #10b981; --warning: #f59e0b; --error: #ef4444; --dark: #1f2937; --light: #f8fafc; --border: #e5e7eb; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--light); color: var(--dark); margin:0; }
        .admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; }
        .sidebar { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 2rem 0; }
        .sidebar-header { padding: 0 2rem; margin-bottom: 2rem; }
        .sidebar-nav { list-style: none; padding:0; margin:0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 2rem; color: white; text-decoration: none; transition: background 0.3s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255, 255, 255, 0.1); }
        .main-content { padding: 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-number { font-size: 2rem; font-weight: bold; color: var(--primary); }
        .section { background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.5rem; font-weight: bold; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-primary { background: var(--primary); color: white; }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 2px solid var(--border); border-radius: 8px; }
        #catalogerLog { background-color: #2c2c2c; color: #f1f1f1; font-family: monospace; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; height: 300px; overflow-y: auto; white-space: pre-wrap; display: none; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-gamepad"></i> COMPREJOGOS</h2>
                <p>Admin</p>
            </div>
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="#dashboard" class="nav-link active" data-tab="dashboard"><i class="fas fa-chart-bar"></i> Dashboard</a></li>
                    <li><a href="#games" class="nav-link" data-tab="games"><i class="fas fa-gamepad"></i> Gerenciar Jogos</a></li>
                    <li><a href="#cataloger" class="nav-link" data-tab="cataloger"><i class="fas fa-upload"></i> Catalogador</a></li>
                    <li><a href="#users" class="nav-link" data-tab="users"><i class="fas fa-users"></i> Gerenciar Usuários</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title" id="page-title">Dashboard</h1>
                <div>Bem-vindo, <?php echo htmlspecialchars($admin['login']); ?>!</div>
            </div>

            <div id="dashboard" class="tab-content active">
                <div class="stats-grid">
                    <?php foreach($stats as $key => $value): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo is_numeric($value) ? number_format($value, 0, ',', '.') : $value; ?></div>
                            <div class="stat-label"><?php echo ucwords(str_replace('_', ' ', $key)); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div id="games" class="tab-content">
                <div class="section">
                    <div class="section-header">
                        <h3 class="section-title">Gerenciar Jogos</h3>
                        <button class="btn btn-primary" onclick="openGameModal()"><i class="fas fa-plus"></i> Adicionar Jogo</button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead><tr><th>AppID</th><th>Nome</th><th>Preço</th><th>Ativo</th><th>Ações</th></tr></thead>
                            <tbody>
                                <?php foreach($games as $game): ?>
                                <tr>
                                    <td><?php echo $game['appid']; ?></td>
                                    <td><?php echo htmlspecialchars($game['nome']); ?></td>
                                    <td>R$ <?php echo number_format($game['preco'], 2, ',', '.'); ?></td>
                                    <td><?php echo $game['ativo'] ? 'Sim' : 'Não'; ?></td>
                                    <td>
                                        <button class="btn" onclick="editGame(<?php echo htmlspecialchars(json_encode($game)); ?>)"><i class="fas fa-edit"></i></button>
                                        <button class="btn" onclick="deleteGame(<?php echo $game['appid']; ?>)"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div id="cataloger" class="tab-content">
                <div class="section">
                    <h3 class="section-title">Catalogador de Jogos por Arquivo .zip</h3>
                    <p style="margin-bottom: 1.5rem; color: #666;">Faça o upload de um arquivo .zip contendo os arquivos do jogo. O sistema irá descompactar, identificar o AppID e os arquivos .lua/.manifest, e cadastrar tudo automaticamente.</p>
                    <form id="catalogerForm" enctype="multipart/form-data">
                        <div class="form-group"><label for="cataloger_nome_jogo">Nome do Jogo *</label><input type="text" id="cataloger_nome_jogo" name="nome_jogo" class="form-control" required></div>
                        <div class="form-group"><label for="cataloger_arquivo_zip">Arquivo .zip do Jogo *</label><input type="file" id="cataloger_arquivo_zip" name="arquivo_zip" class="form-control" accept=".zip" required></div>
                        <button type="submit" id="catalogerSubmitBtn" class="btn btn-primary"><i class="fas fa-upload"></i> Enviar e Catalogar</button>
                    </form>
                    <div id="catalogerLog"></div>
                </div>
            </div>

            <div id="users" class="tab-content">
                <div class="section">
                    <h3 class="section-title">Gerenciar Usuários</h3>
                    </div>
            </div>

        </main>
    </div>
    
    <div id="gameModal" style="display:none; position:fixed; z-index:1001; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
        <div style="background-color:#fefefe; margin:10% auto; padding:20px; border:1px solid #888; width:80%; max-width:600px; border-radius:12px;">
            <h3 id="gameModalTitle">Adicionar Jogo</h3>
            <form id="gameForm">
                <input type="hidden" id="gameAppIdHidden" name="appid">
                <div class="form-group"><label>Nome</label><input type="text" name="nome" class="form-control" required></div>
                <div class="form-group"><label>Preço</label><input type="number" name="preco" class="form-control" step="0.01"></div>
                <div class="form-group"><label>Descrição</label><textarea name="descricao" class="form-control"></textarea></div>
                <div class="form-group"><label>Categoria</label><input type="text" name="categoria" class="form-control"></div>
                <div class="form-group"><label>Desenvolvedor</label><input type="text" name="desenvolvedor" class="form-control"></div>
                <div class="form-group"><label>URL Imagem</label><input type="url" name="imagem" class="form-control"></div>
                <div class="form-group"><label><input type="checkbox" name="ativo" value="1" checked> Ativo</label></div>
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn" onclick="document.getElementById('gameModal').style.display='none'">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        // Lógica de navegação por abas
        const navLinks = document.querySelectorAll('.nav-link');
        const tabContents = document.querySelectorAll('.tab-content');
        const pageTitle = document.getElementById('page-title');

        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.getAttribute('data-tab');
                
                navLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                pageTitle.textContent = this.textContent.trim();
                
                tabContents.forEach(t => t.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Lógica do Modal de Jogos
        const gameModal = document.getElementById('gameModal');
        const gameForm = document.getElementById('gameForm');
        const gameModalTitle = document.getElementById('gameModalTitle');
        const gameAppIdHidden = document.getElementById('gameAppIdHidden');
        
        function openGameModal() {
            gameForm.reset();
            gameAppIdHidden.value = '';
            gameModalTitle.textContent = 'Adicionar Jogo';
            gameModal.style.display = 'block';
        }
        
        function editGame(game) {
            gameForm.reset();
            gameAppIdHidden.value = game.appid;
            gameModalTitle.textContent = 'Editar Jogo';
            // Preencher o formulário
            for (const key in game) {
                if (gameForm.elements[key]) {
                    if (gameForm.elements[key].type === 'checkbox') {
                        gameForm.elements[key].checked = game[key] == 1;
                    } else {
                        gameForm.elements[key].value = game[key];
                    }
                }
            }
            gameModal.style.display = 'block';
        }
        
        gameForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', gameAppIdHidden.value ? 'update_game' : 'add_game');
            
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            });
        });

        function deleteGame(appid) {
            if (confirm('Tem certeza que deseja excluir este jogo?')) {
                const formData = new FormData();
                formData.append('action', 'delete_game');
                formData.append('appid', appid);
                fetch('', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }

        // Lógica do Formulário do Catalogador
        const catalogerForm = document.getElementById('catalogerForm');
        const catalogerLog = document.getElementById('catalogerLog');
        const catalogerSubmitBtn = document.getElementById('catalogerSubmitBtn');

        catalogerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            catalogerLog.innerHTML = '';
            catalogerLog.style.display = 'block';
            catalogerSubmitBtn.disabled = true;
            catalogerSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

            const formData = new FormData(this);
            formData.append('action', 'catalog_game');

            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data && data.log) {
                    data.log.forEach(line => {
                        const div = document.createElement('div');
                        div.textContent = line;
                        if (line.includes('❌')) { div.style.color = '#ff8f8f'; }
                        else if (line.includes('✅') || line.includes('🎉')) { div.style.color = '#90ee90'; }
                        catalogerLog.appendChild(div);
                        catalogerLog.scrollTop = catalogerLog.scrollHeight;
                    });
                }
            })
            .catch(error => {
                catalogerLog.innerHTML = `<div>❌ ERRO DE COMUNICAÇÃO: ${error}</div>`;
            })
            .finally(() => {
                catalogerSubmitBtn.disabled = false;
                catalogerSubmitBtn.innerHTML = '<i class="fas fa-upload"></i> Enviar e Catalogar';
            });
        });
    </script>
</body>
</html>