/* =====================================================================
   Formulário de leads do One para colocar no site da empresa.
   Uso: <form data-one-lead>...</form> + <script src=".../captura-leads.js" data-endpoint="https://.../leads/receber" defer></script>
   - Lê as UTMs (e gclid/fbclid) da URL e guarda na sessão do navegador (vale para as próximas páginas).
   - Envia em JSON para o One; mostra a resposta em [data-one-resposta].
   - Proteção contra robôs: campo escondido + tempo de preenchimento.
   ===================================================================== */
(function () {
  var script = document.currentScript || document.querySelector('script[data-endpoint]');
  var endpoint = script && script.getAttribute('data-endpoint');
  if (!endpoint) return;
  var CAMPOS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'];
  var params = new URLSearchParams(location.search);
  var guardado = {};
  try { guardado = JSON.parse(sessionStorage.getItem('one_utm') || '{}'); } catch (e) { guardado = {}; }
  CAMPOS.forEach(function (c) { if (params.get(c)) guardado[c] = params.get(c); });
  try { sessionStorage.setItem('one_utm', JSON.stringify(guardado)); } catch (e) { /* navegador sem armazenamento */ }
  var inicio = Math.floor(Date.now() / 1000);

  document.querySelectorAll('form[data-one-lead]').forEach(function (form) {
    var robo = document.createElement('input');
    robo.type = 'text'; robo.name = 'site_url'; robo.tabIndex = -1; robo.autocomplete = 'off';
    robo.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;opacity:0';
    robo.setAttribute('aria-hidden', 'true');
    form.appendChild(robo);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var dados = Object.fromEntries(new FormData(form).entries());
      Object.assign(dados, guardado, { inicio: inicio, pagina: location.href.split('?')[0] });
      var resposta = form.querySelector('[data-one-resposta]');
      var botao = form.querySelector('[type=submit]');
      if (botao) botao.disabled = true;
      fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados) })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (resposta) resposta.textContent = r.mensagem || (r.ok ? 'Recebemos seu contato!' : 'Confira os dados.');
          if (r.ok) form.reset();
        })
        .catch(function () { if (resposta) resposta.textContent = 'Não foi possível enviar agora. Tente de novo.'; })
        .finally(function () { if (botao) botao.disabled = false; });
    });
  });
})();
