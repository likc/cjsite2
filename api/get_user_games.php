<?php
// api/get_user_games.php - vFINAL CORRIGIDA 2.0

header('Content-Type: application/json; charset=utf-8');

// --- CONFIGURAÇÕES ---
$db_host = 'localhost';
$db_name = 'minec761_comprejogos';
$db_user = 'minec761_comprejogos';
$db_pass = 'pr9n0xz5zxk2';

// --- VALIDAÇÃO DE ENTRADA ---
$session_token = $_POST['session_token'] ?? '';
$user_id_from_client = (int)($_POST['user_id'] ?? 0);
$mac_address_from_client = $_POST['mac_address'] ?? '';

if (empty($session_token) || empty($user_id_from_client) || empty($mac_address_from_client)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Erro fatal: Dados de autenticação ausentes.']);
    exit;
}

// --- LÓGICA PRINCIPAL ---
try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Valida a sessão do usuário (nenhuma alteração aqui)
    $stmt_session = $pdo->prepare("SELECT usuario_id FROM user_sessions WHERE session_token = ? AND usuario_id = ? AND mac_address = ? AND expires_at > NOW()");
    $stmt_session->execute([$session_token, $user_id_from_client, $mac_address_from_client]);
    $valid_user_id = $stmt_session->fetchColumn();

    if (!$valid_user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sessão inválida ou expirada. Faça o login novamente no cliente.']);
        exit;
    }

    // 2. Busca os jogos para o ID de usuário validado (CONSULTA ATUALIZADA)
    // Esta consulta agora:
    // - Junta 'usuario_jogos' com 'jogos' usando o 'appid'.
    // - Usa LEFT JOIN para buscar os manifests na nova tabela 'manifests_jogos'.
    // - Usa GROUP_CONCAT para agrupar múltiplos manifests em uma única string, separados por vírgula.
    $sql_games = "
        SELECT
            j.appid,
            j.nome,
            j.arquivo_lua,
            GROUP_CONCAT(m.nome_arquivo SEPARATOR ',') AS manifest_files
        FROM usuario_jogos uj
        JOIN jogos j ON uj.jogo_appid = j.appid
        LEFT JOIN manifests_jogos m ON j.appid = m.jogo_appid
        WHERE uj.usuario_id = ?
        GROUP BY j.appid, j.nome, j.arquivo_lua
    ";
    
    $stmt_games = $pdo->prepare($sql_games);
    $stmt_games->execute([$valid_user_id]);
    $jogos = $stmt_games->fetchAll(PDO::FETCH_ASSOC);

    // 3. Retorna a lista de jogos
    echo json_encode(['success' => true, 'games' => $jogos]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error (get_user_games): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>