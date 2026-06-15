<?php
/**
 * GET /auth/me
 * Retourne le profil de l'utilisateur connecté (validation du token).
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';

applyCors();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Méthode non autorisée.', 405);
}

$authUser = requireAuth();
$pdo = getPDO();

$stmt = $pdo->prepare('
    SELECT id, nom, prenom, email, role, ref_id, actif, created_at
    FROM users WHERE id = :id LIMIT 1
');
$stmt->execute([':id' => $authUser['user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['actif']) {
    Response::unauthorized('Compte introuvable ou désactivé.');
}

Response::success([
    'id'         => (int) $user['id'],
    'nom'        => $user['nom'],
    'prenom'     => $user['prenom'],
    'email'      => $user['email'],
    'role'       => $user['role'],
    'ref_id'     => $user['ref_id'] ? (int) $user['ref_id'] : null,
    'created_at' => $user['created_at'],
]);
