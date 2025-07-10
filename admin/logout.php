<?php
// ============================================================================
// admin/logout.php - Logout do Administrador
// ============================================================================

require_once '../config.php';

// Log do logout do admin (se estiver logado)
if (isAdmin()) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (usuario_id, ip_address, user_agent, login_successful) 
            VALUES (?, ?, ?, 4)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        logError("Erro ao salvar log de logout admin: " . $e->getMessage());
    }
}

// Limpar variáveis de sessão do admin
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_nivel']);
unset($_SESSION['admin_nome']);

// Limpar cookie de admin se existir
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time() - 3600, '/admin/', '', true, true);
}

// Redirecionar para login do admin
redirect('auth.php?logout=success');
?>