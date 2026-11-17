<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Core\Sessao;
use App\Integrations\OneIA\OneIAClaude;
use App\Integrations\OneIA\OneIAInterface;
use App\Integrations\OneIA\OneIALocal;

/**
 * "Pergunte ao One": escolhe quem responde (IA ou local), aplica o limite por hora, guarda a conversa
 * na sessão (só as últimas trocas, para ele lembrar do assunto) e registra o USO (sem o texto — LGPD).
 */
class OnePergunta
{
    private const TROCAS = 4; // quantas perguntas e respostas anteriores vão junto

    /** Quem responde: Claude se o admin ligou e há chave; senão o One local (sem IA) */
    public static function provedor(): OneIAInterface
    {
        $chave = (string) config('integracoes.anthropic.chave', '');
        if (Config::get('one.ia_ativa', false) && $chave !== '') {
            return new OneIAClaude($chave, (string) config('integracoes.anthropic.modelo', 'claude-opus-5-5'), config('integracoes.anthropic.endereco') ?: null);
        }
        return new OneIALocal();
    }

    public static function comIa(): bool
    {
        return self::provedor()->nome() === 'claude';
    }

    /** Responde e devolve ['texto', 'ia' => bool] */
    public static function perguntar(string $pergunta, string $especialista = '', string $tela = ''): array
    {
        $esp = OneEspecialistas::escolher($especialista ?: OneEspecialistas::padrao());
        $chaveConversa = 'one_conversa_' . ($esp['slug'] ?? 'geral');
        $pergunta = trim(mb_substr($pergunta, 0, 500));
        if ($pergunta === '') {
            return ['texto' => 'Pode perguntar! Ex.: "como faço um orçamento?" ou "quanto falta para a minha meta?"', 'ia' => false];
        }
        $id = (int) Auth::id();
        $ia = self::provedor();
        if ($ia->nome() === 'claude') {
            $limite = max(1, (int) Config::get('one.ia_limite_hora', 20));
            $feitas = (int) Banco::valor("SELECT COUNT(*) FROM one_perguntas WHERE usuario_id = ? AND provedor = 'claude' AND criado_em >= NOW() - INTERVAL 1 HOUR", [$id]);
            if ($feitas >= $limite) {
                Banco::inserir('one_perguntas', ['usuario_id' => $id, 'provedor' => 'claude', 'situacao' => 'limite']);
                $ia = new OneIALocal(); // passou do limite da hora: responde sem IA
            }
        }
        $historico = (array) Sessao::get($chaveConversa, []);
        try {
            // A tela em que a pessoa está (ex.: a fórmula aberta) vai junto, para o especialista saber do que se trata
            $contexto = preg_match('#^/formulas/(\d+)#', $tela, $m) && in_array('formula_aberta', OneEspecialistas::consultas($esp) ?? [], true)
                ? '[A pessoa está com a fórmula id ' . (int) $m[1] . ' aberta na tela. Use formula_aberta com esse id quando a pergunta for sobre ela.] ' : '';
            $r = $ia->responder($contexto . $pergunta, $historico, $esp);
        } catch (\Throwable $e) {
            error_log('OnePergunta: ' . $e->getMessage());
            $r = ['texto' => 'Ops, algo deu errado aqui. Tente de novo.', 'consultas' => [], 'tokens_entrada' => 0, 'tokens_saida' => 0, 'custo' => 0, 'situacao' => 'erro'];
        }
        Banco::inserir('one_perguntas', ['usuario_id' => $id, 'provedor' => $ia->nome(), 'especialista' => $esp['slug'] ?? null,
            'consultas' => mb_substr(implode(',', array_unique($r['consultas'] ?? [])), 0, 255) ?: null,
            'tokens_entrada' => (int) ($r['tokens_entrada'] ?? 0), 'tokens_saida' => (int) ($r['tokens_saida'] ?? 0),
            'custo_estimado' => (float) ($r['custo'] ?? 0), 'situacao' => $r['situacao'] ?? 'ok']);
        // conversa: só nesta sessão do navegador (some ao sair do sistema)
        $historico[] = ['papel' => 'pessoa', 'texto' => $pergunta];
        $historico[] = ['papel' => 'one', 'texto' => mb_substr((string) $r['texto'], 0, 2000)];
        Sessao::set($chaveConversa, array_slice($historico, -self::TROCAS * 2));
        return ['texto' => (string) $r['texto'], 'ia' => $ia->nome() === 'claude', 'especialista' => $esp['slug'] ?? 'geral'];
    }

    public static function esquecer(string $especialista = ''): void
    {
        Sessao::remover('one_conversa');
        foreach (OneEspecialistas::ativos() as $e) {
            if ($especialista === '' || $especialista === $e['slug']) {
                Sessao::remover('one_conversa_' . $e['slug']);
            }
        }
    }

    /** Uso do mês (para o admin): perguntas, pessoas, custo estimado em US$ */
    public static function usoDoMes(): array
    {
        return Banco::um("SELECT COUNT(*) AS perguntas, COUNT(DISTINCT usuario_id) AS pessoas, COALESCE(SUM(custo_estimado), 0) AS custo,
            SUM(provedor = 'claude') AS com_ia FROM one_perguntas WHERE criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')") ?? [];
    }
}
