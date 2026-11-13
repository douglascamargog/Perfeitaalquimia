<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\MetaService;

/**
 * Meu mês: meta individual, vendas registradas, calendário e tarefas — e a meta da EQUIPE.
 * - Cada pessoa vê e altera só os próprios dados.
 * - A meta da equipe é vista por todos; só quem tem 'metas.gerenciar' (gestor/supervisor de vendas) altera
 *   a meta e lança o total vendido pela equipe até o momento.
 */
class MeuMesController extends Controller
{
    public function index(): void
    {
        $mes = MetaService::mesValido($_GET['mes'] ?? null);
        $aba = in_array($_GET['aba'] ?? '', ['painel', 'vendas', 'clientes', 'calendario', 'tarefas'], true) ? $_GET['aba'] : 'painel';
        $dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['dia'] ?? '')) && str_starts_with($_GET['dia'], $mes) ? $_GET['dia'] : (str_starts_with(date('Y-m-d'), $mes) ? date('Y-m-d') : "$mes-01");
        $eu = MetaService::indicadores(Auth::id(), $mes);
        $equipe = MetaService::equipe($mes);

        $tarefas = Banco::todos(
            'SELECT t.*, c.razao_social FROM tarefas t LEFT JOIN clientes c ON c.id = t.cliente_id
              WHERE t.usuario_id = ? AND t.vence_em LIKE ? ORDER BY t.concluida_em IS NOT NULL, t.vence_em, t.hora',
            [Auth::id(), "$mes-%"]
        );
        $this->tela('meu_mes/index', [
            'titulo'   => 'Meu mês',
            'mes'      => $mes,
            'aba'      => $aba,
            'dia'      => $dia,
            'eu'       => $eu,
            'equipe'   => $equipe,
            'tarefas'  => $tarefas,
            'podeEquipe' => Auth::pode('metas.gerenciar'),
            'participacao' => $equipe && $equipe['vendido'] > 0 ? $eu['vendido'] / $equipe['vendido'] * 100 : null,
            // Pedidos faturados no sistema (catálogo, sistema, app) em que a pessoa é a vendedora
            'faturadoSistema' => \App\Services\VendasService::faturadoNoMes($mes, 'vendedor', (int) Auth::id()),
            // Meus clientes: melhores (12 meses e do mês) e os que estão sem comprar (só a carteira da pessoa)
            'clientes' => [
                'melhores' => $aba === 'clientes' ? \App\Services\EquipeVendasService::melhores((int) Auth::id(), 10) : [],
                'do_mes' => $aba === 'painel' ? \App\Services\EquipeVendasService::melhores((int) Auth::id(), 3, $mes) : [],
                'faixas' => $aba === 'clientes' ? \App\Services\VendasService::semCompra(['vendedor' => (int) Auth::id()]) : [],
                'parados' => $aba === 'clientes' ? \App\Services\EquipeVendasService::semComprar((int) Auth::id(), (int) (\App\Core\Config::get('carteira.faixas_sem_compra', [30])[0] ?? 30)) : [],
            ],
            'estilos'  => ['carteira.css', 'meu_mes.css'],
            'scripts'  => $aba === 'clientes' ? ['carteira.js'] : [], // ficha do cliente na lateral
        ]);
    }

    /** Volta para a mesma tela (mês e aba) depois de salvar */
    private function voltar(string $mes, string $aba = 'painel', string $extra = ''): never
    {
        redirecionar('/meu-mes?mes=' . MetaService::mesValido($mes) . '&aba=' . $aba . $extra);
    }

    /** Valor digitado "1.234,56" ou "1234.56" → 1234.56 (null se inválido) */
    public static function valor(string $texto): ?float
    {
        $t = str_replace(['R$', ' '], '', trim($texto));
        if (str_contains($t, ',')) {
            $t = str_replace(['.', ','], ['', '.'], $t);
        }
        return is_numeric($t) && (float) $t >= 0 ? round((float) $t, 2) : null;
    }

    /** Dias da semana marcados no formulário */
    private function diasSemana(): array
    {
        return array_values(array_intersect(array_map('intval', (array) ($_POST['dias_semana'] ?? [])), array_keys(MetaService::DIAS_SEMANA)));
    }

    // ---------------- Meta individual ----------------

    public function salvarMeta(): void
    {
        $mes = MetaService::mesValido($this->campo('mes'));
        $meta = self::valor($this->campo('meta'));
        $dias = $this->diasSemana();
        if (!$meta || !$dias) {
            $this->voltarComErros("/meu-mes?mes=$mes", ['Informe uma meta maior que zero e pelo menos um dia de trabalho.']);
        }
        $atual = MetaService::meuMes(Auth::id(), $mes);
        Banco::executar(
            'INSERT INTO meu_mes (usuario_id, mes, meta, dias_semana, folgas, anotacoes) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE meta = VALUES(meta), dias_semana = VALUES(dias_semana), anotacoes = VALUES(anotacoes)',
            [Auth::id(), $mes, $meta, implode(',', $dias), json_encode($atual['folgas']), mb_substr($this->campo('anotacoes'), 0, 5000)]
        );
        Sessao::aviso('sucesso', 'Meta do mês salva.');
        $this->voltar($mes);
    }

    public function registrarVenda(): void
    {
        $data = $this->campo('data');
        $mes = substr($data, 0, 7);
        $valor = self::valor($this->campo('valor'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data > date('Y-m-d')) {
            $this->voltarComErros('/meu-mes?aba=vendas', ['Escolha uma data até hoje.']);
        }
        if (!$valor) {
            $this->voltarComErros("/meu-mes?mes=$mes&aba=vendas", ['Digite um valor de venda maior que zero. Exemplo: 1.250,50']);
        }
        $id = Banco::inserir('vendas_registradas', ['usuario_id' => Auth::id(), 'data' => $data, 'valor' => $valor, 'descricao' => mb_substr($this->campo('descricao'), 0, 200) ?: null]);
        Auditoria::registrar('venda_registrada', $id, 'criar', null, ['data' => $data, 'valor' => $valor]);
        Sessao::aviso('sucesso', 'Venda registrada: ' . dinheiro($valor) . '.');
        $this->voltar($mes, $this->campo('voltar_aba') === 'vendas' ? 'vendas' : 'painel');
    }

    public function excluirVenda(string $id): void
    {
        $venda = Banco::um('SELECT * FROM vendas_registradas WHERE id = ? AND usuario_id = ?', [(int) $id, Auth::id()]);
        if ($venda) {
            Banco::executar('DELETE FROM vendas_registradas WHERE id = ?', [$venda['id']]);
            Auditoria::registrar('venda_registrada', (int) $id, 'excluir', ['data' => $venda['data'], 'valor' => $venda['valor']], null);
            Sessao::aviso('sucesso', 'Venda excluída.');
        }
        $this->voltar(substr($venda['data'] ?? date('Y-m'), 0, 7), 'vendas');
    }

    /** Marca/desmarca folga num dia (sai do cálculo de dias de trabalho) */
    public function folga(): void
    {
        $data = $this->campo('data');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $this->voltar(date('Y-m'), 'calendario');
        }
        $mes = substr($data, 0, 7);
        $cfg = MetaService::meuMes(Auth::id(), $mes);
        $folgas = in_array($data, $cfg['folgas'], true) ? array_values(array_diff($cfg['folgas'], [$data])) : [...$cfg['folgas'], $data];
        Banco::executar(
            'INSERT INTO meu_mes (usuario_id, mes, meta, dias_semana, folgas) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE folgas = VALUES(folgas)',
            [Auth::id(), $mes, $cfg['meta'], implode(',', $cfg['dias_semana']), json_encode($folgas)]
        );
        $this->voltar($mes, 'calendario', '&dia=' . $data);
    }

    // ---------------- Tarefas e compromissos ----------------

    public function salvarTarefa(): void
    {
        $titulo = mb_substr($this->campo('titulo'), 0, 200);
        $data = $this->campo('vence_em');
        $tipo = $this->campo('tipo') === 'compromisso' ? 'compromisso' : 'outro';
        $hora = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->campo('hora')) ? $this->campo('hora') : null;
        if ($titulo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $this->voltarComErros('/meu-mes?aba=tarefas', ['Escreva o que precisa ser feito e escolha a data.']);
        }
        Banco::inserir('tarefas', ['usuario_id' => Auth::id(), 'titulo' => $titulo, 'tipo' => $tipo, 'prioridade' => $this->campo('prioridade') === 'alta' ? 'alta' : 'normal',
            'vence_em' => $data, 'hora' => $hora, 'notas' => mb_substr($this->campo('notas'), 0, 1000) ?: null]);
        Sessao::aviso('sucesso', $tipo === 'compromisso' ? 'Compromisso agendado.' : 'Tarefa criada.');
        $this->voltar(substr($data, 0, 7), $this->campo('voltar_aba') === 'calendario' ? 'calendario' : 'tarefas', '&dia=' . $data);
    }

    public function concluirTarefa(string $id): void
    {
        $t = Banco::um('SELECT * FROM tarefas WHERE id = ? AND usuario_id = ?', [(int) $id, Auth::id()]);
        if ($t) {
            Banco::executar('UPDATE tarefas SET concluida_em = IF(concluida_em IS NULL, NOW(), NULL) WHERE id = ?', [$t['id']]);
        }
        $this->voltar(substr($t['vence_em'] ?? date('Y-m'), 0, 7), 'tarefas');
    }

    public function excluirTarefa(string $id): void
    {
        $t = Banco::um('SELECT * FROM tarefas WHERE id = ? AND usuario_id = ? AND cliente_id IS NULL', [(int) $id, Auth::id()]);
        if ($t) {
            Banco::executar('DELETE FROM tarefas WHERE id = ?', [$t['id']]);
            Sessao::aviso('sucesso', 'Excluído.');
        }
        $this->voltar(substr($t['vence_em'] ?? date('Y-m'), 0, 7), 'tarefas');
    }

    // ---------------- Meta da equipe (só gestor) ----------------

    public function salvarEquipe(): void
    {
        $mes = MetaService::mesValido($this->campo('mes'));
        $meta = self::valor($this->campo('meta'));
        $vendido = self::valor($this->campo('vendido'));
        $data = $this->campo('data_referencia') ?: date('Y-m-d');
        $dias = $this->diasSemana();
        // Feriados: datas separadas por vírgula, no formato dd/mm (do mês escolhido)
        $folgas = [];
        foreach (array_filter(array_map('trim', explode(',', $this->campo('feriados')))) as $f) {
            if (preg_match('#^(\d{1,2})(?:/\d{1,2})?$#', $f, $m) && checkdate((int) substr($mes, 5, 2), (int) $m[1], (int) substr($mes, 0, 4))) {
                $folgas[] = sprintf('%s-%02d', $mes, $m[1]);
            }
        }
        $erros = [];
        if (!$meta) {
            $erros[] = 'Informe a meta da equipe (maior que zero).';
        }
        if ($vendido === null) {
            $erros[] = 'Informe o total vendido pela equipe até o momento (pode ser 0).';
        }
        if (!$dias) {
            $erros[] = 'Marque pelo menos um dia de trabalho.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !str_starts_with($data, $mes) || $data > date('Y-m-d')) {
            $erros[] = 'A data de referência do vendido deve ser deste mês e até hoje.';
        }
        if ($erros) {
            $this->voltarComErros("/equipe?mes=$mes", $erros);
        }
        $antes = Banco::um('SELECT meta, vendido FROM metas_equipe WHERE mes = ?', [$mes]);
        Banco::transacao(function () use ($mes, $meta, $vendido, $dias, $folgas, $data, $antes) {
            Banco::executar(
                'INSERT INTO metas_equipe (mes, meta, vendido, dias_semana, folgas, atualizado_por) VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE meta = VALUES(meta), vendido = VALUES(vendido), dias_semana = VALUES(dias_semana), folgas = VALUES(folgas), atualizado_por = VALUES(atualizado_por)',
                [$mes, $meta, $vendido, implode(',', $dias), json_encode($folgas), Auth::id()]
            );
            if (!$antes || (float) $antes['vendido'] !== $vendido) {
                Banco::inserir('metas_equipe_lancamentos', ['mes' => $mes, 'data' => $data, 'vendido' => $vendido, 'usuario_id' => Auth::id()]);
            }
        });
        Auditoria::registrar('meta_equipe', null, 'salvar', $antes ?: null, ['mes' => $mes, 'meta' => $meta, 'vendido' => $vendido, 'referencia' => $data]);
        Sessao::aviso('sucesso', 'Meta da equipe atualizada. As projeções já foram recalculadas.');
        redirecionar('/equipe?mes=' . $mes); // a meta da equipe fica em Equipe de vendas
    }
}
