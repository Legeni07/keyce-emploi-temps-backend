<?php
/**
 * GET /api/stats
 * Statistiques globales pour le Dashboard React.
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/auth.php';

applyCors();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Méthode non autorisée.', 405);
}

requireAuth();
$pdo = getPDO();

// Semaine courante (lundi au dimanche)
$today     = new DateTime();
$monday    = (clone $today)->modify('Monday this week')->format('Y-m-d');
$sunday    = (clone $today)->modify('Sunday this week')->format('Y-m-d');

// ── Compteurs généraux
$counts = [];
foreach (['filieres', 'classes', 'matieres', 'enseignants', 'salles'] as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
    $counts[$table] = (int) $stmt->fetchColumn();
}

// Salles disponibles
$stmtDispo = $pdo->query("SELECT COUNT(*) FROM salles WHERE disponible = 1");
$counts['salles_disponibles'] = (int) $stmtDispo->fetchColumn();

// ── Créneaux cette semaine
$stmtCr = $pdo->prepare("
    SELECT statut, COUNT(*) AS nb
    FROM creneaux
    WHERE :monday BETWEEN semaine_debut AND semaine_fin
       OR :sunday BETWEEN semaine_debut AND semaine_fin
    GROUP BY statut
");
$stmtCr->execute([':monday' => $monday, ':sunday' => $sunday]);
$creneauxStats = ['planifie' => 0, 'confirme' => 0, 'annule' => 0, 'total' => 0];
while ($row = $stmtCr->fetch()) {
    $creneauxStats[$row['statut']] = (int) $row['nb'];
    $creneauxStats['total']       += (int) $row['nb'];
}

// ── Détection conflits (enseignant + salle + classe simultanément)
$stmtConflits = $pdo->query("
    SELECT COUNT(*) FROM (
        -- Conflits enseignant
        SELECT c1.id
        FROM creneaux c1
        JOIN creneaux c2 ON c2.id != c1.id
            AND c2.enseignant_id = c1.enseignant_id
            AND c2.jour          = c1.jour
            AND c2.heure_debut   < c1.heure_fin
            AND c2.heure_fin     > c1.heure_debut
            AND c2.semaine_debut <= c1.semaine_fin
            AND c2.semaine_fin   >= c1.semaine_debut
            AND c2.statut       != 'annule'
        WHERE c1.statut != 'annule'

        UNION

        -- Conflits salle
        SELECT c1.id
        FROM creneaux c1
        JOIN creneaux c2 ON c2.id != c1.id
            AND c2.salle_id    = c1.salle_id
            AND c2.jour        = c1.jour
            AND c2.heure_debut < c1.heure_fin
            AND c2.heure_fin   > c1.heure_debut
            AND c2.semaine_debut <= c1.semaine_fin
            AND c2.semaine_fin   >= c1.semaine_debut
            AND c2.statut     != 'annule'
        WHERE c1.statut != 'annule'
    ) AS conflits_total
");
$nbConflits = (int) $stmtConflits->fetchColumn();

// ── Répartition par filière (pour le graphique)
$stmtFil = $pdo->query("
    SELECT f.code, f.libelle, f.couleur,
           COUNT(DISTINCT cr.id) AS nb_creneaux,
           COUNT(DISTINCT cl.id) AS nb_classes
    FROM filieres f
    LEFT JOIN classes  cl ON cl.filiere_id = f.id
    LEFT JOIN creneaux cr ON cr.classe_id = cl.id AND cr.statut != 'annule'
    GROUP BY f.id
    ORDER BY nb_creneaux DESC
");
$repartitionFilieres = $stmtFil->fetchAll();
foreach ($repartitionFilieres as &$r) {
    $r['nb_creneaux'] = (int) $r['nb_creneaux'];
    $r['nb_classes']  = (int) $r['nb_classes'];
}
unset($r);

// ── Enseignants les plus chargés cette semaine
$stmtEns = $pdo->prepare("
    SELECT e.nom, e.prenom, e.matricule, e.statut,
           COUNT(cr.id) AS nb_heures_cours
    FROM enseignants e
    LEFT JOIN creneaux cr ON cr.enseignant_id = e.id
        AND cr.statut != 'annule'
        AND (:monday BETWEEN cr.semaine_debut AND cr.semaine_fin
             OR :sunday BETWEEN cr.semaine_debut AND cr.semaine_fin)
    GROUP BY e.id
    ORDER BY nb_heures_cours DESC
    LIMIT 5
");
$stmtEns->execute([':monday' => $monday, ':sunday' => $sunday]);
$topEnseignants = $stmtEns->fetchAll();
foreach ($topEnseignants as &$e) {
    $e['nb_heures_cours'] = (int) $e['nb_heures_cours'];
}
unset($e);

// ── Indisponibilités en attente de validation
$stmtIndispo = $pdo->query("
    SELECT COUNT(*) FROM indisponibilites WHERE statut = 'en_attente'
");
$indispoEnAttente = (int) $stmtIndispo->fetchColumn();

Response::success([
    'totaux' => [
        'filieres'         => $counts['filieres'],
        'classes'          => $counts['classes'],
        'matieres'         => $counts['matieres'],
        'enseignants'      => $counts['enseignants'],
        'salles'           => $counts['salles'],
        'salles_disponibles' => $counts['salles_disponibles'],
    ],
    'creneaux_semaine'     => $creneauxStats,
    'conflits_detectes'    => $nbConflits,
    'indispo_en_attente'   => $indispoEnAttente,
    'repartition_filieres' => $repartitionFilieres,
    'top_enseignants'      => $topEnseignants,
    'semaine_courante'     => [
        'debut' => $monday,
        'fin'   => $sunday,
    ],
    'generated_at'         => date('Y-m-d H:i:s'),
]);
