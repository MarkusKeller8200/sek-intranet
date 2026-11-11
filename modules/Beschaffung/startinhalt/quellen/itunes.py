# App Store (Schweiz) nach Symbol, Preis und Link durchsuchen: python3 itunes.py <namen.txt> <ergebnis.json>
# Apple begrenzt die Anfragen stark (HTTP 403) – Abstand mindestens 10 Sekunden.
import json, sys, time, re, unicodedata, urllib.parse, urllib.request, os
src, out = sys.argv[1], sys.argv[2]
names = [l.rstrip('\n') for l in open(src, encoding='utf-8') if l.strip()]
res = json.load(open(out)) if os.path.exists(out) else {}
def norm(s):
    s = unicodedata.normalize('NFKD', s.lower())
    s = ''.join(c for c in s if c.isalnum() or c == ' ')
    return set(w for w in s.split() if len(w) > 1)
for n in names:
    if n in res: continue
    term = re.sub(r'[®™•:|()]', ' ', n)
    url = 'https://itunes.apple.com/search?' + urllib.parse.urlencode({'term': term, 'country': 'ch', 'entity': 'software,iPadSoftware', 'limit': 8, 'lang': 'de_de'})
    data = None
    for attempt in range(8):
        try:
            data = json.load(urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'curl/8.5.0'}), timeout=20)); break
        except Exception as e:
            print('warte', n, e, flush=True); time.sleep(90)
    if data is None:
        continue
    best, score = None, 0.0
    for r in (data or {}).get('results', []):
        a, b = norm(n), norm(r.get('trackName', ''))
        s = len(a & b) / max(1, len(a | b)) if a and b else 0
        if n.lower()[:6] == r.get('trackName', '').lower()[:6]: s += 0.3
        if s > score: best, score = r, s
    res[n] = None if best is None else {k: best.get(k) for k in ['trackId','trackName','sellerName','artworkUrl100','artworkUrl512','price','formattedPrice','currency','trackViewUrl']} | {'score': round(score, 2)}
    json.dump(res, open(out, 'w'), ensure_ascii=False, indent=1)
    time.sleep(10)
print('fertig', len(res))
