<?php
// ============================================================================
// cliente/biblioteca.php - Biblioteca de Jogos do Cliente
// ============================================================================

require_once '../config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('../login.php?redirect=cliente/biblioteca.php');
}

$search = sanitizeInput($_GET['search'] ?? '');
$categoria = sanitizeInput($_GET['categoria'] ?? '');
$ordem = sanitizeInput($_GET['ordem'] ?? 'data_compra');

try {
    $pdo = conectarBanco();
    
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT login FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        redirect('../logout.php');
    }
    
    // Construir query de jogos do usuário
    $where_conditions = ["uj.usuario_id = ?"];
    $params = [$_SESSION['user_id']];
    
    if (!empty($search)) {
        $where_conditions[] = "(j.nome LIKE ? OR j.descricao LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term]);
    }
    
    if (!empty($categoria)) {
        $where_conditions[] = "j.categoria = ?";
        $params[] = $categoria;
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Definir ordenação
    $order_sql = "uj.data_compra DESC";
    switch ($ordem) {
        case 'nome':
            $order_sql = "j.nome ASC";
            break;
        case 'categoria':
            $order_sql = "j.categoria ASC, j.nome ASC";
            break;
        case 'preco':
            $order_sql = "uj.preco_pago DESC";
            break;
    }
    
    // Buscar jogos do usuário
    $stmt = $pdo->prepare("
        SELECT j.*, uj.data_compra, uj.preco_pago, uj.forma_pagamento, uj.status
        FROM usuario_jogos uj
        JOIN jogos j ON uj.jogo_id = j.id
        WHERE $where_sql
        ORDER BY $order_sql
    ");
    $stmt->execute($params);
    $jogos_usuario = $stmt->fetchAll();
    
    // Buscar categorias dos jogos do usuário
    $categorias = $pdo->prepare("
        SELECT DISTINCT j.categoria 
        FROM usuario_jogos uj
        JOIN jogos j ON uj.jogo_id = j.id
        WHERE uj.usuario_id = ? AND j.categoria IS NOT NULL AND j.categoria != ''
        ORDER BY j.categoria
    ");
    $categorias->execute([$_SESSION['user_id']]);
    $categorias = $categorias->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    logError("Erro na biblioteca: " . $e->getMessage());
    $jogos_usuario = [];
    $categorias = [];
}

$site_name = getSystemSetting('site_name', 'COMPREJOGOS');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Biblioteca - <?php echo $site_name; ?></title>
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
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .header-nav {
            display: flex;
            gap: 1rem;
        }
        
        .header-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .header-nav a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }
        
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .sidebar-nav {
            list-style: none;
        }
        
        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-nav a {
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
        
        .sidebar-nav a:hover {
            background: var(--primary);
            color: white;
        }
        
        .sidebar-nav a.active {
            background: var(--primary);
            color: white;
        }
        
        .sidebar-nav .icon {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Search and Filters */
        .filters-section {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-input,
        .form-select {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #15803d;
        }
        
        /* Stats */
        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
        }
        
        .stats-info {
            font-weight: 600;
            color: var(--dark);
        }
        
        .view-toggle {
            display: flex;
            gap: 0.5rem;
        }
        
        .view-btn {
            padding: 0.5rem;
            border: 1px solid var(--border);
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .view-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Games Grid */
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .games-list {
            display: none;
        }
        
        .games-list.active {
            display: block;
        }
        
        .games-grid.list-view {
            grid-template-columns: 1fr;
        }
        
        .game-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .game-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .game-card.list-view {
            display: flex;
            align-items: center;
        }
        
        .game-image {
            width: 100%;
            height: 180px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            position: relative;
        }
        
        .game-card.list-view .game-image {
            width: 120px;
            height: 80px;
            flex-shrink: 0;
        }
        
        .game-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .game-status {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--success);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .game-info {
            padding: 1.5rem;
            flex: 1;
        }
        
        .game-card.list-view .game-info {
            padding: 1rem;
        }
        
        .game-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .game-card.list-view .game-title {
            font-size: 1.1rem;
        }
        
        .game-meta {
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .game-meta-item {
            margin-bottom: 0.25rem;
        }
        
        .game-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .game-card.list-view .game-actions {
            margin-left: auto;
            flex-shrink: 0;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .empty-state h3 {
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin: 1rem auto;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .user-info {
                flex-direction: column;
                gap: 1rem;
            }
            
            .filters-form {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-bar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .games-grid {
                grid-template-columns: 1fr;
            }
            
            .game-card.list-view {
                flex-direction: column;
            }
            
            .game-card.list-view .game-image {
                width: 100%;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <h1><?php echo htmlspecialchars($site_name); ?></h1>
            </div>
            <div class="user-info">
                <div>Olá, <strong><?php echo htmlspecialchars($usuario['login']); ?></strong>!</div>
                <nav class="header-nav">
                    <a href="../index.php">🏠 Loja</a>
                    <a href="download.php">📥 Download</a>
                    <a href="../logout.php">🚪 Sair</a>
                </nav>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul class="sidebar-nav">
                <li><a href="index.php"><span class="icon">🏠</span> Início</a></li>
                <li><a href="biblioteca.php" class="active"><span class="icon">🎮</span> Minha Biblioteca</a></li>
                <li><a href="historico.php"><span class="icon">📊</span> Histórico de Compras</a></li>
                <li><a href="perfil.php"><span class="icon">👤</span> Meu Perfil</a></li>
                <li><a href="download.php"><span class="icon">📥</span> Download Launcher</a></li>
                <li><a href="suporte.php"><span class="icon">💬</span> Suporte</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <h1 class="page-title">
                🎮 Minha Biblioteca
            </h1>
            
            <!-- Filters -->
            <div class="filters-section">
                <form class="filters-form" method="GET">
                    <div class="form-group">
                        <label class="form-label">Buscar jogos:</label>
                        <input type="text" name="search" class="form-input" 
                               placeholder="Digite o nome do jogo..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Categoria:</label>
                        <select name="categoria" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo $categoria === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Ordenar por:</label>
                        <select name="ordem" class="form-select">
                            <option value="data_compra" <?php echo $ordem === 'data_compra' ? 'selected' : ''; ?>>Data de Compra</option>
                            <option value="nome" <?php echo $ordem === 'nome' ? 'selected' : ''; ?>>Nome (A-Z)</option>
                            <option value="categoria" <?php echo $ordem === 'categoria' ? 'selected' : ''; ?>>Categoria</option>
                            <option value="preco" <?php echo $ordem === 'preco' ? 'selected' : ''; ?>>Valor Pago</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </form>
            </div>
            
            <?php if (!empty($jogos_usuario)): ?>
                <!-- Stats -->
                <div class="stats-bar">
                    <div class="stats-info">
                        <?php echo count($jogos_usuario); ?> jogo(s) encontrado(s)
                        <?php if (!empty($search) || !empty($categoria)): ?>
                            | <a href="biblioteca.php" style="color: var(--primary); text-decoration: none;">Limpar filtros</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="view-toggle">
                        <button class="view-btn active" onclick="toggleView('grid')" id="grid-btn">
                            📊 Grid
                        </button>
                        <button class="view-btn" onclick="toggleView('list')" id="list-btn">
                            📋 Lista
                        </button>
                    </div>
                </div>
                
                <!-- Games Grid -->
                <div class="games-grid" id="games-container">
                    <?php foreach ($jogos_usuario as $jogo): ?>
                        <div class="game-card">
                            <div class="game-image">
                                <?php if (!empty($jogo['imagem']) && file_exists(IMAGES_PATH . $jogo['imagem'])): ?>
                                    <img src="../uploads/images/<?php echo htmlspecialchars($jogo['imagem']); ?>" 
                                         alt="<?php echo htmlspecialchars($jogo['nome']); ?>">
                                <?php else: ?>
                                    🎮 <?php echo htmlspecialchars($jogo['nome']); ?>
                                <?php endif; ?>
                                
                                <div class="game-status">
                                    <?php echo $jogo['status'] === 'ativo' ? 'Ativo' : ucfirst($jogo['status']); ?>
                                </div>
                            </div>
                            
                            <div class="game-info">
                                <h3 class="game-title"><?php echo htmlspecialchars($jogo['nome']); ?></h3>
                                
                                <div class="game-meta">
                                    <div class="game-meta-item">
                                        <strong>Categoria:</strong> <?php echo htmlspecialchars($jogo['categoria'] ?? 'Não definida'); ?>
                                    </div>
                                    <div class="game-meta-item">
                                        <strong>Comprado em:</strong> <?php echo date('d/m/Y', strtotime($jogo['data_compra'])); ?>
                                    </div>
                                    <div class="game-meta-item">
                                        <strong>Valor pago:</strong> <?php echo formatarPreco($jogo['preco_pago']); ?>
                                    </div>
                                    <div class="game-meta-item">
                                        <strong>Pagamento:</strong> <?php echo ucfirst($jogo['forma_pagamento']); ?>
                                    </div>
                                </div>
                                
                                <div class="game-actions">
                                    <a href="jogar.php?id=<?php echo $jogo['id']; ?>" class="btn btn-success btn-sm">
                                        ▶️ Jogar
                                    </a>
                                    <a href="../jogo.php?id=<?php echo $jogo['id']; ?>" class="btn btn-primary btn-sm">
                                        ℹ️ Detalhes
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <span class="empty-state-icon">🎮</span>
                    <h3>
                        <?php if (!empty($search) || !empty($categoria)): ?>
                            Nenhum jogo encontrado
                        <?php else: ?>
                            Sua biblioteca está vazia
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?php if (!empty($search) || !empty($categoria)): ?>
                            Tente ajustar os filtros de busca para encontrar seus jogos.
                        <?php else: ?>
                            Explore nossa loja e adicione jogos à sua coleção!
                        <?php endif; ?>
                    </p>
                    
                    <div style="margin-top: 2rem;">
                        <?php if (!empty($search) || !empty($categoria)): ?>
                            <a href="biblioteca.php" class="btn btn-primary" style="margin-right: 1rem;">
                                Limpar Filtros
                            </a>
                        <?php endif; ?>
                        <a href="../index.php" class="btn btn-success">
                            Explorar Loja
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleView(viewType) {
            const container = document.getElementById('games-container');
            const gridBtn = document.getElementById('grid-btn');
            const listBtn = document.getElementById('list-btn');
            const gameCards = container.querySelectorAll('.game-card');
            
            // Update buttons
            gridBtn.classList.remove('active');
            listBtn.classList.remove('active');
            
            if (viewType === 'list') {
                listBtn.classList.add('active');
                container.classList.add('list-view');
                gameCards.forEach(card => card.classList.add('list-view'));
            } else {
                gridBtn.classList.add('active');
                container.classList.remove('list-view');
                gameCards.forEach(card => card.classList.remove('list-view'));
            }
            
            // Save preference
            localStorage.setItem('library-view', viewType);
        }
        
        // Load saved view preference
        window.addEventListener('load', function() {
            const savedView = localStorage.getItem('library-view');
            if (savedView === 'list') {
                toggleView('list');
            }
        });
    </script>
</body>
</html>