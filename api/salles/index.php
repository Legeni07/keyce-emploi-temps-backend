<?php
/**
 * /api/salles/index.php — CRUD salles
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
    $search    = trim($_GET['search'] ?? '');
    $type      = trim($_GET['type_salle'] ?? '');
    $disponible = $_GET['disponible'] ?? null;
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset    = ($page - 1) * $perPage;

    $where = []; $params = [];
    if ($search !== '') { $where[] = '(code LIKE :s OR equipements LIKE :s)'; $params[':s'] = "%{$search}%"; }
    if ($type !== '')   { $where[] = 'type_salle = :type'; $params[':type'] = $type; }
    if ($disponible !== null) { $where[] = 'disponible = :dispo'; $params[':dispo'] = (int)$disponible; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM salles {$whereSQL}"); $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT s.*, COUNT(DISTINCT cr.id) AS nb_creneaux_semaine
        FROM salles s
        LEFT JOIN creneaux cr ON cr.salle_id = s.id AND cr.statut != 'annule'
        {$whereSQL}
        GROUP BY s.id
        ORDER BY s.type_salle ASC, s.code ASC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
    $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $salles = $stmt->fetchAll();
    foreach ($salles as &$s) { $s['id']=(int)$s['id']; $s['capacite']=(int)$s['capacite']; $s['disponible']=(bool)$s['disponible']; $s['nb_creneaux_semaine']=(int)$s['nb_creneaux_semaine']; } unset($s);
    Response::paginated($salles, $total, $page, $perPage);
}

if ($method === 'POST') {
    requireRole(['admin']);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = validateSalle($body, $pdo);
    if (!empty($errors)) Response::validationError($errors);

    $stmt = $pdo->prepare('INSERT INTO salles (code, type_salle, capacite, equipements, disponible) VALUES (:code,:type,:cap,:equip,:dispo)');
    $stmt->execute([':code'=>strtoupper(trim($body['code'])),':type'=>$body['type_salle'],':cap'=>(int)$body['capacite'],':equip'=>trim($body['equipements']??''),':dispo'=>isset($body['disponible'])?(int)$body['disponible']:1]);
    $id=(int)$pdo->lastInsertId();
    $row=$pdo->query("SELECT * FROM salles WHERE id={$id}")->fetch(); $row['id']=(int)$row['id'];
    Response::created($row,'Salle créée.');
}

if ($method === 'PUT') {
    requireRole(['admin']);
    $id=(int)($_GET['id']??0); if(!$id) Response::error('id manquant.',400);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = validateSalle($body, $pdo, $id);
    if (!empty($errors)) Response::validationError($errors);
    $stmt=$pdo->prepare('UPDATE salles SET code=:code,type_salle=:type,capacite=:cap,equipements=:equip,disponible=:dispo WHERE id=:id');
    $stmt->execute([':code'=>strtoupper(trim($body['code'])),':type'=>$body['type_salle'],':cap'=>(int)$body['capacite'],':equip'=>trim($body['equipements']??''),':dispo'=>isset($body['disponible'])?(int)$body['disponible']:1,':id'=>$id]);
    $row=$pdo->query("SELECT * FROM salles WHERE id={$id}")->fetch(); $row['id']=(int)$row['id'];
    Response::success($row,'Salle mise à jour.');
}

if ($method === 'DELETE') {
    requireRole(['admin']);
    $id=(int)($_GET['id']??0); if(!$id) Response::error('id manquant.',400);
    $dep=$pdo->prepare('SELECT COUNT(*) FROM creneaux WHERE salle_id=:id'); $dep->execute([':id'=>$id]);
    if((int)$dep->fetchColumn()>0) Response::error('Des créneaux utilisent cette salle.',409);
    $stmt=$pdo->prepare('DELETE FROM salles WHERE id=:id'); $stmt->execute([':id'=>$id]);
    if($stmt->rowCount()===0) Response::notFound("Salle #{$id} introuvable.");
    Response::success(null,'Salle supprimée.');
}

function validateSalle(array $d, PDO $pdo, int $excl=0): array {
    $errors=[];
    if(empty($d['code'])||strlen($d['code'])>15) $errors['code']='Code requis (max 15 car.).';
    $types=['Amphithéâtre','TD','TP/Labo','Projet'];
    if(!in_array($d['type_salle']??'',$types,true)) $errors['type_salle']='Type invalide. Valeurs : '.implode(', ',$types);
    if(empty($d['capacite'])||!is_numeric($d['capacite'])||(int)$d['capacite']<1) $errors['capacite']='Capacité invalide (min 1).';
    if(empty($errors['code'])) {
        $chk=$pdo->prepare('SELECT id FROM salles WHERE code=:code AND id!=:id LIMIT 1');
        $chk->execute([':code'=>strtoupper(trim($d['code'])),':id'=>$excl]);
        if($chk->fetch()) $errors['code']="Ce code est déjà utilisé.";
    }
    return $errors;
}

Response::error('Méthode non autorisée.',405);
