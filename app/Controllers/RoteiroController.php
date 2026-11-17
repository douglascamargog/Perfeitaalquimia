<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\RoteiroService as R;

/**
 * Planejador de visitas: roteiros de viagem do vendedor externo (lojas de qualquer carteira no caminho).
 * As regras ficam no RoteiroService.
 */
class RoteiroController extends Controller
{
    private const ESTILOS = ['carteira.css', 'roteiros.css', 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css'];
    private const SCRIPTS = ['https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js', 'roteiros.js'];

    public function index(): void
    {
        $gerencia = Auth::pode('roteiros.gerenciar');
        $pessoa = $gerencia ? ((int) ($_GET['pessoa'] ?? 0) ?: null) : (int) Auth::id();
        $this->tela('roteiros/index', [
            'titulo' => 'Planejador de visitas',
            'roteiros' => R::lista($gerencia && !isset($_GET['pessoa']) ? null : $pessoa),
            'gerencia' => $gerencia, 'pessoa' => $pessoa,
            'pessoas' => $gerencia ? Banco::todos('SELECT DISTINCT u.id, u.nome FROM roteiros r JOIN usuarios u ON u.id = r.usuario_id ORDER BY u.nome') : [],
            'estilos' => ['carteira.css', 'roteiros.css'],
        ]);
    }

    public function novo(): void
    {
        $this->formulario(null);
    }

    public function editar(string $id): void
    {
        $this->formulario($this->roteiro($id, true));
    }

    private function formulario(?array $r): void
    {
        $this->tela('roteiros/form', [
            'titulo' => $r ? 'Editar roteiro' : 'Novo roteiro', 'r' => $r,
            'conhecidas' => R::cidadesConhecidas(),
            'estilos' => ['carteira.css', 'roteiros.css'], 'scripts' => ['roteiros.js'],
        ]);
    }

    public function criar(): void
    {
        try {
            $id = R::salvar(null, $_POST, (int) Auth::id());
        } catch (\RuntimeException $e) {
            Sessao::guardarFormulario($_POST);
            Sessao::aviso('erro', $e->getMessage());
            redirecionar('/roteiros/novo');
        }
        Sessao::aviso('sucesso', 'Roteiro criado. Agora escolha as lojas no caminho.');
        redirecionar("/roteiros/$id?aba=caminho");
    }

    public function salvar(string $id): void
    {
        $r = $this->roteiro($id, true);
        try {
            R::salvar((int) $r['id'], $_POST, (int) $r['usuario_id']);
            R::organizar(R::buscar((int) $r['id']));
        } catch (\RuntimeException $e) {
            Sessao::guardarFormulario($_POST);
            Sessao::aviso('erro', $e->getMessage());
            redirecionar("/roteiros/{$r['id']}/editar");
        }
        Sessao::aviso('sucesso', 'Roteiro atualizado.');
        redirecionar("/roteiros/{$r['id']}");
    }

    public function ver(string $id): void
    {
        $r = R::buscar((int) $id);
        // Quem não é dono nem gerencia só abre se for dona de alguma loja do roteiro (vê só as dela)
        if (!$r || (!R::podeVer($r) && !Banco::valor('SELECT 1 FROM roteiro_paradas p JOIN clientes c ON c.id = p.cliente_id WHERE p.roteiro_id = ? AND c.responsavel_id = ?', [$r['id'], Auth::id()]))) {
            $this->negar();
        }
        $aba = in_array($_GET['aba'] ?? '', ['dias', 'caminho'], true) ? $_GET['aba'] : 'dias';
        $podeMexer = R::podeMexer($r) && $r['situacao'] === 'planejado';
        $paradas = R::paradas($r);
        $filtros = ['so' => ($_GET['so'] ?? 'sugeridas') === 'todas' ? 'todas' : 'sugeridas', 'tipo' => in_array($_GET['tipo'] ?? '', ['cliente', 'lead'], true) ? $_GET['tipo'] : '',
            'vendedor' => (int) ($_GET['vendedor'] ?? 0)];
        $this->tela('roteiros/ver', [
            'titulo' => $r['nome'], 'r' => $r, 'aba' => $aba, 'podeMexer' => $podeMexer, 'soLeitura' => !R::podeVer($r),
            'paradas' => $paradas, 'dias' => R::dias($r), 'filtros' => $filtros,
            'caminho' => $aba === 'caminho' && R::podeVer($r) ? R::lojasNoCaminho($r, $filtros) : null,
            'vendedores' => \App\Models\Listas::vendedores(),
            'links' => R::linksGoogleMaps($paradas),
            'estilos' => self::ESTILOS, 'scripts' => self::SCRIPTS,
        ]);
    }

    public function adicionar(string $id): void
    {
        $r = $this->roteiro($id, true);
        $n = R::adicionar($r, (array) ($_POST['clientes'] ?? []), (int) $r['usuario_id']);
        Sessao::aviso($n ? 'sucesso' : 'aviso', $n ? "$n loja(s) no roteiro, organizadas pelo caminho. A dona de cada loja foi avisada." : 'Nenhuma loja nova (marque as lojas ou elas já estavam no roteiro).');
        redirecionar("/roteiros/{$r['id']}" . (($_POST['voltar'] ?? '') === 'caminho' ? '?aba=caminho' : ''));
    }

    public function organizar(string $id): void
    {
        $r = $this->roteiro($id, true);
        R::organizar($r, true);
        Sessao::aviso('sucesso', 'Paradas reorganizadas pelo caminho e divididas pelos dias.');
        redirecionar("/roteiros/{$r['id']}");
    }

    public function situacao(string $id): void
    {
        $r = $this->roteiro($id, false);
        if (!R::podeMexer($r)) {
            $this->negar();
        }
        match ($_POST['acao'] ?? '') {
            'concluir' => R::concluir($r),
            'reabrir' => R::concluir($r, false),
            'cancelar' => R::cancelar($r),
            default => null,
        };
        Sessao::aviso('sucesso', ['concluir' => 'Roteiro concluído.', 'reabrir' => 'Roteiro reaberto.', 'cancelar' => 'Roteiro cancelado.'][$_POST['acao'] ?? ''] ?? 'Nada mudou.');
        redirecionar('/roteiros/' . $r['id']);
    }

    public function visita(string $id, string $parada): void
    {
        $r = $this->roteiro($id, true);
        try {
            R::registrarVisita($r, (int) $parada, (string) ($_POST['situacao'] ?? ''), (string) ($_POST['observacao'] ?? ''), (int) Auth::id());
            Sessao::aviso('sucesso', 'Visita registrada (vai para a linha do tempo da loja).');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/roteiros/{$r['id']}#parada-" . (int) $parada);
    }

    public function dia(string $id, string $parada): void
    {
        $r = $this->roteiro($id, true);
        try {
            R::mudarDia($r, (int) $parada, (string) ($_POST['dia'] ?? ''));
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/roteiros/{$r['id']}#parada-" . (int) $parada);
    }

    public function remover(string $id, string $parada): void
    {
        $r = $this->roteiro($id, true);
        R::remover($r, (int) $parada);
        Sessao::aviso('sucesso', 'Loja tirada do roteiro.');
        redirecionar("/roteiros/{$r['id']}");
    }

    /** Busca de lojas de QUALQUER carteira para pôr no roteiro (só nome, cidade e vendedora) */
    public function buscarLojas(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            $this->json(['lojas' => []]);
            return;
        }
        $like = '%' . $q . '%';
        $digitos = preg_replace('/\D/', '', $q);
        $this->json(['lojas' => Banco::todos("SELECT c.id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS nome, c.cidade, c.uf, u.nome AS vendedor
            FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_id
            WHERE c.excluido_em IS NULL AND (c.razao_social LIKE ? OR c.nome_fantasia LIKE ? OR c.cidade LIKE ?" . (strlen($digitos) >= 4 ? ' OR c.cnpj LIKE ?' : '') . ')
            ORDER BY nome LIMIT 20', strlen($digitos) >= 4 ? [$like, $like, $like, "%$digitos%"] : [$like, $like, $like])]);
    }

    /** Roteiro que a pessoa pode ver (e, se $mexer, alterar: só o dono, com o roteiro em andamento) */
    private function roteiro(string $id, bool $mexer): array
    {
        $r = R::buscar((int) $id);
        if (!$r || !R::podeVer($r) || ($mexer && (!R::podeMexer($r) || $r['situacao'] !== 'planejado'))) {
            $this->negar();
        }
        return $r;
    }

    private function negar(): never
    {
        http_response_code(403);
        \App\Core\Visao::mostrar('erros/erro', ['titulo' => 'Roteiro', 'codigo' => 403, 'mensagem' => 'Este roteiro não é seu (ou já foi concluído).'], 'base');
        exit;
    }
}
