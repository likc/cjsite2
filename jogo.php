<?php
// ============================================================================
// jogo.php - Página de Detalhes e Compra do Jogo
// ============================================================================

require_once 'config.php';

$message = '';
$message_type = '';
$jogo_id = (int)($_GET['id'] ?? 0);

if (!$jogo_id) {
    redirect('index.php');
}

try {
    $pdo = conectarBanco();
    
    // Buscar detalhes do jogo
    $stmt = $pdo->prepare("
        SELECT j.*, 
               (SELECT COUNT(*) FROM usuario_jogos uj WHERE uj.jogo_id = j.id) as total_vendas
        FROM jogos j
        WHERE j.id = ? AND j.ativo = 1
    ");
    $stmt->execute([$jogo_id]);
    $jogo = $stmt->fetch();
    
    if (!$jogo) {
        redirect('index.php');
    }
    
    // Verificar se usuário já possui o jogo
    $usuario_possui_jogo = false;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT id FROM usuario_jogos WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$_SESSION['user_id'], $jogo_id]);
        $usuario_possui_jogo = (bool)$stmt->fetch();
    }
    
    // Processar compra
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comprar'])) {
        if (!isLoggedIn()) {
            $message = 'Você precisa estar logado para comprar jogos.';
            $message_type = 'error';
        } elseif ($usuario_possui_jogo) {
            $message = 'Você já possui este jogo!';
            $message_type = 'warning';
        } else {
            $forma_pagamento = sanitizeInput($_POST['forma_pagamento'] ?? '');
            $cupom_codigo = sanitizeInput($_POST['cupom'] ?? '');
            
            if (empty($forma_pagamento)) {
                $message = 'Selecione uma forma de pagamento.';
                $message_type = 'error';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    $preco_final = $jogo['preco_promocional'] ?? $jogo['preco'];
                    $desconto = 0;
                    
                    // Aplicar cupom se fornecido
                    if (!empty($cupom_codigo)) {
                        $stmt = $pdo->prepare("
                            SELECT * FROM cupons 
                            WHERE codigo = ? AND ativo = 1 
                            AND (data_fim IS NULL OR data_fim > NOW())
                            AND (limite_uso IS NULL OR usado < limite_uso)
                        ");
                        $stmt->execute([$cupom_codigo]);
                        $cupom = $stmt->fetch();
                        
                        if ($cupom && $preco_final >= $cupom['valor_minimo']) {
                            if ($cupom['tipo'] === 'porcentagem') {
                                $desconto = ($preco_final * $cupom['valor']) / 100;
                            } else {
                                $desconto = $cupom['valor'];
                            }
                            $preco_final -= $desconto;
                            
                            // Atualizar uso do cupom
                            $pdo->prepare("UPDATE cupons SET usado = usado + 1 WHERE id = ?")->execute([$cupom['id']]);
                        }
                    }
                    
                    // Registrar venda
                    $stmt = $pdo->prepare("
                        INSERT INTO vendas (usuario_id, jogo_id, preco_original, preco_final, desconto, forma_pagamento, status, ip_address, user_agent) 
                        VALUES (?, ?, ?, ?, ?, ?, 'aprovado', ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $jogo_id,
                        $jogo['preco'],
                        $preco_final,
                        $desconto,
                        $forma_pagamento,
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    // Dar acesso ao jogo
                    $stmt = $pdo->prepare("
                        INSERT INTO usuario_jogos (usuario_id, jogo_id, preco_pago, forma_pagamento) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $jogo_id, $preco_final, $forma_pagamento]);
                    
                    $pdo->commit();
                    
                    $message = 'Compra realizada com sucesso! O jogo foi adicionado à sua biblioteca.';
                    $message_type = 'success';
                    $usuario_possui_jogo = true;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    logError("Erro na compra: " . $e->getMessage(), ['user_id' => $_SESSION['user_id'], 'jogo_id' => $jogo_id]);
                    $message = 'Erro ao processar compra. Tente novamente.';
                    $message_type = 'error';
                }
            }
        }
    }
    
    // Buscar jogos relacionados
    $jogos_relacionados = [];
    if (!empty($jogo['categoria'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM jogos 
            WHERE categoria = ? AND id != ? AND ativo = 1 
            ORDER BY RAND() 
            LIMIT 4
        ");
        $stmt->execute([$jogo['categoria'], $jogo_id]);
        $jogos_relacionados = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    logError("Erro na página do jogo: " . $e->getMessage());
    redirect('index.php');
}

$site_name = getSystemSetting('site_name', 'COMPREJOGOS');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($jogo['nome']); ?> - <?php echo $site_name; ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
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
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-link:hover {
            opacity: 0.8;
        }
        
        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
        }
        
        .game-details {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .game-image-main {
            width: 100%;
            height: 400px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            position: relative;
        }
        
        .game-image-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .game-content {
            padding: 2rem;
        }
        
        .game-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .game-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            color: #666;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .meta-value {
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .game-description {
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 2rem;
        }
        
        .game-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .tag {
            background: var(--light);
            color: var(--dark);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
            border: 1px solid var(--border);
        }
        
        .requirements {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .requirements h3 {
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        /* Purchase Sidebar */
        .purchase-sidebar {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .price-section {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }
        
        .price-current {
            font-size: 3rem;
            font-weight: bold;
            color: var(--success);
            display: block;
        }
        
        .price-original {
            font-size: 1.2rem;
            color: #888;
            text-decoration: line-through;
            margin-bottom: 0.5rem;
        }
        
        .discount-badge {
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: bold;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        .purchase-form {
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-select,
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-select:focus,
        .form-input:focus {
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
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            width: 100%;
            font-size: 1.1rem;
            padding: 1rem;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            width: 100%;
            font-size: 1.1rem;
            padding: 1rem;
        }
        
        .btn-disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            width: 100%;
            font-size: 1.1rem;
            padding: 1rem;
        }
        
        .owned-message {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 1rem;
            border: 1px solid #4caf50;
        }
        
        .login-required {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 1rem;
            border: 1px solid #ffc107;
        }
        
        .login-required a {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
        }
        
        /* Message */
        .message {
            margin-bottom: 2rem;
            padding: 1rem;
            border-radius: 5px;
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
        
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* Related Games */
        .related-games {
            grid-column: 1 / -1;
            margin-top: 3rem;
        }
        
        .section-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: var(--dark);
        }
        
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .game-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .game-card:hover {
            transform: translateY(-3px);
        }
        
        .game-card-image {
            width: 100%;
            height: 150px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        
        .game-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .game-card-content {
            padding: 1rem;
        }
        
        .game-card-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .game-card-price {
            color: var(--success);
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .game-card-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .game-card-link:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                gap: 2rem;
                margin: 1rem auto;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .game-title {
                font-size: 2rem;
            }
            
            .game-meta {
                flex-direction: column;
                gap: 1rem;
            }
            
            .price-current {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="back-link">
                ← Voltar à Loja
            </a>
            <div class="logo">
                <h1><?php echo htmlspecialchars($site_name); ?></h1>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Game Details -->
        <div class="game-details">
            <div class="game-image-main">
                <?php if (!empty($jogo['imagem_banner']) && file_exists(IMAGES_PATH . $jogo['imagem_banner'])): ?>
                    <img src="uploads/images/<?php echo htmlspecialchars($jogo['imagem_banner']); ?>" 
                         alt="<?php echo htmlspecialchars($jogo['nome']); ?>">
                <?php elseif (!empty($jogo['imagem']) && file_exists(IMAGES_PATH . $jogo['imagem'])): ?>
                    <img src="uploads/images/<?php echo htmlspecialchars($jogo['imagem']); ?>" 
                         alt="<?php echo htmlspecialchars($jogo['nome']); ?>">
                <?php else: ?>
                    🎮 <?php echo htmlspecialchars($jogo['nome']); ?>
                <?php endif; ?>
            </div>
            
            <div class="game-content">
                <h1 class="game-title"><?php echo htmlspecialchars($jogo['nome']); ?></h1>
                
                <div class="game-meta">
                    <div class="meta-item">
                        <span class="meta-label">Categoria</span>
                        <span class="meta-value"><?php echo htmlspecialchars($jogo['categoria'] ?? 'Não definida'); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Desenvolvedor</span>
                        <span class="meta-value"><?php echo htmlspecialchars($jogo['desenvolvedor'] ?? 'Não informado'); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Vendas</span>
                        <span class="meta-value"><?php echo number_format($jogo['total_vendas']); ?></span>
                    </div>
                    <?php if (!empty($jogo['tamanho_download'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">Tamanho</span>
                        <span class="meta-value"><?php echo htmlspecialchars($jogo['tamanho_download']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($jogo['descricao'])): ?>
                <div class="game-description">
                    <?php echo nl2br(htmlspecialchars($jogo['descricao'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($jogo['tags'])): ?>
                <div class="game-tags">
                    <?php foreach (explode(',', $jogo['tags']) as $tag): ?>
                        <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($jogo['requisitos_minimos'])): ?>
                <div class="requirements">
                    <h3>Requisitos Mínimos</h3>
                    <p><?php echo nl2br(htmlspecialchars($jogo['requisitos_minimos'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Purchase Sidebar -->
        <div class="purchase-sidebar">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="price-section">
                <?php if ($jogo['preco_promocional']): ?>
                    <span class="price-original"><?php echo formatarPreco($jogo['preco']); ?></span>
                    <span class="price-current"><?php echo formatarPreco($jogo['preco_promocional']); ?></span>
                    <div class="discount-badge">
                        <?php 
                        $desconto_pct = round((($jogo['preco'] - $jogo['preco_promocional']) / $jogo['preco']) * 100);
                        echo "-{$desconto_pct}%";
                        ?>
                    </div>
                <?php else: ?>
                    <span class="price-current"><?php echo formatarPreco($jogo['preco']); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if (!isLoggedIn()): ?>
                <div class="login-required">
                    <p>Faça login para comprar este jogo</p>
                    <a href="login.php">Entrar</a> | <a href="register.php">Criar Conta</a>
                </div>
            <?php elseif ($usuario_possui_jogo): ?>
                <div class="owned-message">
                    ✅ Você já possui este jogo!
                </div>
                <a href="cliente/" class="btn btn-success">Ir para Biblioteca</a>
            <?php else: ?>
                <form method="POST" class="purchase-form">
                    <div class="form-group">
                        <label class="form-label">Forma de Pagamento:</label>
                        <select name="forma_pagamento" class="form-select" required>
                            <option value="">Selecione...</option>
                            <option value="pix">PIX</option>
                            <option value="cartao">Cartão de Crédito</option>
                            <option value="boleto">Boleto Bancário</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Cupom de Desconto (opcional):</label>
                        <input type="text" name="cupom" class="form-input" placeholder="Digite o código do cupom">
                    </div>
                    
                    <button type="submit" name="comprar" class="btn btn-primary">
                        💳 Comprar Agora
                    </button>
                </form>
            <?php endif; ?>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border); font-size: 0.9rem; color: #666; text-align: center;">
                <p>✅ Download imediato após compra</p>
                <p>🔒 Pagamento 100% seguro</p>
                <p>📞 Suporte técnico incluso</p>
            </div>
        </div>

        <!-- Related Games -->
        <?php if (!empty($jogos_relacionados)): ?>
        <div class="related-games">
            <h2 class="section-title">Jogos Relacionados</h2>
            <div class="games-grid">
                <?php foreach ($jogos_relacionados as $jogo_rel): ?>
                    <div class="game-card">
                        <div class="game-card-image">
                            <?php if (!empty($jogo_rel['imagem']) && file_exists(IMAGES_PATH . $jogo_rel['imagem'])): ?>
                                <img src="uploads/images/<?php echo htmlspecialchars($jogo_rel['imagem']); ?>" 
                                     alt="<?php echo htmlspecialchars($jogo_rel['nome']); ?>">
                            <?php else: ?>
                                🎮
                            <?php endif; ?>
                        </div>
                        <div class="game-card-content">
                            <h3 class="game-card-title"><?php echo htmlspecialchars($jogo_rel['nome']); ?></h3>
                            <div class="game-card-price">
                                <?php echo formatarPreco($jogo_rel['preco_promocional'] ?? $jogo_rel['preco']); ?>
                            </div>
                            <a href="jogo.php?id=<?php echo $jogo_rel['id']; ?>" class="game-card-link">Ver Detalhes</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>