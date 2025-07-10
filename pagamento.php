<?php
// ============================================================================
// pagamento.php - Sistema de Pagamento
// ============================================================================

session_start();

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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
$venda_id = (int)($_GET['venda'] ?? 0);
$tipo_pagamento = $_GET['tipo'] ?? '';

// Buscar dados da venda
$stmt = $pdo->prepare("
    SELECT v.*, j.nome as jogo_nome, j.imagem, u.login, u.email 
    FROM vendas v 
    JOIN jogos j ON v.jogo_id = j.id 
    JOIN usuarios u ON v.usuario_id = u.id 
    WHERE v.id = ? AND v.usuario_id = ?
");
$stmt->execute([$venda_id, $user_id]);
$venda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venda) {
    header('Location: index.php?error=venda_nao_encontrada');
    exit;
}

$message = '';
$message_type = '';

// Processar confirmação manual de pagamento (simulação)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pagamento'])) {
    try {
        // Atualizar status da venda para aprovado (simulação de pagamento aprovado)
        $stmt = $pdo->prepare("UPDATE vendas SET status = 'aprovado', transaction_id = ?, updated_at = NOW() WHERE id = ?");
        $transaction_id = 'TXN_' . time() . '_' . $venda_id;
        $stmt->execute([$transaction_id, $venda_id]);
        
        // Dar acesso ao jogo
        $stmt = $pdo->prepare("INSERT INTO usuario_jogos (usuario_id, jogo_id, data_compra, preco_pago, forma_pagamento, status) VALUES (?, ?, NOW(), ?, ?, 'ativo')");
        $stmt->execute([$venda['usuario_id'], $venda['jogo_id'], $venda['preco_final'], $venda['forma_pagamento']]);
        
        // Redirecionar para biblioteca
        header('Location: cliente/biblioteca.php?success=compra_aprovada');
        exit;
        
    } catch (Exception $e) {
        $message = 'Erro ao processar pagamento: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Gerar dados do PIX (simulação)
$pix_data = [
    'chave' => 'admin@comprejogos.com',
    'valor' => $venda['preco_final'],
    'identificador' => 'COMPRE' . $venda_id,
    'qr_code' => "data:image/svg+xml;base64," . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="white"/><text x="100" y="100" text-anchor="middle" dominant-baseline="middle" font-size="12">QR Code PIX<br/>Venda #' . $venda_id . '</text></svg>')
];

// Dados do boleto (simulação)
$boleto_data = [
    'codigo_barras' => '03399.12345 67890.123456 78901.234567 1 12345678901234567890',
    'linha_digitavel' => '03391.12345 67890.123456 78901.234567 1 12345678901234567890',
    'vencimento' => date('d/m/Y', strtotime('+3 days')),
    'valor' => $venda['preco_final']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - Venda #<?php echo $venda_id; ?> - COMPREJOGOS</title>
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
            max-width: 900px;
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

        .payment-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .payment-header {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .payment-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .order-info {
            padding: 2rem;
            border-bottom: 1px solid var(--bg-dark);
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .game-thumb {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            background: var(--bg-dark);
        }

        .item-details h3 {
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .item-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--success);
        }

        .payment-method {
            padding: 2rem;
        }

        .method-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .pix-section, .boleto-section, .card-section {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .pix-container {
            display: grid;
            grid-template-columns: 1fr 200px;
            gap: 2rem;
            align-items: center;
        }

        .pix-info h4 {
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .pix-details {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .pix-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--bg-dark);
        }

        .pix-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .pix-detail label {
            font-weight: 600;
            color: var(--text-light);
        }

        .pix-detail span {
            font-weight: 700;
            color: var(--text-dark);
        }

        .qr-code {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .qr-code img {
            width: 180px;
            height: 180px;
            border-radius: 8px;
        }

        .copy-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .copy-btn:hover {
            background: var(--primary-dark);
        }

        .boleto-info {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .boleto-linha {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            background: var(--bg-dark);
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            word-break: break-all;
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

        .btn-success {
            background: var(--success);
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

        .payment-actions {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin-top: 2rem;
        }

        .timer {
            background: var(--warning);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .instructions {
            background: #e0f2fe;
            border: 1px solid #0891b2;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .instructions h4 {
            color: #0e7490;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .instructions ol {
            color: #0e7490;
            margin-left: 1.5rem;
        }

        .instructions li {
            margin-bottom: 0.5rem;
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
            .container {
                padding: 0 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .payment-header,
            .order-info,
            .payment-method {
                padding: 1.5rem;
            }

            .pix-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .order-item {
                flex-direction: column;
                text-align: center;
            }

            .payment-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> Pagamento</h1>
            <p>Finalize sua compra de forma segura</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="payment-card">
            <!-- Cabeçalho do Pagamento -->
            <div class="payment-header">
                <div class="payment-status">
                    <i class="fas fa-clock"></i>
                    Aguardando Pagamento
                </div>
                <h2>Venda #<?php echo $venda_id; ?></h2>
            </div>

            <!-- Informações do Pedido -->
            <div class="order-info">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-dark);">
                    <i class="fas fa-shopping-bag"></i> Resumo do Pedido
                </h3>
                <div class="order-item">
                    <?php if ($venda['imagem']): ?>
                        <img src="uploads/jogos/<?php echo htmlspecialchars($venda['imagem']); ?>" 
                             alt="<?php echo htmlspecialchars($venda['jogo_nome']); ?>" 
                             class="game-thumb">
                    <?php else: ?>
                        <div class="game-thumb" style="display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-gamepad" style="font-size: 2rem; color: var(--text-light);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="item-details">
                        <h3><?php echo htmlspecialchars($venda['jogo_nome']); ?></h3>
                        <div class="item-price">R$ <?php echo number_format($venda['preco_final'], 2, ',', '.'); ?></div>
                        <?php if ($venda['preco_original'] != $venda['preco_final']): ?>
                            <small style="text-decoration: line-through; color: var(--text-light);">
                                R$ <?php echo number_format($venda['preco_original'], 2, ',', '.'); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Método de Pagamento -->
            <div class="payment-method">
                <?php if ($tipo_pagamento === 'pix' || $venda['forma_pagamento'] === 'pix'): ?>
                    <div class="method-title">
                        <i class="fas fa-qrcode"></i>
                        Pagamento via PIX
                    </div>

                    <div class="timer">
                        <i class="fas fa-clock"></i> Você tem 30 minutos para efetuar o pagamento
                    </div>

                    <div class="instructions">
                        <h4><i class="fas fa-info-circle"></i> Como pagar:</h4>
                        <ol>
                            <li>Abra o app do seu banco ou carteira digital</li>
                            <li>Escaneie o QR Code ou copie a chave PIX</li>
                            <li>Confirme o pagamento no valor exato</li>
                            <li>Aguarde a confirmação automática</li>
                        </ol>
                    </div>

                    <div class="pix-section">
                        <div class="pix-container">
                            <div class="pix-info">
                                <h4>Dados para Pagamento:</h4>
                                <div class="pix-details">
                                    <div class="pix-detail">
                                        <label>Chave PIX:</label>
                                        <span id="pixKey"><?php echo $pix_data['chave']; ?></span>
                                    </div>
                                    <div class="pix-detail">
                                        <label>Valor:</label>
                                        <span>R$ <?php echo number_format($pix_data['valor'], 2, ',', '.'); ?></span>
                                    </div>
                                    <div class="pix-detail">
                                        <label>Identificador:</label>
                                        <span><?php echo $pix_data['identificador']; ?></span>
                                    </div>
                                </div>
                                <button class="copy-btn" onclick="copyPixKey()">
                                    <i class="fas fa-copy"></i> Copiar Chave PIX
                                </button>
                            </div>
                            <div class="qr-code">
                                <img src="<?php echo $pix_data['qr_code']; ?>" alt="QR Code PIX">
                                <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-light);">
                                    Escaneie com seu celular
                                </p>
                            </div>
                        </div>
                    </div>

                <?php elseif ($tipo_pagamento === 'boleto' || $venda['forma_pagamento'] === 'boleto'): ?>
                    <div class="method-title">
                        <i class="fas fa-barcode"></i>
                        Pagamento via Boleto
                    </div>

                    <div class="instructions">
                        <h4><i class="fas fa-info-circle"></i> Como pagar:</h4>
                        <ol>
                            <li>Copie a linha digitável abaixo</li>
                            <li>Acesse o internet banking ou app do seu banco</li>
                            <li>Cole o código na opção "Pagar Boleto"</li>
                            <li>Confirme o pagamento até a data de vencimento</li>
                        </ol>
                    </div>

                    <div class="boleto-section">
                        <div class="boleto-info">
                            <h4>Dados do Boleto:</h4>
                            <div class="pix-detail">
                                <label>Vencimento:</label>
                                <span><?php echo $boleto_data['vencimento']; ?></span>
                            </div>
                            <div class="pix-detail">
                                <label>Valor:</label>
                                <span>R$ <?php echo number_format($boleto_data['valor'], 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        
                        <h4>Linha Digitável:</h4>
                        <div class="boleto-linha" id="boletoLine">
                            <?php echo $boleto_data['linha_digitavel']; ?>
                        </div>
                        <button class="copy-btn" onclick="copyBoletoLine()">
                            <i class="fas fa-copy"></i> Copiar Linha Digitável
                        </button>
                    </div>

                <?php elseif ($tipo_pagamento === 'cartao' || $venda['forma_pagamento'] === 'cartao'): ?>
                    <div class="method-title">
                        <i class="fas fa-credit-card"></i>
                        Pagamento via Cartão de Crédito
                    </div>

                    <div class="card-section">
                        <div style="text-align: center; padding: 2rem;">
                            <i class="fas fa-cog fa-spin" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
                            <h3 style="color: var(--text-dark); margin-bottom: 1rem;">Processando...</h3>
                            <p style="color: var(--text-light);">
                                Você será redirecionado para a página segura de pagamento do nosso parceiro.
                            </p>
                            <div style="margin-top: 2rem;">
                                <button class="btn btn-primary" onclick="alert('Redirecionamento para gateway de pagamento seria implementado aqui')">
                                    <i class="fas fa-credit-card"></i> Continuar para Pagamento
                                </button>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="method-title">
                        <i class="fas fa-question-circle"></i>
                        Método de Pagamento
                    </div>
                    <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                        <p>Método de pagamento não especificado.</p>
                    </div>
                <?php endif; ?>

                <!-- Ações -->
                <div class="payment-actions">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar à Loja
                    </a>
                    
                    <div style="display: flex; gap: 1rem;">
                        <!-- Simulação: Botão para confirmar pagamento manualmente -->
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="confirmar_pagamento" class="btn btn-success"
                                    onclick="return confirm('ATENÇÃO: Isso é uma simulação! Confirmar que o pagamento foi efetuado?')">
                                <i class="fas fa-check"></i> Simular Pagamento Aprovado
                            </button>
                        </form>
                        
                        <a href="cliente/biblioteca.php" class="btn btn-primary">
                            <i class="fas fa-book"></i> Minha Biblioteca
                        </a>
                    </div>
                </div>

                <!-- Aviso de Simulação -->
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 1rem; margin-top: 2rem; text-align: center;">
                    <i class="fas fa-exclamation-triangle" style="color: #856404;"></i>
                    <strong style="color: #856404;">AMBIENTE DE DEMONSTRAÇÃO:</strong>
                    <span style="color: #856404;">
                        Este é um sistema de demonstração. Use o botão "Simular Pagamento Aprovado" para testar a funcionalidade.
                    </span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyPixKey() {
            const pixKey = document.getElementById('pixKey').textContent;
            navigator.clipboard.writeText(pixKey).then(function() {
                alert('Chave PIX copiada!');
            }).catch(function() {
                // Fallback para navegadores mais antigos
                const textArea = document.createElement('textarea');
                textArea.value = pixKey;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Chave PIX copiada!');
            });
        }

        function copyBoletoLine() {
            const boletoLine = document.getElementById('boletoLine').textContent;
            navigator.clipboard.writeText(boletoLine).then(function() {
                alert('Linha digitável copiada!');
            }).catch(function() {
                // Fallback para navegadores mais antigos
                const textArea = document.createElement('textarea');
                textArea.value = boletoLine;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Linha digitável copiada!');
            });
        }

        // Timer de 30 minutos (simulação)
        let timeLeft = 30 * 60; // 30 minutos em segundos
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const timerElement = document.querySelector('.timer');
            
            if (timerElement && timeLeft > 0) {
                timerElement.innerHTML = `<i class="fas fa-clock"></i> Tempo restante: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                timeLeft--;
            } else if (timerElement) {
                timerElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tempo esgotado! Gere um novo pagamento.';
                timerElement.style.backgroundColor = 'var(--danger)';
            }
        }

        // Atualizar timer a cada segundo
        if (document.querySelector('.timer')) {
            setInterval(updateTimer, 1000);
        }

        // Simular verificação de pagamento a cada 10 segundos
        function checkPaymentStatus() {
            // Em uma implementação real, isso faria uma requisição AJAX para verificar o status
            console.log('Verificando status do pagamento...');
        }

        setInterval(checkPaymentStatus, 10000);
    </script>
</body>
</html>