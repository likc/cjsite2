<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Catalogador de Jogos</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; max-width: 600px; margin: auto; }
        h1 { color: #333; }
        form { border: 1px solid #ccc; padding: 20px; border-radius: 5px; background-color: #f9f9f9; }
        input[type="text"], input[type="file"] { border: 1px solid #ccc; padding: 8px; width: 95%; margin-bottom: 10px; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        input[type="submit"]:hover { background-color: #0056b3; }
        label { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Catalogador de Jogos</h1>
    <p>Preencha o nome do jogo e envie o arquivo <code>.zip</code> correspondente.</p>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <p>
            <label for="nome_jogo">Nome do Jogo:</label><br>
            <input type="text" name="nome_jogo" id="nome_jogo" placeholder="Ex: Grand Theft Auto V" required>
        </p>
        <p>
            <label for="arquivo_zip">Arquivo .zip com ID do jogo (ex: 400.zip):</label><br>
            <input type="file" name="arquivo_zip" id="arquivo_zip" accept=".zip" required>
        </p>
        <br>
        <p>
            <input type="submit" value="Catalogar Jogo" name="submit">
        </p>
    </form>
</body>
</html>