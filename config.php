<?php
// ============================================================================
// config.php - Configuração do Sistema COMPREJOGOS
// ============================================================================

// Configurações do MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'minec761_comprejogos');
define('DB_USER', 'minec761_comprejogos');
define('DB_PASS', 'pr9n0xz5zxk2');

// Configurações de caminhos
define('BASE_URL', 'https://comprejogos.com/');
define('DOWNLOAD_FILE_PATH', __DIR__ . '/files/COMPREJOGOS.zip');
define('GAME_FILES_PATH', __DIR__ . '/game_files/');
define('UPLOADS_PATH', __DIR__ . '/uploads/');
define('IMAGES_PATH', __DIR__ . '/uploads/images/');

// Configurações de segurança
define('SESSION_DURATION_HOURS', 24);
define('MAX_DOWNLOADS_PER_MAC', 1);
define('ENCRYPTION_KEY', 'comprejogos_2025_secure_key_!@#$%');

// Configurações de origem permitidas
define('ALLOWED_ORIGINS', [
    'https://likc.net',
    'https://comprejogos.com',
    'http://localhost' // Para testes locais
]);

// Função para conectar ao banco
function conectarBanco() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
                DB_USER, 
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch(PDOException $e) {
            error_log("Erro de conexão: " . $e->getMessage());
            die("Erro de conexão com o banco de dados.");
        }
    }
    
    return $pdo;
}

// Função para gerar token de sessão
function gerarToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Função para validar MAC address
function validarMacAddress($mac) {
    $mac = strtoupper(trim($mac));
    return preg_match('/^([0-9A-F]{2}[:-]){5}([0-9A-F]{2})$/', $mac);
}

// Função para normalizar MAC address
function normalizarMac($mac_input) {
    if (empty($mac_input)) return false;
    
    $mac_clean = strtoupper(preg_replace('/[^0-9A-F:-]/', '', $mac_input));
    $mac_clean = str_replace('-', ':', $mac_clean);
    
    if (strlen($mac_clean) == 12 && !strpos($mac_clean, ':')) {
        $mac_clean = implode(':', str_split($mac_clean, 2));
    }
    
    if (preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac_clean)) {
        $invalid_macs = ['00:00:00:00:00:00', 'FF:FF:FF:FF:FF:FF'];
        if (!in_array($mac_clean, $invalid_macs)) {
            return $mac_clean;
        }
    }
    
    return false;
}

// Função para validar origem da requisição
function validarOrigem() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Permitir requisições locais (aplicação desktop)
    if (empty($origin) && empty($referer)) {
        return true;
    }
    
    foreach (ALLOWED_ORIGINS as $allowed) {
        if (strpos($origin, $allowed) === 0 || strpos($referer, $allowed) === 0) {
            return true;
        }
    }
    
    return false;
}

// Função para log de erros
function logError($message, $context = []) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if (!empty($context)) {
        $log .= " - Context: " . json_encode($context);
    }
    error_log($log);
}

// Headers de segurança padrão
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Função para sanitizar entrada
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
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

// Função para formatar preço
function formatarPreco($preco) {
    return 'R$ ' . number_format($preco, 2, ',', '.');
}

// Função para obter configuração do sistema
function getSystemSetting($key, $default = null) {
    static $settings = [];
    
    if (empty($settings)) {
        try {
            $pdo = conectarBanco();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            logError("Erro ao carregar configurações: " . $e->getMessage());
        }
    }
    
    return $settings[$key] ?? $default;
}

// Função para definir configuração do sistema
function setSystemSetting($key, $value) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        logError("Erro ao salvar configuração: " . $e->getMessage());
        return false;
    }
}

// Função para verificar se usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Função para verificar se é administrador
function isAdmin() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Função para redirecionar
function redirect($url) {
    header("Location: $url");
    exit;
}

// Função para criar diretórios se não existirem
function createDirectories() {
    $dirs = [UPLOADS_PATH, IMAGES_PATH, GAME_FILES_PATH];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Inicializar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Criar diretórios necessários
createDirectories();

// Definir headers de segurança por padrão
setSecurityHeaders();
?>