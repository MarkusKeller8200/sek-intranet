"""Erzeugt modules/Beschaffung/startinhalt/artikel.json aus den Confluence-Listen (ICT-Wiki, Stand 11.11.2026).

Bereinigungen gemäss Entscheid vom 11.11.2026 (#198):
1 Auffälligkeiten werden in der Liste mit «Prüfen» markiert.
2 Hausdienst: nur noch Lenovo (kein MacBook).
3 Schuladministration: doppelte Nummer neu vergeben, fehlende Unterseiten ergänzt.
4 Schulunterricht: Tabelle «ab SJ26/27», AirServer gestrichen, Schreibfehler korrigiert.
5 Entwürfe trotzdem eingefügt (Status «Entwurf»).
6 Mobile Apps: alle Einträge (ohne Personenangaben).
7 Material: alles übernommen, offene Angaben markiert.
"""
import json, os, sys

WIKI = 'https://sekbuelach.atlassian.net/wiki'
def page(pid): return f'{WIKI}/spaces/ictwiki/pages/{pid}'
def tiny(k): return f'{WIKI}/x/{k}'

QUELLEN = {
    'zimmer-ausruestung': page(306118657),
    'ausruestung-lehrpersonen': page(98336771),
    'ausruestung-schuelerinnen': page(314343425),
    'ausruestung-schulverwaltung': page(535986177),
    'ausruestung-hausdienst': page(536313857),
    'plattformen-unterricht': page(314540033),
    'plattformen-administration': page(347209729),
    'plattformen-ict': page(349372417),
    'fachanwendungen-schulbetrieb': page(206766355),
    'fachanwendungen-schulverwaltung': page(196608163),
    'mobile-apps': page(510722052),
    'material-picts-ticts': page(848625665),
}

FELDER = ['katalog', 'position', 'gruppe', 'bestellnummer', 'bezeichnung', 'modell', 'beschreibung', 'bild', 'preis',
          'kosten', 'preis_hinweis', 'zubehoer', 'standort', 'menge', 'zugang', 'nutzerkreis', 'verantwortung',
          'lizenzen', 'url', 'wiki', 'angaben', 'status', 'pruefen', 'pruef_hinweis', 'quelle']

artikel = []
def add(katalog, bezeichnung, **f):
    pos = sum(1 for a in artikel if a['katalog'] == katalog) + 1
    row = {k: '' for k in FELDER}
    row.update({'katalog': katalog, 'position': pos, 'bezeichnung': bezeichnung, 'preis': None, 'status': 'aktiv',
                'pruefen': False, 'quelle': QUELLEN[katalog]})
    for k, v in f.items():
        assert k in FELDER, k
        row[k] = v
    if row['pruef_hinweis']:
        row['pruefen'] = True
    artikel.append(row)

# ---------- Zimmerausrüstung (Standard, IST-Zustand 01.11.2025, alle «Erforderlich») ----------
for bez, zub, preis, ort, pid in [
    ('Beamer', 'Fernsteuerung; HDMI-Kabel Receiver; Stromkabel; Deckenmontage; optional Netzwerkkabel', 950, 'METTMI', 312901633),
    ('Leinwand', 'Deckenaufhängung', 500, 'METTMI', 312246277),
    ('Bildschirm', 'Fernsteuerung; HDMI-Kabel zu Receiver; Stromkabel; Wandaufhängung', 1000, 'HIBI', 312180738),
    ('AV-Receiver', 'Fernsteuerung; HDMI-Kabel zu MacBook; optional Netzwerkkabel', 500, 'METTMI, HIBI', 311099397),
    ('Apple TV', 'Fernsteuerung; HDMI-Kabel Receiver; Netzwerkkabel; Stromkabel', 160, 'METTMI, HIBI', 312573954),
    ('Lautsprecher', 'Lautsprecherkabel zu Receiver', 600, 'METTMI, HIBI', 311558157),
    ('Visualizer (Stand für iPad)', 'kein Zubehör', 140, 'METTMI, HIBI', 312475650),
    ('Zimmerdrucker', 'Netzwerkkabel; Stromkabel', 160, 'METTMI, HIBI', 311394323),
    ('WLAN Access Point', 'Netzwerkkabel', 0, 'METTMI, HIBI', 312803331),
    ('Ladegerät für iPad (min. 4 Ports)', 'Stromkabel; 4 × USB-C-Kabel (alternativ 4 × Lightning)', 100, 'METTMI, HIBI', 311427077),
    ('Steckdosenleiste', 'inkl. Verlängerungskabel', 20, 'METTMI, HIBI', 311132163),
    ('Box für Fernsteuerungen', 'Inhaltsliste laminiert', 5, 'METTMI, HIBI', 313065473),
]:
    add('zimmer-ausruestung', bez, gruppe='Standard Klassenzimmer', zubehoer=zub, preis=float(preis), standort=ort,
        wiki=page(pid), preis_hinweis='Richtwert', angaben='Ausstattung: erforderlich' + ('; Modell: verschiedene Modelle' if bez == 'Lautsprecher' else ''))

# ---------- Persönliche Ausrüstung ----------
MACBOOK_BESCHR = ('10-Core CPU, 10-Core GPU, 16 GB gemeinsamer Arbeitsspeicher, 512 GB SSD, 16-Core Neural Engine, '
                  '13,6" Liquid Retina Display mit True Tone, 12 MP Center Stage Kamera, MagSafe 3, zwei Thunderbolt 4, '
                  'bis zu zwei externe Displays, Magic Keyboard mit Touch ID, Force Touch Trackpad, 35W Dual USB-C Power Adapter')
LENOVO_BESCHR = ('AMD Ryzen AI 7 350, 32 GB Arbeitsspeicher, 512 GB SSD, Windows 11 Pro 64, 14" WUXGA OLED Display, '
                 '5 MP Infrarotkamera, 65W USB-C Steckernetzteil')
RICHT = 'Richtwert aus der letzten Ausschreibung (DQ)'

def ipad_paket(kat, tastatur_modell, mit_klinke=True, sus=False):
    lp = 'LP' if not sus else 'SuS'
    ids = {'LP': (312901643, 311132184, 311558182, 313360387, 312803354),
           'SuS': (311590932, 311263255, 312147983, 311558207, 313425932)}[lp]
    g = 'iPad' if sus else 'iPad-Paket'
    add(kat, 'iPad', gruppe=g, modell='iPad (A16) 128 GB WiFi', beschreibung='iPad 11", 128 GB, WiFi, inkl. USB-C-Ladekabel', preis=330.0, preis_hinweis=RICHT, wiki=page(ids[0]))
    add(kat, 'iPad-Tastatur' + ('' if sus else ' (abnehmbar)'), gruppe=g, modell=tastatur_modell, preis=150.0, preis_hinweis=RICHT, wiki=page(ids[1]))
    add(kat, 'iPad-Ladegerät', gruppe=g, modell='LMP Dual Power Adapter 20W', beschreibung='ohne Kabel', preis=20.0, preis_hinweis=RICHT, wiki=page(ids[2]))
    add(kat, 'iPad-Stift', gruppe=g, modell='Adonit SE2', beschreibung='inkl. Ladekabel USB-A, Ersatzspitzen', preis=30.0, preis_hinweis=RICHT, wiki=page(ids[3]))
    add(kat, 'Kopfhörer', gruppe=g, modell='Bestpick Meier Lernlauscher', beschreibung='USB-C-Variante' + (', alternativ 3,5 mm Klinke' if mit_klinke else ''), preis=10.0, preis_hinweis=RICHT, wiki=page(ids[4]))

k = 'ausruestung-lehrpersonen'
add(k, 'MacBook Air', gruppe='MacBook-Paket', modell='MacBook Air M4 (2025)', beschreibung=MACBOOK_BESCHR, preis=1200.0, preis_hinweis=RICHT, wiki=page(312016934))
add(k, 'Ladegerät für MacBook', gruppe='MacBook-Paket', beschreibung='beim MacBook Air inbegriffen', preis=0.0, preis_hinweis='inbegriffen')
add(k, 'Maus', gruppe='MacBook-Paket', modell='Logitech Pebble 2 M350s', beschreibung='Bluetooth-Maus', preis=25.0, preis_hinweis=RICHT, wiki=page(313425921))
add(k, 'Tasche zu MacBook', gruppe='MacBook-Paket', modell='Ultralight Sleeve for MacBook 14"', preis=35.0, preis_hinweis=RICHT, wiki=page(312934406))
add(k, 'USB-HDMI-Adapter', gruppe='MacBook-Paket', modell='Ugreen Hub CM478', preis=30.0, preis_hinweis=RICHT, wiki=page(312082451))
ipad_paket(k, 'Logitech Combo Touch for iPad')

ipad_paket('ausruestung-schuelerinnen', 'Logitech Rugged Combo 4 Tastaturhülle, iPad 11" (2025, 11. Gen., A16), dunkelblau', mit_klinke=False, sus=True)

def verwaltung_notebook(k, wer):
    add(k, 'Notebook 2-in-1', gruppe='Notebook-Paket', modell='Lenovo Yoga 7 2-in-1', beschreibung=LENOVO_BESCHR, preis=1400.0, preis_hinweis=RICHT)
    add(k, 'Monitor', gruppe='Notebook-Paket', modell='Samsung ViewFinity S8, 27"', beschreibung='3840 × 2160 Pixel, LCD, 1 × DisplayPort, 1 × HDMI', preis=270.0, preis_hinweis=RICHT)
    add(k, 'Docking', gruppe='Notebook-Paket', modell='i-tec USB-C Metal Ergonomic', preis=70.0, preis_hinweis=RICHT)
    add(k, 'Tastatur & Maus', gruppe='Notebook-Paket', modell='Logitech MX Keys S Combo', beschreibung='Bluetooth-Tastatur und -Maus', preis=150.0, preis_hinweis=RICHT,
        wiki=page(313425921), pruef_hinweis='Link im ICT-Wiki zeigt auf die Maus der Lehrpersonen (Logitech Pebble) statt auf die MX Keys S Combo.')
    add(k, 'Tasche zu Notebook', gruppe='Notebook-Paket', modell='Ultralight Sleeve for MacBook 14"', preis=35.0, preis_hinweis=RICHT, wiki=page(312934406),
        pruef_hinweis='In Confluence als «Tasche zu MacBook» aufgeführt – passt die Tasche zum Lenovo Yoga 7?')
    add(k, 'USB-HDMI-Adapter', gruppe='Notebook-Paket', modell='Ugreen Hub CM478', preis=30.0, preis_hinweis=RICHT, wiki=page(312082451),
        pruef_hinweis='Wird zum Lenovo mit Docking ein zusätzlicher USB-HDMI-Adapter gebraucht?')
    add(k, 'Kamera', gruppe='Notebook-Paket', modell='Logitech BRIO 4K', beschreibung='Bildsensor 13 Megapixel', preis=105.0, preis_hinweis=RICHT)
    add(k, 'Headset', gruppe='Notebook-Paket', modell='Logitech Zone 305', preis=65.0, preis_hinweis=RICHT)

verwaltung_notebook('ausruestung-schulverwaltung', 'Schulverwaltung')
verwaltung_notebook('ausruestung-hausdienst', 'Hausdienst')
ipad_paket('ausruestung-hausdienst', 'Logitech Combo Touch for iPad')
# Entscheid 2: Hausdienst neu nur Lenovo – MacBook Air und Ladegerät entfallen.

# ---------- Plattformen im Schulunterricht (ab SJ26/27) ----------
KOSTEN = {'KOSTENLOS': 'kostenlos', 'NIEDRIG': 'niedrig', 'MODERAT': 'moderat', 'HOCH': 'hoch', 'VARIABEL': 'variabel', 'AUSSTEHEND': 'ausstehend'}
k = 'plattformen-unterricht'
for nr, bez, kat, wk, url, kost, lehrmittel in [
    ('1010', '3D Innotech Air', 'MI Unterricht', 'LQChE', 'https://www.3d-innotech.com/', 'MODERAT', 0),
    ('1020', 'Actionbound', 'Andere', 'WACgE', 'https://de.actionbound.com/', 'NIEDRIG', 0),
    ('1030', 'Adobe Suite', 'Office', 'J4CiE', 'https://www.adobe.com/ch_de/education.html', 'MODERAT', 0),
    ('1290', 'Affinity Studio', 'Kreative', 'AQAPG', 'https://www.affinity.studio/de_de', 'NIEDRIG', 0),
    ('1040', 'AirServer', 'Unterricht', 'MoCgE', 'https://www.airserver.com/', 'NIEDRIG', 0),
    ('1210', 'Antolin', 'Lehrmittel', 'OwCZE', 'https://www.westermann-schweiz.ch/reihe/ANTOLONLINE/Antolin', 'NIEDRIG', 1),
    ('1220', 'Anton', 'Lehrmittel', 'YwCgE', 'https://anton.app/de/', 'NIEDRIG', 1),
    ('3000', 'BiBox', 'Lehrmittel', 'PICgE', 'https://www.bibox.westermann-schweiz.ch/', 'VARIABEL', 1),
    ('1270', 'Brisk Teaching', 'KI', 'yACZE', 'https://www.briskteaching.com/de-de', 'KOSTENLOS', 0),
    ('1050', 'Canva', 'Kreative', 'RoCgE', 'https://www.canva.com/education/', 'KOSTENLOS', 0),
    ('1060', 'Classroom', 'Schulung', 'T4CgE', 'https://classroom.ch/#Inhalte', 'HOCH', 0),
    ('1070', 'Classtime', 'Prüfung', 'PICiE', 'https://www.classtime.com/de.html', 'HOCH', 0),
    ('1300', 'ComicLife', 'Kreative', 'AQAOG', 'https://plasq.com/apps/comiclife4/mac/', 'MODERAT', 0),
    ('1080', 'Merge Cube', 'MI Unterricht', 'ZAChE', 'https://mergeedu.com/', 'MODERAT', 0),
    ('1090', 'Delight Pro (Merge Cube)', 'MI Unterricht', 'TwCZE', '', 'MODERAT', 0),
    ('1100', 'Delightex', 'MI Unterricht', 'ZICgE', 'https://www.delightex.com/', 'MODERAT', 0),
    ('1310', 'Easy4me', 'Lehrmittel', 'AYAPG', 'https://www.easy4me.info/', 'NIEDRIG', 0),
    ('1230', 'eduPackage', 'Lehrmittel', 'WQCZE', 'https://edupackage.ch/preise/', 'NIEDRIG', 1),
    ('1110', 'Evulpo', 'Lehrmittel', 'egChE', 'https://evulpo.com/de/ch', 'HOCH', 0),
    ('1120', 'Examiner', 'Prüfung', 'eYCgE', 'https://www.examiner.ch/', 'MODERAT', 0),
    ('1130', 'Examio', 'Prüfung', 'gwChE', 'https://www.examio.de/', 'AUSSTEHEND', 0),
    ('1140', 'Fobizz', 'KI', 'YgCZE', 'https://fobizz.com/de/', 'NIEDRIG', 0),
    ('1150', 'IQES', 'Andere', 'g4CgE', 'https://www.iqesonline.net/', 'NIEDRIG', 0),
    ('1160', 'Kahoot', 'Andere', 'awCZE', 'https://kahoot.it/', 'MODERAT', 0),
    ('1170', 'Learning View', 'Berufswahl', 'jYCgE', 'https://learningview.org/', 'KOSTENLOS', 0),
    ('1180', 'LernPass+', 'Prüfung', 'jgChE', 'https://lernpassplus.ch/lernpass/', 'HOCH', 0),
    ('2000', 'LMVZ', 'Lehrmittel', 'eICiE', 'https://www.lmvz.ch/', 'VARIABEL', 1),
    ('1190', 'nanoo.tv', 'Lehrmittel', 'gACZE', 'https://portal.nanoo.tv/', 'MODERAT', 0),
    ('1240', 'Profolio', 'Lehrmittel', 'mAChE', 'https://www.profolio.ch/', 'HOCH', 1),
    ('4000', 'Revoca', 'Lehrmittel', 'rAChE', 'https://www.revoca.ch/products', 'VARIABEL', 1),
    ('1250', 'Schularena', 'Lehrmittel', 'VoChE', 'https://www.schularena.com/start', 'HOCH', 1),
    ('1320', 'SimpleShow', 'Kreative', 'MQAIG', 'https://simpleshow.com/de/classroom/', 'KOSTENLOS', 0),
    ('1280', 'Snorkl', 'KI', '0ACgE', 'https://snorkl.app/', 'KOSTENLOS', 0),
    ('1330', 'Squirrels – AirParrot', 'Unterricht', 'HIAPG', 'https://www.airsquirrels.com/airparrot/', 'NIEDRIG', 0),
    ('1340', 'Squirrels – Reflector', 'Unterricht', 'AYAQG', 'https://www.airsquirrels.com/reflector', 'NIEDRIG', 0),
    ('1260', 'Tipp 10', 'Andere', 'pQCgE', 'https://www.tipp10.com/de/', 'NIEDRIG', 0),
    ('1350', 'Ultimaker', 'Kreative', 'AQAQG', 'https://ultimaker.com/de/', 'NIEDRIG', 0),
]:
    hinweis = ''
    status = 'aktiv'
    if bez == 'AirServer':
        status = 'gestrichen'
    if bez == 'Delight Pro (Merge Cube)':
        hinweis = 'Webseite fehlt in Confluence.'
    if kost == 'AUSSTEHEND':
        hinweis = 'Kosten in Confluence noch ausstehend.'
    add(k, bez, gruppe=kat, bestellnummer=f'SEKB-SW-{nr}', kosten=KOSTEN[kost], url=url, wiki=tiny(wk), status=status,
        angaben='Lehrmittel bzw. Lehrmittel-Plattform' if lehrmittel else '',
        preis_hinweis='Kostenstufe bei Volllizenzierung', pruef_hinweis=hinweis)

# Fachunterricht Musik (Unterseite 848560141): ICT = durch die Fachstelle lizenziert, FLP = Fachlehrperson
MUSIK = page(848560141)
for nr, bez, geraet, verw, seit, zust, modell, intervall, kosten, anzahl, bem in [
    (1, 'Fender Studio Pro', 'Mac', 'Musik-DAW', '2021', 'ICT', 'Kauf', 'individuell', '193.00 CHF', '1 für Mac', 'alle 3 Jahre'),
    (2, 'Spreaker', '', 'Podcast-Hosting', '2021', 'ICT', 'Abo', 'monatlich', '20.00 USD', '1 für Mac', ''),
    (3, 'Apple Music', '', 'Audio-Streaming', '2022', 'ICT', 'Abo', 'jährlich', '139.00 CHF', '1 für Mac', ''),
    (4, 'Midiculous 4', 'Mac', 'Musik-MIDI', '2023', 'ICT', 'Kauf', 'individuell', '83.42 CHF', '2 für Mac', 'bis die OS-Kompatibilität entfällt'),
    (5, 'FileMaker Pro Advanced', 'Mac', 'Datenbank für Liedsammlung', '2024', 'ICT', 'Kauf', 'individuell', '35.29 CHF', '1 für Mac', 'bis die OS-Kompatibilität entfällt'),
    (6, 'Moises', 'Mac', 'Audio-Stems der Lieder', '2024', 'ICT', 'Abo', 'jährlich', '350.00 CHF', '1 für Mac', ''),
    (7, 'Final Cut für iPad', 'Mac', 'Videoschnitt', '2024', 'ICT', 'Abo', 'jährlich', '50.00 CHF', '1 für Mac', 'Geräteangabe prüfen'),
    (8, 'Hindenburg', '', 'Podcast-Schnitt', '2024', 'ICT', 'Kauf', 'individuell', '369.00 CHF', '1 für Mac', 'alle 5–7 Jahre'),
    (9, 'Digital Concert Hall', 'Mac', 'Konzert-Streaming', '2025', 'ICT', 'Abo', 'jährlich', '152.10 EUR', '1 für Mac', ''),
    (10, 'Suno', 'Mac', 'KI-Musik', '2025', 'ICT', 'Abo', 'jährlich', '86.40 CHF', '1 für Mac', ''),
    (11, 'Logic', 'Mac', 'Audioschnitt', '2021', 'ICT', 'Kauf', 'einmalig', '180.00 CHF', '2 für Mac', ''),
    (12, 'All my guitars', 'Mac, iPad', 'Gitarren- und Bassdatenbank', '2023', 'ICT', 'Kauf', 'einmalig', '3.00 CHF', '2 – iPad und Mac', ''),
    (13, 'Loopseque 8', 'Mac, iPad', 'Schlagzeug-Pad', '', 'ICT', 'Kauf', 'einmalig', '3.00 CHF', '2 – iPad und Mac', ''),
    (14, 'Musicnotes', 'Mac, iPad', 'Partiturenanschaffung', '2021', 'ICT', 'Abo', 'jährlich', '16.00 CHF', '2 – iPad und Mac', ''),
    (15, 'forScore', 'Mac, iPad', 'Partiturensammlung', '2021', 'ICT', 'Kauf', 'einmalig', '20.00 CHF', '2 – iPad und Mac', ''),
    (16, 'Compressor', 'Mac', 'Videokonvertierung', '2021', 'ICT', 'Kauf', 'einmalig', '40.00 CHF', '2 für Mac', ''),
    (17, 'MainStage', 'Mac', 'Virtuelle Instrumente, Effekte und Voice-Compressor', '2021', 'ICT', 'Kauf', 'einmalig', '25.00 CHF', '2 für Mac', ''),
    (18, 'Camera Preview', 'Mac', 'Keyboard-Livestream', '2023', 'ICT', 'Kauf', 'einmalig', '5.00 CHF', '2 für Mac', ''),
    (19, 'Permute 4', 'Mac', 'Audiokonvertierung', '2022', 'ICT', 'Kauf', 'individuell', '14.00 CHF', '2 für Mac', 'alle 4–5 Jahre'),
    (20, 'Amadeus Pro', 'Mac', 'Audioaufnahme', '2015', 'ICT', 'Kauf', 'individuell', '50.00 CHF', '2 für Mac', 'alle 4–5 Jahre'),
    (21, 'Podcast Chapters', 'Mac', 'Podcast-Kapitel hinzufügen', '2021', 'ICT', 'Kauf', 'einmalig', '18.00 CHF', '2 für Mac', ''),
    (22, 'Final Cut (macOS)', 'Mac', 'Videoschnitt', '2018', 'ICT', 'Kauf', 'einmalig', '250.00 CHF', '2 für Mac', ''),
    (23, 'Band-in-a-Box – Audiophile Edition', 'Mac', 'Audio-Tracks, Loops und MIDI-Sounds', '2015', 'ICT', 'Kauf', 'individuell', '669.00 CHF', '1 für Mac', 'bis die OS-Kompatibilität entfällt'),
    (24, 'Brother P-Touch Editor', 'Mac', 'Etiketten für Tasten und Gitarrenhals', '2023', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (25, 'Klassik', 'iPad', 'Streaming klassischer Musik', '2024', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (26, 'Pocket Casts', 'Mac', 'Podcast-Player', '2021', 'FLP', '', 'einmalig', 'kostenlos', '2 – iPad und Mac', ''),
    (27, 'UE Boom / Roll / Blast-Megablast', 'iPad', 'Steuerung von Bluetooth-Lautsprechern', '2021', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (28, 'Roadie', 'Mac', 'Steuerung des Tuners', '2023', 'FLP', '', 'einmalig', 'kostenlos', '2 – iPad und Mac', ''),
    (29, 'Thomann', 'iPad', 'Webshop', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (30, 'Røde Central Mobile', 'iPad', 'Equalisation für Røde-Mikrofon', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (31, 'Røde Capture', 'iPad', 'Audioaufnahme mit Røde-Mikrofon', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (32, 'Røde Smart Pads', 'iPad', 'Audio-Pads', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (33, 'Fender Tune – Guitar Tuner', 'iPad', 'Stimmgerät', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (34, 'Fender Tone', 'iPad', 'Steuerung von Verstärker / Pedal', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (35, 'Fender Universal Control', 'iPad', 'Steuerung von Mixer / Monitor / Mix / Snake', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (36, 'Fender Studio', 'iPad', 'Webshop', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (37, 'Fender Studio Pro Remote', 'iPad', 'Steuerung von Mixer / Monitor / Mix / Snake', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (38, 'Fender Pro Control', 'iPad', 'Steuerung von Mixer / Monitor / Mix / Snake', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (39, 'PreSonus QMIX-UC', 'iPad', 'Steuerung von Mixer / Monitor / Mix / Snake', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (40, 'PreSonus QMIX', 'iPad', 'Steuerung von Mixer / Monitor / Mix / Snake', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (41, 'PreSonus SL Remote', 'iPad', 'Steuerung von Mixer / Monitor / Mix / Snake', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (42, 'PreSonus SL Remote-AI', 'iPad', 'Steuerung von Mixer / Monitor / Mix / Snake', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (43, 'My PreSonus', 'iPad', 'Webshop', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (44, 'Logic Remote', 'iPad', 'Steuerung von Logic', '2023', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (45, 'Das Metronom von Soundbrenner', 'Mac, iPad', 'Steuerung von Soundbrenner', '2025', 'FLP', '', 'einmalig', 'kostenlos', '2 – iPad und Mac', ''),
    (46, 'Berliner Philharmoniker', 'iPad', 'Konzert-Streaming', '2025', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (47, 'Berlin Phil (Apple TV)', 'Apple TV', 'Konzert-Streaming', '2025', 'FLP', '', 'einmalig', 'kostenlos', 'Musik Apple TV & Medienraum?', ''),
    (48, 'Berlin Phil (Mac)', 'Mac', 'Konzert-Streaming', '2025', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (49, 'Røde Pads Mobile', 'Mac', 'Audio-Pads', '2024', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (50, 'Rodecaster App', 'Mac', 'Steuerung des Rodecasters', '2024', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (51, 'Capture 3', 'Mac', 'Aufnahme aller Mixerkanäle', '2021', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (52, 'Universal Control', 'Mac', 'Mixer-Backup', '2021', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (53, 'Alesis V Series MKII Editor', 'Mac', 'Steuerung von Alesis V Series MKII', '2025', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (54, 'Better Audio', 'Mac', 'Steuerung der Audioausgabe', '2026', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (55, 'Tun', 'Mac', 'Lautsprechersteuerung', '2026', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (56, 'Argo', 'Mac', 'Podcast-Bild', '2026', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (57, 'ROLI Connect', 'Mac', 'Verbindung von ROLI-Software und -Hardware', '2026', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (58, 'ROLI Studio', 'Mac', 'Steuerung des ROLI-Keyboards', '2026', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (59, 'ROLI Equator 2', 'Mac', 'Equalisation der ROLI-Keyboard-Sounds', '2026', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (60, 'ROLI Dashboard', 'Mac', 'Individuelle Einstellungen des ROLI-Keyboards', '2026', 'FLP', '', 'einmalig', 'kostenlos', '2 für Mac', ''),
    (61, 'ROLI Learn', 'iPad', 'Klavierunterricht mit ROLI-Keyboard', '2026', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (62, 'Seaboard 5D', 'iPad', 'Digitale Version des ROLI-Keyboards', '2026', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
    (63, 'ROLI Airwave Player', 'iPad', 'Steuerung von Airwave / ROLI-Keyboard', '2026', 'FLP', '', 'einmalig', 'kostenlos', '1 für iPad', ''),
]:
    preis, hinweis, kosten_stufe = None, '', ''
    if kosten == 'kostenlos':
        preis, kosten_stufe = 0.0, 'kostenlos'
    elif kosten.endswith('CHF'):
        preis = float(kosten.split()[0])
    else:
        hinweis = kosten
    intervall_txt = {'monatlich': 'pro Monat', 'jährlich': 'pro Jahr', 'einmalig': 'einmalig', 'individuell': 'individuell'}.get(intervall, '')
    preis_hinweis = '; '.join(x for x in [hinweis, (modell + ' ' if modell else '') + intervall_txt] if x).strip()
    pruef = ''
    if bem == 'Geräteangabe prüfen':
        pruef, bem = 'Geräteangabe prüfen (Final Cut für iPad, in Confluence «für Mac»).', ''
    if anzahl.endswith('?'):
        pruef = 'Anzahl/Geräte offen: ' + anzahl
    angaben = '; '.join(x for x in [f'Geräte: {geraet}' if geraet else '', f'seit: {seit}' if seit else '', f'Bemerkung: {bem}' if bem else ''] if x)
    add(k, bez, gruppe='Fachunterricht Musik', beschreibung=verw, preis=preis, kosten=kosten_stufe, preis_hinweis=preis_hinweis,
        lizenzen=anzahl, verantwortung='Fachstelle ICT' if zust == 'ICT' else 'Fachlehrperson', angaben=angaben,
        quelle=MUSIK, pruef_hinweis=pruef)

# ---------- Plattformen in der Schuladministration (Entwurf) ----------
k = 'plattformen-administration'
NUTZER = {'ADMINISTRATION': 'Administration (SV und SL)', 'SCHULVERWALTUNG': 'Schulverwaltung', 'SCHULLEITUNG': 'Schulleitung'}
admin_rows = [
    ('5010', 'booked', 'SCHULLEITUNG', 'Reservation', 'GQDNF', 'https://www.twinkletoessoftware.com/', ''),
    ('5020', 'BriefButler', 'SCHULVERWALTUNG', 'Post', 'DoDOF', 'https://www.briefbutler.com/#start', ''),
    ('5030', 'Clicktime', 'ADMINISTRATION', 'Zeiterfassung', 'DoDQF', 'https://www.clicktime.ch/', ''),
    ('5040', 'CMI – Dossier', 'ADMINISTRATION', 'Dossier', 'GYDOF', 'https://cmiag.ch/', ''),
    ('5041', 'CMI – Schule', 'ADMINISTRATION', 'Schulverwaltung', 'BIDMF', 'https://cmiag.ch/', ''),
    ('5042', 'CMI – Protokoll', 'SCHULVERWALTUNG', 'Protokoll', 'FADPF', 'https://cmiag.ch/', ''),
    ('5043', 'CMI – Sitzung', 'ADMINISTRATION', 'Sitzung', 'JYDQF', 'https://cmiag.ch/', ''),
    ('5047', 'LehrerOffice', 'SCHULLEITUNG', 'Schulverwaltung', 'RADRF', 'https://cmi-bildung.ch/loesungen/softwareloesung/', ''),
    ('5048', 'KLAPP', 'SCHULLEITUNG', 'Elternkommunikation', 'HwDPF', 'https://cmi-bildung.ch/loesungen/kommunikation-klapp/', ''),
    ('5049', 'CMI – Scolaris', 'SCHULVERWALTUNG', 'Schulverwaltung', 'L4DQF', '', 'In Confluence mit * markiert; Webseite fehlt.'),
    ('5050', 'deepSign', 'ADMINISTRATION', 'Unterschrift', 'DQDQF', 'https://www.deepsign.swiss/de/', ''),
    ('5060', 'ESCOLA', 'ADMINISTRATION', 'Homepage', 'JADQF', 'https://www.escola.ch/', ''),
    ('5070', 'FocusNet', 'SCHULLEITUNG', 'Digital Signage', 'JIDOF', 'https://focusnet.ch/', ''),
    ('5080', 'IncaMail', 'SCHULVERWALTUNG', 'Mail', 'LoDOF', 'https://web.incamail.com/de/', ''),
    ('5090', 'Microsoft – Office 365', 'SCHULVERWALTUNG', 'Office', 'HQDOF', '', ''),
    ('5091', 'Microsoft – SharePoint', 'SCHULVERWALTUNG', 'Dateiablage', 'JwDOF', '', ''),
    ('5100', 'IWP', 'SCHULLEITUNG', 'Mitarbeiterbeurteilung', 'NADNF', '', 'Webseite fehlt in Confluence.'),
    ('5110', 'miro', 'SCHULLEITUNG', 'Collaboration', 'KQDPF', 'https://miro.com/app/dashboard/', ''),
    ('5120', 'SL-Planer', 'SCHULLEITUNG', 'Einsatzplanung', 'UIDOF', 'https://slplaner.ch/my/users/sign_in', ''),
    ('5130', 'Umantis', 'SCHULVERWALTUNG', 'Stellenausschreibung', 'NQDPF', 'https://www.umantis.com/', ''),
    ('5140', 'swiss-untis', 'SCHULLEITUNG', 'Stundenplan', 'Z4DOF', 'https://www.gpuntis.ch/', ''),
    ('5150', 'VSA – Puls', 'SCHULVERWALTUNG', '', 'coDOF', '', 'Bestellnummer neu vergeben (in Confluence doppelt SEKB-SW-5140); Zweck und Webseite ergänzen.'),
]
for nr, bez, nutzer, zweck, wk, url, hinweis in admin_rows:
    add(k, bez, gruppe=zweck, bestellnummer=f'SEKB-SW-{nr}', nutzerkreis=NUTZER[nutzer], url=url, wiki=tiny(wk),
        status='entwurf', pruef_hinweis=hinweis)
for nr, bez, pid in [('5160', 'Abraxas FIS', 575143937), ('5170', 'doodle', 575307777)]:
    add(k, bez, bestellnummer=f'SEKB-SW-{nr}', wiki=page(pid), status='entwurf',
        pruef_hinweis='Als Unterseite in Confluence vorhanden, fehlte in der Tabelle; Bestellnummer neu vergeben – Nutzerkreis, Zweck und Webseite ergänzen.')

# ---------- Plattformen der Fachstelle ICT (Entwurf) ----------
k = 'plattformen-ict'
for nr, bez, zweck, wk, url in [
    ('6010', '3CX', 'Telefonie', 'AYDOF', 'https://www.3cx.de/'),
    ('6020', 'Apple School Manager', 'MDM', 'AQDQF', 'https://school.apple.com/'),
    ('6030', 'Atlassian Confluence', 'Wiki', 'BADNF', 'https://www.atlassian.com/software/confluence'),
    ('6031', 'Atlassian Jira', 'Projektmanagement', 'A4DQF', 'https://www.atlassian.com/software/jira'),
    ('6032', 'Atlassian Service Management', 'Ticket', 'DwDNF', 'https://www.atlassian.com/software/jira/service-management'),
    ('6040', 'Inventory360', 'Inventar', 'OADRF', 'https://www.enteksystems.de/produkte/inventory360'),
    ('6050', 'Jamf – Pro', 'MDM', 'EoDMF', 'https://www.jamf.com/de/produkte/jamf-pro/'),
    ('6051', 'Jamf – Protect', 'Sicherheit', 'RoDOF', 'https://www.jamf.com/de/produkte/jamf-protect/'),
    ('6060', 'Lansweeper', 'Inventar', 'EIDSF', 'https://www.lansweeper.com/'),
    ('6070', 'Roomz', 'Beschilderung', 'HIDSF', 'https://roomz.io'),
    ('6080', 'TeamViewer', 'Fernwartung', 'IYDMF', 'https://www.teamviewer.com/de/'),
    ('6090', 'MIST', '(W)LAN-Verwaltung', 'iQDRF', 'https://www.mist.com/'),
    ('6100', '1Password', 'Passwortverwaltung', 'aoDNF', 'https://1password.com/'),
    ('6110', 'PaperCut', 'Druckerverwaltung', 'T4DMF', 'https://www.papercut.com/'),
]:
    add(k, bez, gruppe=zweck, bestellnummer=f'SEKB-SW-{nr}', nutzerkreis='Fachstelle ICT', url=url, wiki=tiny(wk), status='entwurf')

# ---------- Fachanwendungen (Unterseiten mit Anleitungen) ----------
k = 'fachanwendungen-schulbetrieb'
for bez, pid, anleitungen in [
    ('Classroom', 57442306, ''),
    ('LMVZ', 181764374, 'LMVZ – Anmeldung'),
    ('LernpassPlus', 113934341, 'Erste Schritte als Lehrperson; Berechtigungen und Zugriff; Lehrpersonen verwalten; Klassen und SuS verwalten (Login); Stellwerk – Einführung; Stellwerk – Anleitung für LP'),
    ('PaperCut', 194871345, 'Drucken nicht möglich'),
    ('Microsoft 365 (O365)', 195199003, 'Microsoft Teams; Microsoft Outlook; OneNote (Einschränkungen); Weitere Installationen (private Geräte)'),
    ('LehrerOffice (CMI)', 198443011, 'Adresse (Rufname); Schüler:innen-Fotos; Anzeigen Telefonnummern Eltern'),
    ('Klapp', 199589889, 'Einstellungen (Lehrpersonen); Probleme & FAQ; Absenzen; Elternanmeldung; Einladungsbrief'),
    ('Apple Classroom', 231407617, 'Sitzung starten und beenden'),
    ('Untis – Stundenplaner', 375619587, ''),
]:
    add(k, bez, wiki=page(pid), angaben=f'Anleitungen: {anleitungen}' if anleitungen else '',
        pruef_hinweis='Keine Tabelle in Confluence – Beschreibung, Preis und Webseite ergänzen.')
k = 'fachanwendungen-schulverwaltung'
for bez, pid, anleitungen in [
    ('IncaMail', 22315044, 'Konto einrichten; Nutzungsrichtlinien; Outlook-Add-in Anwendung; Outlook-Add-in (Administrator)'),
    ('deepSign', 22642691, 'Konto erstellen; Mobile App; 2FA; Office 365 Add-in (Administrator); Office 365 Add-in; Unterzeichnete Dokumente in deepBox speichern; Fristen; Monatsrapport digital einreichen'),
    ('BriefButler', 23560194, 'Konto erstellen; Versand; Word-Add-in Installation (Administrator); Outlook-Add-in (Administrator); Word-Plug-in – Serienbrief'),
    ('CMI', 24444994, 'CMI Schule; CMI Dossier; Anträge erstellen (Word Online); LehrerOffice; Datenerfassung (Export in LO, O365 und BISTA); Verträge'),
]:
    add(k, bez, wiki=page(pid), angaben=f'Anleitungen: {anleitungen}',
        pruef_hinweis='Keine Tabelle in Confluence – Beschreibung, Preis und Webseite ergänzen.')

# ---------- Material PICTS / TICTS ----------
k = 'material-picts-ticts'
Z = {'P': 'bei PICTS', 'T': 'bei TICTS', 'B': 'über Booked', 'L': 'LP direkt', '?': 'offen'}
def zugang(code):
    return ' / '.join(Z[c] for c in code) if code else ''
for bez, verw, m_mett, z_mett, m_hibi, z_hibi, bem, hinweis in [
    ('3D-Drucker', 'TICTS', '3', 'P', '3', 'P', 'Gemäss Mettmi-Liste; Softwareinstallation und IT-Anbindung bei Bedarf durch TICTS', ''),
    ('Actionkameras mit Handstativen', 'PICTS', '2', 'PB', '', '', '', ''),
    ('AR-Cubes', 'PICTS', '', '', '5', 'B', '', ''),
    ('AR-Lehrmaterialien', 'PICTS', '', '', '5', 'B', 'Verschiedene Themenbereiche', ''),
    ('Beamer (portabel)', 'TICTS', '1', 'TB', '1', 'TB', '', ''),
    ('Beamer-Leinwand (portabel)', 'TICTS', '', '', '', '', '', 'Anzahl und Zugang fehlen in Confluence.'),
    ('Beleuchtungsset', 'PICTS', '1', '', '', '', '', ''),
    ('Binokulare', 'PICTS', '', '', '11', 'B', 'Digitale Binokulare', ''),
    ('Calliope mini Start-Kits + Calliope mini', 'PICTS', '', '', '85', 'B', '', 'Enthaltene Geräte prüfen.'),
    ('Digipen', 'PICTS / TICTS', '25', 'PB', '', 'TB', 'nur noch für ein Jahr, danach persönliches Material', ''),
    ('Digitalkamera', 'PICTS', '2', 'B', '8', 'B', '', 'HIBI: zusätzlicher Eintrag könnte Kamera oder Transportkoffer sein.'),
    ('Digitalkamera-Ladegeräte', 'PICTS', '', 'B', '1', 'P', 'Anker', ''),
    ('Drohne', 'PICTS', '', 'P', '1', 'B', '', ''),
    ('Ferngläser', 'PICTS', '2 (3?)', 'B', '', '', '', 'Anzahl Mettmenriet offen: 2 oder 3.'),
    ('Fotodrucker', 'PICTS', '1', 'B', '', '', 'Installation und Treiber bei Bedarf durch TICTS', ''),
    ('Funkmikrofone (2er-Sets)', 'PICTS', '12', '', '', '', 'Entspricht 24 Mikrofonen', ''),
    ('Gear Bug', 'PICTS', '', '', '1', 'B', 'Auto aus dem 3D-Drucker', ''),
    ('Green-Screen', 'PICTS', '1', '', '1', 'B', '', ''),
    ('iPad-Stative', 'PICTS', '12', 'B', '', '', '', ''),
    ('Körperscanner', 'TICTS', '', 'TB', '1', 'TB', '', 'Gerätetyp und erforderliche Software bzw. IT-Einbindung klären.'),
    ('Ladegeräte für Oxocard', 'PICTS', '', '', '2', 'P', '', ''),
    ('Laptopwagen', 'TICTS', '3', 'LB', '3', 'LB', 'Laptopwagen à je 25 MacBook Air', ''),
    ('Laminiergerät', 'PICTS', '1', 'LB', '2', 'L', 'Hibi in Vorbereitung A und C', ''),
    ('LED-Ringlicht', 'PICTS', '1', '', '', '', '', ''),
    ('LEGO-Material', 'PICTS', '', '', '4', 'B', '4 Boxen mit Bausteinen – kein Set', ''),
    ('LEGO-Platten', 'PICTS', '', '', '2', 'B', '', 'Stückzahl je Position nicht angegeben.'),
    ('Lehrmaterial «Achtung fertig Code»', 'PICTS', '', '', '1', '?', '', 'Zugang offen.'),
    ('Lehrmaterial «Informatik Magie»', 'PICTS', '', '', '1', '?', '', 'Zugang offen.'),
    ('Lichtzeichen', 'PICTS', '', '', '10', '?', '', 'Genauer Materialtyp und Zugang offen.'),
    ('Micro:bit-Bausätze und Maqueen-Roboter', 'PICTS', '24', '', '10', 'B', 'Unterschiedliche Set-Inhalte; HIBI ohne Maqueen', ''),
    ('Micro:bit-Zusatzmaterial', 'PICTS', '', '', '7', 'B', '', 'Inhalt und Stückzahlen je Position nicht angegeben.'),
    ('Mikrofone', 'PICTS', '24', 'B', '10', 'B', 'Mettmi: verkabelt; HIBI: RØDE mit Lightning-Anschluss (nur alte iPads); Mikrofone aus Sets nicht zusätzlich gezählt', ''),
    ('Mini-Roboter Thymio', 'PICTS', '', '', '12 (2 × 6)', 'B', '', ''),
    ('Musikanlage (portabel)', 'PICTS', '', 'PB', '', 'PB', '', 'Anzahl fehlt in Confluence.'),
    ('Oxocard Programmierboards', 'PICTS', '', '', '14', 'B', '5 Oxocard Kit (RGB Display), 6 Galaxy, 1 Science, 2 Artwork', ''),
    ('Oxocard Spielkonsolen', 'PICTS', '', '', '6', 'B', '', ''),
    ('Podcast-Mischpult mit Mikrofonen, Stativen und Kopfhörern', 'PICTS', '1 + 4', 'B', '1 + 4 + 4', 'B', '', 'Zusammensetzung der Mengenangabe klären.'),
    ('Roboterkatzen', 'PICTS', '', '', '2', 'B', '', ''),
    ('Smart-Home-Kamera', 'PICTS (vorläufig)', '', '', '1', 'B', 'Eve Cam', 'Netzwerk-, Konto- und IT-Einbindung mit TICTS klären.'),
    ('Smart-Home-Leuchtmittel', 'PICTS (vorläufig)', '', '', '1', 'B', 'LIFX Mini Colour', 'IT-Einbindung klären.'),
    ('Smart-Home-Wetterstation', 'PICTS (vorläufig)', '', '', '1', 'B', '', 'IT-Einbindung klären.'),
    ('Spiegelreflexkameras', 'PICTS', '3', 'B', '', '', '', ''),
    ('Turing Tumble', 'PICTS', '', '', '4', 'B', '', ''),
    ('Videokameras', 'PICTS', '2', '', '', '', 'DJI Osmo aus HIBI bereits unter Digitalkameras erfasst', ''),
    ('VR-Brillen für Smartphones', 'PICTS', '', '', '31 (30 + 1)', 'B', '', ''),
    ('VR-Brillen Meta Quest', 'TICTS', '', 'PB', '4', 'PB', '', 'Zentrale Geräte-, Konto- und Lizenzverwaltung mit TICTS klären.'),
    ('WILMA Tüftelbausteine', 'PICTS', '', '', '25', 'B', 'Keine ganzen Sets, nach Einzelteilen sortiert (3D-druckbar bzw. Verbrauchsmaterial)', 'Stückzahlen je Materialart fehlen.'),
]:
    standorte = ', '.join(s for s, m, z in [('METTMI', m_mett, z_mett), ('HIBI', m_hibi, z_hibi)] if m or z)
    menge = ' · '.join(f'{s} {m}' for s, m in [('METTMI', m_mett), ('HIBI', m_hibi)] if m)
    zug = ' · '.join(f'{s}: {zugang(z)}' for s, z in [('METTMI', z_mett), ('HIBI', z_hibi)] if z)
    add(k, bez, gruppe='TICTS-Material' if verw.startswith('TICTS') else 'PICTS-Material', verantwortung=verw,
        standort=standorte, menge=menge, zugang=zug, beschreibung=bem, pruef_hinweis=hinweis)

# ---------- Mobile Apps (Jamf, Stand 31.03.2026) ----------
k = 'mobile-apps'
itunes = json.load(open(sys.argv[1], encoding='utf-8')) if len(sys.argv) > 1 and os.path.exists(sys.argv[1]) else {}
bilder = json.load(open(sys.argv[2], encoding='utf-8')) if len(sys.argv) > 2 and os.path.exists(sys.argv[2]) else {}
for line in open(os.path.join(os.path.dirname(__file__), 'apps.tsv'), encoding='utf-8'):
    p = line.rstrip('\n').split('\t')
    kat, name, version = p[0], p[1], p[2]
    total, belegt, zuweisung = (p[3] if len(p) > 4 else ''), (p[4] if len(p) > 4 else ''), p[-1]
    lizenzen = ''
    if total:
        frei = int(total) - int(belegt or 0)
        lizenzen = f'{total} gesamt · {belegt} belegt · {frei} frei'
    hit = itunes.get(name) or {}
    ok = hit and hit.get('score', 0) >= 0.5
    preis = float(hit['price']) if ok and hit.get('price') is not None and hit.get('currency') == 'CHF' else None
    status, hinweis = 'aktiv', ''
    if name.startswith('zzz_'):
        status, hinweis = 'gestrichen', 'In Jamf als «to_delete» markiert.'
    if kat == '_Test':
        hinweis = 'Test-Kategorie in Jamf.'
    if zuweisung == 'einzelne Person' or zuweisung.endswith('einzelne Person'):
        hinweis = 'Einer einzelnen Person zugewiesen (Angabe nicht übernommen).'
    if not ok:
        hinweis = (hinweis + ' ' if hinweis else '') + 'Im App Store nicht eindeutig gefunden – Bild und Preis ergänzen.'
    angaben = '; '.join(x for x in [f'Version: {version}', f'Zuweisung: {zuweisung}' if zuweisung else '',
                                    f'App Store: {hit.get("trackName")} ({hit.get("sellerName")})' if ok else ''] if x)
    add(k, name, gruppe=kat if kat != 'No category assigned' else 'Ohne Kategorie', modell='', preis=preis,
        kosten='kostenlos' if preis == 0 else '', preis_hinweis='App-Store-Preis (Schweiz)' if preis is not None else '',
        lizenzen=lizenzen, nutzerkreis=zuweisung, url=hit.get('trackViewUrl', '') if ok else '',
        bild=bilder.get(name, ''), angaben=angaben, status=status, pruef_hinweis=hinweis)

# Bilder der Hardware (Herstellerseiten, auf 400 px verkleinert, ohne Personen)
HW_BILDER = {
    'MacBook Air M4 (2025)': ('hw-macbook-air-m4.jpg', 'apple.com'),
    'iPad (A16) 128 GB WiFi': ('hw-ipad-a16.jpg', 'apple.com'),
    'Logitech Pebble 2 M350s': ('hw-pebble-2-m350s.jpg', 'logitech.com'),
    'Logitech Combo Touch for iPad': ('hw-combo-touch-ipad.jpg', 'logitech.com'),
    'Logitech Rugged Combo 4': ('hw-rugged-combo-4.jpg', 'logitech.com'),
    'Logitech MX Keys S Combo': ('hw-mx-keys-s-combo.jpg', 'logitech.com'),
    'Logitech BRIO 4K': ('hw-brio-4k.jpg', 'logitech.com'),
    'Logitech Zone 305': ('hw-zone-305.jpg', 'logitech.com'),
}
for a in artikel:
    for modell, (datei, quelle) in HW_BILDER.items():
        if a['modell'].startswith(modell):
            a['bild'] = 'start:' + datei
            a['angaben'] = '; '.join(x for x in [a['angaben'], f'Bild: {quelle}'] if x)

out = sys.argv[3] if len(sys.argv) > 3 else 'artikel.json'
json.dump(artikel, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
from collections import Counter
print(len(artikel), dict(Counter(a['katalog'] for a in artikel)))
print('prüfen:', sum(a['pruefen'] for a in artikel))
