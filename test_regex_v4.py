import re

html = """
    <div class="partner-section">
        <div style="margin-bottom:2rem; opacity:0.6; font-weight:800; letter-spacing:2px; text-transform:uppercase; font-size:0.75rem;">Sugestão para seu negócio</div>
        <h2 style="font-size:3rem; margin-bottom:1rem; color:#fff;">Hostinger Brasil</h2>
        <div class="partner-grid">
            <div class="partner-card">
                <span class="badge ba" style="margin-bottom:1rem;">Hospedagem</span>
                <h3>Sites Profissionais</h3>
                <div class="partner-price">R$ 19,99<span>/mês</span></div>
                <a href="https://www.hostinger.com/br?REFERRALCODE=1VINICIUS74" class="btn-cta" target="_blank">Ativar Oferta</a>
            </div>
        </div>
    </div>
</div>
<footer>"""

RE_PARTNER = re.compile(r'<div class="partner-section">.*?</div>\s*(?=</div>\s*<footer>)', re.DOTALL)
match = RE_PARTNER.search(html)
if match:
    print("MATCH FOUND:")
    print(match.group(0))
else:
    print("NO MATCH")
