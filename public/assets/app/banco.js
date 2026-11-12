// =====================================================================
// IndexedDB do app: guarda no celular o pacote de dados (carteira, produtos, preços)
// e a fila de pedidos/visitas. Assim o app funciona sem internet.
// =====================================================================
const NOME = 'one-vendas';
let conexao = null;

function abrir() {
  if (conexao) return conexao;
  conexao = new Promise((ok, falha) => {
    const req = indexedDB.open(NOME, 1);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains('dados')) db.createObjectStore('dados');
      if (!db.objectStoreNames.contains('fila')) db.createObjectStore('fila', { keyPath: 'uuid' });
    };
    req.onsuccess = () => ok(req.result);
    req.onerror = () => falha(req.error);
  });
  return conexao;
}

async function operar(loja, modo, acao) {
  const db = await abrir();
  return new Promise((ok, falha) => {
    const tx = db.transaction(loja, modo);
    const req = acao(tx.objectStore(loja));
    tx.oncomplete = () => ok(req?.result);
    tx.onerror = () => falha(tx.error);
  });
}

export const lerPacote = () => operar('dados', 'readonly', (s) => s.get('pacote'));
export const guardarPacote = (pacote) => operar('dados', 'readwrite', (s) => s.put(pacote, 'pacote'));
export const listarFila = () => operar('fila', 'readonly', (s) => s.getAll());
export const salvarItem = (item) => operar('fila', 'readwrite', (s) => s.put(item));
export const removerItem = (uuid) => operar('fila', 'readwrite', (s) => s.delete(uuid));

/** "Sair": apaga tudo o que está no celular */
export async function apagarTudo() {
  await operar('dados', 'readwrite', (s) => s.clear());
  await operar('fila', 'readwrite', (s) => s.clear());
}
