<?php
// ============================================================================
// carrinho.php - Sistema de Carrinho de Compras CORRIGIDO
// ============================================================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['remove_item'])) {
            $game_appid = (int)$_POST['game_appid']; // --- CORRIGIDO ---
            
            $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ? AND jogo_appid = ?"); // --- CORRIGIDO ---
            $stmt->execute([$user_id, $game_appid]);
            
            $message = "Item removido do carrinho!";
            $message_type = 'success';
        }
        
        if (isset($_POST['clear_cart'])) {
            $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ?");
            $stmt->execute([$user_id]);
            
            $message = "Carrinho limpo!";
            $message_type = 'success';
        }
        
        if (isset($_POST['checkout'])) {
            // Buscar itens do carrinho
            $stmt = $pdo->prepare("
                SELECT c.*, j.nome 
                FROM carrinho c 
                JOIN jogos j ON c.jogo_appid = j.appid -- CORRIGIDO
                WHERE c.usuario_id = ?
            ");
            $stmt->execute([$user_id]);
            $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($cart_items) > 0) {
                $total = array_sum(array_column($cart_items, 'preco'));
                
                $pdo->beginTransaction();
                
                try {
                    // Criar pedido
                    $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, total, status, metodo_pagamento) VALUES (?, ?, 'aprovado', 'sistema')");
                    $stmt->execute([$user_id, $total]);
                    $pedido_id = $pdo->lastInsertId();
                    
                    foreach ($cart_items as $item) {
                        // Verificar se usuário já possui o jogo
                        $stmt_check = $pdo->prepare("SELECT id FROM usuario_jogos WHERE usuario_id = ? AND jogo_appid = ?"); // --- CORRIGIDO ---
                        $stmt_check->execute([$user_id, $item['jogo_appid']]);
                        
                        if (!$stmt_check->fetch()) {
                            // Adicionar item ao pedido
                            $stmt_item = $pdo->prepare("INSERT INTO pedido_itens (pedido_id, jogo_appid, preco, quantidade) VALUES (?, ?, ?, 1)"); // --- CORRIGIDO ---
                            $stmt_item->execute([$pedido_id, $item['jogo_appid'], $item['preco']]);
                            
                            // Adicionar jogo à biblioteca do usuário
                            $stmt_lib = $pdo->prepare("INSERT IGNORE INTO usuario_jogos (usuario_id, jogo_appid, preco_pago, data_compra) VALUES (?, ?, ?, NOW())"); // --- CORRIGIDO ---
                            $stmt_lib->execute([$user_id, $item['jogo_appid'], $item['preco']]);
                        }
                    }
                    
                    // Limpar carrinho
                    $stmt_clear = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ?");
                    $stmt_clear->execute([$user_id]);
                    
                    $pdo->commit();
                    
                    $message = "🎉 Compra realizada com sucesso! Os jogos foram adicionados à sua biblioteca.";
                    $message_type = 'success';
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $message = "Erro ao processar compra: " . $e->getMessage();
                    $message_type = 'error';
                }
            } else {
                $message = "Carrinho vazio!";
                $message_type = 'warning';
            }
        }
        
    } catch (Exception $e) {
        $message = "Erro geral: " . $e->getMessage();
        $message_type = 'error';
    }
    
    $_SESSION['cart_message'] = $message;
    $_SESSION['cart_message_type'] = $message_type;
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar mensagem da sessão
if (isset($_SESSION['cart_message'])) {
    $message = $_SESSION['cart_message'];
    $message_type = $_SESSION['cart_message_type'];
    unset($_SESSION['cart_message'], $_SESSION['cart_message_type']);
}

// Buscar itens do carrinho
$cart_items = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*, j.nome, j.appid, j.descricao, j.imagem, j.categoria, j.desenvolvedor
        FROM carrinho c 
        JOIN jogos j ON c.jogo_appid = j.appid -- CORRIGIDO
        WHERE c.usuario_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = "Erro ao carregar o carrinho: " . $e->getMessage();
    $message_type = "error";
    $cart_items = [];
}

$cart_total = count($cart_items) > 0 ? array_sum(array_column($cart_items, 'preco')) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - COMPREJOGOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #10b981; --error: #ef4444; --dark: #1f2937; --border: #e5e7eb; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .header { background: rgba(255, 255, 255, 0.95); padding: 1rem 0; box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 2rem; }
        .logo { font-size: 1.8rem; font-weight: bold; background: linear-gradient(45deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .nav-links a { color: var(--dark); text-decoration: none; font-weight: 500; }
        .main { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .page-title { color: white; text-align: center; margin-bottom: 2rem; }
        .cart-container { display: grid; grid-template-columns: 1fr 350px; gap: 2rem; }
        .cart-items, .cart-summary { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .cart-item { display: flex; gap: 1rem; padding: 1.5rem 0; border-bottom: 1px solid var(--border); }
        .item-image { width: 120px; height: 80px; object-fit: cover; border-radius: 8px; background: #eee; }
        .item-title { font-size: 1.25rem; font-weight: bold; }
        .item-actions { display: flex; flex-direction: column; justify-content: space-between; align-items: flex-end; }
        .item-price { font-size: 1.5rem; font-weight: bold; color: var(--success); }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-danger { background: var(--error); color: white; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .btn-primary { background: var(--primary); color: white; width: 100%; padding: 1rem; font-size: 1.1rem; margin-top: 1rem; }
        .empty-cart { text-align: center; padding: 4rem 2rem; color: #666; background: white; border-radius: 12px; grid-column: 1 / -1; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .message.success { background: #d1fae5; color: #065f46; }
        .message.error { background: #fee2e2; color: #991b1b; }
        .message.warning { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo"><i class="fas fa-gamepad"></i> COMPREJOGOS</div>
            <div class="nav-links" style="display:flex; gap: 2rem;">
                <a href="index.php"><i class="fas fa-home"></i> Início</a>
                <a href="painel_cliente.php"><i class="fas fa-user"></i> Meu Painel</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </header>
    <main class="main">
        <div class="page-title">
            <h1><i class="fas fa-shopping-cart"></i> Meu Carrinho</h1>
        </div>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (count($cart_items) > 0): ?>
            <div class="cart-container">
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <img src="<?php echo htmlspecialchars($item['imagem'] ?: 'https://via.placeholder.com/120x80'); ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>" class="item-image">
                            <div class="item-info">
                                <h3 class="item-title"><?php echo htmlspecialchars($item['nome']); ?></h3>
                            </div>
                            <div class="item-actions">
                                <div class="item-price"><?php echo $item['preco'] == 0 ? 'GRÁTIS' : 'R$ ' . number_format($item['preco'], 2, ',', '.'); ?></div>
                                <form method="POST">
                                    <input type="hidden" name="game_appid" value="<?php echo $item['jogo_appid']; ?>"> <button type="submit" name="remove_item" class="btn btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="cart-summary">
                    <div class="summary-row"><span>Subtotal</span><span>R$ <?php echo number_format($cart_total, 2, ',', '.'); ?></span></div>
                    <div class="summary-row" style="font-weight:bold;"><span>Total</span><span>R$ <?php echo number_format($cart_total, 2, ',', '.'); ?></span></div>
                    <form method="POST">
                        <button type="submit" name="checkout" class="btn btn-primary"><i class="fas fa-credit-card"></i> Finalizar Compra</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart" style="font-size: 4rem; color: #e5e7eb; margin-bottom: 1rem;"></i>
                <h3>Seu carrinho está vazio</h3>
                <a href="index.php" class="btn btn-primary" style="margin-top:1rem;">Ver Jogos</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>