<?php
/**
 * config/cors.php
 * Gestion des en-têtes CORS pour permettre les requêtes
 * depuis le frontend React (localhost:5173 par défaut).
 */

function applyCors(): void
{
    $allowedOrigins = [
        'http://localhost:5173',    // Vite dev server
        'http://localhost:3000',    // CRA fallback
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
    ];

    // En production, remplacer par votre domaine réel
    $prodOrigin = getenv('FRONTEND_URL');
    if ($prodOrigin) {
        $allowedOrigins[] = $prodOrigin;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        // Fallback permissif pour les tests étudiants
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // Cache preflight 24h

    // Répondre aux requêtes preflight OPTIONS immédiatement
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
