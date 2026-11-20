<?php
declare(strict_types=1);

/**
 * ============================================================================
 * FICHIER : xlsx_helper.php
 * RÔLE : Lire et écrire un VRAI fichier Excel (.xlsx OpenXML natif) en PHP pur
 *        grâce à ZipArchive et SimpleXML (aucune bibliothèque externe requise).
 *        Gère aussi une colonne "N°" (1, 2, 3...) recalculée dynamiquement.
 * ============================================================================
 */

/**
 * Convertit un index de colonne (0 -> A, 1 -> B, ..., 25 -> Z, 26 -> AA)
 */
function xlsxColonneLettre(int $index): string
{
    $lettre = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $lettre = chr(65 + $mod) . $lettre;
        $index = intdiv($index - $mod, 26);
    }
    return $lettre;
}

/**
 * Convertit une référence de cellule Excel (ex: "C4") en index de colonne (ex: 2)
 */
function xlsxReferenceVersColIndex(string $ref): int
{
    if (!preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) {
        return 0;
    }
    $lettres = $m[1];
    $index = 0;
    for ($i = 0; $i < strlen($lettres); $i++) {
        $index = $index * 26 + (ord($lettres[$i]) - 64);
    }
    return $index - 1;
}

/**
 * Lit toutes les lignes d'un vrai fichier Excel .xlsx.
 * Retourne un tableau de lignes (chaque ligne est un tableau indexé de chaînes).
 */
function lireLignesXlsx(string $cheminFichier): array
{
    if (!is_file($cheminFichier)) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($cheminFichier) !== true) {
        return [];
    }

    // 1. Lecture éventuelle des chaînes partagées (xl/sharedStrings.xml)
    //    (Excel utilise sharedStrings quand tu modifies et enregistres le fichier dans Microsoft Excel)
    $sharedStrings = [];
    $xmlShared = $zip->getFromName('xl/sharedStrings.xml');
    if ($xmlShared !== false) {
        $sst = @simplexml_load_string($xmlShared);
        if ($sst !== false) {
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $texte = '';
                    foreach ($si->r as $run) {
                        $texte .= (string)$run->t;
                    }
                    $sharedStrings[] = $texte;
                }
            }
        }
    }

    // 2. Lecture de la première feuille (xl/worksheets/sheet1.xml)
    $xmlSheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($xmlSheet === false) {
        return [];
    }

    $sheet = @simplexml_load_string($xmlSheet);
    if ($sheet === false || !isset($sheet->sheetData->row)) {
        return [];
    }

    $lignes = [];
    foreach ($sheet->sheetData->row as $row) {
        $ligneCourante = [];
        $maxCol = -1;

        foreach ($row->c as $c) {
            $ref = (string)($c['r'] ?? '');
            $colIdx = $ref !== '' ? xlsxReferenceVersColIndex($ref) : ($maxCol + 1);
            $type = (string)($c['t'] ?? '');
            $valeur = '';

            if ($type === 's') {
                // Référence vers sharedStrings.xml
                $idx = (int)($c->v ?? 0);
                $valeur = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $valeur = (string)($c->is->t ?? '');
            } else {
                $valeur = (string)($c->v ?? '');
            }

            $ligneCourante[$colIdx] = $valeur;
            if ($colIdx > $maxCol) {
                $maxCol = $colIdx;
            }
        }

        // Remplir les éventuelles cellules vides intermédiaires
        $ligneNormalisee = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $ligneNormalisee[] = $ligneCourante[$i] ?? '';
        }
        $lignes[] = $ligneNormalisee;
    }

    return $lignes;
}

/**
 * Écrit un tableau complet de lignes dans un vrai fichier Excel .xlsx valide.
 * - La 1ère ligne est mise en gras avec fond bleu clair (en-tête).
 * - La colonne 0 ("N°") est renumérotée proprement 1, 2, 3... pour chaque ligne de données.
 */
function ecrireLignesXlsx(string $cheminFichier, array $lignes): bool
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    // Construction du XML de la feuille xl/worksheets/sheet1.xml
    $rowsXml = '';
    foreach ($lignes as $rIdx => $ligne) {
        $numLigne = $rIdx + 1;
        $cellsXml = '';

        foreach ($ligne as $cIdx => $valeur) {
            $ref = xlsxColonneLettre($cIdx) . $numLigne;
            $style = ($rIdx === 0) ? ' s="1"' : ''; // s="1" = style en-tête en gras

            // Colonne N° (index 0) sur les lignes de données : écriture en nombre entier
            if ($rIdx > 0 && $cIdx === 0 && is_numeric($valeur)) {
                $cellsXml .= '<c r="' . $ref . '" t="n"><v>' . (int)$valeur . '</v></c>';
            } else {
                $echappe = htmlspecialchars((string)$valeur, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cellsXml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . $echappe . '</t></is></c>';
            }
        }

        $rowsXml .= '<row r="' . $numLigne . '">' . $cellsXml . '</row>';
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $rowsXml . '</sheetData>'
        . '</worksheet>';

    // Fichiers obligatoires de l'archive OpenXML (.xlsx)
    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Soumissions" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    // Styles : s="0" normal, s="1" en-tête en gras avec fond bleu très clair
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FF102B50"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9E5F7"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '</cellXfs>'
        . '</styleSheet>';

    // Écriture atomique via un fichier temporaire pour éviter toute corruption
    $tmpFichier = $cheminFichier . '.tmp.' . bin2hex(random_bytes(4));
    $zip = new ZipArchive();
    if ($zip->open($tmpFichier, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $relsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    return @rename($tmpFichier, $cheminFichier);
}
