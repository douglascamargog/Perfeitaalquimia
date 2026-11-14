<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\DesenvolvimentoService as D;
use RuntimeException;

/**
 * Desenvolvimento de Produto: quadro de projetos, minhas tarefas, página do projeto e as ações de cada etapa.
 * Cada etapa é concluída por quem é da função dela (Técnica, Marketing, Qualidade, Produção); o serviço confere.
 */
class DesenvolvimentoController extends Controller
{
    private const ESTILOS = ['carteira.css', 'produtos.css', 'desenvolvimento.css'];

    public function index(): void
    {
        $f = ['tipo' => (string) ($_GET['tipo'] ?? ''), 'situacao' => (string) ($_GET['situacao'] ?? ''), 'atrasados' => !empty($_GET['atrasados'])];
        $this->tela('desenvolvimento/index', [
            'titulo' => 'Desenvolvimento de Produto', 'etapas' => D::etapas(), 'projetos' => D::projetos($f), 'f' => $f,
            'categorias' => Banco::todos('SELECT id, nome FROM categorias ORDER BY nome'),
            'clientesTerc' => Banco::todos("SELECT DISTINCT c.id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS nome FROM clientes c
                JOIN funcoes f ON f.id = c.funcao_id WHERE f.slug = 'terceirizacao' AND c.excluido_em IS NULL ORDER BY nome LIMIT 500"),
            'podeGerenciar' => Auth::pode('desenvolvimento.gerenciar'), 'estilos' => self::ESTILOS, 'scripts' => ['carteiras.js'],
        ]);
    }

    public function minhas(): void
    {
        $this->tela('desenvolvimento/minhas', ['titulo' => 'Minhas tarefas · Desenvolvimento', 'projetos' => D::minhas(), 'estilos' => self::ESTILOS]);
    }

    public function ver(string $id): void
    {
        $p = $this->projeto($id);
        $this->tela('desenvolvimento/ver', [
            'titulo' => $p['nome'], 'p' => $p, 'linha' => D::linhaDoProjeto((int) $p['id']), 'historico' => D::historico((int) $p['id']),
            'falta' => $p['situacao'] === 'andamento' ? D::pendencias($p) : [], 'podeConcluir' => $p['situacao'] === 'andamento' && D::podeConcluir($p),
            'aprovacoes' => $p['checagem'] === 'aprovacao' ? D::aprovacoesDaRodada((int) $p['id'], (int) $p['etapa_id']) : [],
            'ficha' => Banco::todos('SELECT ft.materia_prima_id, m.nome, m.unidade, m.tipo, m.estoque FROM ficha_tecnica ft JOIN materias_primas m ON m.id = ft.materia_prima_id
                WHERE ft.produto_id = ? ORDER BY m.tipo, m.nome', [$p['produto_id']]),
            'ordem' => $p['ordem_id'] ? Banco::um('SELECT id, quantidade, situacao FROM ordens_producao WHERE id = ?', [$p['ordem_id']]) : null,
            'podeGerenciar' => Auth::pode('desenvolvimento.gerenciar'), 'estilos' => self::ESTILOS,
        ]);
    }

    public function criar(): void
    {
        try {
            $id = D::criar($_POST, Auth::id());
            Sessao::aviso('sucesso', 'Projeto criado. O produto já existe (oculto) para as áreas preencherem.');
            redirecionar("/desenvolvimento/$id");
        } catch (RuntimeException $e) {
            $this->voltarComErros('/desenvolvimento', [$e->getMessage()]);
        }
    }

    /** Ações da página do projeto: concluir, aprovar, compras, gráfica, rótulos, piloto, voltar, situação, comentar */
    public function acao(string $id, string $acao): void
    {
        $p = $this->projeto($id);
        $pid = (int) $p['id'];
        $uid = Auth::id();
        try {
            $msg = match ($acao) {
                'concluir'  => (function () use ($pid, $uid) { D::concluir($pid, $uid, $this->campo('observacao'), isset($_POST['manual'])); return 'Etapa concluída.'; })(),
                'aprovar'   => D::aprovar($pid, $this->campo('decisao') === 'ajustar' ? 'ajustar' : 'aprovado', $this->campo('comentario'), $uid),
                'codigo'    => (function () use ($pid, $uid) { $this->exigirFuncao('qualidade'); D::salvarCodigo($pid, $this->campo('ean'), $this->campo('ncm'), $uid); return 'Código de barras e NCM salvos.'; })(),
                'arte'      => (function () use ($pid, $uid) { $this->exigirFuncao('marketing'); D::enviarArquivo($pid, 'rotulo', $_FILES['arquivo'] ?? [], $uid); return 'Arte do rótulo enviada.'; })(),
                'prova'     => (function () use ($pid, $uid) { $this->exigirFuncao('qualidade'); D::enviarArquivo($pid, 'prova', $_FILES['arquivo'] ?? [], $uid); return 'Prova da gráfica anexada.'; })(),
                'compra'    => (function () use ($pid, $uid) { $this->exigirFuncao('qualidade'); D::marcarCompra($pid, (int) $this->campo('mp'), $this->campo('comprado') === '1', $uid); return 'Compra atualizada.'; })(),
                'grafica'   => (function () use ($pid, $uid) { $this->exigirFuncao('qualidade'); D::grafica($pid, $this->campo('grafica'), $this->campo('data'), $uid); return 'Pedido à gráfica registrado.'; })(),
                'rotulos'   => (function () use ($pid, $uid) { $this->exigirFuncao('qualidade'); D::receberRotulos($pid, (int) $this->campo('quantidade'), $uid); return 'Rótulos recebidos.'; })(),
                'piloto'    => (function () use ($pid, $uid) { $this->exigirFuncao('producao'); D::criarPiloto($pid, (int) $this->campo('quantidade'), $uid); return 'Ordem do lote piloto criada.'; })(),
                'voltar'    => (function () use ($pid, $uid) { $this->exigirGerenciar(); D::voltar($pid, $uid, $this->campo('motivo')); return 'Projeto voltou uma etapa.'; })(),
                'situacao'  => (function () use ($pid, $uid) { $this->exigirGerenciar(); D::situacao($pid, $this->campo('situacao'), $uid); return 'Situação atualizada.'; })(),
                'comentar'  => (function () use ($pid, $uid) { D::comentar($pid, $this->campo('texto'), $uid); return 'Comentário registrado.'; })(),
                default     => throw new RuntimeException('Ação inválida.'),
            };
            Sessao::aviso('sucesso', $msg);
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/desenvolvimento/$pid");
    }

    /** Configurações › Etapas do desenvolvimento (admin) */
    public function etapas(): void
    {
        $this->tela('desenvolvimento/etapas', ['titulo' => 'Etapas do desenvolvimento', 'etapas' => D::etapas(false),
            'funcoes' => Banco::todos('SELECT slug, nome FROM funcoes WHERE ativa = 1 ORDER BY ordem, nome'), 'estilos' => self::ESTILOS]);
    }

    public function salvarEtapas(): void
    {
        foreach ((array) ($_POST['etapa'] ?? []) as $id => $e) {
            Banco::executar('UPDATE dp_etapas SET nome = ?, ordem = ?, funcao_slug = ?, aprovadores = ?, ativa = ? WHERE id = ?', [
                mb_substr(trim((string) ($e['nome'] ?? '')), 0, 120) ?: 'Etapa', (int) ($e['ordem'] ?? 0), ($e['funcao'] ?? '') ?: null,
                implode(',', array_filter((array) ($e['aprovadores'] ?? []))) ?: null, !empty($e['ativa']) ? 1 : 0, (int) $id]);
        }
        $nova = trim((string) ($_POST['nova'] ?? ''));
        if ($nova !== '') {
            Banco::inserir('dp_etapas', ['nome' => mb_substr($nova, 0, 120), 'ordem' => (int) Banco::valor('SELECT COALESCE(MAX(ordem), 0) + 1 FROM dp_etapas')]);
        }
        \App\Core\Auditoria::registrar('desenvolvimento', null, 'etapas');
        Sessao::aviso('sucesso', 'Etapas salvas.');
        redirecionar('/desenvolvimento/etapas');
    }

    // ------------------------------------------------------------------

    private function projeto(string $id): array
    {
        $p = D::buscar((int) $id);
        if (!$p) {
            Sessao::aviso('erro', 'Projeto não encontrado.');
            redirecionar('/desenvolvimento');
        }
        return $p;
    }

    private function exigirFuncao(string $slug): void
    {
        if (!Auth::pode('*') && !Auth::temFuncao($slug) && !Auth::pode('desenvolvimento.gerenciar')) {
            throw new RuntimeException('Esta parte é feita por ' . (Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$slug]) ?: $slug) . '.');
        }
    }

    private function exigirGerenciar(): void
    {
        if (!Auth::pode('desenvolvimento.gerenciar')) {
            throw new RuntimeException('Só quem gerencia o desenvolvimento pode fazer isso.');
        }
    }
}
