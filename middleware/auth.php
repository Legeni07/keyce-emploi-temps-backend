<?php
/**
 * middleware/auth.php
 * Middleware d'authentification JWT.
 * Injecte $currentUser dans le scope global ou arrête la requête.
 */

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Vérifie le JWT et retourne le payload utilisateur.
 * Arrête l'exécution avec 401 si invalide.
 */
function requireAuth(): array
{
    $token = JWT::fromHeader();

    if (!$token) {
        Response::unauthorized('Token d\'authentification manquant. Connectez-vous.');
    }

    $payload = JWT::decode($token);

    if (!$payload) {
        Response::unauthorized('Token invalide ou expiré. Reconnectez-vous.');
    }

    return $payload;
}

/**
 * Vérifie le JWT ET le rôle requis.
 * Arrête l'exécution avec 403 si rôle insuffisant.
 *
 * @param string|array $roles Rôle(s) autorisé(s)
 */
function requireRole(string|array $roles): array
{
    $user = requireAuth();

    $allowed = is_array($roles) ? $roles : [$roles];

    if (!in_array($user['role'], $allowed, true)) {
        Response::forbidden(
            "Accès refusé. Rôle requis : " . implode(' ou ', $allowed) .
            ". Votre rôle : {$user['role']}."
        );
    }

    return $user;
}

/**
 * Vérifie optionnellement le JWT (ne bloque pas si absent).
 * Utile pour les endpoints accessibles avec ou sans connexion.
 */
function optionalAuth(): ?array
{
    $token = JWT::fromHeader();
    if (!$token) {
        return null;
    }
    return JWT::decode($token);
}
