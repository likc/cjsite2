<?php
// ============================================================================
// painel_cliente.php - Painel do Cliente
// ============================================================================

session_start();

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Configurações do banco
$db_host = 'localhost';
$db_name = 'minec761_comprejogos';
$db_user = 'minec761_comprejogos';
$db_pass = 'pr9n0xz5zxk2';

$message = '';
$message_type = '';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Buscar dados do usuário
$user = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    $message = "Erro ao carregar dados do usuário: " . $e->getMessage();
    $message_type = 'error';
}

// Processar alteração de senha
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "Todos os campos são obrigatórios!";
        $message_type = 'error';
    } elseif ($current_password !== $user['senha']) {
        $message = "Senha atual incorreta!";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Nova senha e confirmação não coincidem!";
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = "Nova senha deve ter pelo menos 6 caracteres!";
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->execute([$new_password, $user_id]);
            
            $user['senha'] = $new_password; // Atualizar localmente
            $message = "Senha alterada com sucesso!";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "Erro ao alterar senha!";
            $message_type = 'error';
        }
    }
}

// Buscar jogos do usuário
$user_games = [];
try {
    // Verificar se a tabela usuario_jogos existe
    $tables = $pdo->query("SHOW TABLES LIKE 'usuario_jogos'")->fetchAll();
    
    if (!empty($tables)) {
        // Verificar colunas disponíveis
        $columns = $pdo->query("DESCRIBE usuario_jogos")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('data_compra', $columns) && in_array('preco_pago', $columns)) {
            // Query completa
$stmt = $pdo->prepare("
    SELECT j.*, uj.data_compra, uj.preco_pago
    FROM usuario_jogos uj 
    JOIN jogos j ON uj.jogo_appid = j.appid -- CORRIGIDO
    WHERE uj.usuario_id = ? 
    ORDER BY uj.data_compra DESC
");
        } else {
            // Query básica
            $stmt = $pdo->prepare("
                SELECT j.*, 'N/A' as data_compra, 0 as preco_pago
                FROM usuario_jogos uj 
                JOIN jogos j ON uj.jogo_id = j.id 
                WHERE uj.usuario_id = ? 
                ORDER BY j.nome ASC
            ");
        }
        
        $stmt->execute([$user_id]);
        $user_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $message = "Aviso: Erro ao carregar biblioteca de jogos. " . $e->getMessage();
    $message_type = 'warning';
}

// Buscar histórico de pedidos
$orders = [];
try {
    // Verificar se a tabela pedidos existe
    $tables = $pdo->query("SHOW TABLES LIKE 'pedidos'")->fetchAll();
    
    if (!empty($tables)) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COALESCE((SELECT COUNT(*) FROM pedido_itens pi WHERE pi.pedido_id = p.id), 0) as total_jogos
            FROM pedidos p 
            WHERE p.usuario_id = ? 
            ORDER BY p.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Silenciar erro de histórico pois não é crítico
    $orders = [];
}

// Estatísticas do usuário
$stats = [
    'total_games' => count($user_games),
    'total_spent' => 0,
    'total_orders' => count($orders),
    'member_since' => isset($user['created_at']) ? $user['created_at'] : date('Y-m-d')
];

// Calcular total gasto
if (!empty($user_games)) {
    foreach ($user_games as $game) {
        if (isset($game['preco_pago']) && is_numeric($game['preco_pago'])) {
            $stats['total_spent'] += $game['preco_pago'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Painel - COMPREJOGOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
            --border: #e5e7eb;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            color: var(--dark);
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
        }
        
        .nav-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-title {
            color: white;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .page-title h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .user-welcome {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 12px;
            color: white;
            margin-bottom: 2rem;
            text-align: center;
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
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .panel-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            background: #059669;
        }
        
        .games-section {
            grid-column: 1 / -1;
        }
        
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .game-card {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .game-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .game-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
        }
        
        .game-info {
            padding: 1rem;
        }
        
        .game-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .game-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .orders-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .order-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-info h4 {
            margin-bottom: 0.25rem;
        }
        
        .order-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-total {
            font-weight: bold;
            color: var(--success);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-aprovado {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pendente {
            background: #fef3c7;
            color: #92400e;
        }
        
        .download-section {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .download-section h3 {
            margin-bottom: 1rem;
        }
        
        .download-section p {
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }
        
        .btn-download {
            background: white;
            color: var(--primary);
            font-size: 1.1rem;
            padding: 1rem 2rem;
        }
        
        .btn-download:hover {
            background: var(--light);
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border);
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .games-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-gamepad"></i> COMPREJOGOS
            </div>
            
            <div class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Início</a>
                <a href="carrinho.php"><i class="fas fa-shopping-cart"></i> Carrinho</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="page-title">
            <h1><i class="fas fa-user-circle"></i> Meu Painel</h1>
        </div>
        
        <div class="user-welcome">
            <h2>Bem-vindo, <?php echo htmlspecialchars($user['login']); ?>!</h2>
            <p>Gerencie sua conta e acesse seus jogos</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Download do Launcher -->
        <div class="download-section">
            <h3><i class="fas fa-download"></i> Download do COMPREJOGOS Launcher (v0.3.6)</h3>
            <p>Baixe nosso launcher oficial para ativar e jogar os seus jogos comprados</p>
            <a href="files/COMPREJOGOS.zip" download class="btn btn-download">
                <i class="fas fa-download"></i> Baixar Launcher (Windows)
            </a>
        </div>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_games']; ?></div>
                <div class="stat-label">Jogos na Biblioteca</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">R$ <?php echo number_format($stats['total_spent'], 2, ',', '.'); ?></div>
                <div class="stat-label">Total Investido</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                <div class="stat-label">Pedidos Realizados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo date('d/m/Y', strtotime($stats['member_since'])); ?></div>
                <div class="stat-label">Membro Desde</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Alterar Senha -->
            <div class="panel-section">
                <h3 class="section-title">
                    <i class="fas fa-key"></i> Alterar Senha
                </h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Senha Atual:</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nova Senha:</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nova Senha:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-save"></i> Alterar Senha
                    </button>
                </form>
            </div>

            <!-- Histórico de Pedidos -->
            <div class="panel-section">
                <h3 class="section-title">
                    <i class="fas fa-history"></i> Últimos Pedidos
                </h3>
                
                <div class="orders-list">
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-item">
                                <div class="order-info">
                                    <h4>Pedido #<?php echo $order['id']; ?></h4>
                                    <div class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
                                    <div><?php echo $order['total_jogos']; ?> jogo(s)</div>
                                </div>
                                <div>
                                    <div class="order-total">R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></div>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>Nenhum pedido realizado ainda</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Minha Biblioteca -->
            <div class="panel-section games-section">
                <h3 class="section-title">
                    <i class="fas fa-gamepad"></i> Minha Biblioteca (<?php echo count($user_games); ?> jogos)
                </h3>
                
                <?php if (count($user_games) > 0): ?>
                    <div class="games-grid">
                        <?php foreach ($user_games as $game): ?>
                            <div class="game-card">
                                <img src="<?php echo htmlspecialchars($game['imagem'] ?: 'https://via.placeholder.com/250x120?text=Sem+Imagem'); ?>" 
                                     alt="<?php echo htmlspecialchars($game['nome']); ?>" 
                                     class="game-image"
                                     onerror="this.src='https://via.placeholder.com/250x120?text=Erro'">
                                
                                <div class="game-info">
                                    <div class="game-title"><?php echo htmlspecialchars($game['nome']); ?></div>
                                    <div class="game-date">
                                        Adquirido em: <?php echo date('d/m/Y', strtotime($game['data_compra'])); ?>
                                    </div>
                                    <?php if ($game['preco_pago'] > 0): ?>
                                        <div style="color: var(--success); font-weight: bold; margin-top: 0.5rem;">
                                            Pago: R$ <?php echo number_format($game['preco_pago'], 2, ',', '.'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-gamepad"></i>
                        <h4>Sua biblioteca está vazia</h4>
                        <p>Compre jogos na loja para adicioná-los à sua biblioteca</p>
                        <br>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart"></i> Ver Jogos
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>