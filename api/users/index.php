<?php
/**
 * /api/users/index.php — Gestion des utilisateurs (admin only)
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
    requireRole(['admin']);
    $page    = max(1,(int)($_GET['page']??1));
    $perPage = min(50,max(1,(int)($_GET['per_page']??20)));
    $offset  = ($page-1)*$perPage;

    $countStmt = $pdo->query('SELECT COUNT(*) FROM users'); $total=(int)$countStmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT id,nom,prenom,email,role,ref_id,actif,created_at FROM users ORDER BY role ASC,nom ASC LIMIT :l OFFSET :o');
    $stmt->bindValue(':l',$perPage,PDO::PARAM_INT); $stmt->bindValue(':o',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $users=$stmt->fetchAll();
    foreach($users as &$u) { $u['id']=(int)$u['id']; $u['actif']=(bool)$u['actif']; } unset($u);
    Response::paginated($users,$total,$page,$perPage);
}

if ($method === 'POST') {
    requireRole(['admin']);
    $body=json_decode(file_get_contents('php://input'),true)??[];
    $errors=[];
    if(empty($body['nom'])) $errors['nom']='Nom requis.';
    if(empty($body['prenom'])) $errors['prenom']='Prénom requis.';
    if(empty($body['email'])||!filter_var($body['email'],FILTER_VALIDATE_EMAIL)) $errors['email']='Email invalide.';
    if(empty($body['password'])||strlen($body['password'])<6) $errors['password']='Mot de passe requis (min 6 car.).';
    if(!in_array($body['role']??'',['admin','responsable','enseignant','etudiant'],true)) $errors['role']='Rôle invalide.';
    if(!empty($errors)) Response::validationError($errors);

    $chk=$pdo->prepare('SELECT id FROM users WHERE email=:e'); $chk->execute([':e'=>$body['email']]);
    if($chk->fetch()) Response::validationError(['email'=>'Email déjà utilisé.']);

    $hash=password_hash($body['password'],PASSWORD_BCRYPT);
    $stmt=$pdo->prepare('INSERT INTO users (nom,prenom,email,password,role,ref_id,actif) VALUES (:n,:p,:e,:pw,:r,:ref,1)');
    $stmt->execute([':n'=>trim($body['nom']),':p'=>trim($body['prenom']),':e'=>strtolower(trim($body['email'])),':pw'=>$hash,':r'=>$body['role'],':ref'=>$body['ref_id']??null]);
    $id=(int)$pdo->lastInsertId();
    $row=$pdo->query("SELECT id,nom,prenom,email,role,ref_id,actif,created_at FROM users WHERE id={$id}")->fetch();
    $row['id']=(int)$row['id']; $row['actif']=(bool)$row['actif'];
    Response::created($row,'Utilisateur créé.');
}

if ($method === 'PATCH') {
    requireRole(['admin']);
    $id=(int)($_GET['id']??0); if(!$id) Response::error('id manquant.',400);
    $body=json_decode(file_get_contents('php://input'),true)??[];
    // Changer seulement actif ou rôle
    if(isset($body['actif'])) {
        $pdo->prepare('UPDATE users SET actif=:a WHERE id=:id')->execute([':a'=>(int)(bool)$body['actif'],':id'=>$id]);
    }
    if(isset($body['role'])&&in_array($body['role'],['admin','responsable','enseignant','etudiant'],true)) {
        $pdo->prepare('UPDATE users SET role=:r WHERE id=:id')->execute([':r'=>$body['role'],':id'=>$id]);
    }
    if(isset($body['password'])&&strlen($body['password'])>=6) {
        $pdo->prepare('UPDATE users SET password=:p WHERE id=:id')->execute([':p'=>password_hash($body['password'],PASSWORD_BCRYPT),':id'=>$id]);
    }
    $row=$pdo->query("SELECT id,nom,prenom,email,role,ref_id,actif FROM users WHERE id={$id}")->fetch();
    if(!$row) Response::notFound("Utilisateur #{$id} introuvable.");
    $row['id']=(int)$row['id']; $row['actif']=(bool)$row['actif'];
    Response::success($row,'Utilisateur mis à jour.');
}

if ($method === 'DELETE') {
    requireRole(['admin']);
    $id=(int)($_GET['id']??0); if(!$id) Response::error('id manquant.',400);
    if($id==1) Response::error('Impossible de supprimer le super-administrateur.',403);
    $stmt=$pdo->prepare('DELETE FROM users WHERE id=:id'); $stmt->execute([':id'=>$id]);
    if($stmt->rowCount()===0) Response::notFound("Utilisateur #{$id} introuvable.");
    Response::success(null,'Utilisateur supprimé.');
}

Response::error('Méthode non autorisée.',405);
