<?php
/**
 * config/database.php
 * Connexion PDO centralisée — Keyce Emplois du Temps
 *
 * Variables d'environnement supportées (via .env.php ou serveur) :
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
 */

function getEnv(string $key, string $default = ''): string
{
    // Priorité : variables système > .env.php local
    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }
    $envFile = __DIR__ . '/../.env.php';
    if (file_exists($envFile)) {
        static $envVars = null;
        if ($envVars === null) {
            $envVars = include $envFile;
        }
        return $envVars[$key] ?? $default;
    }
    return $default;
}

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host    = getEnv('DB_HOST',    'localhost');
    $port    = getEnv('DB_PORT',    '3306');
    $dbname  = getEnv('DB_NAME',    'keyce_emploi_temps');
    $user    = getEnv('DB_USER',    'root');
    $pass    = getEnv('DB_PASS',    '');
    $charset = getEnv('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de connexion à la base de données.',
            'debug'   => (getEnv('APP_ENV') === 'development') ? $e->getMessage() : null,
        ]);
        exit;
    }

    return $pdo;
}
