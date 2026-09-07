<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

use RuntimeException;

final class VendorCoverRenderer
{
    public const WIDTH = 590;
    public const HEIGHT = 300;

    /** @var list<string> */
    private const BOLD_FONTS = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
    ];

    /** @var list<string> */
    private const REGULAR_FONTS = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
    ];

    /**
     * @return array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * }
     */
    public function capabilities(): array
    {
        $bold = $this->firstFont(self::BOLD_FONTS);
        $regular = $this->firstFont(self::REGULAR_FONTS);
        $gd = extension_loaded('gd');
        $info = $gd ? gd_info() : [];
        $freetype = $gd
            && (bool) ($info['FreeType Support'] ?? false);
        $imagick = class_exists('Imagick');
        $imagickWebp = $imagick && $this->imagickHasWebp();

        return [
            'gd' => $gd,
            'webp' => $gd
                && (bool) ($info['WebP Support'] ?? false),
            'freetype' => $freetype,
            'ttf' => $freetype
                && $bold !== ''
                && $regular !== '',
            'imagick' => $imagick,
            'imagickWebp' => $imagickWebp,
            'engine' => $imagickWebp
                ? 'IMAGICK_SVG'
                : 'GD',
            'boldFont' => $bold,
            'regularFont' => $regular,
        ];
    }

    public function render(
        string $path,
        string $title,
        string $subtitle,
        string $productType
    ): void {
        $capabilities = $this->capabilities();

        if ($capabilities['imagickWebp']) {
            $this->renderWithImagick(
                $path,
                trim($title),
                trim($subtitle),
                trim($productType)
            );

            return;
        }

        if (! $capabilities['gd']) {
            throw new RuntimeException(
                'Neither Imagick nor GD is available for Vendor cover rendering.'
            );
        }

        if (! $capabilities['webp']) {
            throw new RuntimeException(
                'No WebP-capable image engine is available on this server.'
            );
        }

        $this->renderWithGd(
            $path,
            trim($title),
            trim($subtitle),
            trim($productType),
            $capabilities
        );
    }

    private function imagickHasWebp(): bool
    {
        $class = 'Imagick';

        try {
            $image = new $class();
            $formats = $image->queryFormats('WEBP');

            return $formats !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    private function renderWithImagick(
        string $path,
        string $title,
        string $subtitle,
        string $productType
    ): void {
        $class = 'Imagick';
        $title = $title !== ''
            ? $title
            : 'Premium WordPress Product';
        $subtitle = $subtitle !== ''
            ? $subtitle
            : $this->genericSubtitle($productType);
        $titleLines = $this->wrapByCharacters(
            $title,
            $this->titleCharacterLimit($title),
            2
        );
        $subtitleLines = $this->wrapByCharacters(
            $subtitle,
            39,
            2
        );
        $monogram = $this->monogram($title);
        $pill = $this->pillText($productType);
        $typeLabel = strtolower($productType) === 'theme'
            ? 'WordPress theme'
            : 'WordPress plugin';

        $titleSize = $this->svgTitleSize($title);
        $titleY = 98;
        $titleText = '';

        foreach ($titleLines as $line) {
            $titleText .= '<tspan x="32" y="'
                . $titleY
                . '">'
                . $this->xml($line)
                . '</tspan>';
            $titleY += $titleSize + 8;
        }

        $subtitleY = max(178, $titleY + 8);
        $subtitleText = '';

        foreach ($subtitleLines as $line) {
            $subtitleText .= '<tspan x="32" y="'
                . $subtitleY
                . '">'
                . $this->xml($line)
                . '</tspan>';
            $subtitleY += 22;
        }

        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="590" height="300" viewBox="0 0 590 300">'
            . '<defs>'
            . '<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0d1830"/><stop offset="55%" stop-color="#172452"/><stop offset="100%" stop-color="#402479"/></linearGradient>'
            . '<linearGradient id="accent" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#31d0aa"/><stop offset="100%" stop-color="#7c3aed"/></linearGradient>'
            . '<filter id="shadow" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="8" stdDeviation="10" flood-color="#000000" flood-opacity=".22"/></filter>'
            . '</defs>'
            . '<rect width="590" height="300" rx="0" fill="url(#bg)"/>'
            . '<circle cx="545" cy="45" r="110" fill="#31d0aa" opacity=".16"/>'
            . '<circle cx="410" cy="290" r="145" fill="#7c3aed" opacity=".17"/>'
            . '<path d="M0 25 C110 52 198 12 315 36 C424 58 508 10 590 22 L590 0 L0 0 Z" fill="#ffffff" opacity=".04"/>'
            . '<circle cx="35" cy="30" r="13" fill="#31d0aa"/>'
            . '<text x="35" y="35" text-anchor="middle" fill="#ffffff" font-family="Arial,DejaVu Sans,sans-serif" font-size="10" font-weight="700">WP</text>'
            . '<text x="56" y="35" fill="#ffffff" font-family="Arial,DejaVu Sans,sans-serif" font-size="13" font-weight="700" letter-spacing=".6">WP SHOP</text>'
            . '<text fill="#ffffff" font-family="Arial,DejaVu Sans,sans-serif" font-size="'
            . $titleSize
            . '" font-weight="700">'
            . $titleText
            . '</text>'
            . '<text fill="#d7deef" font-family="Arial,DejaVu Sans,sans-serif" font-size="15" font-weight="400">'
            . $subtitleText
            . '</text>'
            . '<rect x="32" y="247" width="204" height="31" rx="15.5" fill="#31d0aa" opacity=".18" stroke="#31d0aa" stroke-opacity=".48"/>'
            . '<text x="47" y="267" fill="#5ee6c5" font-family="Arial,DejaVu Sans,sans-serif" font-size="11" font-weight="700" letter-spacing=".4">'
            . $this->xml($pill)
            . '</text>'
            . '<g filter="url(#shadow)">'
            . '<rect x="356" y="43" width="208" height="218" rx="18" fill="#fbfcff"/>'
            . '<rect x="374" y="62" width="58" height="58" rx="14" fill="url(#accent)"/>'
            . '<text x="403" y="99" text-anchor="middle" fill="#ffffff" font-family="Arial,DejaVu Sans,sans-serif" font-size="23" font-weight="700">'
            . $this->xml($monogram)
            . '</text>'
            . '<text x="448" y="78" fill="#7b849b" font-family="Arial,DejaVu Sans,sans-serif" font-size="10">'
            . $this->xml($typeLabel)
            . '</text>'
            . '<text x="448" y="101" fill="#17223b" font-family="Arial,DejaVu Sans,sans-serif" font-size="14" font-weight="700">Premium</text>'
            . '<line x1="374" y1="137" x2="546" y2="137" stroke="#e1e6f0"/>'
            . $this->svgFeatureRow(374, 164, 'Clean product package')
            . $this->svgFeatureRow(374, 195, 'Unified WP Shop cover')
            . $this->svgFeatureRow(374, 226, 'Ready for catalog')
            . '<rect x="374" y="241" width="172" height="10" rx="5" fill="#e7ebf4"/>'
            . '<rect x="374" y="241" width="102" height="10" rx="5" fill="#31d0aa"/>'
            . '</g>'
            . '</svg>';

        try {
            $image = new $class();
            $image->setBackgroundColor('transparent');
            $image->readImageBlob($svg);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(90);
            $image->stripImage();
            $saved = $image->writeImage($path);
            $image->clear();
            $image->destroy();

            if ($saved !== true) {
                throw new RuntimeException(
                    'Imagick could not save the Vendor cover WebP.'
                );
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Imagick Vendor cover render failed: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    private function svgFeatureRow(
        int $x,
        int $y,
        string $label
    ): string {
        return '<circle cx="'
            . ($x + 6)
            . '" cy="'
            . ($y - 4)
            . '" r="6" fill="#31d0aa"/>'
            . '<path d="M'
            . ($x + 3)
            . ' '
            . ($y - 4)
            . ' l2 2 l4 -5" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<text x="'
            . ($x + 20)
            . '" y="'
            . $y
            . '" fill="#17223b" font-family="Arial,DejaVu Sans,sans-serif" font-size="10.5">'
            . $this->xml($label)
            . '</text>';
    }

    /**
     * @param array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * } $capabilities
     */
    private function renderWithGd(
        string $path,
        string $title,
        string $subtitle,
        string $productType,
        array $capabilities
    ): void {
        $image = $this->gd(
            'imagecreatetruecolor',
            self::WIDTH,
            self::HEIGHT
        );

        if ($image === false || $image === null) {
            throw new RuntimeException('Unable to create Vendor cover canvas.');
        }

        try {
            $this->drawBackground($image);
            $this->drawBrand($image, $capabilities);
            $this->drawProductCopy(
                $image,
                $title,
                $subtitle,
                $productType,
                $capabilities
            );
            $this->drawProductCard(
                $image,
                $title,
                $productType,
                $capabilities
            );

            $saved = $this->gd(
                'imagewebp',
                $image,
                $path,
                88
            );

            if ($saved !== true) {
                throw new RuntimeException(
                    'Unable to save Vendor cover as WebP.'
                );
            }
        } finally {
            $this->gd('imagedestroy', $image);
        }
    }

    /**
     * @param mixed $image
     */
    private function drawBackground(mixed $image): void
    {
        for ($x = 0; $x < self::WIDTH; ++$x) {
            $ratio = $x / (self::WIDTH - 1);
            $red = (int) round(13 + (39 - 13) * $ratio);
            $green = (int) round(24 + (31 - 24) * $ratio);
            $blue = (int) round(48 + (88 - 48) * $ratio);
            $color = $this->color($image, $red, $green, $blue);
            $this->gd(
                'imageline',
                $image,
                $x,
                0,
                $x,
                self::HEIGHT,
                $color
            );
        }

        $accent = $this->alphaColor($image, 49, 208, 170, 82);
        $violet = $this->alphaColor($image, 124, 58, 237, 91);
        $soft = $this->alphaColor($image, 255, 255, 255, 112);

        $this->gd(
            'imagefilledellipse',
            $image,
            520,
            62,
            230,
            230,
            $accent
        );
        $this->gd(
            'imagefilledellipse',
            $image,
            455,
            285,
            240,
            190,
            $violet
        );
        $this->gd(
            'imagefilledellipse',
            $image,
            260,
            -35,
            260,
            110,
            $soft
        );
    }

    /**
     * @param mixed $image
     * @param array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * } $caps
     */
    private function drawBrand(mixed $image, array $caps): void
    {
        $white = $this->color($image, 255, 255, 255);
        $accent = $this->color($image, 49, 208, 170);

        $this->gd(
            'imagefilledellipse',
            $image,
            34,
            28,
            24,
            24,
            $accent
        );

        $this->drawSingleLine(
            $image,
            'WP',
            24,
            34,
            10,
            $white,
            true,
            $caps
        );
        $this->drawSingleLine(
            $image,
            'WP SHOP',
            52,
            34,
            13,
            $white,
            true,
            $caps
        );
    }

    /**
     * @param mixed $image
     * @param array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * } $caps
     */
    private function drawProductCopy(
        mixed $image,
        string $title,
        string $subtitle,
        string $productType,
        array $caps
    ): void {
        $white = $this->color($image, 255, 255, 255);
        $muted = $this->color($image, 211, 218, 235);
        $accent = $this->color($image, 49, 208, 170);
        $pill = $this->alphaColor($image, 49, 208, 170, 54);

        $title = $title !== '' ? $title : 'Premium WordPress Product';
        $titleSize = $this->titleSize($title);
        $titleLines = $this->wrapText(
            $title,
            300,
            $titleSize,
            true,
            $caps,
            2
        );

        $y = 82;

        foreach ($titleLines as $line) {
            $this->drawSingleLine(
                $image,
                $line,
                24,
                $y,
                $titleSize,
                $white,
                true,
                $caps
            );
            $y += $titleSize + 8;
        }

        $subtitleY = max(158, $y + 7);
        $subtitle = $subtitle !== ''
            ? $subtitle
            : $this->genericSubtitle($productType);
        $subtitleLines = $this->wrapText(
            $subtitle,
            300,
            16,
            false,
            $caps,
            2
        );

        foreach ($subtitleLines as $line) {
            $this->drawSingleLine(
                $image,
                $line,
                24,
                $subtitleY,
                16,
                $muted,
                false,
                $caps
            );
            $subtitleY += 22;
        }

        $pillText = $this->pillText($productType);
        $pillWidth = min(
            250,
            max(145, 30 + $this->measureText(
                $pillText,
                11,
                true,
                $caps
            ))
        );

        $this->roundedRect(
            $image,
            24,
            247,
            24 + $pillWidth,
            278,
            15,
            $pill
        );
        $this->drawSingleLine(
            $image,
            $pillText,
            38,
            268,
            11,
            $accent,
            true,
            $caps
        );
    }

    /**
     * @param mixed $image
     * @param array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * } $caps
     */
    private function drawProductCard(
        mixed $image,
        string $title,
        string $productType,
        array $caps
    ): void {
        $shadow = $this->alphaColor($image, 0, 0, 0, 92);
        $card = $this->color($image, 249, 251, 255);
        $line = $this->color($image, 220, 226, 239);
        $ink = $this->color($image, 24, 34, 58);
        $muted = $this->color($image, 108, 119, 145);
        $accent = $this->color($image, 49, 208, 170);
        $violet = $this->color($image, 124, 58, 237);

        $this->roundedRect(
            $image,
            371,
            49,
            572,
            267,
            16,
            $shadow
        );
        $this->roundedRect(
            $image,
            365,
            43,
            566,
            261,
            16,
            $card
        );

        $this->roundedRect(
            $image,
            382,
            61,
            438,
            117,
            12,
            $violet
        );

        $monogram = $this->monogram($title);
        $monoWidth = $this->measureText(
            $monogram,
            24,
            true,
            $caps
        );
        $this->drawSingleLine(
            $image,
            $monogram,
            (int) round(410 - $monoWidth / 2),
            98,
            24,
            $this->color($image, 255, 255, 255),
            true,
            $caps
        );

        $label = strtolower($productType) === 'theme'
            ? 'WordPress theme'
            : 'WordPress plugin';

        $this->drawSingleLine(
            $image,
            $label,
            449,
            73,
            10,
            $muted,
            false,
            $caps
        );
        $this->drawSingleLine(
            $image,
            'Premium',
            449,
            97,
            14,
            $ink,
            true,
            $caps
        );

        $this->gd(
            'imageline',
            $image,
            382,
            132,
            548,
            132,
            $line
        );

        $rowY = 157;

        foreach (
            [
                'Clean product package',
                'Unified WP Shop cover',
                'Ready for catalog',
            ]
            as $row
        ) {
            $this->gd(
                'imagefilledellipse',
                $image,
                394,
                $rowY - 4,
                12,
                12,
                $accent
            );
            $this->drawSingleLine(
                $image,
                '+',
                390,
                $rowY,
                9,
                $this->color($image, 255, 255, 255),
                true,
                $caps
            );
            $this->drawSingleLine(
                $image,
                $row,
                410,
                $rowY,
                10,
                $ink,
                false,
                $caps
            );
            $rowY += 31;
        }

        $this->roundedRect(
            $image,
            382,
            229,
            548,
            248,
            9,
            $this->color($image, 233, 238, 248)
        );
        $this->roundedRect(
            $image,
            382,
            229,
            478,
            248,
            9,
            $accent
        );
    }

    private function svgTitleSize(string $title): int
    {
        $length = mb_strlen($title, 'UTF-8');

        if ($length <= 18) {
            return 34;
        }

        if ($length <= 31) {
            return 30;
        }

        if ($length <= 44) {
            return 27;
        }

        return 24;
    }

    private function titleCharacterLimit(string $title): int
    {
        return mb_strlen($title, 'UTF-8') <= 30
            ? 22
            : 28;
    }

    /**
     * @return list<string>
     */
    private function wrapByCharacters(
        string $text,
        int $limit,
        int $maxLines
    ): array {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $line = '';
        $consumed = 0;

        foreach ($words as $word) {
            $candidate = $line === ''
                ? (string) $word
                : $line . ' ' . $word;

            if (
                $line !== ''
                && mb_strlen($candidate, 'UTF-8') > $limit
            ) {
                $lines[] = $line;
                $consumed += count(
                    preg_split('/\s+/u', $line) ?: []
                );
                $line = (string) $word;

                if (count($lines) >= $maxLines - 1) {
                    break;
                }

                continue;
            }

            $line = $candidate;
        }

        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
            $consumed += count(
                preg_split('/\s+/u', $line) ?: []
            );
        }

        if ($consumed < count($words) && $lines !== []) {
            $last = count($lines) - 1;
            $lines[$last] = rtrim(
                mb_substr(
                    $lines[$last],
                    0,
                    max(1, $limit - 1),
                    'UTF-8'
                )
            ) . '…';
        }

        return $lines;
    }

    private function titleSize(string $title): int
    {
        $length = mb_strlen($title, 'UTF-8');

        if ($length <= 18) {
            return 34;
        }

        if ($length <= 30) {
            return 30;
        }

        if ($length <= 42) {
            return 27;
        }

        return 24;
    }

    private function genericSubtitle(string $productType): string
    {
        return strtolower($productType) === 'theme'
            ? 'Premium WordPress theme for modern websites'
            : 'Premium WordPress plugin for your website';
    }

    private function pillText(string $productType): string
    {
        return strtolower($productType) === 'theme'
            ? 'PREMIUM WORDPRESS THEME'
            : 'PREMIUM WORDPRESS PLUGIN';
    }

    private function monogram(string $title): string
    {
        $clean = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            trim($title)
        );
        $clean = is_string($clean) ? trim($clean) : '';

        if ($clean === '') {
            return 'WP';
        }

        $words = preg_split('/\s+/u', $clean) ?: [];

        if (count($words) >= 2) {
            return mb_strtoupper(
                mb_substr((string) $words[0], 0, 1, 'UTF-8')
                . mb_substr((string) $words[1], 0, 1, 'UTF-8'),
                'UTF-8'
            );
        }

        return mb_strtoupper(
            mb_substr($clean, 0, 2, 'UTF-8'),
            'UTF-8'
        );
    }

    /**
     * @param array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * } $caps
     * @return list<string>
     */
    private function wrapText(
        string $text,
        int $maxWidth,
        int $size,
        bool $bold,
        array $caps,
        int $maxLines
    ): array {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        if ($words === []) {
            return [];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === ''
                ? (string) $word
                : $current . ' ' . $word;

            if (
                $current !== ''
                && $this->measureText(
                    $candidate,
                    $size,
                    $bold,
                    $caps
                ) > $maxWidth
            ) {
                $lines[] = $current;
                $current = (string) $word;

                if (count($lines) >= $maxLines - 1) {
                    break;
                }

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        $usedWords = preg_split(
            '/\s+/u',
            implode(' ', $lines)
        ) ?: [];

        if (count($usedWords) < count($words) && $lines !== []) {
            $last = count($lines) - 1;
            $lines[$last] = $this->ellipsize(
                $lines[$last],
                $maxWidth,
                $size,
                $bold,
                $caps
            );
        }

        return $lines;
    }

    /**
     * @param array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * } $caps
     */
    private function ellipsize(
        string $text,
        int $maxWidth,
        int $size,
        bool $bold,
        array $caps
    ): string {
        $suffix = '…';
        $candidate = trim($text);

        while (
            $candidate !== ''
            && $this->measureText(
                $candidate . $suffix,
                $size,
                $bold,
                $caps
            ) > $maxWidth
        ) {
            $candidate = mb_substr(
                $candidate,
                0,
                -1,
                'UTF-8'
            );
        }

        return rtrim($candidate) . $suffix;
    }

    /**
     * @param mixed $image
     * @param array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * } $caps
     */
    private function drawSingleLine(
        mixed $image,
        string $text,
        int $x,
        int $baseline,
        int $size,
        int $color,
        bool $bold,
        array $caps
    ): void {
        if ($caps['ttf']) {
            $font = $bold
                ? $caps['boldFont']
                : $caps['regularFont'];

            $this->gd(
                'imagettftext',
                $image,
                $size,
                0,
                $x,
                $baseline,
                $color,
                $font,
                $text
            );

            return;
        }

        $fallback = str_replace(
            ['–', '—', '…'],
            ['-', '-', '...'],
            $text
        );
        $this->gd(
            'imagestring',
            $image,
            5,
            $x,
            max(0, $baseline - 14),
            $fallback,
            $color
        );
    }

    /**
     * @param array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * } $caps
     */
    private function measureText(
        string $text,
        int $size,
        bool $bold,
        array $caps
    ): int {
        if ($caps['ttf']) {
            $font = $bold
                ? $caps['boldFont']
                : $caps['regularFont'];
            $box = $this->gd(
                'imagettfbbox',
                $size,
                0,
                $font,
                $text
            );

            if (is_array($box) && isset($box[0], $box[2])) {
                return abs((int) $box[2] - (int) $box[0]);
            }
        }

        return max(
            1,
            mb_strlen($text, 'UTF-8') * 9
        );
    }

    /**
     * @param mixed $image
     */
    private function roundedRect(
        mixed $image,
        int $x1,
        int $y1,
        int $x2,
        int $y2,
        int $radius,
        int $color
    ): void {
        $radius = max(1, min(
            $radius,
            (int) floor(($x2 - $x1) / 2),
            (int) floor(($y2 - $y1) / 2)
        ));

        $this->gd(
            'imagefilledrectangle',
            $image,
            $x1 + $radius,
            $y1,
            $x2 - $radius,
            $y2,
            $color
        );
        $this->gd(
            'imagefilledrectangle',
            $image,
            $x1,
            $y1 + $radius,
            $x2,
            $y2 - $radius,
            $color
        );

        foreach (
            [
                [$x1 + $radius, $y1 + $radius],
                [$x2 - $radius, $y1 + $radius],
                [$x1 + $radius, $y2 - $radius],
                [$x2 - $radius, $y2 - $radius],
            ]
            as [$cx, $cy]
        ) {
            $this->gd(
                'imagefilledellipse',
                $image,
                $cx,
                $cy,
                $radius * 2,
                $radius * 2,
                $color
            );
        }
    }

    /**
     * @param mixed $image
     */
    private function color(
        mixed $image,
        int $red,
        int $green,
        int $blue
    ): int {
        return (int) $this->gd(
            'imagecolorallocate',
            $image,
            $red,
            $green,
            $blue
        );
    }

    /**
     * @param mixed $image
     */
    private function alphaColor(
        mixed $image,
        int $red,
        int $green,
        int $blue,
        int $alpha
    ): int {
        return (int) $this->gd(
            'imagecolorallocatealpha',
            $image,
            $red,
            $green,
            $blue,
            max(0, min(127, $alpha))
        );
    }

    /**
     * @param list<string> $fonts
     */
    private function firstFont(array $fonts): string
    {
        foreach ($fonts as $font) {
            if (is_file($font) && is_readable($font)) {
                return $font;
            }
        }

        return '';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );
    }

    /**
     * @param callable-string $function
     */
    private function gd(
        string $function,
        mixed ...$arguments
    ): mixed {
        return $function(...$arguments);
    }
}
