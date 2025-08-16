<?php
// app/config.php
// === Basic App Config ===
declare(strict_types=1);

define('APP_NAME', 'Controle de Acesso');
define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('APP_DEBUG', getenv('APP_DEBUG') ? filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOL) : true);

// Base URL (ajuste se não estiver no domínio raiz)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace(basename($_SERVER['SCRIPT_NAME'] ?? ''), '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
define('BASE_URL', $protocol . $host . $scriptDir);

// === Database Config ===
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'goesconn_evento');
define('DB_USER', getenv('DB_USER') ?: 'goesconn_evento');
define('DB_PASS', getenv('DB_PASS') ?: 'goesconn_evento');
define('DB_CHARSET', 'utf8mb4');

// Timezone e formatos
date_default_timezone_set('America/Sao_Paulo');
