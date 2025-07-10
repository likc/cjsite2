<?php
require_once '../config.php';

// Verificar autenticação (use sua lógica atual)
$session_token = $_POST['session_token'] ?? '';
$user_id = $_POST['user_id'] ?? '';
$mac_address = $_POST['mac_address'] ?? '';

// TODO: Adicione aqui sua validação de sessão
// if (!validar_sessao($session_token, $user_id, $mac_address)) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Não autorizado']);
//     exit;
// }

// Caminho para o arquivo Hid.dll
$hid_dll_path = __DIR__ . '/../files/Hid.dll';

if (file_exists($hid_dll_path)) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="Hid.dll"');
    header('Content-Length: ' . filesize($hid_dll_path));
    readfile($hid_dll_path);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo não encontrado']);
}
?>