import re

html = '''<div class="info-grid">
        <div class="info-box"><label>Razão Social</label><p>BANCO DO BRASIL SA</p></div>'''

label = "Razão Social"
patterns = [
    rf'<label>{re.escape(label)}</label>\s*<p>(.*?)</p>',
    rf'<(?:div|span|label)[^>]*>{re.escape(label)}</(?:div|span|label)>\s*<p>(.*?)</p>',
]

for p in patterns:
    m = re.search(p, html, re.DOTALL | re.IGNORECASE)
    print(f"Pattern: {p}")
    if m:
        print(f"Match: {m.group(1)}")
    else:
        print("No match")
