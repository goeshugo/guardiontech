<?php
// debug.php - Arquivo permanente para diagnóstico e logs
ini_set('display_errors', 1);
error_reporting(E_ALL);

$action = $_GET['action'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Sistema de Visitantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-content {
            background: #1e1e1e;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .error-line {
            background: rgba(220, 53, 69, 0.2);
            border-left: 3px solid #dc3545;
            padding: 2px 5px;
            margin: 2px 0;
        }
        .warning-line {
            background: rgba(255, 193, 7, 0.2);
            border-left: 3px solid #ffc107;
            padding: 2px 5px;
            margin: 2px 0;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .warning {
            color: #ffc107;
        }
        .info {
            color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>🔧 Debug - Sistema de Visitantes</h1>
                <p class="text-muted">Diagnóstico completo do sistema e visualização de logs</p>
                
                <!-- Menu de Navegação -->
                <nav class="nav nav-pills mb-4">
                    <a class="nav-link <?= $action === 'dashboard' ? 'active' : '' ?>" href="?action=dashboard">Dashboard</a>
                    <a class="nav-link <?= $action === 'logs' ? 'active' : '' ?>" href="?action=logs">Logs de Erro</a>
                    <a class="nav-link <?= $action === 'files' ? 'active' : '' ?>" href="?action=files">Verificar Arquivos</a>
                    <a class="nav-link <?= $action === 'database' ? 'active' : '' ?>" href="?action=database">Banco de Dados</a>
                    <a class="nav-link <?= $action === 'phpinfo' ? 'active' : '' ?>" href="?action=phpinfo">PHP Info</a>
                    <a class="nav-link <?= $action === 'test' ? 'active' : '' ?>" href="?action=test">Teste Completo</a>
                </nav>
            </div>
        </div>

        <?php if ($action === 'dashboard'): ?>
            <!-- Dashboard Geral -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Status do Sistema</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $status = [];
                            
                            // Verificar arquivos críticos
                            $criticalFiles = [
                                'app/db.php',
                                'app/helpers.php', 
                                'app/mail.php',
                                'partials/header.php',
                                'pages/visitors.php'
                            ];
                            
                            $filesOk = 0;
                            foreach ($criticalFiles as $file) {
                                if (file_exists($file)) $filesOk++;
                            }
                            
                            echo "<p><strong>Arquivos:</strong> ";
                            if ($filesOk === count($criticalFiles)) {
                                echo "<span class='success'>✅ Todos os arquivos presentes ($filesOk/" . count($criticalFiles) . ")</span>";
                            } else {
                                echo "<span class='error'>❌ Arquivos faltando ($filesOk/" . count($criticalFiles) . ")</span>";
                            }
                            echo "</p>";
                            
                            // Verificar logs de erro
                            $errorLogSize = 0;
                            if (file_exists('error_log')) {
                                $errorLogSize = filesize('error_log');
                            }
                            
                            echo "<p><strong>Logs de Erro:</strong> ";
                            if ($errorLogSize === 0) {
                                echo "<span class='success'>✅ Nenhum erro recente</span>";
                            } elseif ($errorLogSize < 10000) {
                                echo "<span class='warning'>⚠️ Alguns erros (" . number_format($errorLogSize) . " bytes)</span>";
                            } else {
                                echo "<span class='error'>❌ Muitos erros (" . number_format($errorLogSize) . " bytes)</span>";
                            }
                            echo "</p>";
                            
                            // Verificar conexão com banco
                            echo "<p><strong>Banco de Dados:</strong> ";
                            try {
                                if (file_exists('app/db.php')) {
                                    require_once 'app/db.php';
                                    if (function_exists('db')) {
                                        $pdo = db();
                                        $stmt = $pdo->query("SELECT 1");
                                        echo "<span class='success'>✅ Conectado</span>";
                                    } else {
                                        echo "<span class='error'>❌ Função db() não encontrada</span>";
                                    }
                                } else {
                                    echo "<span class='error'>❌ Arquivo db.php não encontrado</span>";
                                }
                            } catch (Exception $e) {
                                echo "<span class='error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</span>";
                            }
                            echo "</p>";
                            
                            // Verificar permissões de upload
                            echo "<p><strong>Upload de Arquivos:</strong> ";
                            $uploadDir = 'public/uploads/visitors';
                            if (is_dir($uploadDir)) {
                                if (is_writable($uploadDir)) {
                                    echo "<span class='success'>✅ Diretório gravável</span>";
                                } else {
                                    echo "<span class='warning'>⚠️ Diretório não gravável</span>";
                                }
                            } else {
                                echo "<span class='error'>❌ Diretório não existe</span>";
                            }
                            echo "</p>";
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Informações do Servidor</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>PHP:</strong> <?= phpversion() ?></p>
                            <p><strong>Servidor:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></p>
                            <p><strong>Memória:</strong> <?= ini_get('memory_limit') ?></p>
                            <p><strong>Upload Máx:</strong> <?= ini_get('upload_max_filesize') ?></p>
                            <p><strong>POST Máx:</strong> <?= ini_get('post_max_size') ?></p>
                            <p><strong>Diretório:</strong> <?= __DIR__ ?></p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'logs'): ?>
            <!-- Visualizar Logs -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">📋 Logs de Erro</h5>
                    <div>
                        <a href="?action=logs&clear=1" class="btn btn-sm btn-warning" onclick="return confirm('Limpar todos os logs?')">Limpar Logs</a>
                        <a href="?action=logs" class="btn btn-sm btn-primary">Atualizar</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    if (isset($_GET['clear'])) {
                        if (file_exists('error_log')) {
                            file_put_contents('error_log', '');
                            echo '<div class="alert alert-success">Logs limpos com sucesso!</div>';
                        }
                    }
                    
                    if (file_exists('error_log') && filesize('error_log') > 0) {
                        $logs = file_get_contents('error_log');
                        $lines = explode("\n", $logs);
                        $lines = array_reverse(array_filter($lines)); // Mais recentes primeiro
                        $lines = array_slice($lines, 0, 100); // Últimas 100 linhas
                        
                        echo '<div class="log-content p-3">';
                        foreach ($lines as $line) {
                            if (empty(trim($line))) continue;
                            
                            $cssClass = '';
                            if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                                $cssClass = 'error-line';
                            } elseif (stripos($line, 'warning') !== false) {
                                $cssClass = 'warning-line';
                            }
                            
                            echo '<div class="' . $cssClass . '">' . htmlspecialchars($line) . '</div>';
                        }
                        echo '</div>';
                        
                        echo '<p class="mt-3 text-muted">Mostrando últimas ' . count($lines) . ' linhas (mais recentes primeiro)</p>';
                    } else {
                        echo '<div class="alert alert-success">Nenhum erro registrado! 🎉</div>';
                    }
                    ?>
                </div>
            </div>

        <?php elseif ($action === 'files'): ?>
            <!-- Verificar Arquivos -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📁 Verificação de Arquivos</h5>
                </div>
                <div class="card-body">
                    <?php
                    $fileStructure = [
                        'Configuração' => [
                            'app/db.php',
                            'app/helpers.php',
                            'app/mail.php',
                            'app/config.php'
                        ],
                        'Interface' => [
                            'partials/header.php',
                            'partials/footer.php',
                            'pages/visitors.php',
                            'public/visitor_register.php'
                        ],
                        'Uploads' => [
                            'public/uploads/',
                            'public/uploads/visitors/'
                        ],
                        'Outros' => [
                            'index.php',
                            'debug.php'
                        ]
                    ];
                    
                    foreach ($fileStructure as $category => $files) {
                        echo "<h6 class='mt-3 mb-2'>$category:</h6>";
                        echo "<ul class='list-unstyled ms-3'>";
                        
                        foreach ($files as $file) {
                            $exists = file_exists($file);
                            $size = $exists ? filesize($file) : 0;
                            $isDir = is_dir($file);
                            $writable = $exists && is_writable($file);
                            
                            if ($exists) {
                                $icon = $isDir ? '📁' : '📄';
                                $sizeText = $isDir ? '' : ' (' . number_format($size) . ' bytes)';
                                $writableText = $isDir ? ($writable ? ' [gravável]' : ' [não gravável]') : '';
                                echo "<li><span class='success'>✅ $icon $file$sizeText$writableText</span></li>";
                            } else {
                                echo "<li><span class='error'>❌ $file (não encontrado)</span></li>";
                            }
                        }
                        echo "</ul>";
                    }
                    ?>
                </div>
            </div>

        <?php elseif ($action === 'database'): ?>
            <!-- Teste de Banco de Dados -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">🗄️ Banco de Dados</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        if (file_exists('app/db.php')) {
                            require_once 'app/db.php';
                            
                            if (function_exists('db')) {
                                $pdo = db();
                                echo '<div class="alert alert-success">✅ Conexão estabelecida com sucesso!</div>';
                                
                                // Verificar tabelas
                                echo '<h6>Tabelas do Sistema:</h6>';
                                $tables = ['users', 'visitors', 'visitor_invites', 'visitor_passes', 'settings'];
                                
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-sm">';
                                echo '<thead><tr><th>Tabela</th><th>Status</th><th>Registros</th></tr></thead><tbody>';
                                
                                foreach ($tables as $table) {
                                    try {
                                        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                                        $count = $stmt->fetchColumn();
                                        echo "<tr><td>$table</td><td><span class='success'>✅ Existe</span></td><td>$count</td></tr>";
                                    } catch (Exception $e) {
                                        echo "<tr><td>$table</td><td><span class='error'>❌ Não existe</span></td><td>-</td></tr>";
                                    }
                                }
                                
                                echo '</tbody></table></div>';
                                
                                // Teste de consulta
                                echo '<h6 class="mt-3">Teste de Consulta:</h6>';
                                try {
                                    $stmt = $pdo->query("SELECT NOW() as current_time");
                                    $result = $stmt->fetch();
                                    echo '<div class="alert alert-success">✅ Consulta executada: ' . $result['current_time'] . '</div>';
                                } catch (Exception $e) {
                                    echo '<div class="alert alert-danger">❌ Erro na consulta: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                }
                                
                            } else {
                                echo '<div class="alert alert-danger">❌ Função db() não encontrada no arquivo db.php</div>';
                            }
                        } else {
                            echo '<div class="alert alert-danger">❌ Arquivo app/db.php não encontrado</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">❌ Erro na conexão: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    ?>
                </div>
            </div>

        <?php elseif ($action === 'phpinfo'): ?>
            <!-- PHP Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">🐘 Informações do PHP</h5>
                </div>
                <div class="card-body">
                    <style>
                        .phpinfo table { width: 100% !important; }
                        .phpinfo .e { background: #f8f9fa; font-weight: bold; }
                        .phpinfo .v { background: white; }
                        .phpinfo h2 { color: #0d6efd; }
                    </style>
                    <div class="phpinfo">
                        <?php 
                        ob_start();
                        phpinfo();
                        $phpinfo = ob_get_clean();
                        
                        // Remover HTML desnecessário e manter apenas o conteúdo
                        $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);
                        echo $phpinfo;
                        ?>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'test'): ?>
            <!-- Teste Completo -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">🧪 Teste Completo do Sistema</h5>
                </div>
                <div class="card-body">
                    <?php
                    echo '<h6>Executando bateria completa de testes...</h6>';
                    
                    $tests = [];
                    
                    // Teste 1: Arquivos
                    try {
                        require_once 'app/helpers.php';
                        $tests[] = ['Helpers carregado', true, 'Arquivo app/helpers.php incluído com sucesso'];
                    } catch (Exception $e) {
                        $tests[] = ['Helpers carregado', false, $e->getMessage()];
                    }
                    
                    // Teste 2: Banco
                    try {
                        require_once 'app/db.php';
                        $pdo = db();
                        $stmt = $pdo->query("SELECT 1");
                        $tests[] = ['Banco de dados', true, 'Conexão e consulta funcionando'];
                    } catch (Exception $e) {
                        $tests[] = ['Banco de dados', false, $e->getMessage()];
                    }
                    
                    // Teste 3: Funções
                    $functions = ['e', 'flash', 'redirect', 'current_user'];
                    foreach ($functions as $func) {
                        $exists = function_exists($func);
                        $tests[] = ["Função $func()", $exists, $exists ? 'Disponível' : 'Não encontrada'];
                    }
                    
                    // Teste 4: Permissões
                    $uploadDir = 'public/uploads/visitors';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $writable = is_writable($uploadDir);
                    $tests[] = ['Diretório de upload', $writable, $writable ? 'Gravável' : 'Não gravável'];
                    
                    // Teste 5: Página visitors
                    try {
                        ob_start();
                        $_GET['page'] = 'visitors';
                        include 'pages/visitors.php';
                        ob_end_clean();
                        $tests[] = ['Página visitors', true, 'Carregou sem erros'];
                    } catch (Exception $e) {
                        ob_end_clean();
                        $tests[] = ['Página visitors', false, $e->getMessage()];
                    }
                    
                    // Exibir resultados
                    echo '<div class="table-responsive mt-3">';
                    echo '<table class="table">';
                    echo '<thead><tr><th>Teste</th><th>Status</th><th>Detalhes</th></tr></thead><tbody>';
                    
                    foreach ($tests as $test) {
                        $status = $test[1] ? '<span class="success">✅ Passou</span>' : '<span class="error">❌ Falhou</span>';
                        echo "<tr><td>{$test[0]}</td><td>$status</td><td>" . htmlspecialchars($test[2]) . "</td></tr>";
                    }
                    
                    echo '</tbody></table></div>';
                    
                    // Resumo
                    $passed = array_filter($tests, function($t) { return $t[1]; });
                    $total = count($tests);
                    $passedCount = count($passed);
                    
                    if ($passedCount === $total) {
                        echo '<div class="alert alert-success mt-3">🎉 Todos os testes passaram! Sistema funcionando corretamente.</div>';
                    } else {
                        echo "<div class='alert alert-warning mt-3'>⚠️ $passedCount de $total testes passaram. Verifique os itens que falharam.</div>";
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="mt-4 text-center text-muted">
            <small>Debug System v1.0 - Última atualização: <?= date('Y-m-d H:i:s') ?></small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh dos logs a cada 30 segundos
        if (window.location.search.includes('action=logs')) {
            setTimeout(() => {
                if (!window.location.search.includes('clear=1')) {
                    window.location.reload();
                }
            }, 30000);
        }
        
        // Scroll automático para o final dos logs
        const logContent = document.querySelector('.log-content');
        if (logContent) {
            logContent.scrollTop = logContent.scrollHeight;
        }
    </script>
</body>
</html>