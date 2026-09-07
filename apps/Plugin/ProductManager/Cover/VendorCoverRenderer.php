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
     * @return array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string}
     */
    public function capabilities(): array
    {
        $bold = $this->firstFont(self::BOLD_FONTS);
        $regular = $this->firstFont(self::REGULAR_FONTS);

        $gd = extension_loaded('gd');
        $info = $gd ? gd_info() : [];

        return [
            'gd' => $gd,
            'webp' => $gd && (bool) ($info['WebP Support'] ?? false),
            'ttf' => $gd
                && (bool) ($info['FreeType Support'] ?? false)
                && $bold !== ''
                && $regular !== '',
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

        if (! $capabilities['gd']) {
            throw new RuntimeException(
                'GD image functions are unavailable on this server.'
            );
        }

        if (! $capabilities['webp']) {
            throw new RuntimeException(
                'GD WebP support is unavailable on this server.'
            );
        }

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
                trim($title),
                trim($subtitle),
                trim($productType),
                $capabilities
            );
            $this->drawProductCard(
                $image,
                trim($title),
                trim($productType),
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
     * @param array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string} $caps
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
     * @param array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string} $caps
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
     * @param array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string} $caps
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
                '✓',
                389,
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
     * @param array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string} $caps
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
     * @param array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string} $caps
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
     * @param array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string} $caps
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
            ['–', '—', '…', '✓'],
            ['-', '-', '...', '+'],
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
     * @param array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string} $caps
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

    private function gd(
        string $function,
        mixed ...$arguments
    ): mixed {
        return $function(...$arguments);
    }
}
