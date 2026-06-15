<?php
/**
 * index.php — Point d'entrée principal de l'API REST
 * Keyce Informatique — Gestion Emplois du Temps
 *
 * Toutes les requêtes sont routées ici via .htaccess (mod_rewrite).
 * Format des URLs : /api/{ressource}[/{action}][?param=valeur]
 *
 * Endpoints disponibles :
 *   POST   /api/auth/login           Connexion
 *   GET    /api/auth/me              Profil utilisateur connecté
 *   GET    /api/auth/logout          Déconnexion (côté client)
 *
 *   GET|POST|PUT|DELETE  /api/filieres
 *   GET|POST|PUT|DELETE  /api/classes
 *   GET|POST|PUT|DELETE  /api/matieres
 *   GET|POST|PUT|DELETE  /api/enseignants
 *   GET|POST|PUT|DELETE  /api/salles
 *   GET|POST|PUT|PATCH|DELETE  /api/creneaux
 *   GET    /api/creneaux/verifier    Vérification des conflits
 *   GET|POST|PUT|DELETE  /api/users
 *   GET    /api/stats                Statistiques dashboard
 *   GET|POST|PUT|DELETE  /api/indisponibilites
 */

require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/utils/Response.php';

applyCors();
header('Content-Type: application/json');

// ── Parse de l'URI
$requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName  = dirname($_SERVER['SCRIPT_NAME']);
$path        = str_replace($scriptName, '', $requestUri);
$path        = strtok($path, '?'); // retirer la query string
$path        = trim($path, '/');
$segments    = explode('/', $path); // ['api', 'creneaux', 'verifier', ...]

// Retirer 'api' si présent
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

$resource = $segments[0] ?? '';
$action   = $segments[1] ?? '';

// ── Table de routage
$routes = [
    'auth'              => [
        ''        => __DIR__ . '/auth/login.php',
        'login'   => __DIR__ . '/auth/login.php',
        'me'      => __DIR__ . '/auth/me.php',
        'logout'  => __DIR__ . '/auth/me.php', // token JWT — logout côté client
    ],
    'filieres'          => __DIR__ . '/api/filieres/index.php',
    'classes'           => __DIR__ . '/api/classes/index.php',
    'matieres'          => __DIR__ . '/api/matieres/index.php',
    'enseignants'       => __DIR__ . '/api/enseignants/index.php',
    'salles'            => __DIR__ . '/api/salles/index.php',
    'creneaux'          => __DIR__ . '/api/creneaux/index.php',
    'users'             => __DIR__ . '/api/users/index.php',
    'stats'             => __DIR__ . '/api/stats/index.php',
    'indisponibilites'  => __DIR__ . '/api/indisponibilites/index.php',
];

// ── Résolution du fichier cible
$target = null;

if (isset($routes[$resource])) {
    $route = $routes[$resource];

    if (is_array($route)) {
        // Route avec sous-actions (ex: auth/login, auth/me)
        $target = $route[$action] ?? $route[''] ?? null;
    } else {
        // Route directe — passer PATH_INFO pour les sous-routes (ex: creneaux/verifier)
        $_SERVER['PATH_INFO'] = '/' . implode('/', array_slice($segments, 1));
        $target = $route;
    }
}

// ── Dispatch
if ($target && file_exists($target)) {
    require $target;
} elseif ($resource === '') {
    // Racine de l'API : documentation
    Response::success([
        'api'      => 'Keyce Emplois du Temps — API REST',
        'version'  => '1.0.0',
        'author'   => 'BOGNI-DANCHI T.',
        'base_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['SCRIPT_NAME']),
        'endpoints' => [
            'POST   /api/auth/login'          => 'Connexion — retourne JWT',
            'GET    /api/auth/me'             => 'Profil utilisateur (JWT requis)',
            'GET    /api/filieres'            => 'Liste des filières',
            'POST   /api/filieres'            => 'Créer une filière',
            'PUT    /api/filieres?id=X'       => 'Modifier une filière',
            'DELETE /api/filieres?id=X'       => 'Supprimer une filière',
            'GET    /api/classes'             => 'Liste des classes',
            'GET    /api/matieres'            => 'Liste des matières',
            'GET    /api/enseignants'         => 'Liste des enseignants',
            'GET    /api/salles'              => 'Liste des salles',
            'GET    /api/creneaux'            => 'Emploi du temps (filtrable)',
            'GET    /api/creneaux/verifier'   => 'Vérifier les conflits horaires',
            'GET    /api/stats'               => 'Statistiques dashboard',
            'GET    /api/indisponibilites'    => 'Indisponibilités enseignants',
        ],
        'demo_accounts' => [
            ['email' => 'admin@keyce.cm',        'password' => 'Keyce2025!', 'role' => 'admin'],
            ['email' => 'responsable@keyce.cm',  'password' => 'Keyce2025!', 'role' => 'responsable'],
            ['email' => 'enseignant1@keyce.cm',  'password' => 'Keyce2025!', 'role' => 'enseignant'],
            ['email' => 'etudiant1@keyce.cm',    'password' => 'Keyce2025!', 'role' => 'etudiant'],
        ],
    ], 'Bienvenue sur l\'API Keyce Emplois du Temps.');
} else {
    Response::notFound("Endpoint /{$resource}" . ($action ? "/{$action}" : '') . " introuvable.");
}
