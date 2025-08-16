<?php
// test.php - Arquivo para testar se o PHP está funcionando
echo "<h1>Teste PHP - " . date('Y-m-d H:i:s') . "</h1>";

// Testar configuração básica
echo "<h2>Informações do Sistema:</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

// Testar se as funções necessárias existem
echo "<h2>Funções Disponíveis:</h2>";
$functions = ['mysqli_connect', 'PDO', 'mail', 'finfo_open', 'random_bytes'];
foreach ($functions as $func) {
    $status = (class_exists($func) || function_exists($func)) ? '✅' : '❌';
    echo "$status $func<br>";
}

// Testar diretórios necessários
echo "<h2>Estrutura de Arquivos:</h2>";
$dirs = [
    __DIR__ . '/partials',
    __DIR__ . '/app', 
    __DIR__ . '/public',
    __DIR__ . '/public/uploads'
];

foreach ($dirs as $dir) {
    $exists = is_dir($dir) ? '✅' : '❌';
    $writable = is_writable($dir) ? '(gravável)' : '(não gravável)';
    echo "$exists $dir $writable<br>";
}

// Testar arquivos necessários
echo "<h2>Arquivos Necessários:</h2>";
$files = [
    __DIR__ . '/partials/header.php',
    __DIR__ . '/partials/footer.php',
    __DIR__ . '/app/db.php',
    __DIR__ . '/app/helpers.php',
    __DIR__ . '/app/mail.php'
];

foreach ($files as $file) {
    $exists = file_exists($file) ? '✅' : '❌';
    echo "$exists $file<br>";
}

// Testar se consegue incluir arquivos essenciais
echo "<h2>Teste de Inclusão de Arquivos:</h2>";
try {
    if (file_exists(__DIR__ . '/app/helpers.php')) {
        require_once __DIR__ . '/app/helpers.php';
        echo "✅ helpers.php carregado com sucesso<br>";
    } else {
        echo "❌ helpers.php não encontrado<br>";
    }
} catch (Throwable $e) {
    echo "❌ Erro ao carregar helpers.php: " . $e->getMessage() . "<br>";
}

try {
    if (file_exists(__DIR__ . '/app/db.php')) {
        require_once __DIR__ . '/app/db.php';
        echo "✅ db.php carregado com sucesso<br>";
        
        // Testar conexão com banco
        if (function_exists('db')) {
            $pdo = db();
            echo "✅ Conexão com banco estabelecida<br>";
        }
    } else {
        echo "❌ db.php não encontrado<br>";
    }
} catch (Throwable $e) {
    echo "❌ Erro com banco de dados: " . $e->getMessage() . "<br>";
}

// Exibir últimos erros PHP
echo "<h2>Últimos Erros PHP:</h2>";
$last_error = error_get_last();
if ($last_error) {
    echo "Último erro: " . $last_error['message'] . " em " . $last_error['file'] . " linha " . $last_error['line'] . "<br>";
} else {
    echo "Nenhum erro PHP registrado.<br>";
}

// Verificar configurações de erro
echo "<h2>Configurações de Erro:</h2>";
echo "display_errors: " . ini_get('display_errors') . "<br>";
echo "log_errors: " . ini_get('log_errors') . "<br>";
echo "error_log: " . ini_get('error_log') . "<br>";

echo "<hr>";
echo "<p><strong>Próximos passos:</strong></p>";
echo "<ul>";
echo "<li>Se houver ❌, corrija os problemas indicados</li>";
echo "<li>Verifique o error_log se houver erros</li>";
echo "<li>Certifique-se de que todos os arquivos necessários existam</li>";
echo "</ul>";
?>