<?php

namespace App\Support;

/**
 * Startinhalt des Glossars (#59): Recherche «Abkürzungs- und Begriffsverzeichnis der Zürcher Volksschule» vom
 * 03.11.2026, Quelle Volksschulamt (VSA). Jede Zeile der Recherche ist ein eigener Eintrag – Doppelte werden erst
 * danach mit «Doppelte Abkürzungen oder Begriffe» bereinigt (#62).
 */
final class GlossarySeed
{
    public const VSA_URL = 'https://www.zh.ch/de/bildung/schulen/volksschule.html';

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}> id, Abkürzung, Bezeichnung, Beschreibung, Label
     */
    public static function kategorien(): array
    {
        return [
            ['beh', 'BEH', 'Behörden & Schulorganisation', 'Bildungsdirektion, Behörden und Schulorganisation', 'Behörden'],
            ['unt', 'UNT', 'Unterricht & Fachbereiche', 'Unterricht, Lehrplan und Fachbereiche', 'Unterricht'],
            ['sop', 'SOP', 'Sonderpädagogik', 'Sonderpädagogik und besondere Förderung', 'Sonderpädagogik'],
            ['per', 'PER', 'Personal', 'Schulpersonal, Berufsauftrag und Personalführung', 'Personal'],
            ['qua', 'QUA', 'Schulqualität', 'Schulqualität und Schulentwicklung', 'Qualität'],
            ['rec', 'REC', 'Recht', 'Rechtliche Grundlagen und Erlasse', 'Recht'],
            ['kis', 'KIS', 'Kindesschutz & Fachstellen', 'Schulsozialarbeit, Kindesschutz und Fachstellen', 'Kindesschutz'],
            ['ver', 'VER', 'Verbände', 'Verbände und Berufsorganisationen', 'Verbände'],
            ['sta', 'STA', 'Statistik & Stufen', 'Bildungsstatistik, Schulstufen und Übergänge', 'Statistik'],
            ['dsi', 'DSI', 'Datenschutz & ICT', 'Datenschutz, Informationssicherheit und ICT', 'Datenschutz'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}> id, Abkürzung, Bezeichnung, URL, Label
     */
    public static function herkunft(): array
    {
        return [
            ['vsa', 'VSA', 'Volksschulamt des Kantons Zürich', self::VSA_URL, 'VSA'],
            ['bi', 'BI', 'Bildungsdirektion des Kantons Zürich', '', 'BI'],
            ['kanton', 'KT', 'Kanton Zürich', 'https://www.zh.ch', 'Kanton'],
            ['bund', 'CH', 'Bund', 'https://www.admin.ch', 'Bund'],
            ['dsb', 'DSB', 'Datenschutzbeauftragte des Kantons Zürich', '', 'DSB'],
            ['gemeinde', 'GEM', 'Gemeindeamt', '', 'Gemeinde'],
            ['schule', 'SCH', 'Schule intern', '', 'Schule'],
            ['gebraeuchlich', 'GEB', 'gebräuchlich (nicht offiziell)', '', 'gebräuchlich'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: list<string>, 3: string, 4: string, 5: string}> Kategorie, Abkürzung, Aliase, Bezeichnung, Status, Notiz
     */
    public static function eintraege(): array
    {
        return [
            ['beh', 'VSA', [], 'Volksschulamt', 'aktuell', ''],
            ['beh', 'BI', [], 'Bildungsdirektion des Kantons Zürich', 'aktuell', ''],
            ['beh', 'BR', [], 'Bildungsrat', 'aktuell', ''],
            ['beh', 'AJB', [], 'Amt für Jugend- und Berufsberatung', 'aktuell', ''],
            ['beh', 'MBA', [], 'Mittelschul- und Berufsbildungsamt', 'aktuell', ''],
            ['beh', 'PHZH', [], 'Pädagogische Hochschule Zürich', 'aktuell', ''],
            ['beh', 'HfH', [], 'Interkantonale Hochschule für Heilpädagogik', 'aktuell', ''],
            ['beh', 'EDK', [], 'Schweizerische Konferenz der kantonalen Erziehungsdirektorinnen und -direktoren', 'aktuell', ''],
            ['beh', 'RR', [], 'Regierungsrat', 'aktuell', ''],
            ['beh', 'RRB', [], 'Regierungsratsbeschluss', 'aktuell', ''],
            ['beh', 'SL', [], 'Schulleitung', 'aktuell', ''],
            ['beh', 'SP', [], 'Schulpflege', 'aktuell', ''],
            ['beh', 'SV', [], 'Schulverwaltung', 'zu_pruefen', 'Laut Recherche gebräuchliche Kurzform, nicht offiziell dokumentiert.'],
            ['beh', 'LP', [], 'Lehrperson', 'aktuell', ''],
            ['beh', 'SuS', [], 'Schülerinnen und Schüler', 'aktuell', ''],
            ['beh', 'RKLP', [], 'Regelklassenlehrperson', 'aktuell', ''],
            ['beh', 'SHP', [], 'Schulische Heilpädagogik bzw. schulische Heilpädagogin / schulischer Heilpädagoge', 'aktuell', ''],
            ['beh', 'LKV', [], 'Lehrpersonenkonferenz der Volksschule', 'aktuell', ''],
            ['beh', 'VZE', [], 'Vollzeiteinheiten', 'aktuell', ''],
            ['beh', 'SJ', [], 'Schuljahr', 'aktuell', ''],
            ['beh', 'WL', [], 'Wochenlektionen', 'aktuell', ''],
            ['unt', 'LP21', [], 'Lehrplan 21', 'aktuell', ''],
            ['unt', 'D', [], 'Deutsch', 'aktuell', ''],
            ['unt', 'E', [], 'Englisch', 'aktuell', ''],
            ['unt', 'F', [], 'Französisch', 'aktuell', ''],
            ['unt', 'I', [], 'Italienisch', 'aktuell', ''],
            ['unt', 'MA', [], 'Mathematik', 'aktuell', ''],
            ['unt', 'NMG', [], 'Natur, Mensch, Gesellschaft', 'aktuell', ''],
            ['unt', 'NT', [], 'Natur und Technik', 'aktuell', ''],
            ['unt', 'RZG', [], 'Räume, Zeiten, Gesellschaften', 'aktuell', ''],
            ['unt', 'WAH', [], 'Wirtschaft, Arbeit, Haushalt', 'aktuell', ''],
            ['unt', 'RKE', [], 'Religionen, Kulturen, Ethik', 'aktuell', ''],
            ['unt', 'BG', [], 'Bildnerisches Gestalten', 'aktuell', ''],
            ['unt', 'TTG', [], 'Textiles und Technisches Gestalten', 'aktuell', ''],
            ['unt', 'MU', [], 'Musik', 'aktuell', ''],
            ['unt', 'BS', [], 'Bewegung und Sport', 'aktuell', ''],
            ['unt', 'MI', [], 'Medien und Informatik', 'aktuell', ''],
            ['unt', 'BO', [], 'Berufliche Orientierung', 'aktuell', ''],
            ['unt', 'PU', [], 'Projektunterricht', 'aktuell', ''],
            ['unt', 'MGA', [], 'Musikalische Grundausbildung', 'aktuell', ''],
            ['unt', 'WF', [], 'Wahlfach', 'aktuell', ''],
            ['unt', 'HSK', [], 'Heimatliche Sprache und Kultur', 'aktuell', ''],
            ['sop', 'IF', [], 'Integrative Förderung', 'aktuell', ''],
            ['sop', 'DaZ', [], 'Deutsch als Zweitsprache', 'aktuell', ''],
            ['sop', 'ISR', [], 'Integrierte Sonderschulung in der Verantwortung der Regelschule', 'aktuell', ''],
            ['sop', 'ISS', [], 'Integrierte Sonderschulung in der Verantwortung der Sonderschule', 'aktuell', ''],
            ['sop', 'SSG', [], 'Schulisches Standortgespräch', 'aktuell', ''],
            ['sop', 'SPD', [], 'Schulpsychologischer Dienst', 'aktuell', ''],
            ['sop', 'SAV', [], 'Standardisiertes Abklärungsverfahren', 'aktuell', ''],
            ['sop', 'ICF', [], 'Internationale Klassifikation der Funktionsfähigkeit, Behinderung und Gesundheit', 'aktuell', ''],
            ['sop', 'ICF-CY', [], 'Internationale Klassifikation der Funktionsfähigkeit, Behinderung und Gesundheit bei Kindern und Jugendlichen', 'aktuell', ''],
            ['sop', 'SHP', [], 'Schulische Heilpädagogik', 'aktuell', ''],
            ['sop', 'QUIMS', [], 'Qualität in multikulturellen Schulen', 'aktuell', ''],
            ['per', 'nBA', [], 'Neu definierter Berufsauftrag', 'aktuell', ''],
            ['per', 'MAG', [], 'Mitarbeitendengespräch', 'aktuell', ''],
            ['per', 'MAB', [], 'Mitarbeiterbeurteilung', 'aktuell', ''],
            ['per', 'KLP', [], 'Klassenlehrperson', 'zu_pruefen', 'Laut Recherche gebräuchliche Kurzform, nicht offiziell dokumentiert.'],
            ['per', 'RKLP', [], 'Regelklassenlehrperson', 'aktuell', ''],
            ['per', 'SHP', [], 'Schulische Heilpädagogin / schulischer Heilpädagoge', 'aktuell', ''],
            ['per', 'SL', [], 'Schulleitung', 'aktuell', ''],
            ['per', 'LP', [], 'Lehrperson', 'aktuell', ''],
            ['per', 'LKV', [], 'Lehrpersonenkonferenz der Volksschule', 'aktuell', ''],
            ['per', 'VZE', [], 'Vollzeiteinheiten', 'aktuell', ''],
            ['per', 'Puls-ZH', [], 'Personal- und Lohnadministrationssystem des Kantons Zürich', 'aktuell', ''],
            ['per', 'SAP-HCM', [], 'SAP Human Capital Management, System für die Personalwirtschaft', 'aktuell', ''],
            ['per', 'Q-Gruppen', [], 'Arbeits- und Qualitätsgruppen', 'aktuell', ''],
            ['qua', 'FSB', [], 'Fachstelle für Schulbeurteilung', 'aktuell', ''],
            ['qua', 'QM', [], 'Qualitätsmanagement', 'zu_pruefen', 'Laut Recherche gebräuchliche Kurzform, nicht offiziell dokumentiert.'],
            ['qua', 'QS', [], 'Qualitätssicherung', 'zu_pruefen', 'Laut Recherche gebräuchliche Kurzform, nicht offiziell dokumentiert.'],
            ['qua', 'QE', [], 'Qualitätsentwicklung', 'zu_pruefen', 'Laut Recherche gebräuchliche Kurzform, nicht offiziell dokumentiert.'],
            ['qua', 'MAB', [], 'Mitarbeiterbeurteilung', 'aktuell', ''],
            ['qua', 'Q-Gruppen', [], 'Arbeits- und Qualitätsgruppen', 'aktuell', ''],
            ['qua', 'QUIMS', [], 'Qualität in multikulturellen Schulen', 'aktuell', ''],
            ['qua', 'argev', [], 'Interkantonale Arbeitsgemeinschaft Externe Evaluation von Schulen', 'aktuell', ''],
            ['qua', 'INSULA', [], 'Bezeichnung eines Instrumentariums zur Erfassung von Unterrichtsqualität', 'aktuell', ''],
            ['qua', 'IFES', [], 'Institut für Externe Schulevaluation (Sekundarstufe II)', 'aktuell', ''],
            ['rec', 'VSG', [], 'Volksschulgesetz', 'aktuell', ''],
            ['rec', 'VSV', [], 'Volksschulverordnung', 'aktuell', ''],
            ['rec', 'VSM', [], 'Verordnung über die sonderpädagogischen Massnahmen', 'aktuell', ''],
            ['rec', 'LPG', [], 'Lehrpersonalgesetz', 'aktuell', ''],
            ['rec', 'LPVO', [], 'Lehrpersonalverordnung', 'aktuell', ''],
            ['rec', 'IDG', [], 'Gesetz über die Information und den Datenschutz des Kantons Zürich', 'aktuell', ''],
            ['rec', 'IDV', [], 'Verordnung über die Information und den Datenschutz', 'aktuell', ''],
            ['rec', 'ZGB', [], 'Schweizerisches Zivilgesetzbuch', 'aktuell', ''],
            ['rec', 'StGB', [], 'Schweizerisches Strafgesetzbuch', 'aktuell', ''],
            ['rec', 'GesG', [], 'Gesundheitsgesetz des Kantons Zürich', 'aktuell', ''],
            ['rec', 'LS', [], 'Loseblattsammlung bzw. Zürcher Gesetzessammlung', 'aktuell', ''],
            ['rec', 'SR', [], 'Systematische Rechtssammlung des Bundes', 'aktuell', ''],
            ['rec', 'RRB', [], 'Regierungsratsbeschluss', 'aktuell', ''],
            ['rec', 'Abs.', [], 'Absatz', 'aktuell', ''],
            ['rec', 'Art.', [], 'Artikel', 'aktuell', ''],
            ['rec', '§', [], 'Paragraf', 'aktuell', 'Gesetzeszeichen, keine eigentliche Abkürzung.'],
            ['kis', 'SSA', [], 'Schulsozialarbeit', 'aktuell', ''],
            ['kis', 'SPD', [], 'Schulpsychologischer Dienst', 'aktuell', ''],
            ['kis', 'KESB', [], 'Kindes- und Erwachsenenschutzbehörde', 'aktuell', ''],
            ['kis', 'AJB', [], 'Amt für Jugend- und Berufsberatung', 'aktuell', ''],
            ['kis', 'kjz', [], 'Kinder- und Jugendhilfezentrum', 'aktuell', ''],
            ['kis', 'RRD', [], 'Regionale Rechtsdienste des AJB', 'aktuell', ''],
            ['kis', 'KPV', [], 'KESB-Präsidien-Vereinigung', 'aktuell', ''],
            ['kis', 'BI', [], 'Bildungsdirektion des Kantons Zürich', 'aktuell', ''],
            ['kis', 'JI', [], 'Direktion der Justiz und des Innern des Kantons Zürich', 'aktuell', ''],
            ['kis', 'MBA', [], 'Mittelschul- und Berufsbildungsamt', 'aktuell', ''],
            ['ver', 'ZLV', [], 'Zürcher Lehrerinnen- und Lehrerverband', 'aktuell', ''],
            ['ver', 'VSLZH', [], 'Verband der Schulleiterinnen und Schulleiter des Kantons Zürich', 'aktuell', ''],
            ['ver', 'VPZS', [], 'Vereinigung des Personals Zürcherischer Schulverwaltungen', 'aktuell', ''],
            ['ver', 'VZS', [], 'Verband Zürcher Schulpräsidien', 'aktuell', ''],
            ['ver', 'VPOD', [], 'Schweizerischer Verband des Personals öffentlicher Dienste', 'aktuell', ''],
            ['ver', 'SekZH', [], 'Verein Sekundarlehrkräfte des Kantons Zürich', 'aktuell', ''],
            ['ver', 'VZL DaZ', [], 'Verein Zürcher Lehrpersonen Deutsch als Zweitsprache', 'aktuell', ''],
            ['ver', 'zbl', [], 'Zürcher Berufsverband der Logopädinnen und Logopäden', 'aktuell', ''],
            ['sta', 'Bista', [], 'Bildungsstatistik des Kantons Zürich', 'aktuell', ''],
            ['sta', 'BFS', [], 'Bundesamt für Statistik', 'aktuell', ''],
            ['sta', 'BRB', [], 'Bildungsratsbeschluss', 'aktuell', ''],
            ['sta', 'ÜGK', [], 'Überprüfung des Erreichens der Grundkompetenzen', 'aktuell', ''],
            ['sta', 'PISA', [], 'Programme for International Student Assessment', 'aktuell', ''],
            ['sta', 'US', [], 'Unterstufe', 'aktuell', ''],
            ['sta', 'MS', [], 'Mittelstufe', 'aktuell', ''],
            ['sta', 'OS', [], 'Oberstufe', 'aktuell', ''],
            ['sta', 'Sek I', [], 'Sekundarstufe I', 'aktuell', ''],
            ['sta', 'Sek II', [], 'Sekundarstufe II', 'aktuell', ''],
            ['sta', 'Sek A', [], 'Sekundarschule, Abteilung A', 'aktuell', ''],
            ['sta', 'Sek B', [], 'Sekundarschule, Abteilung B', 'aktuell', ''],
            ['sta', 'Sek C', [], 'Sekundarschule, Abteilung C', 'aktuell', ''],
            ['sta', 'EBA', [], 'Eidgenössisches Berufsattest', 'aktuell', ''],
            ['sta', 'EFZ', [], 'Eidgenössisches Fähigkeitszeugnis', 'aktuell', ''],
            ['sta', 'BIZ', ['biz'], 'Berufsinformationszentrum', 'aktuell', ''],
            ['sta', 'GER', [], 'Gemeinsamer Europäischer Referenzrahmen für Sprachen', 'aktuell', ''],
            ['sta', 'HSU', [], 'Herkunftssprachlicher Unterricht', 'aktuell', 'Ergänzender Begriff, nicht die Zürcher Standardbezeichnung HSK.'],
            ['dsi', 'IDG', [], 'Gesetz über die Information und den Datenschutz', 'aktuell', ''],
            ['dsi', 'IDV', [], 'Verordnung über die Information und den Datenschutz', 'aktuell', ''],
            ['dsi', 'DSG', [], 'Bundesgesetz über den Datenschutz', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'DSGVO', [], 'Datenschutz-Grundverordnung der EU', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'ICT', [], 'Information and Communication Technology', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'IT', [], 'Informationstechnologie', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'ISMS', [], 'Informationssicherheits-Managementsystem', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'MDM', [], 'Mobile Device Management', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'BYOD', [], 'Bring Your Own Device', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'MFA', [], 'Multi-Faktor-Authentifizierung', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'IAM', [], 'Identity and Access Management', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
            ['dsi', 'DSB', [], 'Datenschutzbeauftragte / Datenschutzbeauftragter, je nach Kontext', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt. Bedeutung je nach Kontext.'],
            ['dsi', 'TOM', [], 'Technische und organisatorische Massnahmen', 'zu_pruefen', 'Fachliche Ergänzung, nicht spezifisch vom Volksschulamt.'],
        ];
    }
}
