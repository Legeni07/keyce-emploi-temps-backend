<?php
/**
 * /api/creneaux/index.php
 * CRUD créneaux + détection de conflits en temps réel.
 *
 * GET    /api/creneaux/             → Liste filtrée des créneaux
 * GET    /api/creneaux/?id=X        → Un créneau avec détails JOIN
 * POST   /api/creneaux/             → Créer un créneau
 * PUT    /api/creneaux/?id=X        → Modifier un créneau
 * PATCH  /api/creneaux/?id=X        → Changer le statut uniquement
 * DELETE /api/creneaux/?id=X        → Supprimer
 * GET    /api/creneaux/verifier     → Vérifier les conflits (useConflitDetection)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/auth.php';

applyCors();
header('Content-Type: application/json');

$method   = $_SERVER['REQUEST_METHOD'];
$pdo      = getPDO();
$pathInfo = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ── Route spéciale : GET /api/creneaux/verifier
if ($method === 'GET' && str_ends_with(rtrim($pathInfo, '/'), 'verifier')) {
    requireAuth();
    verifierConflits($pdo);
    exit;
}

// ── SELECT complet avec JOINs
$SELECT_BASE = "
    SELECT
        cr.id, cr.jour, cr.heure_debut, cr.heure_fin,
        cr.semaine_debut, cr.semaine_fin, cr.recurrent, cr.statut,
        cr.created_at, cr.updated_at,
        -- Classe
        cl.id AS classe_id, cl.nom AS classe_nom, cl.effectif AS classe_effectif,
        -- Filière (via classe)
        fi.id AS filiere_id, fi.code AS filiere_code,
        fi.libelle AS filiere_libelle, fi.couleur AS filiere_couleur,
        -- Matière
        ma.id AS matiere_id, ma.code AS matiere_code,
        ma.intitule AS matiere_intitule, ma.type_cours,
        -- Enseignant
        en.id AS enseignant_id, en.matricule AS enseignant_matricule,
        en.nom AS enseignant_nom, en.prenom AS enseignant_prenom,
        -- Salle
        sa.id AS salle_id, sa.code AS salle_code,
        sa.type_salle, sa.capacite AS salle_capacite
    FROM creneaux cr
    JOIN classes     cl ON cl.id = cr.classe_id
    JOIN filieres    fi ON fi.id = cl.filiere_id
    JOIN matieres    ma ON ma.id = cr.matiere_id
    JOIN enseignants en ON en.id = cr.enseignant_id
    JOIN salles      sa ON sa.id = cr.salle_id
";

// ── GET : liste ou détail
if ($method === 'GET') {
    requireAuth();

    $id = (int) ($_GET['id'] ?? 0);

    // Détail d'un créneau
    if ($id) {
        $stmt = $pdo->prepare("{$SELECT_BASE} WHERE cr.id = :id");
        $stmt->execute([':id' => $id]);
        $creneau = $stmt->fetch();
        if (!$creneau) {
            Response::notFound("Créneau #{$id} introuvable.");
        }
        Response::success(formatCreneau($creneau));
    }

    // Liste filtrée
    $where  = [];
    $params = [];

    if (!empty($_GET['classe_id'])) {
        $where[] = 'cr.classe_id = :classe_id';
        $params[':classe_id'] = (int) $_GET['classe_id'];
    }
    if (!empty($_GET['enseignant_id'])) {
        $where[] = 'cr.enseignant_id = :enseignant_id';
        $params[':enseignant_id'] = (int) $_GET['enseignant_id'];
    }
    if (!empty($_GET['salle_id'])) {
        $where[] = 'cr.salle_id = :salle_id';
        $params[':salle_id'] = (int) $_GET['salle_id'];
    }
    if (!empty($_GET['statut'])) {
        $where[] = 'cr.statut = :statut';
        $params[':statut'] = $_GET['statut'];
    }
    if (!empty($_GET['jour'])) {
        $where[] = 'cr.jour = :jour';
        $params[':jour'] = (int) $_GET['jour'];
    }
    if (!empty($_GET['filiere_id'])) {
        $where[] = 'fi.id = :filiere_id';
        $params[':filiere_id'] = (int) $_GET['filiere_id'];
    }
    // Filtre par semaine (date dans l'intervalle semaine_debut–semaine_fin)
    if (!empty($_GET['semaine'])) {
        $where[] = ':semaine BETWEEN cr.semaine_debut AND cr.semaine_fin';
        $params[':semaine'] = $_GET['semaine'];
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("{$SELECT_BASE} {$whereSQL} ORDER BY cr.jour ASC, cr.heure_debut ASC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $creneaux = array_map('formatCreneau', $rows);
    Response::success($creneaux);
}

// ── POST : créer
if ($method === 'POST') {
    requireRole(['admin', 'responsable']);

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = validateCreneau($body);

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    // Vérification des conflits
    $conflits = detecterConflits($pdo, $body);
    if (!empty($conflits)) {
        Response::json([
            'success'  => false,
            'message'  => 'Conflit(s) détecté(s). Impossible de créer ce créneau.',
            'conflicts' => $conflits,
        ], 409);
    }

    $stmt = $pdo->prepare('
        INSERT INTO creneaux
            (classe_id, matiere_id, enseignant_id, salle_id, jour,
             heure_debut, heure_fin, semaine_debut, semaine_fin, recurrent, statut)
        VALUES
            (:classe_id, :matiere_id, :enseignant_id, :salle_id, :jour,
             :heure_debut, :heure_fin, :semaine_debut, :semaine_fin, :recurrent, :statut)
    ');
    $stmt->execute([
        ':classe_id'     => (int) $body['classe_id'],
        ':matiere_id'    => (int) $body['matiere_id'],
        ':enseignant_id' => (int) $body['enseignant_id'],
        ':salle_id'      => (int) $body['salle_id'],
        ':jour'          => (int) $body['jour'],
        ':heure_debut'   => $body['heure_debut'],
        ':heure_fin'     => $body['heure_fin'],
        ':semaine_debut' => $body['semaine_debut'],
        ':semaine_fin'   => $body['semaine_fin'],
        ':recurrent'     => isset($body['recurrent']) ? (int) $body['recurrent'] : 1,
        ':statut'        => $body['statut'] ?? 'planifie',
    ]);

    $id   = (int) $pdo->lastInsertId();
    $stmt2 = $pdo->prepare("{$SELECT_BASE} WHERE cr.id = :id");
    $stmt2->execute([':id' => $id]);
    $creneau = $stmt2->fetch();

    Response::created(formatCreneau($creneau), 'Créneau planifié avec succès.');
}

// ── PUT : modifier complètement
if ($method === 'PUT') {
    requireRole(['admin', 'responsable']);

    $id   = (int) ($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!$id) {
        Response::error('Paramètre id manquant.', 400);
    }

    $check = $pdo->prepare('SELECT id FROM creneaux WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) {
        Response::notFound("Créneau #{$id} introuvable.");
    }

    $errors = validateCreneau($body);
    if (!empty($errors)) {
        Response::validationError($errors);
    }

    $conflits = detecterConflits($pdo, $body, $id);
    if (!empty($conflits)) {
        Response::json([
            'success'   => false,
            'message'   => 'Conflit(s) détecté(s) lors de la modification.',
            'conflicts' => $conflits,
        ], 409);
    }

    $stmt = $pdo->prepare('
        UPDATE creneaux
        SET classe_id = :classe_id, matiere_id = :matiere_id,
            enseignant_id = :enseignant_id, salle_id = :salle_id,
            jour = :jour, heure_debut = :heure_debut, heure_fin = :heure_fin,
            semaine_debut = :semaine_debut, semaine_fin = :semaine_fin,
            recurrent = :recurrent, statut = :statut
        WHERE id = :id
    ');
    $stmt->execute([
        ':classe_id'     => (int) $body['classe_id'],
        ':matiere_id'    => (int) $body['matiere_id'],
        ':enseignant_id' => (int) $body['enseignant_id'],
        ':salle_id'      => (int) $body['salle_id'],
        ':jour'          => (int) $body['jour'],
        ':heure_debut'   => $body['heure_debut'],
        ':heure_fin'     => $body['heure_fin'],
        ':semaine_debut' => $body['semaine_debut'],
        ':semaine_fin'   => $body['semaine_fin'],
        ':recurrent'     => isset($body['recurrent']) ? (int) $body['recurrent'] : 1,
        ':statut'        => $body['statut'] ?? 'planifie',
        ':id'            => $id,
    ]);

    $stmt2 = $pdo->prepare("{$SELECT_BASE} WHERE cr.id = :id");
    $stmt2->execute([':id' => $id]);
    Response::success(formatCreneau($stmt2->fetch()), 'Créneau modifié.');
}

// ── PATCH : changer le statut uniquement
if ($method === 'PATCH') {
    requireRole(['admin', 'responsable']);

    $id   = (int) ($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!$id) {
        Response::error('Paramètre id manquant.', 400);
    }

    $statutsValides = ['planifie', 'confirme', 'annule'];
    if (!in_array($body['statut'] ?? '', $statutsValides, true)) {
        Response::validationError(['statut' => 'Statut invalide. Valeurs : ' . implode(', ', $statutsValides)]);
    }

    $stmt = $pdo->prepare('UPDATE creneaux SET statut = :statut WHERE id = :id');
    $stmt->execute([':statut' => $body['statut'], ':id' => $id]);

    if ($stmt->rowCount() === 0) {
        Response::notFound("Créneau #{$id} introuvable.");
    }

    $stmt2 = $pdo->prepare("{$SELECT_BASE} WHERE cr.id = :id");
    $stmt2->execute([':id' => $id]);
    Response::success(formatCreneau($stmt2->fetch()), "Statut mis à jour : {$body['statut']}.");
}

// ── DELETE
if ($method === 'DELETE') {
    requireRole(['admin', 'responsable']);

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        Response::error('Paramètre id manquant.', 400);
    }

    $stmt = $pdo->prepare('DELETE FROM creneaux WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        Response::notFound("Créneau #{$id} introuvable.");
    }

    Response::success(null, 'Créneau supprimé.');
}

// ════════════════════════════════════════════════════════════════
// FONCTIONS UTILITAIRES
// ════════════════════════════════════════════════════════════════

/**
 * Détection de conflits.
 * Vérifie le chevauchement horaire pour : enseignant, salle, classe.
 * Chevauchement = heure_debut < heure_fin_autreCreneaux AND heure_fin > heure_debut_autreCreneau
 *
 * @param PDO   $pdo
 * @param array $data      Données du nouveau créneau
 * @param int   $excludeId ID du créneau à exclure (lors d'une modification)
 * @return array           Liste des conflits trouvés
 */
function detecterConflits(PDO $pdo, array $data, int $excludeId = 0): array
{
    $conflits = [];

    $baseSQL = "
        SELECT cr.id, cr.jour, cr.heure_debut, cr.heure_fin, cr.statut,
               cl.nom AS classe_nom,
               en.nom AS enseignant_nom, en.prenom AS enseignant_prenom,
               sa.code AS salle_code,
               ma.intitule AS matiere_intitule
        FROM creneaux cr
        JOIN classes     cl ON cl.id = cr.classe_id
        JOIN enseignants en ON en.id = cr.enseignant_id
        JOIN salles      sa ON sa.id = cr.salle_id
        JOIN matieres    ma ON ma.id = cr.matiere_id
        WHERE cr.id != :exclude
          AND cr.statut != 'annule'
          AND cr.jour = :jour
          AND cr.heure_debut < :heure_fin
          AND cr.heure_fin   > :heure_debut
          AND (
              -- La semaine demandée chevauche-t-elle les semaines du créneau existant ?
              :semaine_debut <= cr.semaine_fin
              AND :semaine_fin >= cr.semaine_debut
          )
    ";

    $baseParams = [
        ':exclude'       => $excludeId,
        ':jour'          => (int) $data['jour'],
        ':heure_debut'   => $data['heure_debut'],
        ':heure_fin'     => $data['heure_fin'],
        ':semaine_debut' => $data['semaine_debut'],
        ':semaine_fin'   => $data['semaine_fin'],
    ];

    // 1. Conflit ENSEIGNANT
    $stmtEns = $pdo->prepare("{$baseSQL} AND cr.enseignant_id = :resource_id");
    $stmtEns->execute(array_merge($baseParams, [':resource_id' => (int) $data['enseignant_id']]));
    $conflitEns = $stmtEns->fetchAll();
    if (!empty($conflitEns)) {
        foreach ($conflitEns as $c) {
            $conflits[] = [
                'type'     => 'enseignant',
                'message'  => "L'enseignant est déjà occupé : {$c['matiere_intitule']} ({$c['heure_debut']}–{$c['heure_fin']}) pour {$c['classe_nom']}",
                'creneau'  => $c,
            ];
        }
    }

    // 2. Conflit SALLE
    $stmtSalle = $pdo->prepare("{$baseSQL} AND cr.salle_id = :resource_id");
    $stmtSalle->execute(array_merge($baseParams, [':resource_id' => (int) $data['salle_id']]));
    $conflitSalle = $stmtSalle->fetchAll();
    if (!empty($conflitSalle)) {
        foreach ($conflitSalle as $c) {
            $conflits[] = [
                'type'    => 'salle',
                'message' => "La salle est déjà occupée : {$c['salle_code']} ({$c['heure_debut']}–{$c['heure_fin']}) par {$c['classe_nom']}",
                'creneau' => $c,
            ];
        }
    }

    // 3. Conflit CLASSE
    $stmtClasse = $pdo->prepare("{$baseSQL} AND cr.classe_id = :resource_id");
    $stmtClasse->execute(array_merge($baseParams, [':resource_id' => (int) $data['classe_id']]));
    $conflitClasse = $stmtClasse->fetchAll();
    if (!empty($conflitClasse)) {
        foreach ($conflitClasse as $c) {
            $conflits[] = [
                'type'    => 'classe',
                'message' => "La classe a déjà un cours : {$c['matiere_intitule']} ({$c['heure_debut']}–{$c['heure_fin']}) en {$c['salle_code']}",
                'creneau' => $c,
            ];
        }
    }

    return $conflits;
}

/**
 * Endpoint GET /api/creneaux/verifier
 * Utilisé par le hook useConflitDetection du frontend React.
 */
function verifierConflits(PDO $pdo): void
{
    $required = ['classe_id', 'enseignant_id', 'salle_id', 'jour', 'heure_debut', 'heure_fin', 'semaine_debut', 'semaine_fin'];
    $missing  = [];

    foreach ($required as $field) {
        if (empty($_GET[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        Response::validationError(
            array_fill_keys($missing, 'Champ requis.'),
            'Paramètres manquants pour la vérification des conflits.'
        );
    }

    $data = [
        'classe_id'     => (int) $_GET['classe_id'],
        'enseignant_id' => (int) $_GET['enseignant_id'],
        'salle_id'      => (int) $_GET['salle_id'],
        'jour'          => (int) $_GET['jour'],
        'heure_debut'   => $_GET['heure_debut'],
        'heure_fin'     => $_GET['heure_fin'],
        'semaine_debut' => $_GET['semaine_debut'],
        'semaine_fin'   => $_GET['semaine_fin'],
    ];

    $excludeId = (int) ($_GET['exclude_id'] ?? 0);
    $conflits  = detecterConflits($pdo, $data, $excludeId);

    Response::success([
        'has_conflict' => !empty($conflits),
        'conflicts'    => $conflits,
        'checked_at'   => date('Y-m-d H:i:s'),
    ], empty($conflits) ? 'Aucun conflit détecté.' : count($conflits) . ' conflit(s) détecté(s).');
}

/**
 * Formate un créneau (résultat JOIN) en objet structuré pour l'API.
 */
function formatCreneau(array $row): array
{
    $jours = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'];

    return [
        'id'           => (int) $row['id'],
        'jour'         => (int) $row['jour'],
        'jour_label'   => $jours[$row['jour']] ?? 'Inconnu',
        'heure_debut'  => $row['heure_debut'],
        'heure_fin'    => $row['heure_fin'],
        'semaine_debut'=> $row['semaine_debut'],
        'semaine_fin'  => $row['semaine_fin'],
        'recurrent'    => (bool) $row['recurrent'],
        'statut'       => $row['statut'],
        'created_at'   => $row['created_at'],
        'updated_at'   => $row['updated_at'],
        'classe'       => [
            'id'       => (int) $row['classe_id'],
            'nom'      => $row['classe_nom'],
            'effectif' => (int) $row['classe_effectif'],
        ],
        'filiere'      => [
            'id'      => (int) $row['filiere_id'],
            'code'    => $row['filiere_code'],
            'libelle' => $row['filiere_libelle'],
            'couleur' => $row['filiere_couleur'],
        ],
        'matiere'      => [
            'id'         => (int) $row['matiere_id'],
            'code'       => $row['matiere_code'],
            'intitule'   => $row['matiere_intitule'],
            'type_cours' => $row['type_cours'],
        ],
        'enseignant'   => [
            'id'        => (int) $row['enseignant_id'],
            'matricule' => $row['enseignant_matricule'],
            'nom'       => $row['enseignant_nom'],
            'prenom'    => $row['enseignant_prenom'],
        ],
        'salle'        => [
            'id'       => (int) $row['salle_id'],
            'code'     => $row['salle_code'],
            'type'     => $row['type_salle'],
            'capacite' => (int) $row['salle_capacite'],
        ],
    ];
}

/**
 * Validation du corps d'un créneau.
 */
function validateCreneau(array $data): array
{
    $errors = [];

    foreach (['classe_id', 'matiere_id', 'enseignant_id', 'salle_id'] as $field) {
        if (empty($data[$field]) || !is_numeric($data[$field]) || (int) $data[$field] <= 0) {
            $errors[$field] = "Le champ {$field} est requis et doit être un entier positif.";
        }
    }

    if (empty($data['jour']) || !in_array((int) $data['jour'], [1, 2, 3, 4, 5, 6], true)) {
        $errors['jour'] = 'Jour invalide (1=Lun, 2=Mar, 3=Mer, 4=Jeu, 5=Ven, 6=Sam).';
    }

    if (empty($data['heure_debut']) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['heure_debut'])) {
        $errors['heure_debut'] = 'Heure de début invalide (format HH:MM).';
    }
    if (empty($data['heure_fin']) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['heure_fin'])) {
        $errors['heure_fin'] = 'Heure de fin invalide (format HH:MM).';
    }

    if (empty($errors['heure_debut']) && empty($errors['heure_fin'])) {
        if ($data['heure_fin'] <= $data['heure_debut']) {
            $errors['heure_fin'] = 'L\'heure de fin doit être après l\'heure de début.';
        }
    }

    if (empty($data['semaine_debut']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['semaine_debut'])) {
        $errors['semaine_debut'] = 'Date de début de semaine invalide (format YYYY-MM-DD).';
    }
    if (empty($data['semaine_fin']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['semaine_fin'])) {
        $errors['semaine_fin'] = 'Date de fin de semaine invalide (format YYYY-MM-DD).';
    }

    if (empty($errors['semaine_debut']) && empty($errors['semaine_fin'])) {
        if ($data['semaine_fin'] < $data['semaine_debut']) {
            $errors['semaine_fin'] = 'La semaine de fin doit être après la semaine de début.';
        }
    }

    if (!empty($data['statut']) && !in_array($data['statut'], ['planifie', 'confirme', 'annule'], true)) {
        $errors['statut'] = 'Statut invalide. Valeurs : planifie, confirme, annule.';
    }

    return $errors;
}

Response::error('Méthode non autorisée.', 405);
