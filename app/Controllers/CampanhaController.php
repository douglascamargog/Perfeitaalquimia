<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Core\Sessao;
use App\Services\Aparencia;
use App\Services\CampanhaService as C;

/**
 * Comercial › Campanhas: o gestor monta as campanhas do mês ou da semana (faixas, brindes, frete, produtos em promoção,
 * público e visual do catálogo) e o visual padrão do catálogo. Quem só vê (campanhas.ver) abre em modo leitura.
 */
class CampanhaController extends Controller
{
    private const ESTILOS = ['campanhas.css'];

    public function index(): void
    {
        $aba = ($_GET['aba'] ?? '') === 'visual' ? 'visual' : 'campanhas';
        $this->tela('campanhas/index', [
            'titulo' => 'Campanhas', 'estilos' => self::ESTILOS, 'scripts' => ['campanhas.js'], 'aba' => $aba,
            'campanhas' => C::todas(), 'pode' => Auth::pode('campanhas.gerenciar'),
            'visual' => C::visual([]), 'banner' => (string) Config::get('catalogo.visual_banner', ''),
        ]);
    }

    public function nova(): void
    {
        $this->exigir();
        $hoje = date('Y-m-d');
        $this->formulario(['id' => null, 'nome' => '', 'titulo' => '', 'chamada' => '', 'selo' => '', 'inicio' => date('Y-m-01'), 'fim' => date('Y-m-t'),
            'ativa' => 1, 'publico' => 'todos', 'publico_valores' => '', 'soma_desconto_vendedor' => 1, 'cor_primaria' => '', 'cor_destaque' => '',
            'tema' => 'claro', 'banner' => null, 'faixas' => [], 'produtos' => []] + ['hoje' => $hoje]);
    }

    public function ver(string $id): void
    {
        $c = C::uma((int) $id);
        if (!$c) {
            redirecionar('/campanhas');
        }
        $this->formulario($c);
    }

    private function formulario(array $c): void
    {
        // Voltou com erro: mostra o que a pessoa tinha digitado (faixas e produtos inclusive)
        $velho = $_SESSION['_antigo'] ?? [];
        if (isset($velho['nome'])) {
            foreach (['nome', 'titulo', 'chamada', 'selo', 'inicio', 'fim', 'publico', 'cor_primaria', 'cor_destaque', 'tema'] as $k) {
                $c[$k] = (string) ($velho[$k] ?? '');
            }
            $c['ativa'] = !empty($velho['ativa']);
            $c['soma_desconto_vendedor'] = !empty($velho['soma_desconto_vendedor']);
            $c['publico_valores'] = implode(',', (array) ($velho['publico_valores'] ?? []));
            $c['faixas'] = [];
            foreach ((array) ($velho['faixa_valor'] ?? []) as $k => $v) {
                $c['faixas'][] = ['a_partir_de' => $v, 'tipo' => $velho['faixa_tipo'][$k] ?? 'desconto', 'desconto_pct' => $velho['faixa_pct'][$k] ?? '',
                    'brinde' => $velho['faixa_brinde'][$k] ?? '', 'brinde_midia_id' => $velho['faixa_brinde_midia'][$k] ?? null, 'brinde_imagem' => null];
            }
            $c['produtos'] = [];
            foreach ((array) ($velho['promo_produto'] ?? []) as $k => $pid) {
                $c['produtos'][] = ['produto_id' => $pid, 'desconto_pct' => $velho['promo_pct'][$k] ?? '', 'destaque' => !empty($velho['promo_destaque'][$k])];
            }
        }
        $this->tela('campanhas/form', [
            'titulo' => $c['id'] ? 'Campanha · ' . $c['nome'] : 'Nova campanha', 'estilos' => self::ESTILOS, 'scripts' => ['campanhas.js'],
            'c' => $c, 'pode' => Auth::pode('campanhas.gerenciar'),
            'tabelas' => Banco::todos('SELECT id, nome FROM tabelas_preco WHERE ativa = 1 ORDER BY id'),
            'regioes' => Banco::todos('SELECT id, nome FROM regioes ORDER BY nome'),
            'setores' => Banco::todos('SELECT id, nome FROM funcoes WHERE ativa = 1 AND setor_venda = 1 ORDER BY ordem, nome'),
            'ufs' => array_column(Banco::todos('SELECT DISTINCT uf FROM regiao_ufs ORDER BY uf'), 'uf'),
            'produtos' => Banco::todos('SELECT p.id, p.codigo, p.nome, p.embalagem, m.arquivo AS imagem FROM produtos p LEFT JOIN midias m ON m.id = p.imagem_id
                WHERE p.ativo = 1 AND p.visivel_catalogo = 1 ORDER BY p.nome'),
            'padrao' => C::visual([]),
        ]);
    }

    public function salvar(?string $id = null): void
    {
        $this->exigir();
        [$novo, $erros] = C::salvar($id ? (int) $id : null, $_POST, $_FILES, (int) Auth::id());
        if ($erros) {
            $this->voltarComErros($id ? '/campanhas/' . (int) $id : '/campanhas/nova', $erros);
        }
        Sessao::aviso('sucesso', 'Campanha salva. ' . (C::situacao(C::uma($novo)) === 'no_ar' ? 'Ela já está valendo no catálogo e nos pedidos.' : 'Ela entra no ar na data de início.'));
        redirecionar('/campanhas/' . $novo);
    }

    public function alternar(string $id): void
    {
        $this->exigir();
        C::alternar((int) $id);
        Sessao::aviso('sucesso', 'Campanha ' . ((int) (C::uma((int) $id)['ativa'] ?? 0) ? 'ligada.' : 'desligada: saiu do catálogo e dos pedidos.'));
        redirecionar('/campanhas');
    }

    public function duplicar(string $id): void
    {
        $this->exigir();
        $novo = C::duplicar((int) $id, (int) Auth::id());
        Sessao::aviso('sucesso', 'Cópia criada para o próximo período (desligada). Confira as datas e ligue quando quiser.');
        redirecionar($novo ? '/campanhas/' . $novo : '/campanhas');
    }

    /** Visual padrão do catálogo (sem campanha mudando as cores) */
    public function visual(): void
    {
        $this->exigir();
        $cor = fn($k) => Aparencia::valida((string) ($_POST[$k] ?? '')) ?? '';
        Config::definir('catalogo.visual_titulo', mb_substr(trim($this->campo('titulo')), 0, 120) ?: 'Nossos produtos');
        Config::definir('catalogo.chamada', mb_substr(trim($this->campo('chamada')), 0, 255));
        Config::definir('catalogo.visual_primaria', $cor('cor_primaria'));
        Config::definir('catalogo.visual_destaque', $cor('cor_destaque'));
        Config::definir('catalogo.visual_tema', $this->campo('tema') === 'escuro' ? 'escuro' : 'claro');
        try {
            $midia = \App\Services\MarketingService::guardarImagem($_FILES['banner'] ?? null, 'Banner do catálogo');
            if ($midia) {
                Config::definir('catalogo.visual_banner', (string) Banco::valor('SELECT arquivo FROM midias WHERE id = ?', [$midia]));
            } elseif (!empty($_POST['tirar_banner'])) {
                Config::definir('catalogo.visual_banner', '');
            }
        } catch (\RuntimeException $e) {
            $this->voltarComErros('/campanhas?aba=visual', [$e->getMessage()]);
        }
        Sessao::aviso('sucesso', 'Visual do catálogo salvo.');
        redirecionar('/campanhas?aba=visual');
    }

    private function exigir(): void
    {
        if (!Auth::pode('campanhas.gerenciar')) {
            http_response_code(403);
            exit('Sem permissão para mudar campanhas.');
        }
    }
}
