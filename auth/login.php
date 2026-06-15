<?php
/**
 * POST /auth/login
 * Authentification — retourne un JWT valide 24h.
 *
 * Body JSON : { "email": "...", "password": "..." }
 * Réponse   : { "success": true, "data": { "token": "...", "user": {...} } }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';

applyCors();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Méthode non autorisée. Utilisez POST.', 405);
}

$body = json_decode(file_get_contents('php://input'), true);

// ── Validation des champs
$errors = [];
if (empty($body['email']) || !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Email invalide ou manquant.';
}
if (empty($body['password']) || strlen($body['password']) < 6) {
    $errors['password'] = 'Mot de passe manquant ou trop court (min. 6 caractères).';
}

if (!empty($errors)) {
    Response::validationError($errors);
}

$pdo = getPDO();

// ── Recherche de l'utilisateur
$stmt = $pdo->prepare('
    SELECT id, nom, prenom, email, password, role, ref_id, actif
    FROM users
    WHERE email = :email
    LIMIT 1
');
$stmt->execute([':email' => trim($body['email'])]);
$user = $stmt->fetch();

if (!$user) {
    Response::error('Identifiants incorrects.', 401);
}

if (!$user['actif']) {
    Response::error('Votre compte est désactivé. Contactez l\'administrateur.', 403);
}

// ── Vérification du mot de passe (bcrypt)
if (!password_verify($body['password'], $user['password'])) {
    Response::error('Identifiants incorrects.', 401);
}

// ── Génération du JWT
$payload = [
    'user_id' => (int) $user['id'],
    'email'   => $user['email'],
    'role'    => $user['role'],
    'ref_id'  => $user['ref_id'] ? (int) $user['ref_id'] : null,
    'nom'     => $user['nom'],
    'prenom'  => $user['prenom'],
];

$token = JWT::encode($payload, expiry: 86400); // 24h

// ── Réponse
unset($user['password']);

Response::success([
    'token'      => $token,
    'token_type' => 'Bearer',
    'expires_in' => 86400,
    'user'       => [
        'id'     => (int) $user['id'],
        'nom'    => $user['nom'],
        'prenom' => $user['prenom'],
        'email'  => $user['email'],
        'role'   => $user['role'],
        'ref_id' => $user['ref_id'] ? (int) $user['ref_id'] : null,
    ],
], 'Connexion réussie. Bienvenue ' . $user['prenom'] . ' !');
