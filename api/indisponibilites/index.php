<?php
/**
 * /api/indisponibilites/index.php — Indisponibilités enseignants
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/auth.php';

applyCors();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getPDO();
$user   = requireAuth();

if ($method === 'GET') {
    $enseignantId = (int)($_GET['enseignant_id']??0);
    $statut       = trim($_GET['statut']??'');
    $page         = max(1,(int)($_GET['page']??1));
    $perPage      = min(50,max(1,(int)($_GET['per_page']??10)));
    $offset       = ($page-1)*$perPage;

    // Un enseignant ne voit que ses propres indisponibilités
    if ($user['role']==='enseignant' && $user['ref_id']) {
        $enseignantId = (int)$user['ref_id'];
    }

    $where=[]; $params=[];
    if($enseignantId) { $where[]='i.enseignant_id=:eid'; $params[':eid']=$enseignantId; }
    if(in_array($statut,['en_attente','validee','refusee'],true)) { $where[]='i.statut=:st'; $params[':st']=$statut; }
    $whereSQL=$where?'WHERE '.implode(' AND ',$where):'';

    $c=$pdo->prepare("SELECT COUNT(*) FROM indisponibilites i {$whereSQL}"); $c->execute($params);
    $total=(int)$c->fetchColumn();

    $stmt=$pdo->prepare("
        SELECT i.*, e.nom AS ens_nom, e.prenom AS ens_prenom, e.matricule
        FROM indisponibilites i
        JOIN enseignants e ON e.id=i.enseignant_id
        {$whereSQL}
        ORDER BY i.date_debut DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
    $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $rows=$stmt->fetchAll();

    foreach($rows as &$r) {
        $r['id']=(int)$r['id']; $r['enseignant_id']=(int)$r['enseignant_id'];
        $r['enseignant']=['id'=>(int)$r['enseignant_id'],'nom'=>$r['ens_nom'],'prenom'=>$r['ens_prenom'],'matricule'=>$r['matricule']];
        unset($r['ens_nom'],$r['ens_prenom'],$r['matricule']);
    } unset($r);
    Response::paginated($rows,$total,$page,$perPage);
}

if ($method === 'POST') {
    $body=json_decode(file_get_contents('php://input'),true)??[];

    // Un enseignant ne peut créer que pour lui-même
    if ($user['role']==='enseignant') {
        $body['enseignant_id'] = (int)$user['ref_id'];
    } elseif (!in_array($user['role'],['admin','responsable'],true)) {
        Response::forbidden();
    }

    $errors=[];
    if(empty($body['enseignant_id'])||!is_numeric($body['enseignant_id'])) $errors['enseignant_id']='Enseignant requis.';
    if(empty($body['date_debut'])) $errors['date_debut']='Date de début requise.';
    if(empty($body['date_fin']))   $errors['date_fin']='Date de fin requise.';
    if(!empty($body['date_debut'])&&!empty($body['date_fin'])&&$body['date_fin']<$body['date_debut']) $errors['date_fin']='La date de fin doit être après la date de début.';
    if(!in_array($body['motif']??'',['Congé','Mission','Maladie','Autre'],true)) $errors['motif']='Motif invalide.';
    if(!empty($errors)) Response::validationError($errors);

    $stmt=$pdo->prepare('INSERT INTO indisponibilites (enseignant_id,date_debut,date_fin,motif,description,statut) VALUES (:eid,:dd,:df,:motif,:desc,:st)');
    $stmt->execute([':eid'=>(int)$body['enseignant_id'],':dd'=>$body['date_debut'],':df'=>$body['date_fin'],':motif'=>$body['motif'],':desc'=>trim($body['description']??''),':st'=>'en_attente']);
    $id=(int)$pdo->lastInsertId();
    $row=$pdo->query("SELECT * FROM indisponibilites WHERE id={$id}")->fetch(); $row['id']=(int)$row['id'];
    Response::created($row,'Demande d\'indisponibilité envoyée.');
}

if ($method === 'PATCH') {
    requireRole(['admin','responsable']);
    $id=(int)($_GET['id']??0); if(!$id) Response::error('id manquant.',400);
    $body=json_decode(file_get_contents('php://input'),true)??[];
    if(!in_array($body['statut']??'',['en_attente','validee','refusee'],true)) Response::validationError(['statut'=>'Statut invalide.']);
    $stmt=$pdo->prepare('UPDATE indisponibilites SET statut=:st WHERE id=:id');
    $stmt->execute([':st'=>$body['statut'],':id'=>$id]);
    if($stmt->rowCount()===0) Response::notFound("Indisponibilité #{$id} introuvable.");
    Response::success(['id'=>$id,'statut'=>$body['statut']],'Statut mis à jour.');
}

if ($method === 'DELETE') {
    $id=(int)($_GET['id']??0); if(!$id) Response::error('id manquant.',400);
    $stmt=$pdo->prepare('DELETE FROM indisponibilites WHERE id=:id');
    $stmt->execute([':id'=>$id]);
    if($stmt->rowCount()===0) Response::notFound("Indisponibilité #{$id} introuvable.");
    Response::success(null,'Indisponibilité supprimée.');
}

Response::error('Méthode non autorisée.',405);
