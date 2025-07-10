<?php
// ============================================================================
// checkout.php - Sistema de Checkout
// ============================================================================

session_start();

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout');
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

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['ativo']) {
    session_destroy();
    header('Location: login.php?message=conta_inativa');
    exit;
}

// Processar compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_compra'])) {
    try {
        $jogo_id = (int)$_POST['jogo_id'];
        $forma_pagamento = $_POST['forma_pagamento'];
        $cupom_codigo = trim($_POST['cupom'] ?? '');
        
        // Verificar se jogo existe e está ativo
        $stmt = $pdo->prepare("SELECT * FROM jogos WHERE id = ? AND ativo = 1");
        $stmt->execute([$jogo_id]);
        $jogo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$jogo) {
            throw new Exception('Jogo não encontrado ou não disponível.');
        }
        
        // Verificar se usuário já possui o jogo
        $stmt = $pdo->prepare("SELECT id FROM usuario_jogos WHERE usuario_id = ? AND jogo_id = ? AND status = 'ativo'");
        $stmt->execute([$user_id, $jogo_id]);
        if ($stmt->fetch()) {
            throw new Exception('Você já possui este jogo!');
        }
        
        // Calcular preços
        $preco_original = $jogo['preco'];
        $preco_final = $jogo['preco_promocional'] ?: $jogo['preco'];
        $desconto = 0;
        
        // Aplicar cupom se fornecido
        if (!empty($cupom_codigo)) {
            $stmt = $pdo->prepare("SELECT * FROM cupons WHERE codigo = ? AND ativo = 1 AND (data_fim IS NULL OR data_fim > NOW()) AND (limite_uso IS NULL OR usado < limite_uso)");
            $stmt->execute([$cupom_codigo]);
            $cupom = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cupom) {
                if ($preco_final >= $cupom['valor_minimo']) {
                    if ($cupom['tipo'] === 'porcentagem') {
                        $desconto = ($preco_final * $cupom['valor']) / 100;
                    } else {
                        $desconto = $cupom['valor'];
                    }
                    $preco_final = max(0, $preco_final - $desconto);
                    
                    // Atualizar uso do cupom
                    $stmt = $pdo->prepare("UPDATE cupons SET usado = usado + 1 WHERE id = ?");
                    $stmt->execute([$cupom['id']]);
                }
            }
        }
        
        // Criar venda
        $stmt = $pdo->prepare("
            INSERT INTO vendas (usuario_id, jogo_id, preco_original, preco_final, desconto, forma_pagamento, status, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $jogo_id,
            $preco_original,
            $preco_final,
            $desconto,
            $forma_pagamento,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        $venda_id = $pdo->lastInsertId();
        
        // Redirecionar baseado na forma de pagamento
        switch ($forma_pagamento) {
            case 'pix':
                header("Location: pagamento.php?venda={$venda_id}&tipo=pix");
                exit;
            case 'cartao':
                header("Location: pagamento.php?venda={$venda_id}&tipo=cartao");
                exit;
            case 'boleto':
                header("Location: pagamento.php?venda={$venda_id}&tipo=boleto");
                exit;
            default:
                // Para jogos gratuitos ou outros métodos
                if ($preco_final == 0) {
                    // Aprovar automaticamente jogos gratuitos
                    $stmt = $pdo->prepare("UPDATE vendas SET status = 'aprovado' WHERE id = ?");
                    $stmt->execute([$venda_id]);
                    
                    // Dar acesso ao jogo
                    $stmt = $pdo->prepare("INSERT INTO usuario_jogos (usuario_id, jogo_id, data_compra, preco_pago, forma_pagamento, status) VALUES (?, ?, NOW(), ?, ?, 'ativo')");
                    $stmt->execute([$user_id, $jogo_id, $preco_final, $forma_pagamento]);
                    
                    header("Location: cliente/biblioteca.php?success=jogo_adicionado");
                    exit;
                } else {
                    header("Location: pagamento.php?venda={$venda_id}");
                    exit;
                }
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Buscar jogo se especificado
$jogo = null;
if (isset($_GET['jogo'])) {
    $jogo_id = (int)$_GET['jogo'];
    $stmt = $pdo->prepare("SELECT * FROM jogos WHERE id = ? AND ativo = 1");
    $stmt->execute([$jogo_id]);
    $jogo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($jogo) {
        // Verificar se usuário já possui o jogo
        $stmt = $pdo->prepare("SELECT id FROM usuario_jogos WHERE usuario_id = ? AND jogo_id = ? AND status = 'ativo'");
        $stmt->execute([$user_id, $jogo_id]);
        if ($stmt->fetch()) {
            header('Location: cliente/biblioteca.php?message=ja_possui');
            exit;
        }
    }
}

if (!$jogo) {
    header('Location: index.php');
    exit;
}

// Calcular preço final
$preco_atual = $jogo['preco_promocional'] ?: $jogo['preco'];
$tem_promocao = $jogo['preco_promocional'] && $jogo['preco_promocional'] < $jogo['preco'];
$desconto_percentual = $tem_promocao ? round((($jogo['preco'] - $jogo['preco_promocional']) / $jogo['preco']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($jogo['nome']); ?> - COMPREJOGOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --bg-light: #f8fafc;
            --bg-dark: #f1f5f9;
            --text-light: #64748b;
            --text-dark: #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: "Segoe UI", system-ui, sans-serif; 
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .checkout-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .game-header {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .game-image {
            width: 150px;
            height: 150px;
            border-radius: 12px;
            object-fit: cover;
            margin-bottom: 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .game-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .game-developer {
            opacity: 0.9;
            font-size: 1rem;
        }

        .pricing {
            padding: 2rem;
            border-bottom: 1px solid var(--bg-dark);
        }

        .price-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .price-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .price-current {
            font-size: 2rem;
            font-weight: 800;
            color: var(--success);
        }

        .price-original {
            font-size: 1.2rem;
            color: var(--text-light);
            text-decoration: line-through;
        }

        .discount-badge {
            background: var(--danger);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .checkout-form {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-size: 1.2rem;
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
            color: var(--text-dark);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--bg-dark);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .payment-option {
            position: relative;
        }

        .payment-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .payment-option label {
            display: block;
            padding: 1.5rem 1rem;
            border: 2px solid var(--bg-dark);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .payment-option input[type="radio"]:checked + label {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .payment-option label:hover {
            border-color: var(--primary);
        }

        .payment-option i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .cupom-section {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .cupom-input {
            display: flex;
            gap: 1rem;
        }

        .cupom-input input {
            flex: 1;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-secondary {
            background: var(--text-light);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .checkout-actions {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin-top: 2rem;
        }

        .order-summary {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
        }

        .summary-row.total {
            border-top: 2px solid var(--bg-dark);
            margin-top: 1rem;
            padding-top: 1rem;
            font-weight: 700;
            font-size: 1.2rem;
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

        .security-info {
            background: #e0f2fe;
            border: 1px solid #0891b2;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #0e7490;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .game-header {
                padding: 1.5rem;
            }

            .game-image {
                width: 120px;
                height: 120px;
            }

            .pricing,
            .checkout-form {
                padding: 1.5rem;
            }

            .price-display {
                flex-direction: column;
                text-align: center;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .checkout-actions {
                flex-direction: column;
            }

            .cupom-input {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shopping-cart"></i> Finalizar Compra</h1>
            <p>Complete sua compra de forma segura</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="checkout-card">
            <!-- Cabeçalho do Jogo -->
            <div class="game-header">
                <?php if ($jogo['imagem']): ?>
                    <img src="uploads/jogos/<?php echo htmlspecialchars($jogo['imagem']); ?>" 
                         alt="<?php echo htmlspecialchars($jogo['nome']); ?>" 
                         class="game-image">
                <?php else: ?>
                    <div class="game-image" style="background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-gamepad" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                <?php endif; ?>
                <h2 class="game-title"><?php echo htmlspecialchars($jogo['nome']); ?></h2>
                <?php if ($jogo['desenvolvedor']): ?>
                    <p class="game-developer">por <?php echo htmlspecialchars($jogo['desenvolvedor']); ?></p>
                <?php endif; ?>
            </div>

            <!-- Preços -->
            <div class="pricing">
                <div class="price-display">
                    <div class="price-info">
                        <div class="price-current">
                            R$ <?php echo number_format($preco_atual, 2, ',', '.'); ?>
                        </div>
                        <?php if ($tem_promocao): ?>
                            <div class="price-original">
                                R$ <?php echo number_format($jogo['preco'], 2, ',', '.'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($tem_promocao): ?>
                        <div class="discount-badge">
                            -<?php echo $desconto_percentual; ?>% OFF
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulário de Checkout -->
            <form method="POST" class="checkout-form">
                <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">

                <!-- Método de Pagamento -->
                <div class="form-section">
                    <h3><i class="fas fa-credit-card"></i> Método de Pagamento</h3>
                    <div class="payment-methods">
                        <div class="payment-option">
                            <input type="radio" name="forma_pagamento" value="pix" id="pix" required>
                            <label for="pix">
                                <i class="fas fa-qrcode"></i>
                                PIX
                            </label>
                        </div>
                        <div class="payment-option">
                            <input type="radio" name="forma_pagamento" value="cartao" id="cartao">
                            <label for="cartao">
                                <i class="fas fa-credit-card"></i>
                                Cartão
                            </label>
                        </div>
                        <div class="payment-option">
                            <input type="radio" name="forma_pagamento" value="boleto" id="boleto">
                            <label for="boleto">
                                <i class="fas fa-barcode"></i>
                                Boleto
                            </label>
                        </div>
                        <?php if ($preco_atual == 0): ?>
                        <div class="payment-option">
                            <input type="radio" name="forma_pagamento" value="gratuito" id="gratuito">
                            <label for="gratuito">
                                <i class="fas fa-gift"></i>
                                Gratuito
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cupom de Desconto -->
                <div class="form-section">
                    <h3><i class="fas fa-ticket-alt"></i> Cupom de Desconto (Opcional)</h3>
                    <div class="cupom-section">
                        <div class="cupom-input">
                            <input type="text" name="cupom" placeholder="Digite seu cupom de desconto" maxlength="50">
                            <button type="button" class="btn btn-secondary" onclick="aplicarCupom()">
                                <i class="fas fa-check"></i> Aplicar
                            </button>
                        </div>
                        <p style="font-size: 0.9rem; color: var(--text-light); margin-top: 0.5rem;">
                            <i class="fas fa-info-circle"></i> Insira um cupom válido para obter desconto
                        </p>
                    </div>
                </div>

                <!-- Resumo do Pedido -->
                <div class="order-summary">
                    <h3 style="margin-bottom: 1rem;"><i class="fas fa-receipt"></i> Resumo do Pedido</h3>
                    <div class="summary-row">
                        <span><?php echo htmlspecialchars($jogo['nome']); ?></span>
                        <span>R$ <?php echo number_format($jogo['preco'], 2, ',', '.'); ?></span>
                    </div>
                    <?php if ($tem_promocao): ?>
                    <div class="summary-row" style="color: var(--success);">
                        <span>Desconto promocional</span>
                        <span>-R$ <?php echo number_format($jogo['preco'] - $jogo['preco_promocional'], 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span style="color: var(--success);">R$ <?php echo number_format($preco_atual, 2, ',', '.'); ?></span>
                    </div>
                </div>

                <!-- Ações -->
                <div class="checkout-actions">
                    <a href="jogo.php?id=<?php echo $jogo['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="submit" name="finalizar_compra" class="btn btn-primary">
                        <i class="fas fa-lock"></i> Finalizar Compra
                    </button>
                </div>

                <!-- Informações de Segurança -->
                <div class="security-info">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Compra Segura:</strong> Seus dados estão protegidos e a transação é processada de forma segura. 
                    Após a confirmação do pagamento, você terá acesso imediato ao jogo em sua biblioteca.
                </div>
            </form>
        </div>
    </div>

    <script>
        function aplicarCupom() {
            const cupomInput = document.querySelector('input[name="cupom"]');
            const cupom = cupomInput.value.trim();
            
            if (!cupom) {
                alert('Digite um cupom válido');
                return;
            }
            
            // Aqui você pode implementar validação AJAX do cupom
            // Por enquanto, apenas uma mensagem
            alert('Funcionalidade de validação de cupom será implementada via AJAX');
        }

        // Auto-selecionar PIX para pagamentos
        document.addEventListener('DOMContentLoaded', function() {
            const pixRadio = document.getElementById('pix');
            const gratuitoRadio = document.getElementById('gratuito');
            
            if (gratuitoRadio) {
                gratuitoRadio.checked = true;
            } else if (pixRadio) {
                pixRadio.checked = true;
            }
        });
    </script>
</body>
</html>