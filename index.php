<?php
// ============================================================================
// index.php - Loja de Jogos COMPREJOGOS - VERSÃO FINAL COM IMAGENS STEAM
// ============================================================================

session_start();
// error_reporting(E_ALL); // Descomente para depurar se necessário
// ini_set('display_errors', 1); // Descomente para depurar se necessário

// Configurações do banco
$db_host = 'localhost';
$db_name = 'minec761_comprejogos';
$db_user = 'minec761_comprejogos';
$db_pass = 'pr9n0xz5zxk2';

$error_message = '';

// Conectar ao banco
try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão com o site. Por favor, tente mais tarde.");
}

$user_logged = $_SESSION['user_id'] ?? null;

// --- LÓGICA DE ADICIONAR AO CARRINHO (CORRIGIDA) ---
if (isset($_POST['add_to_cart']) && $user_logged && isset($pdo)) {
    $game_appid = (int)$_POST['game_appid'];
    
    try {
        $stmt_game_info = $pdo->prepare("SELECT appid, preco FROM jogos WHERE appid = ?");
        $stmt_game_info->execute([$game_appid]);
        $game = $stmt_game_info->fetch(PDO::FETCH_ASSOC);
        
        if ($game) {
            $stmt_owns = $pdo->prepare("SELECT id FROM usuario_jogos WHERE usuario_id = ? AND jogo_appid = ?");
            $stmt_owns->execute([$user_logged, $game_appid]);
            
            if (!$stmt_owns->fetch()) {
                $stmt_cart = $pdo->prepare("INSERT INTO carrinho (usuario_id, jogo_appid, preco) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE preco = VALUES(preco)");
                $stmt_cart->execute([$user_logged, $game_appid, $game['preco']]);
                $_SESSION['cart_message'] = "Jogo adicionado ao carrinho!";
                $_SESSION['cart_message_type'] = 'success';
            } else {
                $_SESSION['cart_message'] = "Você já possui este jogo!";
                $_SESSION['cart_message_type'] = 'warning';
            }
        }
    } catch (Exception $e) {
        $_SESSION['cart_message'] = "Erro ao adicionar ao carrinho!";
        $_SESSION['cart_message_type'] = 'error';
    }
    
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}


// --- LÓGICA DE BUSCA E EXIBIÇÃO ---
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'nome';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$games = [];
$categories = [];
$total_games = 0;
$total_pages = 1;
$stats = ['total_games' => 0, 'total_users' => 0, 'total_sales' => 0];
$cart_items_count = 0;

if (isset($pdo)) {
    try {
        // --- LÓGICA DE BUSCA DE JOGOS (Funcional) ---
        $where_conditions = ["ativo = 1"];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(nome LIKE ? OR desenvolvedor LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($category)) {
            $where_conditions[] = "categoria = ?";
            $params[] = $category;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $order_by = "nome ASC";
        if ($sort === 'preco_asc') $order_by = "preco ASC";
        if ($sort === 'preco_desc') $order_by = "preco DESC";
        
        $count_sql = "SELECT COUNT(*) FROM jogos WHERE $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_games = $count_stmt->fetchColumn();
        $total_pages = ceil($total_games / $per_page);
        
        $sql = "SELECT * FROM jogos WHERE $where_clause ORDER BY $order_by LIMIT $per_page OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $categories = $pdo->query("SELECT DISTINCT categoria FROM jogos WHERE ativo = 1 AND categoria IS NOT NULL AND categoria != '' ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);

        // --- Estatísticas ---
        $stats['total_games'] = $pdo->query("SELECT COUNT(*) FROM jogos WHERE ativo = 1")->fetchColumn();
        $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1")->fetchColumn();
        $stats['total_sales'] = $pdo->query("SELECT COUNT(*) FROM usuario_jogos")->fetchColumn();

        // --- LÓGICA DE CONTAR ITENS DO CARRINHO (Corrigida) ---
        if ($user_logged) {
            $stmt_cart_count = $pdo->prepare("SELECT COUNT(*) FROM carrinho WHERE usuario_id = ?");
            $stmt_cart_count->execute([$user_logged]);
            $cart_items_count = $stmt_cart_count->fetchColumn();
        }
        
    } catch (Exception $e) {
        $error_message = "Erro ao carregar dados da loja: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COMPREJOGOS - Sua Loja de Jogos Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #10b981; --warning: #f59e0b; --dark: #1f2937; --light: #f8fafc; --border: #e5e7eb; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f1f5f9; color: var(--dark); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #5a6fd8; }
        .btn-secondary { background: transparent; color: var(--primary); border: 2px solid var(--primary); }
        .btn-secondary:hover { background: var(--primary); color: white; }
        .header { background: white; padding: 1rem 0; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 20px rgba(0,0,0,0.05); }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 2rem; }
        .logo { font-size: 1.8rem; font-weight: bold; background: linear-gradient(45deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .nav { display: flex; align-items: center; gap: 1rem; }
        .cart-btn { position: relative; }
        .cart-count { position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; }
        .main { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .filters { background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .filters-grid { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end; }
        .form-control { width: 100%; padding: 0.75rem; border: 2px solid var(--border); border-radius: 8px; font-size: 1rem; }
        .games-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .game-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; transition: transform 0.2s ease-in-out; }
        .game-card:hover { transform: translateY(-5px); }
        .game-image { width: 100%; height: 131px; object-fit: cover; background-color: #333; } /* Tamanho 460x215 dividido por ~3.5 */
        .game-content { padding: 1rem; flex-grow: 1; display: flex; flex-direction: column; }
        .game-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; flex-grow: 1; }
        .game-meta { display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border); }
        .game-price { font-size: 1.25rem; font-weight: bold; color: var(--success); }
        .game-price.free { color: var(--primary); }
        .btn-add-cart, .btn-owned { width: 100%; margin-top: 1rem; }
        .btn-add-cart { background: var(--success); color: white; }
        .btn-owned { background: var(--warning); color: #fff; cursor: not-allowed; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; }
        .message.success { background: #d1fae5; color: #065f46; }
        .message.warning { background: #fef3c7; color: #92400e; }
        .empty-state { text-align: center; padding: 4rem 2rem; grid-column: 1 / -1; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php" style="text-decoration: none;" class="logo"><i class="fas fa-gamepad"></i> COMPREJOGOS</a>
            <nav class="nav">
                <?php if ($user_logged): ?>
                    <a href="carrinho.php" class="btn cart-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cart_items_count > 0): ?>
                            <span class="cart-count"><?php echo $cart_items_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="painel_cliente.php" class="btn btn-secondary">Meu Painel</a>
                    <a href="logout.php" class="btn">Sair</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary">Entrar</a>
                    <a href="register.php" class="btn btn-primary">Criar Conta</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main">
        <?php if (isset($_SESSION['cart_message'])): ?>
            <div class="message <?php echo $_SESSION['cart_message_type']; ?>"><?php echo htmlspecialchars($_SESSION['cart_message']); ?></div>
            <?php unset($_SESSION['cart_message'], $_SESSION['cart_message_type']); ?>
        <?php endif; ?>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div><input type="text" name="search" class="form-control" placeholder="Nome do jogo..." value="<?php echo htmlspecialchars($search); ?>"></div>
                    <div>
                        <select name="category" class="form-control">
                            <option value="">Todas Categorias</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <select name="sort" class="form-control">
                            <option value="nome" <?php echo $sort === 'nome' ? 'selected' : ''; ?>>Nome</option>
                            <option value="preco_asc" <?php echo $sort === 'preco_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                            <option value="preco_desc" <?php echo $sort === 'preco_desc' ? 'selected' : ''; ?>>Maior Preço</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </form>
        </div>

        <div class="games-grid">
            <?php if (count($games) > 0): ?>
                <?php foreach ($games as $game): ?>
                    <div class="game-card">
                        <?php
                            // --- LÓGICA DE IMAGEM CORRIGIDA ---
                            $steam_image_url = "https://cdn.akamai.steamstatic.com/steam/apps/{$game['appid']}/header.jpg";
                            $placeholder_svg = "data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 460 215'%3e%3crect width='460' height='215' fill='%232a3f5a'/%3e%3ctext x='50%25' y='50%25' font-family='sans-serif' font-size='20' fill='white' text-anchor='middle' dy='.3em'%3e" . urlencode($game['nome']) . "%3c/text%3e%3c/svg%3e";
                        ?>
                        <img src="<?php echo $steam_image_url; ?>"
                             alt="<?php echo htmlspecialchars($game['nome']); ?>"
                             class="game-image"
                             onerror="this.onerror=null; this.src='<?php echo $placeholder_svg; ?>';">

                        <div class="game-content">
                            <h3 class="game-title"><?php echo htmlspecialchars($game['nome']); ?></h3>
                            <div class="game-meta">
                                <div class="game-price <?php echo $game['preco'] == 0 ? 'free' : ''; ?>">
                                    <?php echo $game['preco'] == 0 ? 'GRÁTIS' : 'R$ ' . number_format($game['preco'], 2, ',', '.'); ?>
                                </div>
                            </div>
                            <?php
                            $user_owns_game = false;
                            if ($user_logged) {
                                $stmt_owns = $pdo->prepare("SELECT id FROM usuario_jogos WHERE usuario_id = ? AND jogo_appid = ?");
                                $stmt_owns->execute([$user_logged, $game['appid']]);
                                $user_owns_game = $stmt_owns->fetch() !== false;
                            }
                            ?>
                            <?php if ($user_owns_game): ?>
                                <button class="btn btn-owned" disabled><i class="fas fa-check"></i> Na Biblioteca</button>
                            <?php elseif ($user_logged): ?>
                                <form method="POST" style="width: 100%; margin-top: 1rem;">
                                    <input type="hidden" name="game_appid" value="<?php echo $game['appid']; ?>">
                                    <button type="submit" name="add_to_cart" class="btn btn-add-cart">
                                        <i class="fas fa-cart-plus"></i> Adicionar
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary" style="width:100%; margin-top: 1rem;">Entrar para Comprar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>Nenhum jogo encontrado</h3>
                    <p>Tente ajustar seus filtros de busca.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>