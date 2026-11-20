<?php
declare(strict_types=1);


require_once __DIR__ . '/xlsx_helper.php';

const FUSEAU          = 'Africa/Porto-Novo';
const DOSSIER_DONNEES = __DIR__ . '/uploads';              // Dossier réservé uniquement aux PDF déposés
const FICHIER_XLSX     = __DIR__ . '/soumissions.xlsx';     // Fichier Excel à part, directement à la racine du projet
const SEPARATEUR_XLSX  = ';';                               // ';' = Excel en français
const GARDER_ACCENTS  = false;                              // false => "Memoire_NOM_Prenom.pdf"
const PAGE_CONFIRMATION = 'confirmation.html';

const MO = 1024 * 1024;

/** id du champ => [libellé du fichier, préfixe du nom final, taille max en octets, obligatoire] */
const FICHIERS = [
    'pv_soutenance'   => ['PV de soutenance',                'PV',           2 * MO,  false],
    'attestation'     => ['Attestation d\'inscription',      'Attestation',  2 * MO,  true],
    'preuve_plagiat'  => ['Preuve de plagiat / certification', 'Plagiat',    2 * MO,  true],
    'memoire_corrige' => ['Mémoire corrigé',                 'Mémoire',      50 * MO, true],
];

const UNIVERSITES = ['UAC', 'UP', 'UNSTIM', 'UNA', 'AUTRE'];
const DIPLOMES    = ['Licence', 'Licence Professionnel', 'Master', 'Ingenieur conception', 'Doctorat', 'Autre'];

date_default_timezone_set(FUSEAU);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/* 2. OUTILS */


function repondre(array $json, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ajouterErreur(array &$erreurs, string $page, string $champ, string $message): void
{
    // On garde seulement la première erreur de chaque champ
    $erreurs[$page][$champ] ??= $message;
}

function texte(array $source, string $cle, int $max = 150): string
{
    $v = $source[$cle] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    return mb_substr($v, 0, $max);
}

function formaterTaille(int $octets): string
{
    return number_format($octets / MO, 2, ',', ' ') . ' Mo';
}

/** Protège contre l'injection de formules quand le CSV est ouvert dans Excel. */
function securiserCellule(string $v): string
{
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

/** "AKOGBATO Jean-rene" => ['AKOGBATO', 'Jean-rene'] (mots en MAJUSCULES = nom). */
function separerNomPrenom(string $complet): array
{
    $mots = preg_split('/\s+/u', trim($complet), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $nom = [];
    $prenom = [];

    foreach ($mots as $mot) {
        $estMajuscule = mb_strtoupper($mot) === $mot && preg_match('/\p{L}/u', $mot);
        if ($estMajuscule) {
            $nom[] = $mot;
        } else {
            $prenom[] = $mot;
        }
    }

    // Aucun mot en majuscules : on considère que le 1er mot est le nom
    if (!$nom && $prenom) {
        $nom[] = array_shift($prenom);
    }

    return [implode(' ', $nom), implode(' ', $prenom)];
}

/** Nettoie un morceau de nom de fichier (garde lettres, chiffres, tiret). */
function nettoyerPourFichier(string $s): string
{
    if (!GARDER_ACCENTS && function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
        $s = $t !== false ? $t : $s;
    }
    $s = preg_replace('/\s+/u', '-', trim($s)) ?? '';
    $s = preg_replace('/[^\p{L}\p{N}\-]/u', '', $s) ?? '';
    return trim($s, '-') ?: 'Inconnu';
}

function messageErreurUpload(int $code): ?string
{
    return match ($code) {
        UPLOAD_ERR_OK         => null,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale autorisée.',
        UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement envoyé. Réessayez.',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier envoyé.',
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION  => 'Erreur serveur lors de la réception du fichier.',
        default               => 'Erreur inconnue lors de l\'envoi du fichier.',
    };
}


/* 3. CONTRÔLES PRÉALABLES */


if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repondre(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

// Si le total envoyé dépasse post_max_size, PHP vide $_POST et $_FILES
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    repondre([
        'ok'      => false,
        'message' => 'L\'envoi dépasse la limite du serveur (' . ini_get('post_max_size')
                   . '). Réduisez la taille des fichiers.',
        'errors'  => ['documents' => ['_general' =>
            'Le total des fichiers dépasse la limite du serveur (' . ini_get('post_max_size') . ').']],
    ], 413);
}

$donnees = json_decode((string)($_POST['donnees'] ?? ''), true);
if (!is_array($donnees)) {
    $donnees = [];
}

$perso = is_array($donnees['personnel']  ?? null) ? $donnees['personnel']  : [];
$acad  = is_array($donnees['academique'] ?? null) ? $donnees['academique'] : [];
$sup   = is_array($donnees['superviseur'] ?? null) ? $donnees['superviseur'] : [];

$erreurs = [];


/* 4. VALIDATION DES CHAMPS TEXTE*/


$regexTel = '/^01( \d{2}){4}$/';

/* ---- Page « personnel » ---- */
$nomComplet = texte($perso, 'nom-complet', 100);
if (mb_strlen($nomComplet) < 3 || !preg_match('/\p{L}/u', $nomComplet)) {
    ajouterErreur($erreurs, 'personnel', 'nom-complet', 'Veuillez saisir votre nom complet.');
}

$jour  = (int)texte($perso, 'jour', 2);
$mois  = (int)texte($perso, 'mois', 2);
$annee = (int)texte($perso, 'annee', 4);
$dateNaissance = '';
if (!$jour || !$mois || !$annee) {
    ajouterErreur($erreurs, 'personnel', 'jour', 'Veuillez renseigner votre date de naissance complète.');
} elseif (!checkdate($mois, $jour, $annee)) {
    ajouterErreur($erreurs, 'personnel', 'jour', 'Cette date de naissance n\'existe pas.');
} else {
    $dateNaissance = sprintf('%02d/%02d/%04d', $jour, $mois, $annee);
    if (sprintf('%04d%02d%02d', $annee, $mois, $jour) > date('Ymd')) {
        ajouterErreur($erreurs, 'personnel', 'jour', 'La date de naissance ne peut pas être dans le futur.');
    }
}

$civilite = texte($perso, 'civilite', 30);
if (!in_array($civilite, ['Monsieur (Mr)', 'Mademoiselle (Mlle)', 'Madame (Mme)', 'Docteur (Dr)', 'Professeur (Prof)'], true)) {
    ajouterErreur($erreurs, 'personnel', 'civilite', 'Veuillez choisir votre civilité.');
}

$emailApprenant = texte($perso, 'email', 120);
if (!filter_var($emailApprenant, FILTER_VALIDATE_EMAIL)) {
    ajouterErreur($erreurs, 'personnel', 'email', 'Adresse email invalide. Format attendu : exemple@domaine.com');
}

$telApprenant = texte($perso, 'telephone', 14);
if (!preg_match($regexTel, $telApprenant)) {
    ajouterErreur($erreurs, 'personnel', 'telephone', 'Format attendu : 01 46 58 58 96.');
}

/* ---- Page « académique » ---- */
$universite = strtoupper(texte($acad, 'universite', 10));
$nomUniversite = '';
$entite = '';

if (!in_array($universite, UNIVERSITES, true)) {
    ajouterErreur($erreurs, 'academique', 'universite', 'Veuillez choisir votre université.');
} elseif ($universite === 'AUTRE') {
    $nomUniversite = texte($acad, 'universite-autre', 120);
    $entite        = texte($acad, 'entite-autre', 150);
    if ($nomUniversite === '') {
        ajouterErreur($erreurs, 'academique', 'universite-autre', 'Précisez le nom de votre université.');
    }
    if ($entite === '') {
        ajouterErreur($erreurs, 'academique', 'entite-autre', 'Précisez votre entité.');
    }
} else {
    $nomUniversite = $universite;
    $idEntite = 'entite-' . strtolower($universite);   // entite-uac, entite-up, ...
    $entite   = texte($acad, 'entite-' . strtolower($universite), 150);
    if ($entite === '') {
        ajouterErreur($erreurs, 'academique', $idEntite, 'Veuillez choisir votre entité.');
    }
}

$filiere = texte($acad, 'filiere', 120);
if ($filiere === '') {
    ajouterErreur($erreurs, 'academique', 'filiere', 'Veuillez indiquer votre filière.');
}

$anneeSoutenance = texte($acad, 'annee-soutenance', 9);
if (!preg_match('/^(\d{4})-(\d{4})$/', $anneeSoutenance, $m) || (int)$m[2] !== (int)$m[1] + 1) {
    ajouterErreur($erreurs, 'academique', 'annee-soutenance', 'Veuillez choisir l\'année de soutenance.');
}

$diplome = texte($acad, 'diplome', 40);
if (!in_array($diplome, DIPLOMES, true)) {
    ajouterErreur($erreurs, 'academique', 'diplome', 'Veuillez choisir votre diplôme.');
} elseif ($diplome === 'Autre') {
    $diplome = texte($acad, 'diplome-autre', 80);
    if ($diplome === '') {
        ajouterErreur($erreurs, 'academique', 'diplome-autre', 'Précisez votre diplôme.');
    }
}

/* ---- Page « superviseur » ---- */
$nomSuperviseur = texte($sup, 'nom', 100);
if (mb_strlen($nomSuperviseur) < 3 || !preg_match('/\p{L}/u', $nomSuperviseur)) {
    ajouterErreur($erreurs, 'superviseur', 'nom', 'Veuillez saisir le nom complet du superviseur.');
}

$gradeSuperviseur = texte($sup, 'grade', 100);   // facultatif

$emailSuperviseur = texte($sup, 'email', 120);
if (!filter_var($emailSuperviseur, FILTER_VALIDATE_EMAIL)) {
    ajouterErreur($erreurs, 'superviseur', 'email', 'Adresse email invalide. Format attendu : exemple@domaine.com');
}

$telSuperviseur = texte($sup, 'telephone', 14);
if (!preg_match($regexTel, $telSuperviseur)) {
    ajouterErreur($erreurs, 'superviseur', 'telephone', 'Format attendu : 01 46 58 58 96.');
}


/* 5. VALIDATION DES FICHIERS (taille, type PDF)*/


$finfo = new finfo(FILEINFO_MIME_TYPE);

foreach (FICHIERS as $champ => [$libelle, , $tailleMax, $obligatoire]) {
    $f = $_FILES[$champ] ?? null;

    if (!is_array($f) || !isset($f['error']) || is_array($f['error'])) {
        if ($obligatoire) {
            ajouterErreur($erreurs, 'documents', $champ, 'Ce document est obligatoire.');
        }
        continue;
    }

    if ($f['error'] === UPLOAD_ERR_NO_FILE) {
        if ($obligatoire) {
            ajouterErreur($erreurs, 'documents', $champ, 'Ce document est obligatoire.');
        }
        continue;
    }

    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = messageErreurUpload((int)$f['error']);
        if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $msg = 'Fichier trop volumineux : maximum ' . formaterTaille($tailleMax) . '.';
        }
        ajouterErreur($erreurs, 'documents', $champ, $msg ?? 'Erreur d\'envoi.');
        continue;
    }

    if ($f['size'] > $tailleMax) {
        ajouterErreur(
            $erreurs, 'documents', $champ,
            'Fichier trop volumineux (' . formaterTaille((int)$f['size']) . ') : maximum '
            . formaterTaille($tailleMax) . '.'
        );
        continue;
    }

    if ($f['size'] === 0) {
        ajouterErreur($erreurs, 'documents', $champ, 'Le fichier est vide.');
        continue;
    }

    $extension = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $mime      = $finfo->file($f['tmp_name']);
    $debut     = (string)file_get_contents($f['tmp_name'], false, null, 0, 5);

    if ($extension !== 'pdf' || $mime !== 'application/pdf' || $debut !== '%PDF-') {
        ajouterErreur($erreurs, 'documents', $champ, 'Le fichier doit être un vrai PDF.');
    }
}

/* 6. S'IL Y A DES ERREURS : on s'arrête ici */

if ($erreurs) {
    repondre([
        'ok'      => false,
        'message' => 'Certaines informations sont à corriger.',
        'errors'  => $erreurs,
    ], 422);
}

/* 7. ENREGISTREMENT DES PDF (renommés) */


[$nom, $prenom] = separerNomPrenom($nomComplet);
$suffixe = nettoyerPourFichier($nom) . '_' . nettoyerPourFichier($prenom);   // AKOGBATO_Jean-rene

$horodatage = new DateTimeImmutable('now', new DateTimeZone(FUSEAU));

if (!is_dir(DOSSIER_DONNEES) && !mkdir(DOSSIER_DONNEES, 0750, true) && !is_dir(DOSSIER_DONNEES)) {
    repondre(['ok' => false, 'message' => 'Erreur serveur : dossier de stockage impossible à créer.'], 500);
}

// Empêche l'accès direct par URL (Apache). Sur Nginx, bloquez le dossier dans la config.
$htaccess = DOSSIER_DONNEES . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Require all denied\nDeny from all\n");
}

// Un sous-dossier par dépôt => aucun risque d'écraser le fichier d'un autre apprenant
$dossierDepot = DOSSIER_DONNEES . '/' . $suffixe . '_' . $horodatage->format('Ymd-His') . '_' . bin2hex(random_bytes(2));
if (!mkdir($dossierDepot, 0750, true) && !is_dir($dossierDepot)) {
    repondre(['ok' => false, 'message' => 'Erreur serveur : dossier de dépôt impossible à créer.'], 500);
}

$nomsFinaux = [];
foreach (FICHIERS as $champ => [, $prefixe]) {
    $f = $_FILES[$champ] ?? null;
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $nomsFinaux[$champ] = '';   // pv_soutenance facultatif non fourni
        continue;
    }

    $nomFinal = $prefixe . '_' . $suffixe . '.pdf';     // Mémoire_AKOGBATO_Jean-rene.pdf

    if (!move_uploaded_file($f['tmp_name'], $dossierDepot . '/' . $nomFinal)) {
        repondre(['ok' => false, 'message' => 'Erreur serveur : impossible d\'enregistrer ' . $nomFinal . '.'], 500);
    }
    $nomsFinaux[$champ] = $nomFinal;
}


/* 8. ENREGISTREMENT DANS LE VRAI FICHIER EXCEL (.xlsx OpenXML natif) */

$entetes = [
    'N°',
    'Nom', 'Prénom', 'Date de naissance', 'Civilité', 'Email', 'Téléphone',
    'Université', 'Entité', 'Filière', 'Année de soutenance', 'Diplôme',
    'Superviseur - Nom', 'Superviseur - Grade', 'Superviseur - Email', 'Superviseur - Téléphone',
    'PV de soutenance', 'Attestation', 'Preuve de plagiat', 'Mémoire corrigé',
    'Dossier',
    'Date de soumission', 'Heure de soumission',
];

$nouvelleLigne = [
    '', // Le N° (1, 2, 3...) est calculé automatiquement ci-dessous
    $nom, $prenom, $dateNaissance, $civilite, $emailApprenant, $telApprenant,
    $nomUniversite, $entite, $filiere, $anneeSoutenance, $diplome,
    $nomSuperviseur, $gradeSuperviseur, $emailSuperviseur, $telSuperviseur,
    $nomsFinaux['pv_soutenance'] ?: 'Non fourni',
    $nomsFinaux['attestation'], $nomsFinaux['preuve_plagiat'], $nomsFinaux['memoire_corrige'],
    basename($dossierDepot),
    $horodatage->format('d/m/Y'), $horodatage->format('H:i:s'),
];

// Verrouillage exclusif pour éviter toute collision si 2 étudiants soumettent en même temps
$verrou = fopen(dirname(FICHIER_XLSX) . '/.xlsx.lock', 'cb');
if ($verrou === false || !flock($verrou, LOCK_EX)) {
    repondre(['ok' => false, 'message' => 'Erreur serveur : verrouillage du fichier Excel impossible.'], 500);
}

try {
    // Si un ancien fichier CSV mal nommé (.xlsx texte) traîne encore, on le migre proprement
    $lignesExistantes = [];
    if (is_file(FICHIER_XLSX)) {
        $signature = (string)file_get_contents(FICHIER_XLSX, false, null, 0, 2);
        if ($signature === 'PK') {
            // Vrai fichier .xlsx (archive ZIP)
            $lignesExistantes = lireLignesXlsx(FICHIER_XLSX);
        } else {
            // Migration automatique depuis l'ancien format CSV vers vrai .xlsx
            $fh = fopen(FICHIER_XLSX, 'rb');
            if ($fh !== false) {
                if (fread($fh, 3) !== "\xEF\xBB\xBF") {
                    rewind($fh);
                }
                fgetcsv($fh, 0, ';'); // ignorer ancien en-tête sans N°
                while (($rowCsv = fgetcsv($fh, 0, ';')) !== false) {
                    if (count($rowCsv) >= 22) {
                        $rowCsv = array_map(fn($v) => ltrim((string)$v, "'"), $rowCsv);
                        $lignesExistantes[] = array_merge([''], array_slice($rowCsv, 0, 22));
                    }
                }
                fclose($fh);
            }
        }
    }

    // Vérifier si le fichier existant avait l'ancien ordre (Date de soumission en colonne 1)
    $ancienOrdre = isset($lignesExistantes[0][1]) && $lignesExistantes[0][1] === 'Date de soumission';

    // Construire la table complète avec l'en-tête en ligne 0
    $toutesLignes = [$entetes];
    foreach ($lignesExistantes as $idx => $ancienne) {
        if ($idx === 0 && (($ancienne[0] ?? '') === 'N°' || ($ancienne[0] ?? '') === 'Date de soumission')) {
            continue;
        }
        // Si la ligne migrée n'avait pas la colonne N°, on l'ajoute devant
        if (count($ancienne) === 22) {
            array_unshift($ancienne, '');
            $ancienOrdre = true;
        }
        if (count($ancienne) >= 23) {
            $ligne23 = array_slice($ancienne, 0, 23);
            if ($ancienOrdre) {
                // Déplacer Date (index 1) et Heure (index 2) vers la fin de la ligne
                $dateSoum  = $ligne23[1];
                $heureSoum = $ligne23[2];
                $ligne23 = array_merge(
                    [$ligne23[0]],
                    array_slice($ligne23, 3, 20),
                    [$dateSoum, $heureSoum]
                );
            }
            $toutesLignes[] = $ligne23;
        }
    }
    $toutesLignes[] = $nouvelleLigne;

    // Renumérotation dynamique de la colonne N° (1, 2, 3...)
    // Ainsi, même si une ligne est supprimée dans Excel, l'ordre reste toujours continu !
    for ($i = 1, $total = count($toutesLignes); $i < $total; $i++) {
        $toutesLignes[$i][0] = (string)$i;
    }

    if (!ecrireLignesXlsx(FICHIER_XLSX, $toutesLignes)) {
        repondre(['ok' => false, 'message' => 'Erreur serveur : impossible d\'écrire le fichier Excel (.xlsx).'], 500);
    }
} finally {
    flock($verrou, LOCK_UN);
    fclose($verrou);
}


/* 9. SUCCÈS   */

repondre(['ok' => true, 'redirect' => PAGE_CONFIRMATION]);
