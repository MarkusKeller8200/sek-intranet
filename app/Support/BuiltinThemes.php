<?php

namespace App\Support;

/**
 * Eingebaute Farbkonzepte (#216, White-Labeling): CI von EduKSX und MarKusSXCH nach dem CI-Manual v1.0
 * (Repo markussxch, docs/brand/MarKusSXCH_CI_Manual_v1.0.md). Markenfarben: Violett #7952B3 / #B59AFF,
 * Orange #FF8A00, Blau #007EBB / #32B8F2, Neutral #222222 / #F2F5F8, Fläche dunkel #151B24.
 *
 * Für Bedienelemente gelten kontrastgeprüfte Abstufungen (WCAG AA, CI-Manual Abschnitt 2 und 5): EduKSX-Orange
 * hell #A85400 (5,3:1 auf Weiss), dunkel das Markenorange auf #151B24 (7,3:1); MarKusSXCH-Violett hell #7952B3
 * (5,7:1), dunkel #B59AFF (7,4:1). Statusfarben (Fehler, Warnung, Erfolg) bleiben wie im Standard.
 * Nicht änderbar; als Vorlage für ein eigenes Theme kopierbar.
 */
final class BuiltinThemes
{
    /** Gemeinsame Neutral- und Flächenfarben beider Marken. */
    private const NEUTRAL_LIGHT = [
        'foreground' => '#222222', 'card-foreground' => '#222222', 'popover-foreground' => '#222222',
        'muted' => '#f2f5f8', 'muted-foreground' => '#64748b', 'border' => '#e2e8f0', 'input' => '#cbd5e1',
        'sidebar' => '#f8fafc', 'sidebar-foreground' => '#222222', 'sidebar-border' => '#e2e8f0',
        'brand' => '#007ebb', 'info' => '#00699e', 'info-soft' => '#e6f3fa',
        'term-underline' => '#007ebb', 'term-underline-hover' => '#00699e',
        'chart-1' => '#ff8a00', 'chart-2' => '#007ebb', 'chart-3' => '#7952b3', 'chart-4' => '#0d9488', 'chart-5' => '#e11d48',
    ];

    private const NEUTRAL_DARK = [
        'background' => '#151b24', 'foreground' => '#f2f5f8', 'card' => '#1b2230', 'card-foreground' => '#f2f5f8',
        'popover' => '#1b2230', 'popover-foreground' => '#f2f5f8', 'muted' => '#222b38', 'muted-foreground' => '#aab6c7',
        'border' => '#3a4556', 'input' => '#3a4556', 'sidebar' => '#111620', 'sidebar-foreground' => '#f2f5f8',
        'sidebar-border' => '#3a4556', 'brand' => '#32b8f2', 'info' => '#32b8f2', 'info-soft' => '#102a3a',
        'term-underline' => '#32b8f2', 'term-underline-hover' => '#b9e6fb',
        'chart-1' => '#ff8a00', 'chart-2' => '#32b8f2', 'chart-3' => '#b59aff', 'chart-4' => '#2dd4bf', 'chart-5' => '#fb7185',
    ];

    /**
     * @return array<string, array{name: string, description: string, light: array<string, string>, dark: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            'eduksx' => [
                'name' => 'EduKSX',
                'description' => 'CI EduKSX (Bildung/Services): Orange als Hauptakzent, Blau und Violett ergänzend.',
                'light' => array_merge(self::NEUTRAL_LIGHT, [
                    'primary' => '#a85400', 'primary-foreground' => '#ffffff', 'ring' => '#a85400',
                    'secondary' => '#fff4e6', 'secondary-foreground' => '#5c2e00', 'accent' => '#fff1e0', 'accent-foreground' => '#5c2e00',
                    'sidebar-primary' => '#a85400', 'sidebar-primary-foreground' => '#ffffff', 'sidebar-accent' => '#fff1e0',
                    'sidebar-accent-foreground' => '#5c2e00', 'sidebar-ring' => '#a85400',
                ]),
                'dark' => array_merge(self::NEUTRAL_DARK, [
                    'primary' => '#ff8a00', 'primary-foreground' => '#151b24', 'ring' => '#ff8a00',
                    'secondary' => '#3a2610', 'secondary-foreground' => '#ffe2bf', 'accent' => '#3a2610', 'accent-foreground' => '#ffe2bf',
                    'sidebar-primary' => '#ff8a00', 'sidebar-primary-foreground' => '#151b24', 'sidebar-accent' => '#3a2610',
                    'sidebar-accent-foreground' => '#ffe2bf', 'sidebar-ring' => '#ff8a00',
                ]),
            ],
            'markussx' => [
                'name' => 'MarKusSXCH',
                'description' => 'CI MarKusSXCH (Dachmarke): Violett als Hauptakzent, Blau und Orange ergänzend.',
                'light' => array_merge(self::NEUTRAL_LIGHT, [
                    'primary' => '#7952b3', 'primary-foreground' => '#ffffff', 'ring' => '#7952b3',
                    'secondary' => '#f3eefb', 'secondary-foreground' => '#3d2766', 'accent' => '#efe8fa', 'accent-foreground' => '#3d2766',
                    'sidebar-primary' => '#7952b3', 'sidebar-primary-foreground' => '#ffffff', 'sidebar-accent' => '#efe8fa',
                    'sidebar-accent-foreground' => '#3d2766', 'sidebar-ring' => '#7952b3',
                    'chart-1' => '#7952b3', 'chart-3' => '#ff8a00',
                ]),
                'dark' => array_merge(self::NEUTRAL_DARK, [
                    'primary' => '#b59aff', 'primary-foreground' => '#151b24', 'ring' => '#b59aff',
                    'secondary' => '#2a2140', 'secondary-foreground' => '#e5dcff', 'accent' => '#2a2140', 'accent-foreground' => '#e5dcff',
                    'sidebar-primary' => '#b59aff', 'sidebar-primary-foreground' => '#151b24', 'sidebar-accent' => '#2a2140',
                    'sidebar-accent-foreground' => '#e5dcff', 'sidebar-ring' => '#b59aff',
                    'chart-1' => '#b59aff', 'chart-3' => '#ff8a00',
                ]),
            ],
        ];
    }
}
