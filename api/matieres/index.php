<?php
/**
 * /api/matieres/index.php — CRUD matières
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
    $filiereId  = (int)($_GET['filiere_id']??0);
    $typeCours  = trim($_GET['type_cours']??'');
    $search     = trim($_GET['search']??'');
    $page       = max(1,(int)($_GET['page']??1));
    $perPage    = min(100,max(1,(int)($_GET['per_page']??20)));
    $offset     = ($page-1)*$perPage;

    $where=[]; $params=[];
    if($filiereId) { $where[]='m.filiere_id=:fid'; $params[':fid']=$filiereId; }
    if($typeCours) { $where[]='m.type_cours=:tc'; $params[':tc']=$typeCours; }
    if($search!=='') { $where[]='(m.code LIKE :s OR m.intitule LIKE :s)'; $params[':s']="%{$search}%"; }
    $whereSQL=$where?'WHERE '.implode(' AND ',$where):'';

    $c=$pdo->prepare("SELECT COUNT(*) FROM matieres m {$whereSQL}"); $c->execute($params);
    $total=(int)$c->fetchColumn();

    $stmt=$pdo->prepare("
        SELECT m.*, f.code AS filiere_code, f.libelle AS filiere_libelle, f.couleur AS filiere_couleur,
               COUNT(DISTINCT cr.id) AS nb_creneaux
        FROM matieres m
        JOIN filieres f ON f.id=m.filiere_id
        LEFT JOIN creneaux cr ON cr.matiere_id=m.id AND cr.statut!='annule'
        {$whereSQL}
        GROUP BY m.id
        ORDER BY f.code ASC, m.code ASC
        LIMIT :limit OFFSET :offset
    ");
    foreach($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
    $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $matieres=$stmt->fetchAll();

    foreach($matieres as &$m) {
        $m['id']=(int)$m['id']; $m['filiere_id']=(int)$m['filiere_id'];
        $m['volume_h']=(int)$m['volume_h']; $m['nb_creneaux']=(int)$m['nb_creneaux'];
        $m['filiere']=['id'=>(int)$m['filiere_id'],'code'=>$m['filiere_code'],'libelle'=>$m['filiere_libelle'],'couleur'=>$m['filiere_couleur']];
        unset($m['filiere_code'],$m['filiere_libelle'],$m['filiere_couleur']);
    } unset($m);
    Response::paginated($matieres,$total,$page,$perPage);
}

if ($method === 'POST') {
    requireRole(['admin','responsable']);
    $body=json_decode(file_get_contents('php://input'),true)??[];
    $errors=validateMatiere($body,$pdo);
    if(!empty($errors)) Response::validationError($errors);
    $stmt=$pdo->prepare('INSERT INTO matieres (code,intitule,volume_h,type_cours,filiere_id) VALUES (:code,:intitule,:vol,:type,:fid)');
    $stmt->execute([':code'=>strtoupper(trim($body['code'])),':intitule'=>trim($body['intitule']),':vol'=>(int)$body['volume_h'],':type'=>$body['type_cours'],':fid'=>(int)$body['filiere_id']]);
    $id=(int)$pdo->lastInsertId();
    $row=$pdo->query("SELECT * FROM matieres WHERE id={$id}")->fetch(); $row['id']=(int)$row['id'];
    Response::created($row,'Matière créée.');
}

if ($method === 'PUT') {
    requireRole(['admin','responsable']);
    $id=(int)($_GET['id']??0); if(!$id) Response::error('id manquant.',400);
    $body=json_decode(file_get_contents('php://input'),true)??[];
    $errors=validateMatiere($body,$pdo,$id);
    if(!empty($errors)) Response::validationError($errors);
    $stmt=$pdo->prepare('UPDATE matieres SET code=:code,intitule=:intitule,volume_h=:vol,type_cours=:type,filiere_id=:fid WHERE id=:id');
    $stmt->execute([':code'=>strtoupper(trim($body['code'])),':intitule'=>trim($body['intitule']),':vol'=>(int)$body['volume_h'],':type'=>$body['type_cours'],':fid'=>(int)$body['filiere_id'],':id'=>$id]);
    $row=$pdo->query("SELECT * FROM matieres WHERE id={$id}")->fetch(); $row['id']=(int)$row['id'];
    Response::success($row,'Matière mise à jour.');
}

if ($method === 'DELETE') {
    requireRole(['admin']);
    $id=(int)($_GET['id']??0); if(!$id) Response::error('id manquant.',400);
    $dep=$pdo->prepare('SELECT COUNT(*) FROM creneaux WHERE matiere_id=:id'); $dep->execute([':id'=>$id]);
    if((int)$dep->fetchColumn()>0) Response::error('Des créneaux utilisent cette matière.',409);
    $stmt=$pdo->prepare('DELETE FROM matieres WHERE id=:id'); $stmt->execute([':id'=>$id]);
    if($stmt->rowCount()===0) Response::notFound("Matière #{$id} introuvable.");
    Response::success(null,'Matière supprimée.');
}

function validateMatiere(array $d, PDO $pdo, int $excl=0): array {
    $errors=[];
    if(empty($d['code'])||strlen($d['code'])>10) $errors['code']='Code requis (max 10 car., ex: DEV-301).';
    if(empty($d['intitule'])||strlen($d['intitule'])>100) $errors['intitule']='Intitulé requis (max 100 car.).';
    if(empty($d['volume_h'])||!is_numeric($d['volume_h'])||(int)$d['volume_h']<1) $errors['volume_h']='Volume horaire invalide (min 1h).';
    if(!in_array($d['type_cours']??'',['CM','TD','TP','Projet'],true)) $errors['type_cours']='Type invalide. Valeurs : CM, TD, TP, Projet.';
    if(empty($d['filiere_id'])||!is_numeric($d['filiere_id'])) $errors['filiere_id']='Filière requise.';
    if(empty($errors['code'])) {
        $chk=$pdo->prepare('SELECT id FROM matieres WHERE code=:code AND id!=:id LIMIT 1');
        $chk->execute([':code'=>strtoupper(trim($d['code'])),':id'=>$excl]);
        if($chk->fetch()) $errors['code']="Ce code est déjà utilisé.";
    }
    return $errors;
}

Response::error('Méthode non autorisée.',405);
