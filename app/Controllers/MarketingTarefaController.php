<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Post;
use App\Services\DesenvolvimentoService;
use App\Services\MarketingService;
use App\Services\MarketingTarefaService as T;

/**
 * Início do Marketing (resumo, mini calendário e lista de tarefas), "Pedir ao Marketing" (qualquer pessoa)
 * e a página de cada tarefa.
 */
class MarketingTarefaController extends Controller
{
    public function inicio(): void
    {
        $so = ($_GET['ver'] ?? '') === 'todas' ? 'todas' : 'minhas';
        $mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['mes'] ?? '')) ? $_GET['mes'] : date('Y-m');
        $semanas = MarketingService::semanas($mes);
        $de = $semanas[0][0];
        $ate = end($semanas)[6];
        $tarefas = T::quadro($so);
        $colunas = ['fazendo' => [], 'a_fazer' => [], 'aprovacao' => [], 'feito' => []];
        foreach ($tarefas as $t) {
            $colunas[$t['situacao']][] = ['tipo' => 'tarefa'] + $t;
        }
        // Posts esperando aprovação (para quem aprova, ou para todos na visão "todas")
        if (Auth::pode('marketing.aprovar') || $so === 'todas') {
            foreach (Banco::todos("SELECT p.id, p.titulo, p.data_publicacao, p.formato FROM mkt_posts p JOIN mkt_post_etapas e ON e.id = p.etapa_id
                WHERE e.tipo = 'aprovacao' ORDER BY p.data_publicacao, p.id LIMIT 30") as $p) {
                $colunas['aprovacao'][] = ['tipo' => 'post', 'id' => $p['id'], 'titulo' => 'Aprovar post: ' . $p['titulo'], 'prazo' => $p['data_publicacao'],
                    'link' => '/marketing?post=' . (int) $p['id']];
            }
        }
        // Rótulos e outras etapas do Desenvolvimento esperando as funções da pessoa
        $funcoes = T::minhasFuncoes();
        foreach (DesenvolvimentoService::minhas() as $p) {
            foreach (DesenvolvimentoService::atuais($p) as $a) {
                if ($so === 'todas' || in_array((string) $a['funcao_slug'], $funcoes, true)) {
                    $colunas['a_fazer'][] = ['tipo' => 'projeto', 'id' => $p['id'], 'titulo' => $a['etapa'] . ' — ' . $p['nome'], 'prazo' => $p['prazo'] ?? null,
                        'link' => '/desenvolvimento/' . (int) $p['id'], 'funcao' => $a['funcao']];
                }
            }
        }
        $porDia = [];
        foreach (Post::doPeriodo($de, $ate) as $p) {
            $porDia[$p['data_publicacao']][] = ['tipo' => 'post', 'titulo' => $p['titulo'], 'cor' => $p['etapa_cor'] ?? '#94a3b8', 'link' => '/marketing?post=' . (int) $p['id']];
        }
        foreach (T::prazosEntre($de, $ate) as $t) {
            $porDia[$t['prazo']][] = ['tipo' => 'prazo', 'titulo' => $t['titulo'], 'situacao' => $t['situacao'], 'link' => '/marketing/tarefas/' . (int) $t['id']];
        }
        $this->tela('marketing/inicio', [
            'titulo' => 'Início do Marketing', 'so' => $so, 'mes' => $mes, 'semanas' => $semanas, 'porDia' => $porDia,
            'colunas' => $colunas, 'resumo' => T::resumo(), 'datas' => MarketingService::datasComemorativas(),
            'tipos' => T::tipos(), 'funcoes' => T::funcoes(), 'pessoas' => T::pessoasDoMarketing(), 'pode' => T::podeMexer(),
            'estilos' => ['carteira.css', 'marketing.css'], 'scripts' => ['mkt-inicio.js'],
        ]);
    }

    /** Pedir ao Marketing (qualquer pessoa logada) + meus pedidos */
    public function pedir(): void
    {
        $this->tela('marketing/pedir', [
            'titulo' => 'Pedir ao Marketing', 'tipos' => T::tipos(), 'pedidos' => T::meusPedidos((int) Auth::id()),
            'estilos' => ['carteira.css', 'marketing.css'],
        ]);
    }

    /** POST /marketing/tarefas — pedido de outro setor (pedido=1) ou tarefa interna do Marketing */
    public function criar(): void
    {
        $pedido = $this->campo('pedido') === '1' || !T::podeMexer();
        [$id, $erros] = T::criar([
            'titulo' => $this->campo('titulo'), 'descricao' => $this->campo('descricao'), 'tipo_id' => $this->campo('tipo_id'),
            'prazo' => $this->campo('prazo'), 'urgente' => $this->campo('urgente'), 'funcao_slug' => $this->campo('funcao_slug'),
            'responsavel_id' => $this->campo('responsavel_id'),
        ], $_FILES['anexo'] ?? null, (int) Auth::id(), $pedido);
        $voltar = $pedido ? '/marketing/pedir' : '/marketing/inicio';
        if ($erros) {
            $this->voltarComErros($voltar, $erros);
        }
        Sessao::aviso('sucesso', $pedido ? 'Pedido enviado ao Marketing. Você é avisado quando ficar pronto.' : 'Tarefa criada.');
        redirecionar($pedido ? $voltar : "/marketing/tarefas/$id");
    }

    private function tarefaOuErro(string $id): array
    {
        $t = ctype_digit($id) ? T::buscar((int) $id) : null;
        if (!$t || !T::podeVer($t)) {
            http_response_code(404);
            (new \App\Core\Roteador([]))->erro(404, 'Tarefa não encontrada.');
            exit;
        }
        return $t;
    }

    public function ver(string $id): void
    {
        $t = $this->tarefaOuErro($id);
        $this->tela('marketing/tarefa', [
            'titulo' => 'Tarefa #' . $t['id'], 't' => $t, 'historico' => T::historico((int) $t['id']), 'pode' => T::podeMexer(),
            'podeAprovar' => Auth::pode('marketing.aprovar'), 'funcoes' => T::funcoes(), 'pessoas' => T::pessoasDoMarketing(),
            'estilos' => ['carteira.css', 'marketing.css'],
        ]);
    }

    public function mudar(string $id): void
    {
        $t = $this->tarefaOuErro($id);
        $erro = T::mudar($t, $this->campo('acao'), (int) Auth::id(), $this->campo('comentario'));
        if ($this->querJson()) {
            $this->json(['ok' => !$erro, 'mensagem' => $erro ?? 'Feito.'], $erro ? 422 : 200);
            return;
        }
        Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Tarefa atualizada.');
        redirecionar($this->campo('voltar') === 'inicio' ? '/marketing/inicio' : '/marketing/tarefas/' . (int) $t['id']);
    }

    /** Quem cuida de cada função do Marketing (gestor de Marketing / Admin) */
    public function responsavel(): void
    {
        try {
            T::definirResponsavel((string) $this->campo('funcao'), (int) $this->campo('usuario_id'), (int) Auth::id());
            Sessao::aviso('sucesso', 'Pronto: os pedidos dessa função vão direto para essa pessoa.');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/marketing/inicio');
    }

    /** Editar o pedido/tarefa (V15.7): quem pediu, enquanto está "A fazer"; o Marketing, até concluir */
    public function editar(string $id): void
    {
        $t = $this->tarefaOuErro($id);
        $erro = T::editar($t, $_POST, (int) Auth::id());
        Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Pedido atualizado.');
        redirecionar('/marketing/tarefas/' . (int) $t['id']);
    }

    public function comentar(string $id): void
    {
        $t = $this->tarefaOuErro($id);
        $erro = T::comentar($t, $this->campo('texto'), (int) Auth::id());
        Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Comentário enviado.');
        redirecionar('/marketing/tarefas/' . (int) $t['id']);
    }

    public function encaminhar(string $id): void
    {
        $t = $this->tarefaOuErro($id);
        $erro = T::encaminhar($t, ['funcao_slug' => $this->campo('funcao_slug'), 'responsavel_id' => $this->campo('responsavel_id'), 'prazo' => $this->campo('prazo')], (int) Auth::id());
        Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Tarefa encaminhada.');
        redirecionar('/marketing/tarefas/' . (int) $t['id']);
    }

    public function anexo(string $id): void
    {
        $t = $this->tarefaOuErro($id);
        $caminho = T::pasta() . '/' . basename((string) $t['anexo']);
        if (!$t['anexo'] || !is_file($caminho)) {
            http_response_code(404);
            (new \App\Core\Roteador([]))->erro(404, 'Anexo não encontrado.');
            return;
        }
        $nome = preg_replace('/[^\w\-. ]+/u', '', (string) $t['anexo_nome']) ?: 'anexo';
        header('Content-Type: ' . $t['anexo_mime']);
        header('Content-Length: ' . filesize($caminho));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $nome) . '"');
        readfile($caminho);
    }

    private function querJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
