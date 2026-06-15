<?php
/**
 * /api/filieres/index.php
 * GET    → Liste des filières (paginée, recherche)
 * POST   → Créer une filière
 * PUT    → Modifier (via ?id=)
 * DELETE → Supprimer (via ?id=)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/auth.php';

applyCors();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getPDO();

// ── GET : liste avec pagination + recherche
if ($method === 'GET') {
    requireAuth(); // Tous les rôles peuvent lire

    $search  = trim($_GET['search'] ?? '');
    $niveau  = trim($_GET['niveau'] ?? '');
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]          = '(code LIKE :search OR libelle LIKE :search OR responsable LIKE :search)';
        $params[':search'] = "%{$search}%";
    }
    if ($niveau !== '') {
        $where[]          = 'niveau = :niveau';
        $params[':niveau'] = $niveau;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM filieres {$whereSQL}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Données
    $stmt = $pdo->prepare("
        SELECT f.*,
               COUNT(DISTINCT c.id) AS nb_classes,
               COUNT(DISTINCT m.id) AS nb_matieres
        FROM filieres f
        LEFT JOIN classes  c ON c.filiere_id = f.id
        LEFT JOIN matieres m ON m.filiere_id = f.id
        {$whereSQL}
        GROUP BY f.id
        ORDER BY f.niveau ASC, f.code ASC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $filieres = $stmt->fetchAll();

    // Cast types
    foreach ($filieres as &$f) {
        $f['id']          = (int) $f['id'];
        $f['nb_classes']  = (int) $f['nb_classes'];
        $f['nb_matieres'] = (int) $f['nb_matieres'];
    }
    unset($f);

    Response::paginated($filieres, $total, $page, $perPage);
}

// ── POST : créer
if ($method === 'POST') {
    requireRole(['admin', 'responsable']);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = validateFiliere($body);

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    // Vérifier unicité du code
    $check = $pdo->prepare('SELECT id FROM filieres WHERE code = :code LIMIT 1');
    $check->execute([':code' => strtoupper(trim($body['code']))]);
    if ($check->fetch()) {
        Response::validationError(['code' => "Le code '{$body['code']}' est déjà utilisé."]);
    }

    $stmt = $pdo->prepare('
        INSERT INTO filieres (code, libelle, niveau, responsable, couleur)
        VALUES (:code, :libelle, :niveau, :responsable, :couleur)
    ');
    $stmt->execute([
        ':code'        => strtoupper(trim($body['code'])),
        ':libelle'     => trim($body['libelle']),
        ':niveau'      => $body['niveau'],
        ':responsable' => trim($body['responsable'] ?? ''),
        ':couleur'     => $body['couleur'] ?? '#1565C0',
    ]);

    $id = (int) $pdo->lastInsertId();
    $filiere = $pdo->query("SELECT * FROM filieres WHERE id = {$id}")->fetch();
    $filiere['id'] = (int) $filiere['id'];

    Response::created($filiere, 'Filière créée avec succès.');
}

// ── PUT : modifier
if ($method === 'PUT') {
    requireRole(['admin', 'responsable']);

    $id   = (int) ($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!$id) {
        Response::error('Paramètre id manquant.', 400);
    }

    $existing = $pdo->prepare('SELECT id FROM filieres WHERE id = :id');
    $existing->execute([':id' => $id]);
    if (!$existing->fetch()) {
        Response::notFound("Filière #{$id} introuvable.");
    }

    $errors = validateFiliere($body, $id);
    if (!empty($errors)) {
        Response::validationError($errors);
    }

    // Unicité code (exclure soi-même)
    $check = $pdo->prepare('SELECT id FROM filieres WHERE code = :code AND id != :id LIMIT 1');
    $check->execute([':code' => strtoupper(trim($body['code'])), ':id' => $id]);
    if ($check->fetch()) {
        Response::validationError(['code' => "Le code '{$body['code']}' est déjà utilisé."]);
    }

    $stmt = $pdo->prepare('
        UPDATE filieres
        SET code = :code, libelle = :libelle, niveau = :niveau,
            responsable = :responsable, couleur = :couleur
        WHERE id = :id
    ');
    $stmt->execute([
        ':code'        => strtoupper(trim($body['code'])),
        ':libelle'     => trim($body['libelle']),
        ':niveau'      => $body['niveau'],
        ':responsable' => trim($body['responsable'] ?? ''),
        ':couleur'     => $body['couleur'] ?? '#1565C0',
        ':id'          => $id,
    ]);

    $filiere = $pdo->query("SELECT * FROM filieres WHERE id = {$id}")->fetch();
    $filiere['id'] = (int) $filiere['id'];

    Response::success($filiere, 'Filière mise à jour.');
}

// ── DELETE : supprimer
if ($method === 'DELETE') {
    requireRole(['admin']);

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        Response::error('Paramètre id manquant.', 400);
    }

    // Vérifier dépendances
    $depCheck = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE filiere_id = :id');
    $depCheck->execute([':id' => $id]);
    if ((int) $depCheck->fetchColumn() > 0) {
        Response::error('Impossible de supprimer : des classes sont rattachées à cette filière.', 409);
    }

    $stmt = $pdo->prepare('DELETE FROM filieres WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        Response::notFound("Filière #{$id} introuvable.");
    }

    Response::success(null, 'Filière supprimée.');
}

// ── Validation
function validateFiliere(array $data, int $excludeId = 0): array
{
    $errors = [];
    if (empty($data['code']) || strlen($data['code']) > 10) {
        $errors['code'] = 'Le code est requis (max 10 caractères).';
    }
    if (empty($data['libelle']) || strlen($data['libelle']) > 100) {
        $errors['libelle'] = 'Le libellé est requis (max 100 caractères).';
    }
    $niveaux = ['B1', 'B2', 'B3', 'M1', 'M2'];
    if (empty($data['niveau']) || !in_array($data['niveau'], $niveaux, true)) {
        $errors['niveau'] = 'Niveau invalide. Valeurs acceptées : ' . implode(', ', $niveaux);
    }
    if (!empty($data['couleur']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['couleur'])) {
        $errors['couleur'] = 'Format de couleur invalide (ex: #1565C0).';
    }
    return $errors;
}

Response::error('Méthode non autorisée.', 405);
