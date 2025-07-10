<?php
// ============================================================================
// cliente/historico.php - Histórico de Compras do Cliente
// ============================================================================

require_once '../config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('../login.php?redirect=cliente/historico.php');
}

$filtro_status = sanitizeInput($_GET['status'] ?? '');
$filtro_periodo = sanitizeInput($_GET['periodo'] ?? '');
$filtro_pagamento = sanitizeInput($_GET['pagamento'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

try {
    $pdo = conectarBanco();
    
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT login FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        redirect('../logout.php');
    }
    
    // Construir filtros
    $where_conditions = ["v.usuario_id = ?"];
    $params = [$_SESSION['user_id']];
    
    if (!empty($filtro_status)) {
        $where_conditions[] = "v.status = ?";
        $params[] = $filtro_status;
    }
    
    if (!empty($filtro_pagamento)) {
        $where_conditions[] = "v.forma_pagamento = ?";
        $params[] = $filtro_pagamento;
    }
    
    if (!empty($filtro_periodo)) {
        switch ($filtro_periodo) {
            case '7d':
                $where_conditions[] = "v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30d':
                $where_conditions[] = "v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90d':
                $where_conditions[] = "v.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
            case '1y':
                $where_conditions[] = "v.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Buscar total de registros
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM vendas v 
        JOIN jogos j ON v.jogo_id = j.id 
        WHERE $where_sql
    ");
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    
    // Calcular paginação
    $total_pages = ceil($total_records / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Buscar compras do usuário
    $stmt = $pdo->prepare("
        SELECT v.*, j.nome as jogo_nome, j.imagem, j.categoria
        FROM vendas v
        JOIN jogos j ON v.jogo_id = j.id
        WHERE $where_sql
        ORDER BY v.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $compras = $stmt->fetchAll();
    
    // Buscar estatísticas gerais
    $stats = [
        'total_compras' => $pdo->prepare("SELECT COUNT(*) FROM vendas WHERE usuario_id = ?"),
        'total_gasto' => $pdo->prepare("SELECT COALESCE(SUM(preco_final), 0) FROM vendas WHERE usuario_id = ? AND status = 'aprovado'"),
        'compras_aprovadas' => $pdo->prepare("SELECT COUNT(*) FROM vendas WHERE usuario_id = ? AND status = 'aprovado'"),
        'economia_total' => $pdo->prepare("SELECT COALESCE(SUM(preco_original - preco_final), 0) FROM vendas WHERE usuario_id = ? AND status = 'aprovado'")
    ];
    
    foreach ($stats as $key => $stmt) {
        $stmt->execute([$_SESSION['user_id']]);
        $stats[$key] = $stmt->fetchColumn();
    }
    
    // Buscar formas de pagamento disponíveis
    $formas_pagamento = $pdo->prepare("
        SELECT DISTINCT forma_pagamento 
        FROM vendas 
        WHERE usuario_id = ? 
        ORDER BY forma_pagamento
    ");
    $formas_pagamento->execute([$_SESSION['user_id']]);
    $formas_pagamento = $formas_pagamento->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    logError("Erro no histórico: " . $e->getMessage());
    $compras = [];
    $stats = ['total_compras' => 0, 'total_gasto' => 0, 'compras_aprovadas' => 0, 'economia_total' => 0];
    $formas_pagamento = [];
}

$site_name = getSystemSetting('site_name', 'COMPREJOGOS');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Compras - <?php echo $site_name; ?></title>
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
            display: grid;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
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
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-select {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 5px;
            font-size: 1rem;
            background: white;
        }
        
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
        
        /* Purchase Table */
        .purchases-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .purchases-table th,
        .purchases-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .purchases-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
        }
        
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
        
        .game-category {
            font-size: 0.9rem;
            color: #666;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-aprovado {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-reembolsado {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .price-info {
            text-align: right;
        }
        
        .price-final {
            font-weight: bold;
            color: var(--success);
            font-size: 1.1rem;
        }
        
        .price-original {
            font-size: 0.9rem;
            color: #666;
            text-decoration: line-through;
        }
        
        .discount-info {
            font-size: 0.8rem;
            color: var(--info);
            margin-top: 0.25rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 5px;
            text-decoration: none;
            color: var(--dark);
        }
        
        .pagination a:hover {
            background: var(--light);
        }
        
        .pagination .current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .disabled {
            color: #ccc;
            pointer-events: none;
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
        
        /* Summary */
        .summary-section {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary);
            display: block;
        }
        
        .summary-label {
            font-size: 0.9rem;
            color: #666;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .purchases-table {
                font-size: 0.9rem;
            }
            
            .purchases-table th,
            .purchases-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .game-info {
                flex-direction: column;
                text-align: center;
            }
            
            .game-image {
                width: 50px;
                height: 50px;
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
                    <a href="biblioteca.php">🎮 Biblioteca</a>
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
                <li><a href="biblioteca.php"><span class="icon">🎮</span> Minha Biblioteca</a></li>
                <li><a href="historico.php" class="active"><span class="icon">📊</span> Histórico de Compras</a></li>
                <li><a href="perfil.php"><span class="icon">👤</span> Meu Perfil</a></li>
                <li><a href="download.php"><span class="icon">📥</span> Download Launcher</a></li>
                <li><a href="suporte.php"><span class="icon">💬</span> Suporte</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['total_compras']; ?></span>
                    <span class="stat-label">Total de Compras</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo formatarPreco($stats['total_gasto']); ?></span>
                    <span class="stat-label">Total Investido</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['compras_aprovadas']; ?></span>
                    <span class="stat-label">Compras Aprovadas</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo formatarPreco($stats['economia_total']); ?></span>
                    <span class="stat-label">Economia Total</span>
                </div>
            </div>
            
            <div class="card">
                <h1 class="card-title">
                    📊 Histórico de Compras
                </h1>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form class="filters-grid" method="GET">
                        <div class="form-group">
                            <label class="form-label">Status:</label>
                            <select name="status" class="form-select">
                                <option value="">Todos</option>
                                <option value="aprovado" <?php echo $filtro_status === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                                <option value="pendente" <?php echo $filtro_status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="cancelado" <?php echo $filtro_status === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                <option value="reembolsado" <?php echo $filtro_status === 'reembolsado' ? 'selected' : ''; ?>>Reembolsado</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Período:</label>
                            <select name="periodo" class="form-select">
                                <option value="">Todos</option>
                                <option value="7d" <?php echo $filtro_periodo === '7d' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                                <option value="30d" <?php echo $filtro_periodo === '30d' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                                <option value="90d" <?php echo $filtro_periodo === '90d' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                                <option value="1y" <?php echo $filtro_periodo === '1y' ? 'selected' : ''; ?>>Último ano</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Pagamento:</label>
                            <select name="pagamento" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($formas_pagamento as $forma): ?>
                                    <option value="<?php echo htmlspecialchars($forma); ?>" 
                                            <?php echo $filtro_pagamento === $forma ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($forma); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </form>
                </div>
                
                <?php if (empty($compras)): ?>
                    <div class="empty-state">
                        <span class="empty-state-icon">📊</span>
                        <h3>
                            <?php if (!empty($filtro_status) || !empty($filtro_periodo) || !empty($filtro_pagamento)): ?>
                                Nenhuma compra encontrada
                            <?php else: ?>
                                Você ainda não fez nenhuma compra
                            <?php endif; ?>
                        </h3>
                        <p>
                            <?php if (!empty($filtro_status) || !empty($filtro_periodo) || !empty($filtro_pagamento)): ?>
                                Tente ajustar os filtros para encontrar suas compras.
                            <?php else: ?>
                                Explore nossa loja e faça sua primeira compra!
                            <?php endif; ?>
                        </p>
                        
                        <div style="margin-top: 2rem;">
                            <?php if (!empty($filtro_status) || !empty($filtro_periodo) || !empty($filtro_pagamento)): ?>
                                <a href="historico.php" class="btn btn-primary" style="margin-right: 1rem;">
                                    Limpar Filtros
                                </a>
                            <?php endif; ?>
                            <a href="../index.php" class="btn btn-primary">
                                Explorar Loja
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Purchases Table -->
                    <table class="purchases-table">
                        <thead>
                            <tr>
                                <th>Jogo</th>
                                <th>Data</th>
                                <th>Preço</th>
                                <th>Pagamento</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($compras as $compra): ?>
                                <tr>
                                    <td>
                                        <div class="game-info">
                                            <div class="game-image">
                                                <?php if (!empty($compra['imagem']) && file_exists(IMAGES_PATH . $compra['imagem'])): ?>
                                                    <img src="../uploads/images/<?php echo htmlspecialchars($compra['imagem']); ?>" 
                                                         alt="<?php echo htmlspecialchars($compra['jogo_nome']); ?>">
                                                <?php else: ?>
                                                    🎮
                                                <?php endif; ?>
                                            </div>
                                            <div class="game-details">
                                                <h4><?php echo htmlspecialchars($compra['jogo_nome']); ?></h4>
                                                <div class="game-category"><?php echo htmlspecialchars($compra['categoria'] ?? 'Sem categoria'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($compra['created_at'])); ?></div>
                                        <div style="font-size: 0.9rem; color: #666;">
                                            <?php echo date('H:i', strtotime($compra['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="price-info">
                                            <div class="price-final"><?php echo formatarPreco($compra['preco_final']); ?></div>
                                            <?php if ($compra['preco_original'] > $compra['preco_final']): ?>
                                                <div class="price-original"><?php echo formatarPreco($compra['preco_original']); ?></div>
                                                <div class="discount-info">
                                                    Desconto: <?php echo formatarPreco($compra['desconto']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo ucfirst($compra['forma_pagamento']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $compra['status']; ?>">
                                            <?php echo ucfirst($compra['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">« Anterior</a>
                            <?php else: ?>
                                <span class="disabled">« Anterior</span>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Próxima »</a>
                            <?php else: ?>
                                <span class="disabled">Próxima »</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Summary -->
                    <div class="summary-section">
                        <h3 style="text-align: center; margin-bottom: 1rem; color: var(--dark);">
                            Resumo dos Resultados
                        </h3>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <span class="summary-value"><?php echo count($compras); ?></span>
                                <span class="summary-label">Compras Exibidas</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-value"><?php echo $total_records; ?></span>
                                <span class="summary-label">Total Encontradas</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-value">
                                    <?php 
                                    $total_pagina = array_sum(array_column($compras, 'preco_final'));
                                    echo formatarPreco($total_pagina);
                                    ?>
                                </span>
                                <span class="summary-label">Valor desta Página</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-value"><?php echo $total_pages; ?></span>
                                <span class="summary-label">Total de Páginas</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>