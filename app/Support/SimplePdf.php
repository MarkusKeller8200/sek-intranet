<?php

namespace App\Support;

/**
 * Sehr einfaches PDF (A4 hoch, Helvetica) ohne Bibliothek: Text, Linien, Rechtecke, mehrere Seiten.
 * Für Dokumente, die der Server erzeugen muss (z. B. Monatsrapport zur Unterschrift bei DeepSign).
 * Koordinaten in Punkten (1/72 Zoll) ab der linken oberen Ecke. Umlaute über WinAnsi (Windows-1252).
 */
final class SimplePdf
{
    public const WIDTH = 595.28;

    public const HEIGHT = 841.89;

    /** @var list<string> Inhalt je Seite */
    private array $pages = [];

    public function __construct(private readonly string $title = '')
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        $this->pages[] = '';
    }

    /** Aktuelle Seite (ab 1). */
    public function page(): int
    {
        return count($this->pages);
    }

    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $this->draw(sprintf('BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', $font, $size, $x, self::HEIGHT - $y, self::escape($text)));
    }

    /** Text rechtsbündig bis $right. */
    public function textRight(float $right, float $y, string $text, float $size = 10, bool $bold = false): void
    {
        $this->text($right - self::width($text, $size, $bold), $y, $text, $size, $bold);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): void
    {
        $this->draw(sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $width, $x1, self::HEIGHT - $y1, $x2, self::HEIGHT - $y2));
    }

    public function rect(float $x, float $y, float $w, float $h, float $width = 0.5): void
    {
        $this->draw(sprintf('%.2F w %.2F %.2F %.2F %.2F re S', $width, $x, self::HEIGHT - $y - $h, $w, $h));
    }

    /**
     * Ungefähre Textbreite (Helvetica): genug, um Spalten zu kürzen und rechts auszurichten.
     */
    public static function width(string $text, float $size, bool $bold = false): float
    {
        $chars = mb_strlen($text);
        $narrow = preg_match_all('/[iljtf.,:;|!\' ]/u', $text);
        $wide = preg_match_all('/[MWmw@%]/u', $text);

        return ($chars * 0.52 - (int) $narrow * 0.25 + (int) $wide * 0.3) * $size * ($bold ? 1.06 : 1);
    }

    /** Text auf eine Breite kürzen («…»). */
    public static function fit(string $text, float $width, float $size, bool $bold = false): string
    {
        if (self::width($text, $size, $bold) <= $width) {
            return $text;
        }
        while ($text !== '' && self::width($text.'…', $size, $bold) > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    public function output(): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $next = 5;
        $pageObjects = [];
        foreach ($this->pages as $content) {
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = "{$pageId} 0 R";
            $pageObjects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::WIDTH, self::HEIGHT, $contentId);
            $pageObjects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects += $pageObjects;
        $infoId = $next;
        $objects[$infoId] = '<< /Producer (SekBuelach Intranet) /Title ('.self::escape($this->title).') /CreationDate (D:'.gmdate('YmdHis').'Z) >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".($infoId + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= $infoId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= 'trailer'."\n<< /Size ".($infoId + 1)." /Root 1 0 R /Info {$infoId} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    private function draw(string $operation): void
    {
        $this->pages[count($this->pages) - 1] .= $operation."\n";
    }

    private static function escape(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], is_string($converted) ? $converted : $text);
    }
}
