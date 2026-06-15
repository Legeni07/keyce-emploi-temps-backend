<?php
/**
 * /api/enseignants/index.php
 * CRUD complet pour les enseignants.
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/auth.php';

applyCors();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getPDO();

// ── GET : liste
if ($method === 'GET') {
    requireAuth();

    $search  = trim($_GET['search'] ?? '');
    $statut  = trim($_GET['statut'] ?? '');
    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]           = '(nom LIKE :search OR prenom LIKE :search OR matricule LIKE :search OR email LIKE :search OR specialite LIKE :search)';
        $params[':search']  = "%{$search}%";
    }
    if (in_array($statut, ['Permanent', 'Vacataire'], true)) {
        $where[]           = 'statut = :statut';
        $params[':statut']  = $statut;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM enseignants {$whereSQL}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT e.*,
               COUNT(DISTINCT c.id) AS nb_creneaux_semaine
        FROM enseignants e
        LEFT JOIN creneaux c ON c.enseignant_id = e.id
            AND c.statut != 'annule'
        {$whereSQL}
        GROUP BY e.id
        ORDER BY e.nom ASC, e.prenom ASC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();

    $enseignants = $stmt->fetchAll();
    foreach ($enseignants as &$e) {
        $e['id']                  = (int) $e['id'];
        $e['nb_creneaux_semaine'] = (int) $e['nb_creneaux_semaine'];
    }
    unset($e);

    Response::paginated($enseignants, $total, $page, $perPage);
}

// ── POST : créer
if ($method === 'POST') {
    requireRole(['admin', 'responsable']);

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = validateEnseignant($body, $pdo);
    if (!empty($errors)) {
        Response::validationError($errors);
    }

    $stmt = $pdo->prepare('
        INSERT INTO enseignants (matricule, nom, prenom, email, specialite, statut)
        VALUES (:matricule, :nom, :prenom, :email, :specialite, :statut)
    ');
    $stmt->execute([
        ':matricule'  => strtoupper(trim($body['matricule'])),
        ':nom'        => strtoupper(trim($body['nom'])),
        ':prenom'     => ucfirst(strtolower(trim($body['prenom']))),
        ':email'      => strtolower(trim($body['email'])),
        ':specialite' => trim($body['specialite'] ?? ''),
        ':statut'     => $body['statut'],
    ]);

    $id = (int) $pdo->lastInsertId();
    $ens = $pdo->query("SELECT * FROM enseignants WHERE id = {$id}")->fetch();
    $ens['id'] = (int) $ens['id'];

    Response::created($ens, 'Enseignant créé avec succès.');
}

// ── PUT : modifier
if ($method === 'PUT') {
    requireRole(['admin', 'responsable']);

    $id   = (int) ($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!$id) {
        Response::error('Paramètre id manquant.', 400);
    }

    $check = $pdo->prepare('SELECT id FROM enseignants WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) {
        Response::notFound("Enseignant #{$id} introuvable.");
    }

    $errors = validateEnseignant($body, $pdo, $id);
    if (!empty($errors)) {
        Response::validationError($errors);
    }

    $stmt = $pdo->prepare('
        UPDATE enseignants
        SET matricule = :matricule, nom = :nom, prenom = :prenom,
            email = :email, specialite = :specialite, statut = :statut
        WHERE id = :id
    ');
    $stmt->execute([
        ':matricule'  => strtoupper(trim($body['matricule'])),
        ':nom'        => strtoupper(trim($body['nom'])),
        ':prenom'     => ucfirst(strtolower(trim($body['prenom']))),
        ':email'      => strtolower(trim($body['email'])),
        ':specialite' => trim($body['specialite'] ?? ''),
        ':statut'     => $body['statut'],
        ':id'         => $id,
    ]);

    $ens = $pdo->query("SELECT * FROM enseignants WHERE id = {$id}")->fetch();
    $ens['id'] = (int) $ens['id'];

    Response::success($ens, 'Enseignant mis à jour.');
}

// ── DELETE : supprimer
if ($method === 'DELETE') {
    requireRole(['admin']);

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        Response::error('Paramètre id manquant.', 400);
    }

    $dep = $pdo->prepare('SELECT COUNT(*) FROM creneaux WHERE enseignant_id = :id');
    $dep->execute([':id' => $id]);
    if ((int) $dep->fetchColumn() > 0) {
        Response::error('Impossible de supprimer : cet enseignant a des créneaux assignés.', 409);
    }

    $stmt = $pdo->prepare('DELETE FROM enseignants WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        Response::notFound("Enseignant #{$id} introuvable.");
    }

    Response::success(null, 'Enseignant supprimé.');
}

function validateEnseignant(array $data, PDO $pdo, int $excludeId = 0): array
{
    $errors = [];

    if (empty($data['matricule']) || !preg_match('/^ENS-\d{3,6}$/i', $data['matricule'])) {
        $errors['matricule'] = 'Matricule requis au format ENS-XXX (ex: ENS-045).';
    }
    if (empty($data['nom']) || strlen($data['nom']) > 50) {
        $errors['nom'] = 'Nom requis (max 50 caractères).';
    }
    if (empty($data['prenom']) || strlen($data['prenom']) > 50) {
        $errors['prenom'] = 'Prénom requis (max 50 caractères).';
    }
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email invalide.';
    }
    if (!in_array($data['statut'] ?? '', ['Permanent', 'Vacataire'], true)) {
        $errors['statut'] = 'Statut invalide. Valeurs : Permanent, Vacataire.';
    }

    // Unicité email
    if (empty($errors['email'])) {
        $checkEmail = $pdo->prepare('SELECT id FROM enseignants WHERE email = :email AND id != :id LIMIT 1');
        $checkEmail->execute([':email' => strtolower(trim($data['email'])), ':id' => $excludeId]);
        if ($checkEmail->fetch()) {
            $errors['email'] = 'Cet email est déjà utilisé par un autre enseignant.';
        }
    }

    // Unicité matricule
    if (empty($errors['matricule'])) {
        $checkMat = $pdo->prepare('SELECT id FROM enseignants WHERE matricule = :mat AND id != :id LIMIT 1');
        $checkMat->execute([':mat' => strtoupper(trim($data['matricule'])), ':id' => $excludeId]);
        if ($checkMat->fetch()) {
            $errors['matricule'] = 'Ce matricule est déjà utilisé.';
        }
    }

    return $errors;
}

Response::error('Méthode non autorisée.', 405);
