<?php
// ============================================================================
// admin/vendas.php - Gerenciamento de Vendas
// ============================================================================

session_start();

// Verificar autenticação de admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: auth.php');
    exit;
}

// Configurações do banco
$db_host = 'localhost';
$db_name = 'minec761_comprejogos';
$db_user = 'minec761_comprejogos';
$db_pass = 'pr9n0xz5zxk2';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro de conexão: ' . $e->getMessage());
}

// Processar ações
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update_status':
                    $venda_id = (int)$_POST['venda_id'];
                    $new_status = $_POST['status'];
                    
                    $stmt = $pdo->prepare("UPDATE vendas SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_status, $venda_id]);
                    
                    // Se aprovado, dar acesso ao jogo
                    if ($new_status === 'aprovado') {
                        $stmt = $pdo->prepare("SELECT usuario_id, jogo_id, preco_final FROM vendas WHERE id = ?");
                        $stmt->execute([$venda_id]);
                        $venda = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($venda) {
                            // Adicionar jogo ao usuário
                            $stmt = $pdo->prepare("INSERT IGNORE INTO usuario_jogos (usuario_id, jogo_id, data_compra, preco_pago, forma_pagamento, status) VALUES (?, ?, NOW(), ?, (SELECT forma_pagamento FROM vendas WHERE id = ?), 'ativo')");
                            $stmt->execute([$venda['usuario_id'], $venda['jogo_id'], $venda['preco_final'], $venda_id]);
                        }
                    }
                    
                    $message = 'Status da venda atualizado com sucesso!';
                    $message_type = 'success';
                    break;
                    
                case 'add_manual_sale':
                    $usuario_id = (int)$_POST['usuario_id'];
                    $jogo_id = (int)$_POST['jogo_id'];
                    $preco = (float)$_POST['preco'];
                    $forma_pagamento = $_POST['forma_pagamento'];
                    
                    // Verificar se usuário e jogo existem
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
                    $stmt->execute([$usuario_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Usuário não encontrado');
                    }
                    
                    $stmt = $pdo->prepare("SELECT id, nome FROM jogos WHERE id = ?");
                    $stmt->execute([$jogo_id]);
                    $jogo = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$jogo) {
                        throw new Exception('Jogo não encontrado');
                    }
                    
                    // Criar venda
                    $stmt = $pdo->prepare("INSERT INTO vendas (usuario_id, jogo_id, preco_original, preco_final, forma_pagamento, status, ip_address, created_at) VALUES (?, ?, ?, ?, ?, 'aprovado', ?, NOW())");
                    $stmt->execute([$usuario_id, $jogo_id, $preco, $preco, $forma_pagamento, $_SERVER['REMOTE_ADDR'] ?? '']);
                    
                    $venda_id = $pdo->lastInsertId();
                    
                    // Dar acesso ao jogo
                    $stmt = $pdo->prepare("INSERT IGNORE INTO usuario_jogos (usuario_id, jogo_id, data_compra, preco_pago, forma_pagamento, status) VALUES (?, ?, NOW(), ?, ?, 'ativo')");
                    $stmt->execute([$usuario_id, $jogo_id, $preco, $forma_pagamento]);
                    
                    $message = "Venda manual criada com sucesso! Jogo '{$jogo['nome']}' adicionado ao usuário.";
                    $message_type = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = 'Erro: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Filtros e paginação
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// Construir query
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "v.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.login LIKE ? OR u.email LIKE ? OR j.nome LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Contar total
$count_sql = "SELECT COUNT(*) FROM vendas v 
              JOIN usuarios u ON v.usuario_id = u.id 
              JOIN jogos j ON v.jogo_id = j.id 
              {$where_clause}";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_vendas = $stmt->fetchColumn();
$total_pages = ceil($total_vendas / $per_page);

// Buscar vendas
$offset = ($page - 1) * $per_page;
$sql = "SELECT v.*, u.login, u.email, j.nome as jogo_nome, j.imagem
        FROM vendas v 
        JOIN usuarios u ON v.usuario_id = u.id 
        JOIN jogos j ON v.jogo_id = j.id 
        {$where_clause}
        ORDER BY v.created_at DESC 
        LIMIT {$per_page} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stats = [];
$stats['total_vendas'] = $pdo->query("SELECT COUNT(*) FROM vendas")->fetchColumn();
$stats['vendas_aprovadas'] = $pdo->query("SELECT COUNT(*) FROM vendas WHERE status = 'aprovado'")->fetchColumn();
$stats['vendas_pendentes'] = $pdo->query("SELECT COUNT(*) FROM vendas WHERE status = 'pendente'")->fetchColumn();
$stats['receita_total'] = $pdo->query("SELECT COALESCE(SUM(preco_final), 0) FROM vendas WHERE status = 'aprovado'")->fetchColumn();
$stats['receita_mes'] = $pdo->query("SELECT COALESCE(SUM(preco_final), 0) FROM vendas WHERE status = 'aprovado' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();

// Buscar usuários e jogos para venda manual
$usuarios = $pdo->query("SELECT id, login, email FROM usuarios WHERE ativo = 1 ORDER BY login")->fetchAll(PDO::FETCH_ASSOC);
$jogos = $pdo->query("SELECT id, nome, preco FROM jogos WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Vendas - COMPREJOGOS Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --info: #2563eb;
            --bg-light: #f8fafc;
            --bg-dark: #f1f5f9;
            --text-light: #64748b;
            --text-dark: #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", system-ui, sans-serif; background: var(--bg-light); color: var(--text-dark); }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 { font-size: 1.8rem; font-weight: 700; }
        .header .subtitle { opacity: 0.9; margin-top: 0.25rem; }

        .nav-links {
            display: flex;
            gap: 1rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.3s;
            font-weight: 500;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover { transform: translateY(-2px); }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-light);
            font-weight: 600;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body { padding: 1.5rem 2rem; }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-light);
        }

        select, input[type="text"] {
            padding: 0.75rem;
            border: 2px solid var(--bg-dark);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.3s;
        }

        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--bg-dark);
            vertical-align: middle;
        }

        th {
            background: var(--bg-dark);
            font-weight: 700;
            color: var(--text-light);
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pendente { background: #fef3c7; color: #92400e; }
        .status-aprovado { background: #dcfce7; color: #15803d; }
        .status-cancelado { background: #fee2e2; color: #b91c1c; }
        .status-reembolsado { background: #e5e7eb; color: #4b5563; }

        .game-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .game-thumb {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--bg-dark);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-email {
            color: var(--text-light);
            font-size: 0.875rem;
        }

        .price {
            font-weight: 700;
            color: var(--success);
            font-size: 1.1rem;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a, .pagination span {
            padding: 0.75rem 1rem;
            border: 1px solid var(--bg-dark);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--bg-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-light);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--bg-dark);
            border-radius: 8px;
            font-size: 1rem;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .message.success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 0.75rem 0.5rem;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1><i class="fas fa-shopping-cart"></i> Gerenciamento de Vendas</h1>
                <div class="subtitle">Controle completo das vendas e transações</div>
            </div>
            <div class="nav-links">
                <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="jogos.php"><i class="fas fa-gamepad"></i> Jogos</a>
                <a href="usuarios.php"><i class="fas fa-users"></i> Usuários</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_vendas']); ?></div>
                <div class="stat-label">Total de Vendas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['vendas_aprovadas']); ?></div>
                <div class="stat-label">Vendas Aprovadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['vendas_pendentes']); ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">R$ <?php echo number_format($stats['receita_total'], 2, ',', '.'); ?></div>
                <div class="stat-label">Receita Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">R$ <?php echo number_format($stats['receita_mes'], 2, ',', '.'); ?></div>
                <div class="stat-label">Receita do Mês</div>
            </div>
        </div>

        <!-- Filtros e Ações -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-filter"></i> Filtros e Ações</span>
                <button class="btn btn-success" onclick="openManualSaleModal()">
                    <i class="fas fa-plus"></i> Venda Manual
                </button>
            </div>
            <div class="card-body">
                <form method="GET" class="filters">
                    <div class="filter-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="pendente" <?php echo $status_filter === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="aprovado" <?php echo $status_filter === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                            <option value="cancelado" <?php echo $status_filter === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            <option value="reembolsado" <?php echo $status_filter === 'reembolsado' ? 'selected' : ''; ?>>Reembolsado</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Buscar:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Usuário, email ou jogo...">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Vendas -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-list"></i> Vendas (<?php echo number_format($total_vendas); ?> total)</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($vendas)): ?>
                    <div style="padding: 3rem; text-align: center; color: var(--text-light);">
                        <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Nenhuma venda encontrada</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Jogo</th>
                                <th>Valor</th>
                                <th>Pagamento</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendas as $venda): ?>
                                <tr>
                                    <td>#<?php echo $venda['id']; ?></td>
                                    <td>
                                        <div class="user-info">
                                            <div><?php echo htmlspecialchars($venda['login']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($venda['email']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="game-info">
                                            <?php if ($venda['imagem']): ?>
                                                <img src="../uploads/jogos/<?php echo htmlspecialchars($venda['imagem']); ?>" 
                                                     alt="<?php echo htmlspecialchars($venda['jogo_nome']); ?>" 
                                                     class="game-thumb">
                                            <?php else: ?>
                                                <div class="game-thumb"></div>
                                            <?php endif; ?>
                                            <div><?php echo htmlspecialchars($venda['jogo_nome']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="price">R$ <?php echo number_format($venda['preco_final'], 2, ',', '.'); ?></div>
                                        <?php if ($venda['preco_original'] != $venda['preco_final']): ?>
                                            <small style="text-decoration: line-through; color: var(--text-light);">
                                                R$ <?php echo number_format($venda['preco_original'], 2, ',', '.'); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($venda['forma_pagamento']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $venda['status']; ?>">
                                            <?php echo ucfirst($venda['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venda['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($venda['status'] === 'pendente'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="venda_id" value="<?php echo $venda['id']; ?>">
                                                    <input type="hidden" name="status" value="aprovado">
                                                    <button type="submit" class="btn btn-success" title="Aprovar Venda"
                                                            onclick="return confirm('Aprovar esta venda?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="venda_id" value="<?php echo $venda['id']; ?>">
                                                    <input type="hidden" name="status" value="cancelado">
                                                    <button type="submit" class="btn btn-danger" title="Cancelar Venda"
                                                            onclick="return confirm('Cancelar esta venda?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($venda['status'] === 'aprovado'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="venda_id" value="<?php echo $venda['id']; ?>">
                                                    <input type="hidden" name="status" value="reembolsado">
                                                    <button type="submit" class="btn btn-warning" title="Reembolsar"
                                                            onclick="return confirm('Reembolsar esta venda? O acesso ao jogo será removido.')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">&laquo; Primeira</a>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">&lsaquo; Anterior</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">Próxima &rsaquo;</a>
                    <a href="?page=<?php echo $total_pages; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">Última &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Venda Manual -->
    <div class="modal" id="manualSaleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Adicionar Venda Manual</h2>
                <button class="modal-close" onclick="closeManualSaleModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_manual_sale">
                
                <div class="form-group">
                    <label>Usuário:</label>
                    <select name="usuario_id" required>
                        <option value="">Selecione um usuário</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?php echo $usuario['id']; ?>">
                                <?php echo htmlspecialchars($usuario['login']); ?> (<?php echo htmlspecialchars($usuario['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Jogo:</label>
                    <select name="jogo_id" required id="jogoSelect">
                        <option value="">Selecione um jogo</option>
                        <?php foreach ($jogos as $jogo): ?>
                            <option value="<?php echo $jogo['id']; ?>" data-preco="<?php echo $jogo['preco']; ?>">
                                <?php echo htmlspecialchars($jogo['nome']); ?> - R$ <?php echo number_format($jogo['preco'], 2, ',', '.'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Preço:</label>
                    <input type="number" name="preco" step="0.01" min="0" required id="precoInput">
                </div>

                <div class="form-group">
                    <label>Forma de Pagamento:</label>
                    <select name="forma_pagamento" required>
                        <option value="">Selecione</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão de Crédito</option>
                        <option value="boleto">Boleto</option>
                        <option value="manual">Manual</option>
                        <option value="gratuito">Gratuito</option>
                    </select>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn" onclick="closeManualSaleModal()" style="background: var(--text-light); color: white;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Criar Venda
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openManualSaleModal() {
            document.getElementById('manualSaleModal').style.display = 'flex';
        }

        function closeManualSaleModal() {
            document.getElementById('manualSaleModal').style.display = 'none';
        }

        // Auto-preencher preço quando jogo for selecionado
        document.getElementById('jogoSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const preco = selectedOption.getAttribute('data-preco');
            if (preco) {
                document.getElementById('precoInput').value = preco;
            }
        });

        // Fechar modal ao clicar fora
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('manualSaleModal');
            if (e.target === modal) {
                closeManualSaleModal();
            }
        });
    </script>
</body>
</html>