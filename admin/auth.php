<?php
// ============================================================================
// admin/auth.php - Sistema de Autenticação do Administrador
// ============================================================================

require_once '../config.php';

// Redirecionar se já estiver logado como admin
if (isAdmin()) {
    redirect('index.php');
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $message = 'Por favor, preencha todos os campos.';
        $message_type = 'error';
    } else {
        try {
            $pdo = conectarBanco();
            
            // Verificar se a tabela de admins existe
            try {
                $pdo->query("SELECT 1 FROM admins LIMIT 1");
            } catch (Exception $e) {
                // Criar tabela de admins se não existir
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `admins` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `username` varchar(50) NOT NULL,
                        `email` varchar(100) NOT NULL,
                        `password_hash` varchar(255) NOT NULL,
                        `nome_completo` varchar(200) DEFAULT NULL,
                        `nivel` enum('admin','super_admin') DEFAULT 'admin',
                        `ativo` tinyint(1) DEFAULT 1,
                        `ultimo_login` timestamp NULL,
                        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `username` (`username`),
                        UNIQUE KEY `email` (`email`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Inserir admin padrão
                $default_hash = password_hash('admin123', PASSWORD_DEFAULT);
                $pdo->prepare("
                    INSERT IGNORE INTO `admins` (`username`, `email`, `password_hash`, `nome_completo`, `nivel`) 
                    VALUES ('admin', 'admin@comprejogos.com', ?, 'Administrador do Sistema', 'super_admin')
                ")->execute([$default_hash]);
            }
            
            // Buscar administrador
            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, ativo, nivel, nome_completo 
                FROM admins 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                $message = 'Credenciais inválidas.';
                $message_type = 'error';
            } elseif (!$admin['ativo']) {
                $message = 'Conta de administrador desativada.';
                $message_type = 'error';
            } elseif (!password_verify($password, $admin['password_hash'])) {
                $message = 'Credenciais inválidas.';
                $message_type = 'error';
            } else {
                // Login bem-sucedido
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_nivel'] = $admin['nivel'];
                $_SESSION['admin_nome'] = $admin['nome_completo'];
                
                // Atualizar último login
                $stmt = $pdo->prepare("UPDATE admins SET ultimo_login = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
                
                // Configurar cookie se solicitado
                if ($remember) {
                    $token = gerarToken();
                    setcookie('admin_remember', $token, time() + (30 * 24 * 60 * 60), '/admin/', '', true, true);
                }
                
                // Log da sessão administrativa
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO access_logs (usuario_id, ip_address, user_agent, login_successful) 
                        VALUES (?, ?, ?, 3)
                    ");
                    $stmt->execute([
                        $admin['id'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                } catch (Exception $e) {
                    logError("Erro ao salvar log de admin: " . $e->getMessage());
                }
                
                redirect('index.php');
            }
            
        } catch (Exception $e) {
            logError("Erro no login admin: " . $e->getMessage());
            $message = 'Erro interno. Tente novamente.';
            $message_type = 'error';
        }
    }
}

$site_name = getSystemSetting('site_name', 'COMPREJOGOS');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo $site_name; ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .admin-login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .admin-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .admin-logo-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .admin-logo h1 {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .admin-logo p {
            color: #666;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .admin-badge {
            background: linear-gradient(45deg, #dc2626, #ef4444);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            display: inline-block;
            margin-top: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-checkbox input[type="checkbox"] {
            transform: scale(1.2);
        }
        
        .form-checkbox label {
            color: #666;
            font-size: 0.95rem;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .message {
            margin-bottom: 1.5rem;
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
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin: 0 1rem;
            transition: color 0.3s ease;
        }
        
        .links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .security-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .security-info strong {
            display: block;
            margin-bottom: 0.5rem;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .admin-login-container {
                padding: 2rem;
            }
            
            .admin-logo h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-logo">
            <span class="admin-logo-icon">🛡️</span>
            <h1><?php echo htmlspecialchars($site_name); ?></h1>
            <p>Painel Administrativo</p>
            <div class="admin-badge">Área Restrita</div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="adminLoginForm">
            <div class="form-group">
                <label for="username" class="form-label">👤 Usuário ou Email:</label>
                <input type="text" id="username" name="username" class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">🔒 Senha:</label>
                <input type="password" id="password" name="password" class="form-input" 
                       required autocomplete="current-password">
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Lembrar por 30 dias</label>
            </div>
            
            <button type="submit" class="btn" id="loginBtn">
                Acessar Painel
            </button>
        </form>
        
        <div class="security-info">
            <strong>🔐 Área de Segurança Máxima</strong>
            Apenas administradores autorizados podem acessar esta área.
            Todas as ações são registradas e monitoradas.
        </div>
        
        <div class="links">
            <a href="../index.php">🏠 Voltar à Loja</a>
            <a href="../login.php">👤 Login de Cliente</a>
        </div>
    </div>
    
    <script>
        // Prevenir duplo submit
        document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.textContent = 'Verificando...';
            
            // Reabilitar após 5 segundos em caso de erro
            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = 'Acessar Painel';
            }, 5000);
        });
        
        // Focar no primeiro campo
        document.getElementById('username').focus();
        
        // Mostrar aviso de segurança
        console.log('%c🛡️ ÁREA ADMINISTRATIVA 🛡️', 'color: red; font-size: 20px; font-weight: bold;');
        console.log('Esta é uma área restrita. Acesso não autorizado é crime.');
    </script>
</body>
</html>