<?php
// ============================================================================
// register.php - VERSÃO CORRIGIDA COM DEBUG
// ============================================================================

// MODO DEBUG TEMPORÁRIO - Remover depois
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configurações de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configurações do banco
$db_host = 'localhost';
$db_name = 'minec761_comprejogos';
$db_user = 'minec761_comprejogos';
$db_pass = 'pr9n0xz5zxk2';

// Variáveis para mensagens
$message = '';
$message_type = '';
$show_download_info = false;
$debug_info = []; // Para debug

// Função para conectar ao banco
function conectarBanco() {
    global $db_host, $db_name, $db_user, $db_pass, $debug_info;
    try {
        $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $debug_info[] = "✅ Conexão com banco estabelecida";
        return $pdo;
    } catch(PDOException $e) {
        $debug_info[] = "❌ Erro de conexão: " . $e->getMessage();
        throw new Exception('Erro de conexão com banco de dados');
    }
}

// Função para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && 
           !preg_match('/[<>"\']/', $email) &&
           strlen($email) <= 100;
}

// Função para validar login
function validarLogin($login) {
    return preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $login);
}

// Função para validar senha
function validarSenha($senha) {
    return strlen($senha) >= 6 && strlen($senha) <= 100;
}

// DEBUG: Verificar estado inicial
$debug_info[] = "🔍 Script iniciado";
$debug_info[] = "📋 Método: " . $_SERVER['REQUEST_METHOD'];

// Processar formulário
if (isset($_POST['cadastrar'])) {
    $debug_info[] = "📝 Formulário enviado";
    
    $login = trim($_POST['login'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $termos = isset($_POST['termos']);
    
    $debug_info[] = "📊 Dados recebidos - Login: '$login', Email: '$email', Termos: " . ($termos ? 'SIM' : 'NÃO');
    
    // Log da tentativa de registro
    error_log("Tentativa de registro: $login, $email, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));
    
    try {
        // Validações básicas
        $debug_info[] = "🔍 Iniciando validações...";
        
        if (empty($login) || empty($email) || empty($senha)) {
            throw new Exception('Todos os campos são obrigatórios!');
        }
        $debug_info[] = "✅ Campos obrigatórios preenchidos";
        
        if (!validarLogin($login)) {
            throw new Exception('Login deve ter 3-30 caracteres (apenas letras, números, _ e -)');
        }
        $debug_info[] = "✅ Login válido";
        
        if (!validarEmail($email)) {
            throw new Exception('Email inválido!');
        }
        $debug_info[] = "✅ Email válido";
        
        if (!validarSenha($senha)) {
            throw new Exception('A senha deve ter entre 6 e 100 caracteres!');
        }
        $debug_info[] = "✅ Senha válida";
        
        if ($senha !== $confirmar_senha) {
            throw new Exception('As senhas não coincidem!');
        }
        $debug_info[] = "✅ Senhas coincidem";
        
        if (!$termos) {
            throw new Exception('Você deve aceitar os termos de uso!');
        }
        $debug_info[] = "✅ Termos aceitos";
        
        // Conectar ao banco
        $pdo = conectarBanco();
        
        // Verificar se registro está habilitado - SIMPLIFICADO
        $registration_enabled = true; // Padrão habilitado
        try {
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_enabled'");
            $reg_setting = $stmt->fetchColumn();
            if ($reg_setting === '0') {
                $registration_enabled = false;
            }
            $debug_info[] = "📋 Configuração de registro: " . ($registration_enabled ? 'HABILITADO' : 'DESABILITADO');
        } catch (Exception $e) {
            $debug_info[] = "⚠️ Tabela system_settings não encontrada - assumindo registro habilitado";
        }
        
        if (!$registration_enabled) {
            throw new Exception('Novos registros estão temporariamente desabilitados!');
        }
        
        // Verificar se login já existe
        $debug_info[] = "🔍 Verificando se login já existe...";
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            throw new Exception('Este login já está em uso! Escolha outro.');
        }
        $debug_info[] = "✅ Login disponível";
        
        // Verificar se email já existe
        $debug_info[] = "🔍 Verificando se email já existe...";
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Este email já está cadastrado! Use outro email ou faça login.');
        }
        $debug_info[] = "✅ Email disponível";
        
        // Verificar configuração de auto-ativação - SIMPLIFICADO
        $auto_activate = 1; // Padrão ativar automaticamente
        try {
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_activate_clients'");
            $auto_setting = $stmt->fetchColumn();
            if ($auto_setting !== null) {
                $auto_activate = (int)$auto_setting;
            }
            $debug_info[] = "📋 Auto-ativação: " . ($auto_activate ? 'SIM' : 'NÃO');
        } catch (Exception $e) {
            $debug_info[] = "⚠️ Configuração de auto-ativação não encontrada - ativando automaticamente";
        }
        
        // Inserir usuário
        $debug_info[] = "💾 Inserindo usuário no banco...";
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (login, email, senha, ativo, is_client, created_at) 
            VALUES (?, ?, ?, 1, ?, NOW())
        ");
        
        if ($stmt->execute([$login, $email, $senha, $auto_activate])) {
            $user_id = $pdo->lastInsertId();
            $debug_info[] = "✅ Usuário criado com ID: $user_id";
            
            // Log de sucesso
            error_log("Usuário criado com sucesso: ID=$user_id, Login=$login, Email=$email, Auto-ativado=$auto_activate");
            
            if ($auto_activate) {
                $message = '✅ Conta criada e ativada com sucesso! Você já pode usar o programa.';
                $show_download_info = true;
            } else {
                $message = '✅ Conta criada com sucesso! Aguarde a ativação pelo administrador para usar o programa.';
            }
            $message_type = 'success';
            
            // Limpar campos após sucesso
            $login = $email = '';
            
        } else {
            throw new Exception('Erro ao criar conta. Tente novamente.');
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
        $debug_info[] = "❌ ERRO: " . $e->getMessage();
        error_log("Erro no registro: " . $e->getMessage() . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));
    }
}

// Buscar informações do sistema - SIMPLIFICADO
$system_info = [
    'total_users' => 0,
    'active_clients' => 0,
    'registration_enabled' => true
];

try {
    $pdo = conectarBanco();
    $system_info['total_users'] = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    
    // Verificar se coluna is_client existe
    try {
        $system_info['active_clients'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1 AND COALESCE(is_client, 0) = 1")->fetchColumn();
    } catch (Exception $e) {
        $system_info['active_clients'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1")->fetchColumn();
    }
    
    $debug_info[] = "📊 Estatísticas carregadas";
    
} catch (Exception $e) {
    $debug_info[] = "⚠️ Erro ao carregar estatísticas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COMPREJOGOS - Criar Conta</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            backdrop-filter: blur(10px);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 1.1em;
        }
        
        .stats {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .stats h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .stat-item {
            padding: 10px;
            background: white;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 0.9em;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 20px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            transform: scale(1.2);
        }
        
        .checkbox-group label {
            margin: 0;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
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
            margin-top: 15px;
            padding: 15px;
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
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .debug-info h4 {
            color: #495057;
            margin-bottom: 10px;
            font-family: sans-serif;
        }
        
        .download-info {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #4caf50;
        }
        
        .download-info h4 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        
        .download-btn {
            background: #4caf50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            transition: background 0.3s;
        }
        
        .download-btn:hover {
            background: #45a049;
        }
        
        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
            transition: color 0.3s ease;
        }
        
        .links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>COMPREJOGOS</h1>
            <p>Criar Nova Conta</p>
        </div>
        
        <div class="stats">
            <h3>📊 Estatísticas da Plataforma</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($system_info['total_users']); ?></div>
                    <div class="stat-label">Usuários Registrados</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($system_info['active_clients']); ?></div>
                    <div class="stat-label">Clientes Ativos</div>
                </div>
            </div>
        </div>
        
        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="login">👤 Nome de Usuário:</label>
                <input type="text" id="login" name="login" value="<?php echo htmlspecialchars($login ?? ''); ?>" 
                       required maxlength="30" pattern="[a-zA-Z0-9_-]{3,30}"
                       title="3-30 caracteres: letras, números, _ e -">
            </div>
            
            <div class="form-group">
                <label for="email">📧 Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                       required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="senha">🔒 Senha:</label>
                <input type="password" id="senha" name="senha" required maxlength="100" minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirmar_senha">🔒 Confirmar Senha:</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required maxlength="100">
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="termos" name="termos" required>
                <label for="termos">
                    Eu aceito os termos de uso e confirmo que as informações fornecidas são verdadeiras.
                </label>
            </div>
            
            <button type="submit" name="cadastrar" class="btn" id="submitBtn">
                Criar Conta
            </button>
        </form>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($show_download_info): ?>
            <div class="download-info">
                <h4>🎉 Conta Ativada com Sucesso!</h4>
                <p>Sua conta foi automaticamente ativada. Agora você pode baixar o programa:</p>
                <a href="COMPREJOGOS.exe" class="download-btn" download>
                    📥 Baixar COMPREJOGOS
                </a>
                <p><small>Após o download, execute o programa e faça login com suas credenciais.</small></p>
            </div>
        <?php endif; ?>
        
        <!-- DEBUG INFO - REMOVER EM PRODUÇÃO -->
        <?php if (!empty($debug_info)): ?>
            <div class="debug-info">
                <h4>🔧 Informações de Debug:</h4>
                <?php foreach ($debug_info as $info): ?>
                    <div><?php echo htmlspecialchars($info); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="links">
            <a href="index.php">🏠 Página Principal</a>
            <a href="login.php">🔐 Já Tenho Conta</a>
            <a href="mailto:suporte@comprejogos.com">📞 Suporte</a>
        </div>
    </div>
    
    <script>
        // Validação em tempo real do formulário
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const senha = document.getElementById('senha').value;
            const confirmar = document.getElementById('confirmar_senha').value;
            const termos = document.getElementById('termos').checked;
            
            if (senha !== confirmar) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                return false;
            }
            
            if (!termos) {
                e.preventDefault();
                alert('Você deve aceitar os termos de uso!');
                return false;
            }
            
            // Desabilitar botão para evitar duplo clique
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').textContent = 'Criando conta...';
        });
    </script>
</body>
</html>