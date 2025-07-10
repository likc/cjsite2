<?php
// ============================================================================
// logout.php - Sistema de Logout
// ============================================================================

require_once 'config.php';

// Log do logout (se estiver logado)
if (isLoggedIn()) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (usuario_id, ip_address, user_agent, login_successful) 
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        logError("Erro ao salvar log de logout: " . $e->getMessage());
    }
}

// Limpar sessão
$_SESSION = [];

// Limpar cookies de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Limpar cookie de lembrar
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Destruir sessão
session_destroy();

// Redirecionar para página inicial
redirect('index.php?logout=success');
?>