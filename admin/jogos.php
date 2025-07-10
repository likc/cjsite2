<?php
// ============================================================================
// admin/jogos.php - Gerenciamento de Jogos
// ============================================================================

require_once '../config.php';

// Verificar se está logado como admin
if (!isAdmin()) {
    redirect('auth.php');
}

$message = '';
$message_type = '';
$action = sanitizeInput($_GET['action'] ?? 'list');
$jogo_id = (int)($_GET['id'] ?? 0);

try {
    $pdo = conectarBanco();
    
    // Processar ações
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['salvar_jogo'])) {
            $nome = sanitizeInput($_POST['nome'] ?? '');
            $appid = sanitizeInput($_POST['appid'] ?? '');
            $descricao = sanitizeInput($_POST['descricao'] ?? '');
            $preco = (float)($_POST['preco'] ?? 0);
            $preco_promocional = !empty($_POST['preco_promocional']) ? (float)$_POST['preco_promocional'] : null;
            $categoria = sanitizeInput($_POST['categoria'] ?? '');
            $desenvolvedor = sanitizeInput($_POST['desenvolvedor'] ?? '');
            $tags = sanitizeInput($_POST['tags'] ?? '');
            $requisitos_minimos = sanitizeInput($_POST['requisitos_minimos'] ?? '');
            $arquivo_manifest = sanitizeInput($_POST['arquivo_manifest'] ?? '');
            $arquivo_lua = sanitizeInput($_POST['arquivo_lua'] ?? '');
            $tamanho_download = sanitizeInput($_POST['tamanho_download'] ?? '');
            $ativo = isset($_POST['ativo']);
            $destaque = isset($_POST['destaque']);
            $data_lancamento = !empty($_POST['data_lancamento']) ? $_POST['data_lancamento'] : null;
            
            // Validações
            if (empty($nome)) {
                $message = 'O nome do jogo é obrigatório.';
                $message_type = 'error';
            } elseif ($preco < 0) {
                $message = 'O preço deve ser maior ou igual a zero.';
                $message_type = 'error';
            } elseif ($preco_promocional !== null && $preco_promocional >= $preco) {
                $message = 'O preço promocional deve ser menor que o preço normal.';
                $message_type = 'error';
            } else {
                // Upload de imagens
                $imagem_nome = '';
                $imagem_banner_nome = '';
                
                // Processar upload da imagem principal
                if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = IMAGES_PATH;
                    $file_extension = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($file_extension, $allowed_types)) {
                        $imagem_nome = uniqid('game_') . '.' . $file_extension;
                        $upload_path = $upload_dir . $imagem_nome;
                        
                        if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_path)) {
                            $message = 'Erro ao fazer upload da imagem.';
                            $message_type = 'error';
                        }
                    } else {
                        $message = 'Formato de imagem não permitido. Use JPG, PNG ou WebP.';
                        $message_type = 'error';
                    }
                }
                
                // Processar upload da imagem banner
                if (isset($_FILES['imagem_banner']) && $_FILES['imagem_banner']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = IMAGES_PATH;
                    $file_extension = strtolower(pathinfo($_FILES['imagem_banner']['name'], PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($file_extension, $allowed_types)) {
                        $imagem_banner_nome = uniqid('banner_') . '.' . $file_extension;
                        $upload_path = $upload_dir . $imagem_banner_nome;
                        
                        if (!move_uploaded_file($_FILES['imagem_banner']['tmp_name'], $upload_path)) {
                            $message = 'Erro ao fazer upload do banner.';
                            $message_type = 'error';
                        }
                    } else {
                        $message = 'Formato de banner não permitido. Use JPG, PNG ou WebP.';
                        $message_type = 'error';
                    }
                }
                
                if (empty($message)) {
                    try {
                        if ($action === 'add') {
                            // Verificar se AppID já existe
                            if (!empty($appid)) {
                                $stmt = $pdo->prepare("SELECT id FROM jogos WHERE appid = ?");
                                $stmt->execute([$appid]);
                                if ($stmt->fetch()) {
                                    $message = 'Este AppID já está sendo usado por outro jogo.';
                                    $message_type = 'error';
                                } else {
                                    // Inserir novo jogo
                                    $stmt = $pdo->prepare("
                                        INSERT INTO jogos (nome, appid, descricao, preco, preco_promocional, imagem, imagem_banner, categoria, desenvolvedor, tags, requisitos_minimos, arquivo_manifest, arquivo_lua, tamanho_download, ativo, destaque, data_lancamento) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmt->execute([$nome, $appid, $descricao, $preco, $preco_promocional, $imagem_nome, $imagem_banner_nome, $categoria, $desenvolvedor, $tags, $requisitos_minimos, $arquivo_manifest, $arquivo_lua, $tamanho_download, $ativo, $destaque, $data_lancamento]);
                                    
                                    $message = 'Jogo adicionado com sucesso!';
                                    $message_type = 'success';
                                    $action = 'list';
                                }
                            } else {
                                // Inserir sem AppID
                                $stmt = $pdo->prepare("
                                    INSERT INTO jogos (nome, descricao, preco, preco_promocional, imagem, imagem_banner, categoria, desenvolvedor, tags, requisitos_minimos, arquivo_manifest, arquivo_lua, tamanho_download, ativo, destaque, data_lancamento) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([$nome, $descricao, $preco, $preco_promocional, $imagem_nome, $imagem_banner_nome, $categoria, $desenvolvedor, $tags, $requisitos_minimos, $arquivo_manifest, $arquivo_lua, $tamanho_download, $ativo, $destaque, $data_lancamento]);
                                
                                $message = 'Jogo adicionado com sucesso!';
                                $message_type = 'success';
                                $action = 'list';
                            }
                        } elseif ($action === 'edit' && $jogo_id) {
                            // Atualizar jogo existente
                            $update_fields = [];
                            $update_params = [];
                            
                            $update_fields[] = "nome = ?";
                            $update_params[] = $nome;
                            
                            if (!empty($appid)) {
                                // Verificar se AppID já existe em outro jogo
                                $stmt = $pdo->prepare("SELECT id FROM jogos WHERE appid = ? AND id != ?");
                                $stmt->execute([$appid, $jogo_id]);
                                if ($stmt->fetch()) {
                                    $message = 'Este AppID já está sendo usado por outro jogo.';
                                    $message_type = 'error';
                                } else {
                                    $update_fields[] = "appid = ?";
                                    $update_params[] = $appid;
                                }
                            }
                            
                            if (empty($message)) {
                                $update_fields = array_merge($update_fields, [
                                    "descricao = ?", "preco = ?", "preco_promocional = ?", "categoria = ?", 
                                    "desenvolvedor = ?", "tags = ?", "requisitos_minimos = ?", "arquivo_manifest = ?", 
                                    "arquivo_lua = ?", "tamanho_download = ?", "ativo = ?", "destaque = ?", "data_lancamento = ?"
                                ]);
                                $update_params = array_merge($update_params, [
                                    $descricao, $preco, $preco_promocional, $categoria, $desenvolvedor, $tags, 
                                    $requisitos_minimos, $arquivo_manifest, $arquivo_lua, $tamanho_download, 
                                    $ativo, $destaque, $data_lancamento
                                ]);
                                
                                // Adicionar imagens se foram enviadas
                                if (!empty($imagem_nome)) {
                                    $update_fields[] = "imagem = ?";
                                    $update_params[] = $imagem_nome;
                                }
                                
                                if (!empty($imagem_banner_nome)) {
                                    $update_fields[] = "imagem_banner = ?";
                                    $update_params[] = $imagem_banner_nome;
                                }
                                
                                $update_params[] = $jogo_id;
                                
                                $stmt = $pdo->prepare("UPDATE jogos SET " . implode(', ', $update_fields) . " WHERE id = ?");
                                $stmt->execute($update_params);
                                
                                $message = 'Jogo atualizado com sucesso!';
                                $message_type = 'success';
                                $action = 'list';
                            }
                        }
                    } catch (Exception $e) {
                        logError("Erro ao salvar jogo: " . $e->getMessage());
                        $message = 'Erro ao salvar jogo. Tente novamente.';
                        $message_type = 'error';
                    }
                }
            }
        }
        
        // Ações rápidas
        if (isset($_POST['toggle_status'])) {
            $id = (int)$_POST['jogo_id'];
            $stmt = $pdo->prepare("UPDATE jogos SET ativo = NOT ativo WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Status do jogo atualizado!';
            $message_type = 'success';
        }
        
        if (isset($_POST['toggle_destaque'])) {
            $id = (int)$_POST['jogo_id'];
            $stmt = $pdo->prepare("UPDATE jogos SET destaque = NOT destaque WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Destaque do jogo atualizado!';
            $message_type = 'success';
        }
        
        if (isset($_POST['delete_jogo'])) {
            $id = (int)$_POST['jogo_id'];
            if (confirm('Tem certeza que deseja excluir este jogo?')) {
                // Remover imagens se existirem
                $stmt = $pdo->prepare("SELECT imagem, imagem_banner FROM jogos WHERE id = ?");
                $stmt->execute([$id]);
                $jogo = $stmt->fetch();
                
                if ($jogo) {
                    if (!empty($jogo['imagem']) && file_exists(IMAGES_PATH . $jogo['imagem'])) {
                        unlink(IMAGES_PATH . $jogo['imagem']);
                    }
                    if (!empty($jogo['imagem_banner']) && file_exists(IMAGES_PATH . $jogo['imagem_banner'])) {
                        unlink(IMAGES_PATH . $jogo['imagem_banner']);
                    }
                    
                    // Remover o jogo
                    $stmt = $pdo->prepare("DELETE FROM jogos WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $message = 'Jogo excluído com sucesso!';
                    $message_type = 'success';
                }
            }
        }
    }
    
    // Buscar dados conforme a ação
    if ($action === 'edit' && $jogo_id) {
        $stmt = $pdo->prepare("SELECT * FROM jogos WHERE id = ?");
        $stmt->execute([$jogo_id]);
        $jogo_atual = $stmt->fetch();
        
        if (!$jogo_atual) {
            $action = 'list';
            $message = 'Jogo não encontrado.';
            $message_type = 'error';
        }
    }
    
    if ($action === 'list') {
        // Buscar todos os jogos
        $search = sanitizeInput($_GET['search'] ?? '');
        $categoria_filter = sanitizeInput($_GET['categoria'] ?? '');
        $status_filter = sanitizeInput($_GET['status'] ?? '');
        
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(nome LIKE ? OR descricao LIKE ?)";
            $search_term = "%$search%";
            $params = array_merge($params, [$search_term, $search_term]);
        }
        
        if (!empty($categoria_filter)) {
            $where_conditions[] = "categoria = ?";
            $params[] = $categoria_filter;
        }
        
        if ($status_filter !== '') {
            $where_conditions[] = "ativo = ?";
            $params[] = (int)$status_filter;
        }
        
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $stmt = $pdo->prepare("
            SELECT j.*, 
                   (SELECT COUNT(*) FROM vendas v WHERE v.jogo_id = j.id AND v.status = 'aprovado') as total_vendas,
                   (SELECT SUM(v.preco_final) FROM vendas v WHERE v.jogo_id = j.id AND v.status = 'aprovado') as receita_total
            FROM jogos j
            $where_sql
            ORDER BY j.created_at DESC
        ");
        $stmt->execute($params);
        $jogos = $stmt->fetchAll();
        
        // Buscar categorias para filtro
        $categorias = $pdo->query("
            SELECT DISTINCT categoria 
            FROM jogos 
            WHERE categoria IS NOT NULL AND categoria != '' 
            ORDER BY categoria
        ")->fetchAll(PDO::FETCH_COLUMN);
    }
    
} catch (Exception $e) {
    logError("Erro na página de jogos: " . $e->getMessage());
    $message = "Erro ao carregar página. Tente novamente.";
    $message_type = 'error';
}

$site_name = getSystemSetting('site_name', 'COMPREJOGOS');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Jogos - Admin <?php echo $site_name; ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
            --info: #2563eb;
            --dark: #1a1a1a;
            --light: #f8fafc;
            --border: #e5e7eb;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        
        /* Header */
        .admin-header {
            background: linear-gradient(135deg, var(--dark), #2d3748);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-logo h1 {
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .admin-nav {
            display: flex;
            gap: 1rem;
        }
        
        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .admin-nav a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        /* Container */
        .admin-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }
        
        .admin-sidebar {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .admin-sidebar-nav {
            list-style: none;
        }
        
        .admin-sidebar-nav li {
            margin-bottom: 0.5rem;
        }
        
        .admin-sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #666;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .admin-sidebar-nav a:hover {
            background: var(--primary);
            color: white;
        }
        
        .admin-sidebar-nav a.active {
            background: var(--primary);
            color: white;
        }
        
        .admin-sidebar-nav .icon {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .admin-main {
            display: grid;
            gap: 2rem;
        }
        
        .admin-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .admin-card-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: space-between;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group {
            display: grid;
            gap: 0.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-checkbox input[type="checkbox"] {
            transform: scale(1.2);
        }
        
        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: block;
            padding: 0.75rem;
            border: 2px dashed var(--border);
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s;
            background: var(--light);
        }
        
        .file-upload-label:hover {
            border-color: var(--primary);
            background: #f0f9ff;
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover:not(:disabled) {
            background: #15803d;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover:not(:disabled) {
            background: #b91c1c;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-warning:hover:not(:disabled) {
            background: #c2410c;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* Table */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .admin-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
        }
        
        .admin-table tr:hover {
            background: #f9fafb;
        }
        
        /* Game card for table */
        .game-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .game-image {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .game-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .game-details h4 {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .game-meta {
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-ativo {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inativo {
            background: #f8d7da;
            color: #721c24;
        }
        
        .destaque-badge {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Filters */
        .filters-section {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        /* Message */
        .message {
            margin-bottom: 2rem;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Actions */
        .actions-cell {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        /* Image preview */
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 1rem;
            display: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin: 1rem auto;
            }
            
            .admin-header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-cell {
                flex-direction: column;
            }
            
            .admin-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="admin-header-content">
            <div class="admin-logo">
                <h1>🛡️ <?php echo htmlspecialchars($site_name); ?> <span style="font-size: 0.8rem; opacity: 0.8;">Admin</span></h1>
            </div>
            <nav class="admin-nav">
                <a href="index.php">📊 Dashboard</a>
                <a href="../index.php" target="_blank">🏠 Ver Loja</a>
                <a href="logout.php">🚪 Sair</a>
            </nav>
        </div>
    </header>

    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <ul class="admin-sidebar-nav">
                <li><a href="index.php"><span class="icon">📊</span> Dashboard</a></li>
                <li><a href="usuarios.php"><span class="icon">👥</span> Usuários</a></li>
                <li><a href="jogos.php" class="active"><span class="icon">🎮</span> Jogos</a></li>
                <li><a href="vendas.php"><span class="icon">💰</span> Vendas</a></li>
                <li><a href="cupons.php"><span class="icon">🎫</span> Cupons</a></li>
                <li><a href="suporte.php"><span class="icon">💬</span> Suporte</a></li>
                <li><a href="configuracoes.php"><span class="icon">⚙️</span> Configurações</a></li>
                <li><a href="logs.php"><span class="icon">📋</span> Logs</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                <!-- Game List -->
                <div class="admin-card">
                    <div class="admin-card-title">
                        🎮 Gerenciar Jogos
                        <div>
                            <a href="jogos.php?action=add" class="btn btn-success">➕ Adicionar Jogo</a>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filters-section">
                        <form class="filters-grid" method="GET">
                            <input type="hidden" name="action" value="list">
                            
                            <div class="form-group">
                                <label class="form-label">Buscar:</label>
                                <input type="text" name="search" class="form-input" 
                                       placeholder="Nome ou descrição..." 
                                       value="<?php echo htmlspecialchars($search ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Categoria:</label>
                                <select name="categoria" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($categorias ?? [] as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                <?php echo ($categoria_filter ?? '') === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Status:</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="1" <?php echo ($status_filter ?? '') === '1' ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="0" <?php echo ($status_filter ?? '') === '0' ? 'selected' : ''; ?>>Inativo</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                        </form>
                    </div>
                    
                    <!-- Games Table -->
                    <?php if (empty($jogos)): ?>
                        <p style="text-align: center; color: #666; padding: 3rem;">
                            Nenhum jogo encontrado.
                        </p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Jogo</th>
                                    <th>Preço</th>
                                    <th>Vendas</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogos as $jogo): ?>
                                    <tr>
                                        <td>
                                            <div class="game-info">
                                                <div class="game-image">
                                                    <?php if (!empty($jogo['imagem']) && file_exists(IMAGES_PATH . $jogo['imagem'])): ?>
                                                        <img src="../uploads/images/<?php echo htmlspecialchars($jogo['imagem']); ?>" 
                                                             alt="<?php echo htmlspecialchars($jogo['nome']); ?>">
                                                    <?php else: ?>
                                                        🎮
                                                    <?php endif; ?>
                                                </div>
                                                <div class="game-details">
                                                    <h4><?php echo htmlspecialchars($jogo['nome']); ?></h4>
                                                    <div class="game-meta">
                                                        <?php if (!empty($jogo['categoria'])): ?>
                                                            <span><?php echo htmlspecialchars($jogo['categoria']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($jogo['appid'])): ?>
                                                            <span> • AppID: <?php echo htmlspecialchars($jogo['appid']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($jogo['destaque']): ?>
                                                            <span class="destaque-badge">DESTAQUE</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php if ($jogo['preco_promocional']): ?>
                                                    <del style="color: #666;"><?php echo formatarPreco($jogo['preco']); ?></del><br>
                                                    <strong style="color: var(--success);"><?php echo formatarPreco($jogo['preco_promocional']); ?></strong>
                                                <?php else: ?>
                                                    <strong><?php echo formatarPreco($jogo['preco']); ?></strong>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo number_format($jogo['total_vendas']); ?></strong> vendas<br>
                                                <small style="color: #666;"><?php echo formatarPreco($jogo['receita_total'] ?? 0); ?> em receita</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $jogo['ativo'] ? 'ativo' : 'inativo'; ?>">
                                                <?php echo $jogo['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="jogos.php?action=edit&id=<?php echo $jogo['id']; ?>" class="btn btn-primary btn-sm">
                                                ✏️ Editar
                                            </a>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
                                                <button type="submit" name="toggle_status" 
                                                        class="btn btn-warning btn-sm">
                                                    <?php echo $jogo['ativo'] ? '⏸️ Desativar' : '▶️ Ativar'; ?>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
                                                <button type="submit" name="toggle_destaque" 
                                                        class="btn btn-sm" 
                                                        style="background: <?php echo $jogo['destaque'] ? '#f59e0b' : '#6b7280'; ?>; color: white;">
                                                    <?php echo $jogo['destaque'] ? '⭐ Remover Destaque' : '⭐ Destacar'; ?>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Tem certeza que deseja excluir este jogo?')">
                                                <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
                                                <button type="submit" name="delete_jogo" class="btn btn-danger btn-sm">
                                                    🗑️ Excluir
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Add/Edit Game Form -->
                <div class="admin-card">
                    <div class="admin-card-title">
                        <?php echo $action === 'add' ? '➕ Adicionar Novo Jogo' : '✏️ Editar Jogo'; ?>
                        <div>
                            <a href="jogos.php" class="btn btn-primary">← Voltar à Lista</a>
                        </div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="form-grid">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nome do Jogo *:</label>
                                <input type="text" name="nome" class="form-input" 
                                       value="<?php echo htmlspecialchars($jogo_atual['nome'] ?? ''); ?>" 
                                       required maxlength="200">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">AppID (Steam):</label>
                                <input type="text" name="appid" class="form-input" 
                                       value="<?php echo htmlspecialchars($jogo_atual['appid'] ?? ''); ?>" 
                                       maxlength="50">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Descrição:</label>
                            <textarea name="descricao" class="form-textarea" rows="4"><?php echo htmlspecialchars($jogo_atual['descricao'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Preço Normal *:</label>
                                <input type="number" name="preco" class="form-input" 
                                       value="<?php echo $jogo_atual['preco'] ?? ''; ?>" 
                                       step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Preço Promocional:</label>
                                <input type="number" name="preco_promocional" class="form-input" 
                                       value="<?php echo $jogo_atual['preco_promocional'] ?? ''; ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Categoria:</label>
                                <input type="text" name="categoria" class="form-input" 
                                       value="<?php echo htmlspecialchars($jogo_atual['categoria'] ?? ''); ?>" 
                                       maxlength="100">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Desenvolvedor:</label>
                                <input type="text" name="desenvolvedor" class="form-input" 
                                       value="<?php echo htmlspecialchars($jogo_atual['desenvolvedor'] ?? ''); ?>" 
                                       maxlength="200">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tags (separadas por vírgula):</label>
                            <input type="text" name="tags" class="form-input" 
                                   value="<?php echo htmlspecialchars($jogo_atual['tags'] ?? ''); ?>" 
                                   placeholder="Ação, Aventura, RPG">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Arquivo Manifest:</label>
                                <input type="text" name="arquivo_manifest" class="form-input" 
                                       value="<?php echo htmlspecialchars($jogo_atual['arquivo_manifest'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Arquivo LUA:</label>
                                <input type="text" name="arquivo_lua" class="form-input" 
                                       value="<?php echo htmlspecialchars($jogo_atual['arquivo_lua'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Tamanho Download:</label>
                                <input type="text" name="tamanho_download" class="form-input" 
                                       value="<?php echo htmlspecialchars($jogo_atual['tamanho_download'] ?? ''); ?>" 
                                       placeholder="ex: 15 GB">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Data de Lançamento:</label>
                                <input type="date" name="data_lancamento" class="form-input" 
                                       value="<?php echo $jogo_atual['data_lancamento'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Requisitos Mínimos:</label>
                            <textarea name="requisitos_minimos" class="form-textarea" rows="3"><?php echo htmlspecialchars($jogo_atual['requisitos_minimos'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Imagem Principal:</label>
                                <div class="file-upload">
                                    <input type="file" name="imagem" accept="image/*" onchange="previewImage(this, 'preview1')">
                                    <label class="file-upload-label">
                                        📷 Clique para selecionar imagem
                                        <br><small>JPG, PNG ou WebP (máx. 5MB)</small>
                                    </label>
                                </div>
                                <?php if (!empty($jogo_atual['imagem'])): ?>
                                    <img src="../uploads/images/<?php echo htmlspecialchars($jogo_atual['imagem']); ?>" 
                                         class="image-preview" style="display: block;" id="current1">
                                <?php endif; ?>
                                <img id="preview1" class="image-preview">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Banner/Imagem Grande:</label>
                                <div class="file-upload">
                                    <input type="file" name="imagem_banner" accept="image/*" onchange="previewImage(this, 'preview2')">
                                    <label class="file-upload-label">
                                        🖼️ Clique para selecionar banner
                                        <br><small>JPG, PNG ou WebP (máx. 5MB)</small>
                                    </label>
                                </div>
                                <?php if (!empty($jogo_atual['imagem_banner'])): ?>
                                    <img src="../uploads/images/<?php echo htmlspecialchars($jogo_atual['imagem_banner']); ?>" 
                                         class="image-preview" style="display: block;" id="current2">
                                <?php endif; ?>
                                <img id="preview2" class="image-preview">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <div class="form-checkbox">
                                    <input type="checkbox" name="ativo" id="ativo" 
                                           <?php echo ($jogo_atual['ativo'] ?? 1) ? 'checked' : ''; ?>>
                                    <label for="ativo">Jogo Ativo (visível na loja)</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-checkbox">
                                    <input type="checkbox" name="destaque" id="destaque" 
                                           <?php echo ($jogo_atual['destaque'] ?? 0) ? 'checked' : ''; ?>>
                                    <label for="destaque">Jogo em Destaque</label>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                            <a href="jogos.php" class="btn btn-warning">Cancelar</a>
                            <button type="submit" name="salvar_jogo" class="btn btn-success">
                                💾 <?php echo $action === 'add' ? 'Adicionar Jogo' : 'Salvar Alterações'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    
                    // Esconder imagem atual se existir
                    const current = document.getElementById('current' + previewId.slice(-1));
                    if (current) {
                        current.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Validação do formulário
        document.querySelector('form[enctype="multipart/form-data"]')?.addEventListener('submit', function(e) {
            const preco = parseFloat(document.querySelector('input[name="preco"]').value);
            const precoPromo = document.querySelector('input[name="preco_promocional"]').value;
            
            if (precoPromo && parseFloat(precoPromo) >= preco) {
                e.preventDefault();
                alert('O preço promocional deve ser menor que o preço normal!');
                return false;
            }
        });
    </script>
</body>
</html>