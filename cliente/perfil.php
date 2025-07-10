<?php
// ============================================================================
// cliente/perfil.php - Perfil do Cliente
// ============================================================================

require_once '../config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('../login.php?redirect=cliente/perfil.php');
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
    
    // Processar atualização do perfil
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email)) {
            $message = 'O email é obrigatório.';
            $message_type = 'error';
        } elseif (!validarEmail($email)) {
            $message = 'Por favor, insira um email válido.';
            $message_type = 'error';
        } else {
            // Verificar se email já está em uso por outro usuário
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                $message = 'Este email já está sendo usado por outro usuário.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE usuarios SET email = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$email, $_SESSION['user_id']]);
                    
                    $usuario['email'] = $email;
                    $message = 'Perfil atualizado com sucesso!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    logError("Erro ao atualizar perfil: " . $e->getMessage());
                    $message = 'Erro ao atualizar perfil. Tente novamente.';
                    $message_type = 'error';
                }
            }
        }
    }
    
    // Processar mudança de senha
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trocar_senha'])) {
        $senha_atual = $_POST['senha_atual'] ?? '';
        $nova_senha = $_POST['nova_senha'] ?? '';
        $confirmar_senha = $_POST['confirmar_senha'] ?? '';
        
        if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
            $message = 'Todos os campos de senha são obrigatórios.';
            $message_type = 'error';
        } elseif ($senha_atual !== $usuario['senha']) {
            $message = 'Senha atual incorreta.';
            $message_type = 'error';
        } elseif (!validarSenha($nova_senha)) {
            $message = 'A nova senha deve ter entre 6 e 100 caracteres.';
            $message_type = 'error';
        } elseif ($nova_senha !== $confirmar_senha) {
            $message = 'A confirmação da nova senha não confere.';
            $message_type = 'error';
        } elseif ($nova_senha === $senha_atual) {
            $message = 'A nova senha deve ser diferente da atual.';
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nova_senha, $_SESSION['user_id']]);
                
                $usuario['senha'] = $nova_senha;
                $message = 'Senha alterada com sucesso!';
                $message_type = 'success';
                
                // Log da alteração
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO access_logs (usuario_id, ip_address, user_agent, login_successful) 
                        VALUES (?, ?, ?, 2)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                } catch (Exception $e) {
                    logError("Erro ao salvar log de mudança de senha: " . $e->getMessage());
                }
                
            } catch (Exception $e) {
                logError("Erro ao trocar senha: " . $e->getMessage());
                $message = 'Erro ao alterar senha. Tente novamente.';
                $message_type = 'error';
            }
        }
    }
    
    // Buscar estatísticas do usuário
    $stats = [
        'total_jogos' => $pdo->prepare("SELECT COUNT(*) FROM usuario_jogos WHERE usuario_id = ?"),
        'total_gasto' => $pdo->prepare("SELECT COALESCE(SUM(preco_final), 0) FROM vendas WHERE usuario_id = ? AND status = 'aprovado'"),
        'membro_desde' => date('d/m/Y', strtotime($usuario['created_at']))
    ];
    
    $stats['total_jogos']->execute([$_SESSION['user_id']]);
    $stats['total_jogos'] = $stats['total_jogos']->fetchColumn();
    
    $stats['total_gasto']->execute([$_SESSION['user_id']]);
    $stats['total_gasto'] = $stats['total_gasto']->fetchColumn();
    
} catch (Exception $e) {
    logError("Erro na página de perfil: " . $e->getMessage());
    redirect('index.php');
}

$site_name = getSystemSetting('site_name', 'COMPREJOGOS');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - <?php echo $site_name; ?></title>
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
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Profile Stats */
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .profile-email {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Forms */
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: grid;
            gap: 1rem;
        }
        
        .form-group {
            display: grid;
            gap: 0.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-input {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-input:disabled {
            background: #f5f5f5;
            color: #666;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover:not(:disabled) {
            background: #b91c1c;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* Message */
        .message {
            margin-bottom: 2rem;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        /* Security Info */
        .security-info {
            background: var(--light);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--info);
        }
        
        .security-info h4 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .security-info ul {
            margin-left: 1rem;
            color: #666;
        }
        
        .danger-zone {
            border: 2px solid var(--danger);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .danger-zone h3 {
            color: var(--danger);
            margin-bottom: 1rem;
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
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
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
                    <a href="download.php">📥 Download</a>
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
                <li><a href="perfil.php" class="active"><span class="icon">👤</span> Meu Perfil</a></li>
                <li><a href="download.php"><span class="icon">📥</span> Download Launcher</a></li>
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
            
            <!-- Profile Overview -->
            <div class="card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        👤
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($usuario['login']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($usuario['email']); ?></div>
                    
                    <div class="stats-row">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['total_jogos']; ?></span>
                            <span class="stat-label">Jogos</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo formatarPreco($stats['total_gasto']); ?></span>
                            <span class="stat-label">Total Gasto</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['membro_desde']; ?></span>
                            <span class="stat-label">Membro desde</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Profile -->
            <div class="card">
                <h2 class="card-title">
                    ✏️ Editar Perfil
                </h2>
                
                <form method="POST" class="form-section">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nome de Usuário:</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($usuario['login']); ?>" disabled>
                            <small style="color: #666;">O nome de usuário não pode ser alterado.</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email:</label>
                            <input type="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($usuario['email']); ?>" 
                                   required maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status da Conta:</label>
                            <input type="text" class="form-input" 
                                   value="<?php echo $usuario['ativo'] ? 'Ativa' : 'Inativa'; ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tipo de Conta:</label>
                            <input type="text" class="form-input" 
                                   value="<?php echo $usuario['is_client'] ? 'Cliente Autorizado' : 'Usuário Padrão'; ?>" disabled>
                        </div>
                    </div>
                    
                    <button type="submit" name="atualizar_perfil" class="btn btn-primary">
                        💾 Salvar Alterações
                    </button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="card">
                <h2 class="card-title">
                    🔒 Alterar Senha
                </h2>
                
                <div class="security-info">
                    <h4>Dicas de Segurança:</h4>
                    <ul>
                        <li>Use uma senha com pelo menos 6 caracteres</li>
                        <li>Combine letras, números e símbolos</li>
                        <li>Não compartilhe sua senha com ninguém</li>
                        <li>Troque sua senha regularmente</li>
                    </ul>
                </div>
                
                <form method="POST" class="form-section" id="passwordForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Senha Atual:</label>
                            <input type="password" name="senha_atual" class="form-input" 
                                   required maxlength="100" autocomplete="current-password">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nova Senha:</label>
                            <input type="password" name="nova_senha" class="form-input" 
                                   required minlength="6" maxlength="100" autocomplete="new-password">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirmar Nova Senha:</label>
                            <input type="password" name="confirmar_senha" class="form-input" 
                                   required minlength="6" maxlength="100" autocomplete="new-password">
                        </div>
                    </div>
                    
                    <button type="submit" name="trocar_senha" class="btn btn-danger" id="changePasswordBtn">
                        🔒 Alterar Senha
                    </button>
                </form>
            </div>
            
            <!-- Account Information -->
            <div class="card">
                <h2 class="card-title">
                    ℹ️ Informações da Conta
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">ID do Usuário:</label>
                        <input type="text" class="form-input" value="<?php echo $usuario['id']; ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Data de Criação:</label>
                        <input type="text" class="form-input" 
                               value="<?php echo date('d/m/Y H:i', strtotime($usuario['created_at'])); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Última Atualização:</label>
                        <input type="text" class="form-input" 
                               value="<?php echo $usuario['updated_at'] ? date('d/m/Y H:i', strtotime($usuario['updated_at'])) : 'Nunca'; ?>" disabled>
                    </div>
                </div>
                
                <div class="danger-zone">
                    <h3>⚠️ Zona de Perigo</h3>
                    <p>Se você deseja excluir sua conta, entre em contato com o suporte. Esta ação é irreversível e resultará na perda de todos os seus jogos e dados.</p>
                    <a href="suporte.php" class="btn btn-danger" style="margin-top: 1rem;">
                        📞 Contatar Suporte
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validação de senha em tempo real
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const novaSenha = document.querySelector('input[name="nova_senha"]').value;
            const confirmarSenha = document.querySelector('input[name="confirmar_senha"]').value;
            
            if (novaSenha !== confirmarSenha) {
                e.preventDefault();
                alert('A confirmação da nova senha não confere!');
                return false;
            }
            
            if (novaSenha.length < 6) {
                e.preventDefault();
                alert('A nova senha deve ter pelo menos 6 caracteres!');
                return false;
            }
            
            // Prevenir duplo submit
            const btn = document.getElementById('changePasswordBtn');
            btn.disabled = true;
            btn.textContent = 'Alterando...';
            
            // Reabilitar após 5 segundos em caso de erro
            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = '🔒 Alterar Senha';
            }, 5000);
        });
        
        // Confirmar antes de enviar formulário de senha
        document.getElementById('changePasswordBtn').addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja alterar sua senha?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>