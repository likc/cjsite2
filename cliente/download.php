<?php
// ============================================================================
// cliente/download.php - Download do Launcher
// ============================================================================

require_once '../config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('../login.php?redirect=cliente/download.php');
}

$message = '';
$message_type = '';

try {
    $pdo = conectarBanco();
    
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT login, is_client FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        redirect('../logout.php');
    }
    
    // Verificar se o download está habilitado
    $download_enabled = getSystemSetting('download_enabled', '1') === '1';
    
    // Verificar se o arquivo existe
    $launcher_file = DOWNLOAD_FILE_PATH;
    $launcher_exists = file_exists($launcher_file);
    $launcher_size = $launcher_exists ? filesize($launcher_file) : 0;
    
    // Buscar histórico de downloads do usuário
    $stmt = $pdo->prepare("
        SELECT * FROM download_logs 
        WHERE usuario_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $download_history = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Erro na página de download: " . $e->getMessage());
    $message = "Erro ao carregar página. Tente novamente.";
    $message_type = 'error';
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

$site_name = getSystemSetting('site_name', 'COMPREJOGOS');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Launcher - <?php echo $site_name; ?></title>
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
        
        /* Download Section */
        .download-hero {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .download-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .download-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .download-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .download-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .info-value {
            font-size: 1.3rem;
            font-weight: bold;
            display: block;
        }
        
        .info-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background: white;
            color: var(--primary);
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #f0f0f0;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover:not(:disabled) {
            background: #15803d;
        }
        
        .btn:disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-large {
            padding: 1.2rem 3rem;
            font-size: 1.2rem;
        }
        
        /* Instructions */
        .instructions {
            background: var(--light);
            padding: 2rem;
            border-radius: 10px;
            border-left: 5px solid var(--info);
        }
        
        .instructions h3 {
            color: var(--dark);
            margin-bottom: 1rem;
        }
        
        .instruction-steps {
            list-style: none;
            counter-reset: step-counter;
        }
        
        .instruction-steps li {
            counter-increment: step-counter;
            margin-bottom: 1rem;
            padding-left: 3rem;
            position: relative;
        }
        
        .instruction-steps li::before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            background: var(--info);
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* System Requirements */
        .requirements {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .requirement-section {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .requirement-section h4 {
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .requirement-list {
            list-style: none;
        }
        
        .requirement-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
        }
        
        .requirement-list li:last-child {
            border-bottom: none;
        }
        
        /* Download History */
        .history-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .history-table th,
        .history-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .history-table th {
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
        
        .status-1 {
            background: #d4edda;
            color: #155724;
        }
        
        .status-0 {
            background: #f8d7da;
            color: #721c24;
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
        
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 5px solid;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-color: var(--warning);
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: var(--danger);
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
            
            .download-title {
                font-size: 2rem;
            }
            
            .download-info {
                grid-template-columns: 1fr;
            }
            
            .requirements {
                grid-template-columns: 1fr;
            }
            
            .history-table {
                font-size: 0.9rem;
            }
            
            .history-table th,
            .history-table td {
                padding: 0.75rem 0.5rem;
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
                <li><a href="download.php" class="active"><span class="icon">📥</span> Download Launcher</a></li>
                <li><a href="suporte.php"><span class="icon">💬</span> Suporte</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Download Hero -->
            <div class="download-hero">
                <span class="download-icon">📥</span>
                <h1 class="download-title"><?php echo htmlspecialchars($site_name); ?> Launcher</h1>
                <p class="download-subtitle">Baixe o launcher oficial para jogar seus jogos</p>
                
                <?php if ($launcher_exists && $download_enabled && $usuario['is_client']): ?>
                    <div class="download-info">
                        <div class="info-item">
                            <span class="info-value"><?php echo formatBytes($launcher_size); ?></span>
                            <span class="info-label">Tamanho do Arquivo</span>
                        </div>
                        <div class="info-item">
                            <span class="info-value">Windows</span>
                            <span class="info-label">Sistema Compatível</span>
                        </div>
                        <div class="info-item">
                            <span class="info-value">Gratuito</span>
                            <span class="info-label">Preço</span>
                        </div>
                    </div>
                    
                    <a href="../api/download.php?session_token=<?php echo urlencode($_SESSION['session_token'] ?? ''); ?>&user_id=<?php echo $_SESSION['user_id']; ?>&mac_address=auto" 
                       class="btn btn-primary btn-large" onclick="trackDownload()">
                        📥 Baixar Launcher
                    </a>
                <?php else: ?>
                    <?php if (!$usuario['is_client']): ?>
                        <div class="alert alert-warning">
                            <h4>⚠️ Acesso Restrito</h4>
                            <p>Sua conta ainda não foi aprovada para download. Entre em contato com o suporte para ativação.</p>
                        </div>
                        <a href="suporte.php" class="btn btn-primary btn-large">
                            📞 Contatar Suporte
                        </a>
                    <?php elseif (!$download_enabled): ?>
                        <div class="alert alert-warning">
                            <h4>⚠️ Downloads Temporariamente Indisponíveis</h4>
                            <p>O sistema de downloads está em manutenção. Tente novamente mais tarde.</p>
                        </div>
                    <?php elseif (!$launcher_exists): ?>
                        <div class="alert alert-danger">
                            <h4>❌ Arquivo Não Encontrado</h4>
                            <p>O arquivo do launcher não está disponível no momento. Entre em contato com o suporte.</p>
                        </div>
                        <a href="suporte.php" class="btn btn-primary btn-large">
                            📞 Contatar Suporte
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Instructions -->
            <div class="card">
                <h2 class="card-title">
                    📋 Como Instalar e Usar
                </h2>
                
                <div class="instructions">
                    <h3>Instruções de Instalação:</h3>
                    <ol class="instruction-steps">
                        <li>Clique no botão "Baixar Launcher" acima para fazer o download</li>
                        <li>Aguarde o download ser concluído (cerca de <?php echo formatBytes($launcher_size); ?>)</li>
                        <li>Execute o arquivo baixado como administrador</li>
                        <li>Siga as instruções do assistente de instalação</li>
                        <li>Abra o launcher e faça login com suas credenciais</li>
                        <li>Pronto! Agora você pode baixar e jogar seus jogos</li>
                    </ol>
                </div>
                
                <div style="background: #e8f5e8; padding: 1rem; border-radius: 8px; margin-top: 2rem; border-left: 5px solid var(--success);">
                    <h4 style="color: var(--success); margin-bottom: 0.5rem;">💡 Dica Importante:</h4>
                    <p>Execute sempre o arquivo como administrador para evitar problemas de permissão durante a instalação.</p>
                </div>
            </div>
            
            <!-- System Requirements -->
            <div class="card">
                <h2 class="card-title">
                    💻 Requisitos do Sistema
                </h2>
                
                <div class="requirements">
                    <div class="requirement-section">
                        <h4>🔧 Requisitos Mínimos</h4>
                        <ul class="requirement-list">
                            <li><span>Sistema Operacional:</span><span>Windows 7 ou superior</span></li>
                            <li><span>Processador:</span><span>Intel Dual Core 2.0 GHz</span></li>
                            <li><span>Memória RAM:</span><span>2 GB</span></li>
                            <li><span>Espaço em Disco:</span><span>500 MB livres</span></li>
                            <li><span>Conexão:</span><span>Internet banda larga</span></li>
                        </ul>
                    </div>
                    
                    <div class="requirement-section">
                        <h4>⚡ Requisitos Recomendados</h4>
                        <ul class="requirement-list">
                            <li><span>Sistema Operacional:</span><span>Windows 10 ou 11</span></li>
                            <li><span>Processador:</span><span>Intel Core i5 ou AMD equivalente</span></li>
                            <li><span>Memória RAM:</span><span>4 GB ou mais</span></li>
                            <li><span>Espaço em Disco:</span><span>2 GB livres</span></li>
                            <li><span>Conexão:</span><span>Internet banda larga estável</span></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Download History -->
            <?php if (!empty($download_history)): ?>
            <div class="card">
                <h2 class="card-title">
                    📊 Histórico de Downloads
                </h2>
                
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>IP</th>
                            <th>Tamanho</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($download_history as $download): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($download['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($download['ip_address']); ?></td>
                                <td><?php echo $download['file_size'] ? formatBytes($download['file_size']) : 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $download['download_completed']; ?>">
                                        <?php echo $download['download_completed'] ? 'Concluído' : 'Incompleto'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Support -->
            <div class="card">
                <h2 class="card-title">
                    💬 Precisa de Ajuda?
                </h2>
                
                <p style="margin-bottom: 2rem;">
                    Se você está enfrentando problemas com o download ou instalação do launcher, 
                    nossa equipe de suporte está pronta para ajudar.
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="suporte.php" class="btn btn-primary">
                        📞 Contatar Suporte
                    </a>
                    <a href="#" class="btn btn-success" onclick="window.open('https://api.whatsapp.com/send?phone=5511999999999&text=Olá, preciso de ajuda com o launcher', '_blank')">
                        💬 WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function trackDownload() {
            // Log do clique no download
            console.log('Download iniciado:', new Date().toISOString());
            
            // Opcional: Enviar analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'download', {
                    'event_category': 'launcher',
                    'event_label': 'main_download'
                });
            }
        }
        
        // Detectar se o download foi bem-sucedido
        window.addEventListener('beforeunload', function() {
            // Opcional: Salvar estado do download
        });
    </script>
</body>
</html>