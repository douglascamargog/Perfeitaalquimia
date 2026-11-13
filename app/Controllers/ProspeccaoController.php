<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Integrations\Integracoes;
use App\Models\Listas;
use App\Services\ProspeccaoService;
use RuntimeException;

/**
 * Comercial › Prospecção
 *  - Para abordar: a fila única de leads (qualquer origem), conversão por origem e abordagens da equipe.
 *  - Encontrar empresas: base de CNPJs dos CNAEs do nosso público, marcando quem já está na carteira;
 *    as escolhidas vão para o funil.
 */
class ProspeccaoController extends Controller
{
    private const ESTILOS = ['carteira.css', 'inicio.css', 'painel.css', 'prospeccao.css'];

    /** Aba "Para abordar" */
    public function index(): void
    {
        $f = ProspeccaoService::filtrosFila($_GET);
        $this->tela('prospeccao/fila', [
            'titulo' => 'Prospecção', 'f' => $f, 'p' => ProspeccaoService::painelFila($f, (int) ($_GET['pagina'] ?? 1)),
            'origens' => Listas::origens(), 'cidades' => array_values(array_unique(array_column(Listas::cidadesComClientes(''), 'cidade'))), 'prioridades' => Listas::prioridades(),
            'vendedores' => Auth::pode('carteira.ver_todas') ? Listas::vendedores() : [],
            'estilos' => self::ESTILOS, 'scripts' => ['carteira.js', 'busca_contatos.js'],
        ]);
    }

    /** Aba "Encontrar empresas" (base de CNPJs) */
    public function empresas(): void
    {
        $f = ProspeccaoService::filtrosDaUrl($_GET);
        $this->tela('prospeccao/empresas', [
            'titulo' => 'Prospecção', 'f' => $f, 'r' => ProspeccaoService::buscar($f, (int) ($_GET['pagina'] ?? 1)),
            'resumo' => ProspeccaoService::resumo($f['uf']), 'cnaes' => ProspeccaoService::cnaes(), 'ufs' => Integracoes::baseCnpj()->ufs(),
            'bairros' => $f['cidade'] !== '' ? array_column(Banco::todos('SELECT DISTINCT bairro FROM prospeccao_empresas WHERE uf = ? AND cidade = ? AND bairro IS NOT NULL ORDER BY bairro', [$f['uf'], $f['cidade']]), 'bairro') : [],
            'podeEscolher' => Auth::pode('carteira.transferir'),
            'vendedores' => Auth::pode('carteira.transferir') ? Listas::vendedores() : [],
            'estilos' => self::ESTILOS, 'scripts' => ['carteira.js', 'busca_contatos.js', 'prospeccao.js'],
        ]);
    }

    public function enviar(): void
    {
        $voltar = '/prospeccao/empresas' . (($q = (string) ($_POST['voltar'] ?? '')) !== '' && str_starts_with($q, '?') ? $q : '');
        $responsavel = Auth::id();
        if (Auth::pode('carteira.transferir')) {
            $escolha = (string) ($_POST['responsavel'] ?? '');
            $validos = array_map(fn($v) => (int) $v['id'], Listas::vendedores());
            $responsavel = $escolha === 'fila' ? null : (in_array((int) $escolha, $validos, true) ? (int) $escolha : Auth::id());
        }
        try {
            $r = ProspeccaoService::enviarParaFunil((array) ($_POST['cnpjs'] ?? []), $responsavel, (int) Auth::id());
            $msg = "{$r['criados']} lead(s) criado(s) no funil";
            $msg .= $r['existentes'] ? " · {$r['existentes']} já estavam na carteira" : '';
            Sessao::aviso($r['criados'] ? 'sucesso' : 'aviso', $msg . '.');
            if ($r['criados']) {
                Sessao::set('prospeccao_ultimos', $r['ids']);
            }
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar($voltar);
    }
}
