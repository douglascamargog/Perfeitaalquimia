// =====================================================================
// "Buscar contatos com IA" (ficha do cliente e caso de cobrança)
// e "Enviar para cobrança" / copiar mensagens prontas.
// O conteúdo da gaveta é carregado depois da página, por isso tudo usa delegação de eventos.
// =====================================================================

const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const avisar = (texto, tipo) => window.Carteira?.avisar(texto, tipo);

function escapar(texto) {
  return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/** Troca a carinha do One (pensando, ideia, ops...) */
function carinha(caixa, nome) {
  const img = caixa.querySelector('[data-one-img]');
  if (img) img.src = img.src.replace(/one-[a-z]+-padrao\.svg/, `one-${nome}-padrao.svg`);
}

async function chamar(url, opcoes = {}) {
  const resposta = await fetch(BASE + url, { headers: { Accept: 'application/json', 'X-CSRF-Token': CSRF }, ...opcoes });
  if (resposta.redirected && resposta.url.includes('/entrar')) { window.location.href = resposta.url; throw new Error('Sessão expirada.'); }
  const dados = await resposta.json().catch(() => ({ ok: false, mensagem: 'Resposta inválida do servidor.' }));
  if (!dados.ok) throw new Error(dados.mensagem || 'Não foi possível concluir.');
  return dados;
}

const CONFIANCA = { alta: 'Confiança alta', media: 'Confiança média', baixa: 'Confiança baixa' };

/** Monta a lista de sugestões com caixinhas para marcar */
function mostrarResultado(caixa, busca) {
  const area = caixa.querySelector('[data-resultado]');
  if (!busca) { area.innerHTML = '<p class="pequeno suave">Ainda não houve busca para esta empresa.</p>'; return; }
  const linhas = busca.contatos.map((c) => `
    <label class="busca-ia-item ${c.igual ? 'igual' : ''}">
      <input type="checkbox" name="itens[]" value="${c.indice}" ${c.igual || c.confianca === 'baixa' || c.tipo === 'endereco' ? '' : 'checked'} ${c.igual ? 'disabled' : ''}>
      <span class="busca-ia-texto">
        <span class="busca-ia-tipo">${escapar(c.rotulo)}</span>
        <strong>${escapar(c.valor)}</strong>
        <small>
          ${c.fonte ? `<a href="${escapar(c.fonte)}" target="_blank" rel="noopener noreferrer">${escapar(c.fonte_nome || 'fonte')}</a>` : escapar(c.fonte_nome)}
          · <span class="conf-${escapar(c.confianca)}">${CONFIANCA[c.confianca] || ''}</span>
          ${c.igual ? ' · <b>já está no cadastro</b>' : (c.atual ? ` · hoje: ${escapar(c.atual)}` : '')}
        </small>
        ${c.observacao ? `<small class="suave">${escapar(c.observacao)}</small>` : ''}
      </span>
    </label>`).join('');
  area.innerHTML = `
    <form class="busca-ia-lista" data-aplicar>
      <input type="hidden" name="busca_id" value="${busca.id}">
      <p class="pequeno">${busca.repetida ? '<b>Busca recente</b> (não foi gasta uma busca nova): ' : ''}${escapar(busca.criado_em)}${busca.usuario ? ' · ' + escapar(busca.usuario) : ''}${busca.demonstracao ? ' · <span class="selo laranja">demonstração</span>' : ''}</p>
      ${busca.resumo ? `<p class="busca-ia-resumo">${escapar(busca.resumo)}</p>` : ''}
      ${linhas || '<p class="pequeno suave">Nenhum contato encontrado.</p>'}
      ${linhas ? '<button class="botao pequeno" type="submit">Salvar marcados no cadastro</button> <small class="suave">Confira antes: a IA pode errar. O valor antigo fica na linha do tempo.</small>' : ''}
    </form>`;
}

document.addEventListener('click', async (evento) => {
  // Buscar
  const botao = evento.target.closest('[data-busca-contatos] [data-buscar], [data-busca-contatos] [data-ver-ultima]');
  if (botao) {
    const caixa = botao.closest('[data-busca-contatos]');
    const area = caixa.querySelector('[data-resultado]');
    const nova = botao.hasAttribute('data-buscar');
    botao.disabled = true;
    carinha(caixa, 'pensando');
    caixa.classList.add('procurando');
    area.innerHTML = nova ? '<p class="busca-ia-procurando">O One está procurando na internet… <small>(a busca real pode levar até 1 minuto)</small></p>' : '<p class="pequeno suave">Carregando…</p>';
    try {
      const dados = await chamar(`/clientes/${caixa.dataset.buscaContatos}/contatos-ia`, nova ? { method: 'POST', body: new URLSearchParams({ _csrf: CSRF }) } : {});
      mostrarResultado(caixa, dados.busca);
      carinha(caixa, dados.busca?.contatos?.length ? 'ideia' : 'pensando');
    } catch (erro) {
      area.innerHTML = `<p class="aviso erro">${escapar(erro.message)}</p>`;
      carinha(caixa, 'ops');
    } finally {
      botao.disabled = false;
      caixa.classList.remove('procurando');
    }
    return;
  }

  // Copiar mensagem pronta
  const copiar = evento.target.closest('[data-copiar-texto]');
  if (copiar) {
    try { await navigator.clipboard.writeText(copiar.dataset.copiarTexto); avisar('Mensagem copiada.'); }
    catch { window.prompt('Copie a mensagem:', copiar.dataset.copiarTexto); }
  }
});

// Salvar os contatos marcados
document.addEventListener('submit', async (evento) => {
  const form = evento.target.closest('form[data-aplicar]');
  if (!form) return;
  evento.preventDefault();
  const caixa = form.closest('[data-busca-contatos]');
  const corpo = new FormData(form);
  corpo.append('_csrf', CSRF);
  form.querySelector('button[type="submit"]').disabled = true;
  try {
    const dados = await chamar(`/clientes/${caixa.dataset.buscaContatos}/contatos-ia/aplicar`, { method: 'POST', body: corpo });
    avisar(dados.mensagem);
    carinha(caixa, 'feliz');
    // Recarrega a ficha/caso para mostrar os contatos novos e a linha do tempo
    setTimeout(() => window.Carteira?.recarregar(), 900);
  } catch (erro) {
    avisar(erro.message, 'erro');
    form.querySelector('button[type="submit"]').disabled = false;
  }
});
