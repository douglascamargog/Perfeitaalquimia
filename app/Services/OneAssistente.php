<?php
namespace App\Services;

use App\Controllers\CarteiraController;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Models\Tarefa;

/**
 * One — o ajudante do sistema.
 *
 * Monta as "falas" do One com base nos dados da pessoa logada: tarefas do dia, meta do mês,
 * meta da equipe, clientes que passaram do prazo de recompra, campanha em andamento e Academia.
 * Respeita as permissões: cada pessoa só recebe dicas do que pode ver.
 *
 * Cada fala: ['humor' => ola|pensando|feliz|ops|ideia|piscando, 'texto' => '...', 'link' => '/...', 'acao' => 'Abrir']
 * Para criar uma dica nova, acrescente um método aqui e chame em falas().
 */
class OneAssistente
{
    /** Saudação conforme o horário */
    public static function saudacao(): array
    {
        $hora = (int) date('G');
        $periodo = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
        $nome = explode(' ', (string) (Auth::usuario()['nome'] ?? ''))[0];
        return ['humor' => 'ola', 'texto' => "$periodo, $nome! Eu sou o One. Separei o que merece sua atenção hoje."];
    }

    /**
     * Falas do One. $pagina = endereço atual (para dar prioridade às dicas daquela tela).
     */
    public static function falas(string $pagina = '/'): array
    {
        $falas = [];
        $id = (int) Auth::id();

        if (Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas')) {
            $falas[] = self::tarefas($id);
            $falas[] = self::recompra();
            $falas[] = self::campanha();
        }
        if (Auth::pode('cobranca.ver')) {
            $falas[] = self::promessasHoje();
            $falas[] = self::semContato();
        }
        if (Auth::pode('meu_mes.usar')) {
            $falas[] = self::metaIndividual($id);
            $falas[] = self::metaEquipe();
        }
        if (Auth::pode('academia.ver')) {
            $falas[] = self::academia($id);
        }
        if (Auth::pode('usuarios.gerenciar')) {
            $falas[] = ['humor' => 'piscando', 'texto' => 'Gente nova na equipe? Crie o login em Usuários e marque a função da pessoa: as filas e regras seguem a função.', 'link' => '/usuarios', 'acao' => 'Usuários'];
        }

        $falas = array_values(array_filter($falas));
        // Dicas da tela atual aparecem primeiro
        usort($falas, fn($a, $b) => (int) self::daPagina($b, $pagina) <=> (int) self::daPagina($a, $pagina));
        return $falas ?: [['humor' => 'feliz', 'texto' => 'Tudo em ordem por aqui. Quando houver algo importante, eu aviso!']];
    }

    /** A fala é da mesma seção da tela atual? (compara o primeiro pedaço do endereço: /carteira, /meu-mes...) */
    private static function daPagina(array $fala, string $pagina): bool
    {
        $secao = fn(string $url) => explode('/', trim((string) strtok($url, '?'), '/'))[0];
        return isset($fala['link']) && $secao($fala['link']) !== '' && $secao($fala['link']) === $secao($pagina);
    }

    // ------------------------------------------------------------------

    private static function tarefas(int $id): ?array
    {
        $agenda = Tarefa::agenda($id);
        $atrasadas = count($agenda['atrasadas']);
        $hoje = count($agenda['hoje']);
        if ($atrasadas) {
            return ['humor' => 'ops', 'texto' => "Você tem $atrasadas retorno(s) atrasado(s)" . ($hoje ? " e $hoje para hoje" : '') . '. Que tal começar por eles?', 'link' => '/agenda', 'acao' => 'Abrir agenda'];
        }
        if ($hoje) {
            return ['humor' => 'piscando', 'texto' => "Hoje você tem $hoje retorno(s) combinado(s) com clientes.", 'link' => '/agenda', 'acao' => 'Ver agenda'];
        }
        return null;
    }

    /** Clientes que já passaram do intervalo médio de compra (alerta de recompra) */
    /** Cobrança: promessas de pagamento que vencem hoje */
    private static function promessasHoje(): ?array
    {
        $qtd = (int) Banco::valor("SELECT COUNT(*) FROM cobrancas WHERE situacao = 'aberta' AND promessa_em = CURDATE()");
        return $qtd ? ['humor' => 'ideia', 'texto' => "$qtd promessa(s) de pagamento vencem hoje. Vale conferir se o Pix/boleto entrou.", 'link' => '/cobranca', 'acao' => 'Abrir Cobrança'] : null;
    }

    /** Cobrança: casos sem contato (pedem a busca com IA) */
    private static function semContato(): ?array
    {
        $qtd = (int) Banco::valor("SELECT COUNT(*) FROM cobrancas cb JOIN cobranca_etapas e ON e.id = cb.etapa_id WHERE cb.situacao = 'aberta' AND e.tipo = 'sem_contato'");
        return $qtd ? ['humor' => 'pensando', 'texto' => "$qtd cliente(s) em cobrança estão sem contato. Que tal eu procurar telefones e WhatsApp novos na internet? É só abrir o caso e clicar em \"Buscar contatos com IA\".", 'link' => '/cobranca', 'acao' => 'Ver casos'] : null;
    }

    private static function recompra(): ?array
    {
        $escopo = Auth::pode('carteira.ver_todas') ? '' : ' AND responsavel_id = ' . (int) Auth::id();
        $qtd = (int) Banco::valor(
            "SELECT COUNT(*) FROM clientes WHERE excluido_em IS NULL AND tipo = 'cliente' AND intervalo_medio_dias > 0
                AND ultima_compra_em + INTERVAL intervalo_medio_dias DAY < CURDATE()
                AND ultima_compra_em >= CURDATE() - INTERVAL ? DAY$escopo",
            [(int) Config::get('carteira.dias_inatividade', 90)]
        );
        return $qtd ? ['humor' => 'ideia', 'texto' => "$qtd cliente(s) já passaram do tempo normal de recompra. Um contato agora pode virar pedido!", 'link' => '/carteira/lista?tipo=cliente&ordem=compra', 'acao' => 'Ver clientes'] : null;
    }

    /** Lojas da campanha (ex.: Black Friday) ainda não abordadas */
    private static function campanha(): ?array
    {
        $origem = CarteiraController::campanhaOrigemId();
        if (!$origem) {
            return null;
        }
        $escopo = Auth::pode('carteira.ver_todas') ? '' : ' AND responsavel_id = ' . (int) Auth::id();
        $qtd = (int) Banco::valor("SELECT COUNT(*) FROM clientes WHERE excluido_em IS NULL AND origem_id = ? AND contatado = 0$escopo", [$origem]);
        $titulo = (string) Config::get('carteira.campanha_titulo', 'campanha');
        return $qtd ? ['humor' => 'ideia', 'texto' => "$titulo: $qtd loja(s) ainda aguardam o primeiro contato.", 'link' => '/carteira/campanha', 'acao' => 'Prospectar'] : null;
    }

    private static function metaIndividual(int $id): ?array
    {
        $mes = date('Y-m');
        $m = MetaService::indicadores($id, $mes);
        if ($m['config']['meta'] <= 0) {
            if (Auth::pode('metas.gerenciar')) {
                return null; // gestor acompanha a meta da equipe; meta individual é opcional para ele
            }
            return ['humor' => 'pensando', 'texto' => 'Você ainda não definiu sua meta deste mês. Com ela, eu calculo quanto falta por dia e a sua projeção.', 'link' => '/meu-mes', 'acao' => 'Definir meta'];
        }
        if ($m['atingida']) {
            return ['humor' => 'feliz', 'texto' => 'Meta do mês batida! 🎉 Você já vendeu ' . dinheiro($m['vendido']) . '. Continue registrando suas vendas.', 'link' => '/meu-mes', 'acao' => 'Meu mês'];
        }
        $texto = 'Sua meta: ' . number_format($m['percentual'], 0, ',', '.') . '% atingida. Faltam ' . dinheiro($m['falta']);
        $texto .= $m['necessario_dia'] !== null ? ' — cerca de ' . dinheiro($m['necessario_dia']) . ' por dia de trabalho.' : '.';
        if ($m['qtd_vendas']) {
            $texto .= ' Ticket médio: ' . dinheiro($m['ticket_medio']) . '.';
        }
        return ['humor' => $m['projecao_mes'] !== null && $m['projecao_mes'] >= $m['meta'] ? 'feliz' : 'ideia', 'texto' => $texto, 'link' => '/meu-mes', 'acao' => 'Meu mês'];
    }

    private static function metaEquipe(): ?array
    {
        $q = MetaService::equipe(date('Y-m'));
        if (!$q) {
            return Auth::pode('metas.gerenciar')
                ? ['humor' => 'pensando', 'texto' => 'A meta da equipe deste mês ainda não foi cadastrada. Cadastre para todos acompanharem as projeções.', 'link' => '/meu-mes', 'acao' => 'Cadastrar']
                : null;
        }
        if ($q['atingida']) {
            return ['humor' => 'feliz', 'texto' => 'A equipe bateu a meta do mês! 🎉 ' . dinheiro($q['vendido']) . ' vendidos.', 'link' => '/meu-mes', 'acao' => 'Ver equipe'];
        }
        $noRitmo = $q['projecao_mes'] !== null && $q['projecao_mes'] >= $q['meta'];
        return ['humor' => $noRitmo ? 'feliz' : 'pensando',
            'texto' => 'Equipe: ' . number_format($q['percentual'], 0, ',', '.') . '% da meta. Projeção do mês: ' . ($q['projecao_mes'] !== null ? dinheiro($q['projecao_mes']) : '—')
                . ($noRitmo ? ' — no ritmo para bater!' : ($q['necessario_dia'] !== null ? '. Precisamos de ' . dinheiro($q['necessario_dia']) . ' por dia.' : '.')),
            'link' => '/meu-mes', 'acao' => 'Ver equipe'];
    }

    private static function academia(int $id): ?array
    {
        $feitos = AcademiaService::progresso($id);
        foreach (AcademiaService::modulos() as $m) {
            if (!isset($feitos['modulo:' . $m['id']])) {
                return ['humor' => 'piscando', 'texto' => "Próximo passo na Academia: \"{$m['title']}\" ({$m['time']}).", 'link' => '/academia/trilha/' . $m['id'], 'acao' => 'Estudar'];
            }
        }
        return null;
    }
}
