<?php
// api/download_game_file.php - vFINAL CORRIGIDA 3.0

// --- CONFIGURAÇÕES ---
$db_host = 'localhost';
$db_name = 'minec761_comprejogos';
$db_user = 'minec761_comprejogos';
$db_pass = 'pr9n0xz5zxk2';
// O caminho da base dos arquivos precisa ser o mesmo do seu script upload.php
$base_file_path = '/home/minec761/public_html/jogos/';

// --- VALIDAÇÃO DE ENTRADA ---
$session_token = $_POST['session_token'] ?? '';
$user_id_from_client = (int)($_POST['user_id'] ?? 0);
$mac_address_from_client = $_POST['mac_address'] ?? '';
$appid = $_POST['appid'] ?? '';
$filename_requested = $_POST['filename'] ?? ''; // O cliente agora envia o nome exato do arquivo

if (empty($session_token) || empty($user_id_from_client) || empty($mac_address_from_client) || empty($appid) || empty($filename_requested)) {
    http_response_code(401); exit("Autenticação ou nome de arquivo ausente.");
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
        http_response_code(403); exit("Sessão inválida ou expirada.");
    }
    
    // 2. VERIFICA A PERMISSÃO PARA O JOGO (CONSULTA SIMPLIFICADA E CORRIGIDA)
    // Apenas verifica se o usuário tem acesso ao AppID. Não precisamos mais buscar o nome do arquivo aqui.
    $sql_permission = "
        SELECT j.nome_pasta
        FROM usuario_jogos uj
        JOIN jogos j ON uj.jogo_appid = j.appid
        WHERE j.appid = ? AND uj.usuario_id = ?
    ";
    
    $stmt_permission = $pdo->prepare($sql_permission);
    $stmt_permission->execute([$appid, $valid_user_id]);
    $jogo = $stmt_permission->fetch(PDO::FETCH_ASSOC);

    if (!$jogo) {
        http_response_code(403); exit("Permissão negada para este jogo.");
    }

    // 3. MONTA O CAMINHO DO ARQUIVO (LÓGICA CORRIGIDA)
    // Confia no nome do arquivo enviado pelo launcher, já que a permissão foi validada.
    $game_folder_name = $jogo['nome_pasta'];
    $full_path = rtrim($base_file_path, '/') . '/' . $game_folder_name . '/' . $filename_requested;
    
    // 4. ENTREGA O ARQUIVO
    if (file_exists($full_path) && is_readable($full_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename_requested) . '"');
        header('Content-Length: ' . filesize($full_path));
        readfile($full_path);
        exit;
    } else {
        http_response_code(404);
        error_log("Arquivo não encontrado no servidor: " . $full_path);
        exit("Arquivo não encontrado no servidor.");
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error (download_game_file): " . $e->getMessage());
    exit("Erro interno do servidor.");
}
?>