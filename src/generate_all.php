<?php
/**
 * Génère, à partir du fichier Excel des employés :
 *   1. Une page de profil HTML par employé (output/pages/<ID>.html)
 *   2. Un QR code par employé, pointant vers sa page (output/qrcodes/<ID>.png)
 *   3. Un PDF imprimable avec toutes les cartes recto/verso (output/Cartes_Imprimables.pdf)
 *
 * Utilisation :
 *     php src/generate_all.php
 *
 * Prérequis : `composer install` exécuté au préalable, data/employees.xlsx rempli
 * (voir README.md pour le détail).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use Endroid\QrCode\Builder\Builder;
use Dompdf\Dompdf;
use Dompdf\Options;

const ROOT = __DIR__ . '/..';

function load_config(): array
{
    return require ROOT . '/config.php';
}

/**
 * Lit le fichier Excel (en-têtes sur la 2e ligne, comme dans le modèle fourni).
 * Pensez à supprimer la ligne d'exemple (en jaune) avant de lancer le script.
 */
function load_employees(array $config): array
{
    $path = ROOT . '/' . $config['data_file'];
    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true); // clés = lettres de colonnes, 1-indexé

    $employees = [];
    foreach ($rows as $rowIndex => $row) {
        if ($rowIndex <= 2) {
            continue; // ligne 1 = légende, ligne 2 = en-têtes
        }
        $prenom = trim((string) ($row['B'] ?? ''));
        $nom = trim((string) ($row['C'] ?? ''));
        if ($prenom === '' || $nom === '') {
            continue; // ligne vide, on l'ignore
        }
        $employees[] = [
            'id'         => trim((string) ($row['A'] ?? '')),
            'prenom'     => $prenom,
            'nom'        => $nom,
            'tel'        => trim((string) ($row['D'] ?? '')),
            'adresse'    => trim((string) ($row['E'] ?? '')),
            'photo_file' => trim((string) ($row['F'] ?? '')),
        ];
    }

    echo "→ " . count($employees) . " employé(s) chargé(s) depuis {$config['data_file']}\n";
    return $employees;
}

/**
 * Charge la photo depuis photos_dir, la recadre en carré, l'encode en base64.
 * Si le fichier est absent, génère un avatar de secours avec les initiales (via GD).
 */
function load_photo_b64(array $employee, array $config): string
{
    $photoFile = $employee['photo_file'];
    $photoPath = $photoFile !== '' ? ROOT . '/' . $config['photos_dir'] . '/' . $photoFile : null;

    if ($photoPath && is_file($photoPath)) {
        $src = imagecreatefromstring(file_get_contents($photoPath));
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $srcX = (int) (($w - $side) / 2);
        $srcY = (int) (($h - $side) / 2);

        $img = imagecreatetruecolor(240, 240);
        imagecopyresampled($img, $src, 0, 0, $srcX, $srcY, 240, 240, $side, $side);
        imagedestroy($src);
    } else {
        echo "  ⚠ Photo introuvable pour {$employee['id']} ('{$photoFile}') — avatar de secours utilisé.\n";
        $img = imagecreatetruecolor(240, 240);
        [$r, $g, $b] = hex_to_rgb($config['accent_color']);
        $bg = imagecolorallocate($img, $r, $g, $b);
        imagefill($img, 0, 0, $bg);

        $initials = strtoupper(mb_substr($employee['prenom'], 0, 1) . mb_substr($employee['nom'], 0, 1));
        $white = imagecolorallocate($img, 255, 255, 255);
        $ttf = find_ttf_font();

        if ($ttf) {
            $fontSize = 70;
            $bbox = imagettfbbox($fontSize, 0, $ttf, $initials);
            $textW = abs($bbox[4] - $bbox[0]);
            $textH = abs($bbox[5] - $bbox[1]);
            imagettftext($img, $fontSize, 0, (int) ((240 - $textW) / 2), (int) ((240 + $textH) / 2), $white, $ttf, $initials);
        } else {
            // Repli si aucune police TrueType n'est trouvée sur le serveur : police bitmap interne de GD.
            $fontSize = 5;
            $textW = imagefontwidth($fontSize) * strlen($initials);
            $textH = imagefontheight($fontSize);
            imagestring($img, $fontSize, (int) ((240 - $textW) / 2), (int) ((240 - $textH) / 2), $initials, $white);
        }
    }

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,' . base64_encode($data);
}

/** Cherche une police TrueType usuelle sur le serveur, pour l'avatar de secours. */
function find_ttf_font(): ?string
{
    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        'C:\\Windows\\Fonts\\arialbd.ttf',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function hex_to_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function profile_url(string $employeeId, array $config): string
{
    return rtrim($config['base_url'], '/') . '/' . $employeeId . '.html';
}

/** Repère générique si aucun vrai logo n'est fourni (voir config.php → logo_path). */
function logo_mark_svg(array $config): string
{
    $accent = $config['accent_color'];
    return <<<SVG
    <svg viewBox="0 0 40 40" fill="none">
        <circle cx="20" cy="20" r="19" stroke="{$accent}" stroke-width="1.5"/>
        <path d="M20 8 27 15 20 32 13 15 20 8Z" fill="{$accent}"/>
    </svg>
    SVG;
}

function logo_mark_html(array $config): string
{
    $logoPath = $config['logo_path'] ? ROOT . '/' . $config['logo_path'] : null;
    if ($logoPath && is_file($logoPath)) {
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION) ?: 'png');
        $b64 = base64_encode(file_get_contents($logoPath));
        return "<img src=\"data:image/{$ext};base64,{$b64}\" alt=\"{$config['company_name']}\">";
    }
    return logo_mark_svg($config);
}

function generate_profile_pages(array $employees, array $config): void
{
    $template = file_get_contents(ROOT . '/' . $config['template_file']);
    $pagesDir = ROOT . '/' . $config['output_dir'] . '/pages';
    if (!is_dir($pagesDir)) {
        mkdir($pagesDir, 0777, true);
    }
    $logoHtml = logo_mark_html($config);

    foreach ($employees as $emp) {
        $html = strtr($template, [
            '{{COMPANY_NAME}}' => $config['company_name'],
            '{{ACCENT_COLOR}}' => $config['accent_color'],
            '{{INK_COLOR}}'    => $config['ink_color'],
            '{{LOGO_MARK}}'    => $logoHtml,
            '{{ID}}'           => $emp['id'],
            '{{PRENOM}}'       => $emp['prenom'],
            '{{NOM}}'          => $emp['nom'],
            '{{TEL}}'          => $emp['tel'],
            '{{ADRESSE}}'      => $emp['adresse'],
            '{{COMPANY_NAME_UPPER}}' => strtoupper($config['company_name']),
            '{{PHOTO_B64}}'    => $emp['photo_b64'],
        ]);
        file_put_contents("{$pagesDir}/{$emp['id']}.html", $html);
    }

    echo "→ " . count($employees) . " page(s) de profil générée(s) dans {$config['output_dir']}/pages/\n";
}

function generate_qr_codes(array &$employees, array $config): void
{
    $qrDir = ROOT . '/' . $config['output_dir'] . '/qrcodes';
    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0777, true);
    }

    foreach ($employees as &$emp) {
        $url = profile_url($emp['id'], $config);
        $emp['url'] = $url;

        $result = Builder::create()
            ->data($url)
            ->size(300)
            ->margin(10)
            ->build();

        $result->saveToFile("{$qrDir}/{$emp['id']}.png");
    }
    unset($emp);

    echo "→ " . count($employees) . " QR code(s) généré(s) dans {$config['output_dir']}/qrcodes/ (pointant vers {$config['base_url']})\n";
}

function generate_printable_pdf(array $employees, array $config): void
{
    $template = file_get_contents(ROOT . '/' . $config['card_template_file']);
    $logoPath = $config['logo_path'] ? ROOT . '/' . $config['logo_path'] : null;
    $hasLogoFile = $logoPath && is_file($logoPath);
    $logoHtml = $hasLogoFile
        ? '<img class="logo-img" src="' . image_to_data_uri($logoPath) . '">'
        : '<div class="logo-mark">' . logo_mark_svg($config) . '</div>';

    $qrDir = ROOT . '/' . $config['output_dir'] . '/qrcodes';
    $cardsHtml = '';
    $total = count($employees);

    foreach ($employees as $i => $emp) {
        $qrDataUri = image_to_data_uri("{$qrDir}/{$emp['id']}.png");
        $isLastPair = ($i === $total - 1);

        // Recto
        $cardsHtml .= '<div class="card front">'
            . '<div class="bar"></div>'
            . '<div class="id">' . htmlspecialchars($emp['id']) . '</div>'
            . '<div class="name">' . htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']) . '</div>'
            . '<div class="footer">' . htmlspecialchars($config['company_name']) . ' — Carte professionnelle</div>'
            . '<img class="qr" src="' . $qrDataUri . '">'
            . '</div>';

        // Verso
        $cardsHtml .= '<div class="card back' . ($isLastPair ? ' last' : '') . '">'
            . '<div class="logo-wrap">' . $logoHtml . '</div>'
            . '<div class="company">' . htmlspecialchars(strtoupper($config['company_name'])) . '</div>'
            . '</div>';
    }

    $html = strtr($template, [
        '{{CARD_WIDTH_MM}}'  => $config['card_width_mm'],
        '{{CARD_HEIGHT_MM}}' => $config['card_height_mm'],
        '{{ACCENT_COLOR}}'   => $config['accent_color'],
        '{{INK_COLOR}}'      => $config['ink_color'],
        '{{CARDS}}'          => $cardsHtml,
    ]);

    $options = new Options();
    $options->set('isRemoteEnabled', false); // tout est déjà encodé en base64, pas besoin de réseau
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $widthPt = $config['card_width_mm'] * 2.83465;
    $heightPt = $config['card_height_mm'] * 2.83465;
    $dompdf->setPaper([0, 0, $widthPt, $heightPt]);
    $dompdf->loadHtml($html);
    $dompdf->render();

    file_put_contents(
        ROOT . '/' . $config['output_dir'] . '/Cartes_Imprimables.pdf',
        $dompdf->output()
    );

    echo "→ PDF imprimable généré : {$config['output_dir']}/Cartes_Imprimables.pdf ({$total} carte(s), recto/verso)\n";
}

function image_to_data_uri(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'png');
    return "data:image/{$ext};base64," . base64_encode(file_get_contents($path));
}

function main(): void
{
    $config = load_config();
    $employees = load_employees($config);

    if (empty($employees)) {
        echo "Aucun employé trouvé. Vérifiez data/employees.xlsx.\n";
        return;
    }

    foreach ($employees as &$emp) {
        $emp['photo_b64'] = load_photo_b64($emp, $config);
    }
    unset($emp);

    generate_profile_pages($employees, $config);
    generate_qr_codes($employees, $config);
    generate_printable_pdf($employees, $config);

    echo "\n✓ Terminé. Voir le dossier output/.\n";
}

main();
