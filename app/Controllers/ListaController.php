<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Listas;

/**
 * Configurações › Listas: etapas do funil, segmentos e origens.
 * Itens não são apagados (há clientes ligados a eles): são desativados e somem dos formulários e filtros.
 */
class ListaController extends Controller
{
    /** Cada lista: tabela, campos editáveis e nome da coluna "ativo" */
    private const LISTAS = [
        'etapas'    => ['tabela' => 'funil_etapas', 'ativo' => 'ativa', 'titulo' => 'Etapas do funil'],
        'segmentos' => ['tabela' => 'segmentos',    'ativo' => 'ativo', 'titulo' => 'Segmentos'],
        'origens'   => ['tabela' => 'origens',      'ativo' => 'ativo', 'titulo' => 'Origens'],
    ];
    public const TIPOS_ETAPA = ['aberta' => 'Em andamento', 'ganho' => 'Ganho (cliente)', 'perdido' => 'Perdido', 'inativo' => 'Inativo'];

    public function index(): void
    {
        $contar = fn(string $coluna) => array_column(Banco::todos("SELECT $coluna AS id, COUNT(*) AS total FROM clientes WHERE excluido_em IS NULL GROUP BY $coluna"), 'total', 'id');
        $this->tela('config/listas', [
            'titulo'    => 'Listas',
            'etapas'    => Listas::etapas(false),
            'segmentos' => Listas::segmentos(false),
            'origens'   => Listas::origens(false),
            'usos'      => ['etapas' => $contar('etapa_id'), 'segmentos' => $contar('segmento_id'), 'origens' => $contar('origem_id')],
            'tipos'     => self::TIPOS_ETAPA,
        ]);
    }

    public function salvar(string $lista, string $id): void
    {
        $cfg = self::LISTAS[$lista] ?? null;
        $antes = $cfg ? Banco::um("SELECT * FROM {$cfg['tabela']} WHERE id = ?", [(int) $id]) : null;
        if (!$antes) {
            redirecionar('/configuracoes/listas');
        }
        $dados = $this->dados($lista, $antes);
        if ($dados === null) {
            $this->voltarComErros('/configuracoes/listas', ['Informe o nome.']);
        }
        Banco::atualizar($cfg['tabela'], (int) $id, $dados);
        Auditoria::registrar($lista, (int) $id, 'editar', $antes, $dados);
        Sessao::aviso('sucesso', 'Lista atualizada.');
        redirecionar('/configuracoes/listas#' . $lista);
    }

    public function criar(string $lista): void
    {
        $cfg = self::LISTAS[$lista] ?? null;
        if (!$cfg) {
            redirecionar('/configuracoes/listas');
        }
        $dados = $this->dados($lista, null);
        if ($dados === null) {
            $this->voltarComErros('/configuracoes/listas', ['Informe o nome.']);
        }
        if (Banco::valor("SELECT 1 FROM {$cfg['tabela']} WHERE nome = ?", [$dados['nome']])) {
            $this->voltarComErros('/configuracoes/listas', ["\"{$dados['nome']}\" já existe em {$cfg['titulo']}."]);
        }
        $id = Banco::inserir($cfg['tabela'], $dados);
        Auditoria::registrar($lista, $id, 'criar', null, $dados);
        Sessao::aviso('sucesso', 'Item criado.');
        redirecionar('/configuracoes/listas#' . $lista);
    }

    /** Lê o formulário conforme a lista. null = nome vazio */
    private function dados(string $lista, ?array $antes): ?array
    {
        $nome = mb_substr($this->campo('nome'), 0, 80);
        if ($nome === '') {
            return null;
        }
        $dados = ['nome' => $nome, self::LISTAS[$lista]['ativo'] => $antes === null || isset($_POST['ativo']) ? 1 : 0];
        if ($lista !== 'origens') {
            $dados['ordem'] = (int) $this->campo('ordem', (string) ($antes['ordem'] ?? 99));
        }
        if ($lista === 'etapas') {
            $dados['cor'] = preg_match('/^#[0-9a-f]{6}$/i', $this->campo('cor')) ? $this->campo('cor') : ($antes['cor'] ?? '#94a3b8');
            $dados['tipo'] = array_key_exists($this->campo('tipo'), self::TIPOS_ETAPA) ? $this->campo('tipo') : 'aberta';
        }
        return $dados;
    }
}
