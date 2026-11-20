<?php
declare(strict_types=1);

/**
 * ============================================================================
 * FICHIER : lister_memoires.php
 * RÔLE :
 *   1. Lire automatiquement les soumissions enregistrées dans soumissions.xlsx
 *   2. Trier du plus récent au plus ancien
 *   3. Gérer la pagination (3 mémoires sur l'accueil, 10 par page sur memoires.html)
 *   4. Filtrer par recherche texte (?q=...) ET/OU par filtres en cascade :
 *      Université -> Entité -> Filière -> Année de soutenance -> Superviseur
 *   5. Fournir les options en cascade et toutes les informations du mémoire
 *      (sauf date de naissance et documents) pour le bouton "Voir plus".
 * ============================================================================
 */

require_once __DIR__ . '/xlsx_helper.php';

const DOSSIER_DONNEES = __DIR__ . '/uploads';
const FICHIER_XLSX    = __DIR__ . '/soumissions.xlsx';

// ----------------------------------------------------------------------------
// CAS 1 : Affichage sécurisé d'un fichier PDF (aperçu de page de garde / lecture)
// ----------------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'pdf') {
    $dossier = basename((string)($_GET['dossier'] ?? ''));
    $fichier = basename((string)($_GET['fichier'] ?? ''));

    $cheminComplet = DOSSIER_DONNEES . '/' . $dossier . '/' . $fichier;

    if ($dossier === '' || $fichier === '' || strtolower(pathinfo($fichier, PATHINFO_EXTENSION)) !== 'pdf' || !is_file($cheminComplet)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Document PDF introuvable.';
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $fichier . '"');
    header('Content-Length: ' . filesize($cheminComplet));
    header('Cache-Control: public, max-age=3600');
    readfile($cheminComplet);
    exit;
}

// ----------------------------------------------------------------------------
// CAS 2 : Renvoi de la liste des mémoires et des données de filtres au format JSON
// ----------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$limite            = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$page              = max(1, (int)($_GET['page'] ?? 1));
$recherche         = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$filtreUniversite  = trim((string)($_GET['universite'] ?? ''));
$filtreEntite      = trim((string)($_GET['entite'] ?? ''));
$filtreFiliere     = trim((string)($_GET['filiere'] ?? ''));
$filtreAnnee       = trim((string)($_GET['annee'] ?? ''));
$filtreSuperviseur = trim((string)($_GET['superviseur'] ?? ''));

if (!is_file(FICHIER_XLSX)) {
    echo json_encode([
        'ok'           => true,
        'memoires'     => [],
        'catalogue'    => [],
        'total'        => 0,
        'page'         => 1,
        'totalPages'   => 1
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$lignes = lireLignesXlsx(FICHIER_XLSX);
$tousLesMemoires = [];

$estNouvelOrdre = (($lignes[0][21] ?? '') === 'Date de soumission');

for ($i = 1, $nb = count($lignes); $i < $nb; $i++) {
    $ligne = $lignes[$i];

    if ($estNouvelOrdre) {
        if (count($ligne) < 23) {
            continue;
        }
        // Toutes les informations SAUF date de naissance ($ligne[3]) et les documents ($ligne[16..18])
        $memoire = [
            'numero'               => $ligne[0] ?? (string)$i,
            'nom'                  => $ligne[1] ?? '',
            'prenom'               => $ligne[2] ?? '',
            'civilite'             => $ligne[4] ?? '',
            'email'                => $ligne[5] ?? '',
            'telephone'            => $ligne[6] ?? '',
            'universite'           => $ligne[7] ?? '',
            'entite'               => $ligne[8] ?? '',
            'filiere'              => $ligne[9] ?? '',
            'anneeSoutenance'      => $ligne[10] ?? '',
            'diplome'              => $ligne[11] ?? '',
            'superviseur'          => $ligne[12] ?? '',
            'gradeSuperviseur'     => $ligne[13] ?? '',
            'emailSuperviseur'     => $ligne[14] ?? '',
            'telephoneSuperviseur' => $ligne[15] ?? '',
            'fichierMemoire'       => $ligne[19] ?? '',
            'dossier'              => $ligne[20] ?? '',
            'dateSoumission'       => $ligne[21] ?? '',
            'heureSoumission'      => $ligne[22] ?? '',
        ];
    } else {
        $decalage = (count($ligne) >= 23) ? 1 : 0;
        if (count($ligne) < (22 + $decalage)) {
            continue;
        }
        $memoire = [
            'numero'               => $decalage === 1 ? ($ligne[0] ?? (string)$i) : (string)$i,
            'dateSoumission'       => $ligne[0 + $decalage] ?? '',
            'heureSoumission'      => $ligne[1 + $decalage] ?? '',
            'nom'                  => $ligne[2 + $decalage] ?? '',
            'prenom'               => $ligne[3 + $decalage] ?? '',
            'civilite'             => $ligne[5 + $decalage] ?? '',
            'email'                => $ligne[6 + $decalage] ?? '',
            'telephone'            => $ligne[7 + $decalage] ?? '',
            'universite'           => $ligne[8 + $decalage] ?? '',
            'entite'               => $ligne[9 + $decalage] ?? '',
            'filiere'              => $ligne[10 + $decalage] ?? '',
            'anneeSoutenance'      => $ligne[11 + $decalage] ?? '',
            'diplome'              => $ligne[12 + $decalage] ?? '',
            'superviseur'          => $ligne[13 + $decalage] ?? '',
            'gradeSuperviseur'     => $ligne[14 + $decalage] ?? '',
            'emailSuperviseur'     => $ligne[15 + $decalage] ?? '',
            'telephoneSuperviseur' => $ligne[16 + $decalage] ?? '',
            'fichierMemoire'       => $ligne[20 + $decalage] ?? '',
            'dossier'              => $ligne[21 + $decalage] ?? '',
        ];
    }

    $memoire['urlPdf'] = 'lister_memoires.php?action=pdf&dossier='
        . rawurlencode($memoire['dossier'])
        . '&fichier='
        . rawurlencode($memoire['fichierMemoire']);

    $tousLesMemoires[] = $memoire;
}

// Les plus récents en premier
$tousLesMemoires = array_reverse($tousLesMemoires);

// Catalogue des combinaisons existantes pour le filtre en cascade
$catalogue = [];
foreach ($tousLesMemoires as $m) {
    $catalogue[] = [
        'universite'      => $m['universite'],
        'entite'          => $m['entite'],
        'filiere'         => $m['filiere'],
        'anneeSoutenance' => $m['anneeSoutenance'],
        'superviseur'     => $m['superviseur'],
    ];
}

// Application des filtres en cascade + recherche globale
$memoiresFiltres = array_values(array_filter($tousLesMemoires, static function (array $m) use (
    $recherche,
    $filtreUniversite,
    $filtreEntite,
    $filtreFiliere,
    $filtreAnnee,
    $filtreSuperviseur
): bool {
    if ($filtreUniversite !== '' && mb_strtolower($m['universite']) !== mb_strtolower($filtreUniversite)) {
        return false;
    }
    if ($filtreEntite !== '' && mb_strtolower($m['entite']) !== mb_strtolower($filtreEntite)) {
        return false;
    }
    if ($filtreFiliere !== '' && mb_strtolower($m['filiere']) !== mb_strtolower($filtreFiliere)) {
        return false;
    }
    if ($filtreAnnee !== '' && mb_strtolower($m['anneeSoutenance']) !== mb_strtolower($filtreAnnee)) {
        return false;
    }
    if ($filtreSuperviseur !== '' && mb_strtolower($m['superviseur']) !== mb_strtolower($filtreSuperviseur)) {
        return false;
    }
    if ($recherche !== '') {
        $texteGlobal = mb_strtolower(implode(' ', [
            $m['nom'],
            $m['prenom'],
            $m['civilite'],
            $m['email'],
            $m['telephone'],
            $m['universite'],
            $m['entite'],
            $m['filiere'],
            $m['diplome'],
            $m['anneeSoutenance'],
            $m['superviseur'],
            $m['gradeSuperviseur'],
        ]));
        if (!str_contains($texteGlobal, $recherche)) {
            return false;
        }
    }
    return true;
}));

$total      = count($memoiresFiltres);
$totalPages = max(1, (int)ceil($total / $limite));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $limite;

$pageMemoires = array_slice($memoiresFiltres, $offset, $limite);

echo json_encode([
    'ok'         => true,
    'memoires'   => $pageMemoires,
    'catalogue'  => $catalogue,
    'total'      => $total,
    'page'       => $page,
    'totalPages' => $totalPages,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
