<?php
// Exibe todos os erros para facilitar a depuração. Remova em produção.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==============================================================================
// CONFIGURAÇÕES - PREENCHA COM OS SEUS DADOS
// ==============================================================================
// Dados do seu banco de dados
$db_config = [
    'host' => 'localhost', // <<< By LiKc
    'dbname' => 'minec761_comprejogos',
    'user' => 'minec761_comprejogos',
    'password' => 'pr9n0xz5zxk2'
];

// Caminho ABSOLUTO no servidor para a pasta onde os jogos serão descompactados.
define('JOGOS_ORGANIZADOS_PATH', '/home/minec761/public_html/jogos'); // <<< 

// Tamanho máximo do arquivo .zip em bytes (ex: 2GB)
define('MAX_FILE_SIZE', 2 * 1024 * 1024 * 1024);
// ==============================================================================

// Inicia o buffer de saída para ver o progresso em tempo real
ob_implicit_flush(true);
ob_end_flush();

echo "<pre>"; // Usa a tag <pre> para formatar a saída de forma legível

// Verifica se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Acesso inválido. Por favor, use o formulário de upload.");
}

// 1. VALIDAÇÃO DOS DADOS E DO ARQUIVO
// =================================
echo "PASSO 1/5: Validando dados do formulário e arquivo...\n";

// <<< ALTERADO: Valida e recebe o nome do jogo do novo campo de texto
if (empty(trim($_POST['nome_jogo']))) {
    die("[ERRO] O campo 'Nome do Jogo' não pode estar vazio.");
}
// Pega o nome do jogo do formulário
$game_name = trim($_POST['nome_jogo']);

// Sanitiza o nome para criar um nome de pasta seguro (remove caracteres inválidos)
$game_folder_name = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $game_name);
$game_folder_name = preg_replace('/\s+/', '_', $game_folder_name); // Substitui espaços por underscores

if (!isset($_FILES["arquivo_zip"]) || $_FILES["arquivo_zip"]["error"] !== UPLOAD_ERR_OK) {
    die("[ERRO] Falha no upload. Código do erro: " . $_FILES["arquivo_zip"]["error"]);
}

$file_path = $_FILES["arquivo_zip"]["tmp_name"];
$file_size = $_FILES["arquivo_zip"]["size"];
$file_type = mime_content_type($file_path);
$original_filename = $_FILES["arquivo_zip"]["name"];

if ($file_size > MAX_FILE_SIZE) {
    die("[ERRO] O arquivo é muito grande. O tamanho máximo permitido é " . (MAX_FILE_SIZE / 1024 / 1024) . " MB.");
}

if ($file_type !== 'application/zip' && pathinfo($original_filename, PATHINFO_EXTENSION) !== 'zip') {
    die("[ERRO] Tipo de arquivo inválido. Apenas arquivos .zip são permitidos.");
}

echo "-> Nome do Jogo: $game_name\n";
echo "-> Nome da Pasta: $game_folder_name\n";


// 2. DESCOMPACTAÇÃO
// =================================
echo "\nPASSO 2/5: Descompactando arquivos...\n";
$extract_path = JOGOS_ORGANIZADOS_PATH . '/' . $game_folder_name;

if (!is_dir(JOGOS_ORGANIZADOS_PATH)) {
    mkdir(JOGOS_ORGANIZADOS_PATH, 0755, true);
}

$zip = new ZipArchive;
if ($zip->open($file_path) !== TRUE) {
    die("[ERRO] Não foi possível abrir o arquivo .zip.");
}

if (is_dir($extract_path)) {
    echo "-> AVISO: A pasta '$extract_path' já existe. Os arquivos serão sobrescritos.\n";
} else {
    mkdir($extract_path, 0755, true);
}

$zip->extractTo($extract_path);
$zip->close();
unlink($file_path); // Apaga o .zip temporário após descompactar
echo "-> Arquivos descompactados para: $extract_path\n";

// 3. BUSCA DE DADOS (AppID, Manifests, Lua)
// =================================
echo "\nPASSO 3/5: Analisando arquivos do jogo...\n";
$manifest_files = [];
$lua_files = [];
$appid = null;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extract_path));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $filename = $file->getFilename();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        if ($extension === 'manifest') {
            $manifest_files[] = $filename;
        } elseif ($extension === 'lua') {
            $lua_files[] = $filename;
        }
    }
}

$source_file_for_appid = !empty($manifest_files) ? $manifest_files[0] : (!empty($lua_files) ? $lua_files[0] : null);

if ($source_file_for_appid && preg_match('/^(\d+)/', $source_file_for_appid, $matches)) {
    $appid = $matches[1];
}

if (!$appid) {
    die("[ERRO FATAL] AppID não encontrado em nenhum arquivo .manifest ou .lua. A pasta '$game_folder_name' foi criada, mas o jogo não foi catalogado.");
}

echo "-> AppID encontrado: $appid\n";
echo "-> Arquivos .manifest encontrados: " . (!empty($manifest_files) ? implode(', ', $manifest_files) : 'Nenhum') . "\n";
echo "-> Arquivos .lua encontrados: " . (!empty($lua_files) ? implode(', ', $lua_files) : 'Nenhum') . "\n";


// 4. CONEXÃO E ATUALIZAÇÃO DO BANCO DE DADOS
// =================================
echo "\nPASSO 4/5: Conectando ao banco de dados e preparando a transação...\n";
$pdo = null;
try {
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, $db_config['user'], $db_config['password'], $options);
    echo "-> Conexão bem-sucedida!\n";
} catch (PDOException $e) {
    die("[ERRO DE CONEXÃO] " . $e->getMessage());
}

try {
    $pdo->beginTransaction();

    // <<< ALTERADO: Usa a variável $game_name (do formulário) para a coluna 'nome'
    // e $game_folder_name (nome sanitizado) para a coluna 'nome_pasta'.
    $sql_jogo = "
        INSERT INTO jogos (appid, nome, nome_pasta, arquivo_lua)
        VALUES (:appid, :nome, :nome_pasta, :arquivo_lua)
        ON DUPLICATE KEY UPDATE
            nome = VALUES(nome),
            nome_pasta = VALUES(nome_pasta),
            arquivo_lua = VALUES(arquivo_lua)
    ";
    
    $stmt_jogo = $pdo->prepare($sql_jogo);
    $stmt_jogo->execute([
        ':appid' => $appid,
        ':nome' => $game_name,
        ':nome_pasta' => $game_folder_name,
        ':arquivo_lua' => !empty($lua_files) ? $lua_files[0] : null
    ]);
    echo "-> Jogo principal (AppID: $appid) inserido/atualizado na tabela 'jogos'.\n";

    $stmt_delete = $pdo->prepare("DELETE FROM manifests_jogos WHERE jogo_appid = :appid");
    $stmt_delete->execute([':appid' => $appid]);
    echo "-> Manifests antigos para o AppID $appid foram removidos.\n";
    
    if (!empty($manifest_files)) {
        $sql_manifest = "INSERT INTO manifests_jogos (jogo_appid, nome_arquivo) VALUES (:appid, :nome_arquivo)";
        $stmt_manifest = $pdo->prepare($sql_manifest);
        
        foreach ($manifest_files as $manifest) {
            $stmt_manifest->execute([
                ':appid' => $appid,
                ':nome_arquivo' => $manifest
            ]);
        }
        echo "-> " . count($manifest_files) . " novo(s) manifest(s) inserido(s) na tabela 'manifests_jogos'.\n";
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo) {
        $pdo->rollBack();
    }
    die("[ERRO DE BANCO DE DADOS] A operação foi cancelada. Erro: " . $e->getMessage());
}

// 5. CONCLUSÃO
// =================================
echo "\nPASSO 5/5: Processo concluído!\n";
echo "\n=======================================================\n";
echo "✅  O JOGO '$game_name' FOI CATALOGADO COM SUCESSO!\n";
echo "=======================================================\n";

echo '</pre>';
echo '<br><a href="index.html">Voltar para a página de upload</a>';

?>