<?php
// ============================================================================
// cliente/suporte.php - Suporte ao Cliente
// ============================================================================

require_once '../config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('../login.php?redirect=cliente/suporte.php');
}

$message = '';
$message_type = '';

try {
    $pdo = conectarBanco();
    
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        redirect('../logout.php');
    }
    
    // Processar envio de ticket de suporte
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_ticket'])) {
        $assunto = sanitizeInput($_POST['assunto'] ?? '');
        $categoria = sanitizeInput($_POST['categoria'] ?? '');
        $prioridade = sanitizeInput($_POST['prioridade'] ?? 'normal');
        $mensagem = sanitizeInput($_POST['mensagem'] ?? '');
        
        if (empty($assunto) || empty($categoria) || empty($mensagem)) {
            $message = 'Todos os campos são obrigatórios.';
            $message_type = 'error';
        } elseif (strlen($mensagem) < 10) {
            $message = 'A mensagem deve ter pelo menos 10 caracteres.';
            $message_type = 'error';
        } else {
            try {
                // Criar tabela de tickets se não existir
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `tickets_suporte` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `usuario_id` int(11) NOT NULL,
                        `assunto` varchar(200) NOT NULL,
                        `categoria` varchar(50) NOT NULL,
                        `prioridade` enum('baixa','normal','alta','urgente') DEFAULT 'normal',
                        `status` enum('aberto','em_andamento','aguardando_cliente','resolvido','fechado') DEFAULT 'aberto',
                        `mensagem` text NOT NULL,
                        `resposta_admin` text DEFAULT NULL,
                        `admin_id` int(11) DEFAULT NULL,
                        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `usuario_id` (`usuario_id`),
                        KEY `status` (`status`),
                        KEY `categoria` (`categoria`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Inserir ticket
                $stmt = $pdo->prepare("
                    INSERT INTO tickets_suporte (usuario_id, assunto, categoria, prioridade, mensagem)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $assunto, $categoria, $prioridade, $mensagem]);
                
                $ticket_id = $pdo->lastInsertId();
                
                $message = "Ticket #{$ticket_id} criado com sucesso! Nossa equipe entrará em contato em breve.";
                $message_type = 'success';
                
                // Limpar campos
                $assunto = $categoria = $mensagem = '';
                
            } catch (Exception $e) {
                logError("Erro ao criar ticket: " . $e->getMessage());
                $message = 'Erro ao enviar ticket. Tente novamente.';
                $message_type = 'error';
            }
        }
    }
    
    // Buscar tickets do usuário
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM tickets_suporte 
            WHERE usuario_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $tickets = $stmt->fetchAll();
    } catch (Exception $e) {
        $tickets = [];
    }
    
} catch (Exception $e) {
    logError("Erro na página de suporte: " . $e->getMessage());
    $message = "Erro ao carregar página. Tente novamente.";
    $message_type = 'error';
}

$site_name = getSystemSetting('site_name', 'COMPREJOGOS');
$email_admin = getSystemSetting('email_admin', 'admin@comprejogos.com');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte - <?php echo $site_name; ?></title>
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
        
        /* Support Options */
        .support-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .support-option {
            background: var(--light);
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            border: 2px solid transparent;
        }
        
        .support-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .support-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .support-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .support-description {
            color: #666;
            margin-bottom: 1.5rem;
        }
        
        /* Form */
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: grid;
            gap: 1.5rem;
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
            min-height: 120px;
        }
        
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
        
        .btn-info {
            background: var(--info);
            color: white;
        }
        
        .btn-info:hover:not(:disabled) {
            background: #1e40af;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* Tickets Table */
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .tickets-table th,
        .tickets-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .tickets-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-aberto {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-em_andamento {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-aguardando_cliente {
            background: #fed7d7;
            color: #9b2c2c;
        }
        
        .status-resolvido {
            background: #d4edda;
            color: #155724;
        }
        
        .status-fechado {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-baixa {
            background: #e6fffa;
            color: #065f46;
        }
        
        .priority-normal {
            background: #e0f2fe;
            color: #0c4a6e;
        }
        
        .priority-alta {
            background: #fff7ed;
            color: #9a3412;
        }
        
        .priority-urgente {
            background: #fef2f2;
            color: #991b1b;
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
        
        /* FAQ */
        .faq-section {
            margin-top: 2rem;
        }
        
        .faq-item {
            background: var(--light);
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        
        .faq-question {
            padding: 1rem;
            background: white;
            border: none;
            width: 100%;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .faq-question:hover {
            background: var(--light);
        }
        
        .faq-answer {
            padding: 0 1rem 1rem;
            color: #666;
            display: none;
        }
        
        .faq-answer.active {
            display: block;
        }
        
        /* Contact Info */
        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .contact-item {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .contact-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
            color: var(--primary);
        }
        
        .contact-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .contact-value {
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
            
            .support-options {
                grid-template-columns: 1fr;
            }
            
            .tickets-table {
                font-size: 0.9rem;
            }
            
            .tickets-table th,
            .tickets-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .contact-info {
                grid-template-columns: 1fr;
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
                <li><a href="historico.php"><span class="icon">📊</span> Histórico de Compras</a></li>
                <li><a href="perfil.php"><span class="icon">👤</span> Meu Perfil</a></li>
                <li><a href="download.php"><span class="icon">📥</span> Download Launcher</a></li>
                <li><a href="suporte.php" class="active"><span class="icon">💬</span> Suporte</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Support Options -->
            <div class="card">
                <h1 class="card-title">
                    💬 Central de Suporte
                </h1>
                
                <div class="support-options">
                    <div class="support-option">
                        <span class="support-icon">🎫</span>
                        <h3 class="support-title">Abrir Ticket</h3>
                        <p class="support-description">
                            Envie uma solicitação detalhada e nossa equipe responderá em até 24 horas.
                        </p>
                        <a href="#novo-ticket" class="btn btn-primary">Criar Ticket</a>
                    </div>
                    
                    <div class="support-option">
                        <span class="support-icon">💬</span>
                        <h3 class="support-title">WhatsApp</h3>
                        <p class="support-description">
                            Converse conosco diretamente pelo WhatsApp para suporte rápido.
                        </p>
                        <a href="https://api.whatsapp.com/send?phone=5511999999999&text=Olá, preciso de ajuda" 
                           target="_blank" class="btn btn-success">Abrir WhatsApp</a>
                    </div>
                    
                    <div class="support-option">
                        <span class="support-icon">📧</span>
                        <h3 class="support-title">Email</h3>
                        <p class="support-description">
                            Envie um email diretamente para nossa equipe de suporte.
                        </p>
                        <a href="mailto:<?php echo htmlspecialchars($email_admin); ?>" class="btn btn-info">Enviar Email</a>
                    </div>
                </div>
            </div>
            
            <!-- New Ticket Form -->
            <div class="card" id="novo-ticket">
                <h2 class="card-title">
                    🎫 Abrir Novo Ticket
                </h2>
                
                <form method="POST" class="form-section" id="ticketForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Assunto do Ticket:</label>
                            <input type="text" name="assunto" class="form-input" 
                                   value="<?php echo htmlspecialchars($assunto ?? ''); ?>" 
                                   required maxlength="200" 
                                   placeholder="Descreva brevemente o problema">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Categoria:</label>
                                <select name="categoria" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <option value="tecnico" <?php echo ($categoria ?? '') === 'tecnico' ? 'selected' : ''; ?>>Problema Técnico</option>
                                    <option value="conta" <?php echo ($categoria ?? '') === 'conta' ? 'selected' : ''; ?>>Problema na Conta</option>
                                    <option value="pagamento" <?php echo ($categoria ?? '') === 'pagamento' ? 'selected' : ''; ?>>Pagamento/Cobrança</option>
                                    <option value="jogo" <?php echo ($categoria ?? '') === 'jogo' ? 'selected' : ''; ?>>Problema com Jogo</option>
                                    <option value="download" <?php echo ($categoria ?? '') === 'download' ? 'selected' : ''; ?>>Problema de Download</option>
                                    <option value="sugestao" <?php echo ($categoria ?? '') === 'sugestao' ? 'selected' : ''; ?>>Sugestão</option>
                                    <option value="outro" <?php echo ($categoria ?? '') === 'outro' ? 'selected' : ''; ?>>Outro</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Prioridade:</label>
                                <select name="prioridade" class="form-select">
                                    <option value="baixa" <?php echo ($prioridade ?? 'normal') === 'baixa' ? 'selected' : ''; ?>>Baixa</option>
                                    <option value="normal" <?php echo ($prioridade ?? 'normal') === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="alta" <?php echo ($prioridade ?? 'normal') === 'alta' ? 'selected' : ''; ?>>Alta</option>
                                    <option value="urgente" <?php echo ($prioridade ?? 'normal') === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Descrição Detalhada:</label>
                            <textarea name="mensagem" class="form-textarea" 
                                      required minlength="10" maxlength="2000"
                                      placeholder="Descreva detalhadamente o problema ou sua solicitação. Inclua informações como: quando o problema começou, que erro você vê, que passos você tentou, etc."><?php echo htmlspecialchars($mensagem ?? ''); ?></textarea>
                            <small style="color: #666;">Mínimo 10 caracteres, máximo 2000.</small>
                        </div>
                    </div>
                    
                    <button type="submit" name="enviar_ticket" class="btn btn-primary" id="submitBtn">
                        📤 Enviar Ticket
                    </button>
                </form>
            </div>
            
            <!-- My Tickets -->
            <?php if (!empty($tickets)): ?>
            <div class="card">
                <h2 class="card-title">
                    📋 Meus Tickets
                </h2>
                
                <table class="tickets-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Assunto</th>
                            <th>Categoria</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><strong>#<?php echo $ticket['id']; ?></strong></td>
                                <td>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($ticket['assunto']); ?>
                                    </div>
                                </td>
                                <td><?php echo ucfirst($ticket['categoria']); ?></td>
                                <td>
                                    <span class="priority-badge priority-<?php echo $ticket['prioridade']; ?>">
                                        <?php echo ucfirst($ticket['prioridade']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('d/m/Y', strtotime($ticket['created_at'])); ?></div>
                                    <small style="color: #666;"><?php echo date('H:i', strtotime($ticket['created_at'])); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- FAQ -->
            <div class="card">
                <h2 class="card-title">
                    ❓ Perguntas Frequentes
                </h2>
                
                <div class="faq-section">
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Como faço para baixar meus jogos?
                        </button>
                        <div class="faq-answer">
                            <p>Para baixar seus jogos, você precisa primeiro baixar nosso launcher oficial. Acesse a seção "Download Launcher" no seu painel, instale o programa e faça login com suas credenciais.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Posso instalar os jogos em mais de um computador?
                        </button>
                        <div class="faq-answer">
                            <p>Atualmente, permitimos a instalação em apenas um computador por conta. Isso garante a segurança e evita o compartilhamento não autorizado de jogos.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Como altero minha senha?
                        </button>
                        <div class="faq-answer">
                            <p>Acesse a seção "Meu Perfil" no seu painel e use a opção "Alterar Senha". Você precisará informar sua senha atual e a nova senha.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Posso solicitar reembolso?
                        </button>
                        <div class="faq-answer">
                            <p>Reembolsos são analisados caso a caso. Abra um ticket de suporte explicando o motivo da solicitação e nossa equipe analisará sua solicitação.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            O que fazer se o launcher não abre?
                        </button>
                        <div class="faq-answer">
                            <p>Certifique-se de que está executando o launcher como administrador. Se o problema persistir, tente reinstalar o programa. Se ainda assim não funcionar, abra um ticket de suporte.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="card">
                <h2 class="card-title">
                    📞 Informações de Contato
                </h2>
                
                <div class="contact-info">
                    <div class="contact-item">
                        <span class="contact-icon">📧</span>
                        <div class="contact-title">Email de Suporte</div>
                        <div class="contact-value"><?php echo htmlspecialchars($email_admin); ?></div>
                    </div>
                    
                    <div class="contact-item">
                        <span class="contact-icon">⏰</span>
                        <div class="contact-title">Horário de Atendimento</div>
                        <div class="contact-value">Segunda à Sexta<br>08:00 às 18:00</div>
                    </div>
                    
                    <div class="contact-item">
                        <span class="contact-icon">⚡</span>
                        <div class="contact-title">Tempo de Resposta</div>
                        <div class="contact-value">Até 24 horas<br>em dias úteis</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle FAQ
        function toggleFaq(button) {
            const answer = button.nextElementSibling;
            const isActive = answer.classList.contains('active');
            
            // Fechar todas as respostas
            document.querySelectorAll('.faq-answer').forEach(item => {
                item.classList.remove('active');
            });
            
            // Abrir a resposta clicada se não estava ativa
            if (!isActive) {
                answer.classList.add('active');
            }
        }
        
        // Validação do formulário
        document.getElementById('ticketForm').addEventListener('submit', function(e) {
            const mensagem = document.querySelector('textarea[name="mensagem"]').value;
            
            if (mensagem.length < 10) {
                e.preventDefault();
                alert('A descrição deve ter pelo menos 10 caracteres.');
                return false;
            }
            
            // Prevenir duplo submit
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Enviando...';
            
            // Reabilitar após 5 segundos em caso de erro
            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = '📤 Enviar Ticket';
            }, 5000);
        });
        
        // Contador de caracteres
        const textarea = document.querySelector('textarea[name="mensagem"]');
        if (textarea) {
            const counter = document.createElement('div');
            counter.style.cssText = 'text-align: right; font-size: 0.9rem; color: #666; margin-top: 0.5rem;';
            textarea.parentNode.appendChild(counter);
            
            function updateCounter() {
                const count = textarea.value.length;
                counter.textContent = `${count}/2000 caracteres`;
                
                if (count < 10) {
                    counter.style.color = '#dc2626';
                } else if (count > 1900) {
                    counter.style.color = '#d97706';
                } else {
                    counter.style.color = '#666';
                }
            }
            
            textarea.addEventListener('input', updateCounter);
            updateCounter();
        }
    </script>
</body>
</html>