<?php
// ============================================================================
// cliente/index.php - Redirecionamento para o Painel Correto
// ============================================================================

session_start();

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    // Se não está logado, redirecionar para login
    header('Location: ../login.php');
    exit;
}

// Verificar se é admin
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
    // Se é admin, redirecionar para painel admin
    header('Location: ../admin/index.php');
    exit;
}

// Para usuários normais, redirecionar para painel cliente
header('Location: ../painel_cliente.php');
exit;
?>