<?php
/**
 * /api/classes/index.php — CRUD classes (promotions)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/auth.php';

applyCors();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getPDO();

if ($method === 'GET') {
    requireAuth();

    $filiereId = (int) ($_GET['filiere_id'] ?? 0);
    $search    = trim($_GET['search'] ?? '');
    $page      = max(1, (int) ($_GET['page'] ?? 1));
    $perPage   = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset    = ($page - 1) * $perPage;

    $where = []; $params = [];
    if ($filiereId) {
        $where[] = 'c.filiere_id = :filiere_id';
        $params[':filiere_id'] = $filiereId;
    }
    if ($search !== '') {
        $where[] = 'c.nom LIKE :search';
        $params[':search'] = "%{$search}%";
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM classes c {$whereSQL}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT c.*, f.code AS filiere_code, f.libelle AS filiere_libelle,
               f.niveau AS filiere_niveau, f.couleur AS filiere_couleur,
               COUNT(DISTINCT cr.id) AS nb_creneaux
        FROM classes c
        JOIN filieres f ON f.id = c.filiere_id
        LEFT JOIN creneaux cr ON cr.classe_id = c.id AND cr.statut != 'annule'
        {$whereSQL}
        GROUP BY c.id
        ORDER BY f.code ASC, c.nom ASC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $classes = $stmt->fetchAll();

    foreach ($classes as &$c) {
        $c['id']          = (int) $c['id'];
        $c['filiere_id']  = (int) $c['filiere_id'];
        $c['effectif']    = (int) $c['effectif'];
        $c['nb_creneaux'] = (int) $c['nb_creneaux'];
        $c['filiere']     = [
            'id'      => (int) $c['filiere_id'],
            'code'    => $c['filiere_code'],
            'libelle' => $c['filiere_libelle'],
            'niveau'  => $c['filiere_niveau'],
            'couleur' => $c['filiere_couleur'],
        ];
        unset($c['filiere_code'], $c['filiere_libelle'], $c['filiere_niveau'], $c['filiere_couleur']);
    }
    unset($c);

    Response::paginated($classes, $total, $page, $perPage);
}

if ($method === 'POST') {
    requireRole(['admin', 'responsable']);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = validateClasse($body, $pdo);
    if (!empty($errors)) Response::validationError($errors);

    $stmt = $pdo->prepare('INSERT INTO classes (nom, filiere_id, effectif, annee_scolaire) VALUES (:nom, :filiere_id, :effectif, :annee)');
    $stmt->execute([':nom' => trim($body['nom']), ':filiere_id' => (int)$body['filiere_id'], ':effectif' => (int)($body['effectif'] ?? 0), ':annee' => $body['annee_scolaire'] ?? '2025-2026']);
    $id = (int) $pdo->lastInsertId();

    $row = $pdo->query("SELECT c.*, f.code AS filiere_code, f.libelle AS filiere_libelle FROM classes c JOIN filieres f ON f.id=c.filiere_id WHERE c.id={$id}")->fetch();
    $row['id'] = (int)$row['id'];
    Response::created($row, 'Classe créée.');
}

if ($method === 'PUT') {
    requireRole(['admin', 'responsable']);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) Response::error('id manquant.', 400);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = validateClasse($body, $pdo, $id);
    if (!empty($errors)) Response::validationError($errors);

    $stmt = $pdo->prepare('UPDATE classes SET nom=:nom, filiere_id=:filiere_id, effectif=:effectif, annee_scolaire=:annee WHERE id=:id');
    $stmt->execute([':nom'=>trim($body['nom']),':filiere_id'=>(int)$body['filiere_id'],':effectif'=>(int)($body['effectif']??0),':annee'=>$body['annee_scolaire']??'2025-2026',':id'=>$id]);

    $row = $pdo->query("SELECT c.*, f.code AS filiere_code, f.libelle AS filiere_libelle FROM classes c JOIN filieres f ON f.id=c.filiere_id WHERE c.id={$id}")->fetch();
    $row['id'] = (int)$row['id'];
    Response::success($row, 'Classe mise à jour.');
}

if ($method === 'DELETE') {
    requireRole(['admin']);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) Response::error('id manquant.', 400);
    $dep = $pdo->prepare('SELECT COUNT(*) FROM creneaux WHERE classe_id=:id');
    $dep->execute([':id'=>$id]);
    if ((int)$dep->fetchColumn() > 0) Response::error('Des créneaux sont rattachés à cette classe.', 409);
    $stmt = $pdo->prepare('DELETE FROM classes WHERE id=:id');
    $stmt->execute([':id'=>$id]);
    if ($stmt->rowCount()===0) Response::notFound("Classe #{$id} introuvable.");
    Response::success(null, 'Classe supprimée.');
}

function validateClasse(array $d, PDO $pdo, int $excludeId=0): array {
    $errors = [];
    if (empty($d['nom'])||strlen($d['nom'])>50) $errors['nom']='Nom requis (max 50 car.).';
    if (empty($d['filiere_id'])||!is_numeric($d['filiere_id'])) { $errors['filiere_id']='Filière requise.'; }
    else {
        $chk=$pdo->prepare('SELECT id FROM filieres WHERE id=:id');
        $chk->execute([':id'=>(int)$d['filiere_id']]);
        if(!$chk->fetch()) $errors['filiere_id']='Filière introuvable.';
    }
    if (isset($d['effectif']) && (!is_numeric($d['effectif'])||(int)$d['effectif']<0)) $errors['effectif']='Effectif invalide.';
    return $errors;
}

Response::error('Méthode non autorisée.', 405);
