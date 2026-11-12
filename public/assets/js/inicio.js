// =====================================================================
// Início: previsão do tempo (Open-Meteo — grátis, sem chave, sem dados pessoais).
// A cidade vem de Configurações › Início. Guarda a previsão por 30 min no navegador.
// =====================================================================
const card = document.querySelector('[data-clima]');

// Código do tempo (padrão WMO) → [texto, ícone]
const TEMPO = {
  0: ['Céu limpo', 'sol'], 1: ['Poucas nuvens', 'sol-nuvem'], 2: ['Parcialmente nublado', 'sol-nuvem'], 3: ['Nublado', 'nuvem'],
  45: ['Neblina', 'neblina'], 48: ['Neblina', 'neblina'],
  51: ['Garoa fraca', 'chuva'], 53: ['Garoa', 'chuva'], 55: ['Garoa forte', 'chuva'], 56: ['Garoa gelada', 'chuva'], 57: ['Garoa gelada', 'chuva'],
  61: ['Chuva fraca', 'chuva'], 63: ['Chuva', 'chuva'], 65: ['Chuva forte', 'chuva'], 66: ['Chuva gelada', 'chuva'], 67: ['Chuva gelada', 'chuva'],
  71: ['Neve fraca', 'neve'], 73: ['Neve', 'neve'], 75: ['Neve forte', 'neve'], 77: ['Neve', 'neve'],
  80: ['Pancadas de chuva', 'chuva'], 81: ['Pancadas de chuva', 'chuva'], 82: ['Temporal', 'tempestade'],
  85: ['Neve', 'neve'], 86: ['Neve', 'neve'], 95: ['Trovoadas', 'tempestade'], 96: ['Trovoadas com granizo', 'tempestade'], 99: ['Trovoadas com granizo', 'tempestade'],
};

// Ícones desenhados aqui mesmo (SVG), sem biblioteca
const ICONES = {
  'sol': '<circle cx="12" cy="12" r="4.5" fill="#fbbf24"/><g stroke="#f59e0b" stroke-width="2" stroke-linecap="round"><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></g>',
  'sol-nuvem': '<circle cx="9" cy="9" r="4" fill="#fbbf24"/><path d="M8 19h9a4 4 0 0 0 0-8 5 5 0 0 0-9.6 1.5A3.3 3.3 0 0 0 8 19z" fill="#cbd5e1"/>',
  'nuvem': '<path d="M7 19h10a4.5 4.5 0 0 0 .2-9A6 6 0 0 0 5.7 11.4 3.8 3.8 0 0 0 7 19z" fill="#94a3b8"/>',
  'neblina': '<path d="M7 14h10a4 4 0 0 0 0-8 5.5 5.5 0 0 0-10.4 1.8A3.2 3.2 0 0 0 7 14z" fill="#cbd5e1"/><path d="M4 17h16M6 20h12" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>',
  'chuva': '<path d="M7 15h10a4 4 0 0 0 0-8 5.5 5.5 0 0 0-10.4 1.8A3.2 3.2 0 0 0 7 15z" fill="#94a3b8"/><path d="M8 18l-1 3M12 18l-1 3M16 18l-1 3" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>',
  'tempestade': '<path d="M7 14h10a4 4 0 0 0 0-8 5.5 5.5 0 0 0-10.4 1.8A3.2 3.2 0 0 0 7 14z" fill="#64748b"/><path d="M12.5 14l-2.5 4h3l-2 4" stroke="#f59e0b" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
  'neve': '<path d="M7 15h10a4 4 0 0 0 0-8 5.5 5.5 0 0 0-10.4 1.8A3.2 3.2 0 0 0 7 15z" fill="#cbd5e1"/><g fill="#93c5fd"><circle cx="8" cy="19" r="1.3"/><circle cx="12" cy="20.5" r="1.3"/><circle cx="16" cy="19" r="1.3"/></g>',
};
const icone = (nome, classe = 'icone-tempo') => `<svg class="${classe}" viewBox="0 0 24 24" aria-hidden="true">${ICONES[nome] || ICONES.nuvem}</svg>`;
const DIAS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

async function previsao(lat, lng) {
  const chave = `one-clima-${lat},${lng}`;
  try {
    const guardado = JSON.parse(localStorage.getItem(chave) || 'null');
    if (guardado && Date.now() - guardado.quando < 30 * 60 * 1000) return guardado.dados;
  } catch { /* navegador sem armazenamento: busca de novo */ }
  const url = 'https://api.open-meteo.com/v1/forecast?' + new URLSearchParams({
    latitude: lat, longitude: lng, timezone: 'America/Sao_Paulo', forecast_days: '5',
    current: 'temperature_2m,apparent_temperature,weather_code,relative_humidity_2m,wind_speed_10m',
    daily: 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
  });
  const resposta = await fetch(url);
  if (!resposta.ok) throw new Error('sem previsão');
  const dados = await resposta.json();
  try { localStorage.setItem(chave, JSON.stringify({ quando: Date.now(), dados })); } catch { /* sem espaço: tudo bem */ }
  return dados;
}

async function mostrarClima() {
  if (!card) return;
  const $ = (seletor) => card.querySelector(seletor);
  try {
    const d = await previsao(card.dataset.lat, card.dataset.lng);
    const [texto, nome] = TEMPO[d.current.weather_code] || ['—', 'nuvem'];
    $('[data-clima-temp]').textContent = Math.round(d.current.temperature_2m) + '°';
    $('[data-clima-texto]').textContent = texto;
    $('[data-clima-extra]').textContent = `Sensação ${Math.round(d.current.apparent_temperature)}° · umidade ${d.current.relative_humidity_2m}% · vento ${Math.round(d.current.wind_speed_10m)} km/h`;
    $('[data-clima-icone]').innerHTML = icone(nome);

    // Próximos 4 dias (o primeiro é hoje)
    $('[data-clima-dias]').innerHTML = d.daily.time.slice(0, 4).map((dia, i) => {
      const [, nomeDia] = TEMPO[d.daily.weather_code[i]] || ['', 'nuvem'];
      const rotulo = i === 0 ? 'hoje' : DIAS[new Date(dia + 'T12:00').getDay()];
      return `<li>${rotulo}${icone(nomeDia)}<b>${Math.round(d.daily.temperature_2m_max[i])}°</b>${Math.round(d.daily.temperature_2m_min[i])}°</li>`;
    }).join('');

    // Dica do dia para a equipe comercial
    const chuva = d.daily.precipitation_probability_max[0] ?? 0;
    const dica = $('[data-clima-dica]');
    if (chuva >= 60) dica.textContent = `☔ ${chuva}% de chance de chuva hoje: bom dia para televendas; visitas externas podem atrasar.`;
    else if (d.daily.temperature_2m_max[0] >= 32) dica.textContent = '🥵 Dia quente: lembre o cliente de hidratação e eletrólitos.';
    else if (d.daily.temperature_2m_min[0] <= 10) dica.textContent = '🧣 Dia frio: boa hora para falar de imunidade e vitaminas.';
    dica.hidden = !dica.textContent;
  } catch {
    $('[data-clima-texto]').textContent = 'Previsão indisponível agora';
    $('[data-clima-extra]').textContent = 'Sem conexão com o serviço do tempo.';
    $('[data-clima-icone]').innerHTML = icone('nuvem');
  }
}

mostrarClima();
