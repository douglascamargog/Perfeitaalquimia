<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Integrations\Integracoes;
use App\Models\Cliente;
use RuntimeException;

/**
 * Ligações (Ligue Talk) dentro do One:
 *  - "Ligar" na ficha do cliente (demonstração: simula; Ligue Talk real: a ligação sai pelo softphone);
 *  - sincronização das chamadas e gravações (cron/sincronizar_ligacoes.php) → tabela ligacoes;
 *  - enviar clientes para o discador do Ligue Talk (lista);
 *  - ouvir a gravação (só pelo sistema, para quem pode).
 */
class TelefoniaService
{
    public const SITUACOES = ['atendida' => 'Atendida', 'nao_atendida' => 'Não atendeu', 'ocupado' => 'Ocupado', 'falha' => 'Falhou', 'chamando' => 'Chamando'];

    public static function modo(): string
    {
        return (string) Config::get('telefonia.modo', 'simulado');
    }

    /** "Ligar" para o cliente (telefone ou whatsapp). Devolve [ok, mensagem, tel (número para abrir o discador do aparelho)] */
    public static function ligar(array $cliente, string $qual = 'telefone'): array
    {
        $numero = preg_replace('/\D/', '', (string) ($qual === 'whatsapp' ? $cliente['whatsapp'] : ($cliente['telefone'] ?: $cliente['whatsapp'])));
        if (strlen($numero) < 10) {
            throw new RuntimeException('Este cliente não tem telefone cadastrado.');
        }
        $ramal = (string) (Auth::usuario()['ramal'] ?? '');
        $r = Integracoes::telefonia()->ligar($ramal, $numero);
        if ($r['situacao'] === 'manual') {
            return ['ok' => true, 'manual' => true, 'tel' => '+55' . $numero, 'mensagem' => $r['mensagem']];
        }
        // Resultado imediato (demonstração): grava a ligação e o contato na linha do tempo
        self::gravar([
            'id_externo' => $r['id_externo'], 'usuario_id' => Auth::id(), 'cliente_id' => (int) $cliente['id'], 'numero' => $numero,
            'situacao' => $r['situacao'], 'inicio' => date('Y-m-d H:i:s'), 'duracao_seg' => (int) $r['duracao'], 'gravacao' => $r['gravacao'],
            'origem' => self::modo() === 'simulado' ? 'demonstracao' : 'sistema',
        ]);
        return ['ok' => true, 'manual' => false, 'mensagem' => $r['mensagem'] . ($r['duracao'] ? ' Duração: ' . self::duracao((int) $r['duracao']) . '.' : '')];
    }

    /** Grava a ligação (sem duplicar pelo id do Ligue Talk) e registra o contato na linha do tempo do cliente */
    private static function gravar(array $l): bool
    {
        if ($l['id_externo'] && Banco::valor('SELECT 1 FROM ligacoes WHERE id_externo = ?', [$l['id_externo']])) {
            return false;
        }
        Banco::inserir('ligacoes', $l);
        if ($l['cliente_id'] && $l['usuario_id']) {
            $texto = 'Ligação pelo Ligue Talk: ' . mb_strtolower(self::SITUACOES[$l['situacao']] ?? $l['situacao'])
                . ($l['duracao_seg'] ? ' (' . self::duracao((int) $l['duracao_seg']) . ')' : '');
            if ($l['situacao'] === 'atendida' || PHP_SAPI !== 'cli') {
                CarteiraService::registrarLigacao((int) $l['cliente_id'], $texto, (int) $l['usuario_id']);
            }
        }
        return true;
    }

    /**
     * Busca no Ligue Talk as chamadas desde a última sincronização (no máximo 7 dias para trás) e grava as novas.
     * Liga cada chamada à pessoa (usuário no Ligue Talk = campo "ramal" do cadastro) e ao cliente (pelo número).
     * Devolve quantas chamadas novas entraram.
     */
    public static function sincronizar(): int
    {
        $tel = Integracoes::telefonia();
        if (self::modo() !== 'api' || !$tel->automatico()) {
            return 0;
        }
        $ultima = (string) Config::get('telefonia.ultima_sincronizacao', '');
        $desde = $ultima !== '' ? max(substr($ultima, 0, 10), date('Y-m-d', strtotime('-7 days'))) : date('Y-m-d', strtotime('-2 days'));
        $pessoas = [];
        foreach (Banco::todos("SELECT id, ramal FROM usuarios WHERE ramal IS NOT NULL AND ramal <> ''") as $u) {
            $pessoas[mb_strtolower(trim($u['ramal']))] = (int) $u['id'];
        }
        $n = 0;
        foreach ($tel->listarChamadas($desde, date('Y-m-d')) as $c) {
            $usuario = $pessoas[mb_strtolower((string) $c['usuario_externo'])] ?? $pessoas[(string) $c['origem_num']] ?? null;
            $n += self::gravar([
                'id_externo' => mb_substr((string) $c['id_externo'], 0, 80), 'usuario_id' => $usuario, 'cliente_id' => self::clientePeloNumero((string) $c['destino_num']),
                'numero' => mb_substr((string) $c['destino_num'], 0, 20), 'situacao' => $c['situacao'], 'inicio' => $c['inicio'], 'duracao_seg' => (int) $c['duracao'],
                'gravacao' => $c['gravacao'] ? mb_substr((string) $c['gravacao'], 0, 255) : null, 'origem' => 'ligue_talk',
            ]) ? 1 : 0;
        }
        Config::definir('telefonia.ultima_sincronizacao', date('Y-m-d H:i:s'));
        return $n;
    }

    /** Cliente pelo número (últimos 10 dígitos, para aceitar com ou sem 55 e com ou sem o 9) */
    private static function clientePeloNumero(string $numero): ?int
    {
        $fim = substr(preg_replace('/\D/', '', $numero), -8);
        if (strlen($fim) < 8) {
            return null;
        }
        // Só dígitos com REPLACE (o MySQL 5.7 da hospedagem não tem REGEXP_REPLACE)
        $digitos = fn(string $c) => "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($c, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), 8)";
        $id = Banco::valor('SELECT id FROM clientes WHERE excluido_em IS NULL AND (' . $digitos('telefone') . ' = ? OR ' . $digitos('whatsapp') . ' = ?)
            ORDER BY ultima_compra_em DESC LIMIT 1', [$fim, $fim]);
        return $id ? (int) $id : null;
    }

    /** Envia clientes (que a pessoa pode ver) para o discador do Ligue Talk como uma lista */
    public static function enviarParaDiscador(array $clienteIds, string $nome): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $clienteIds))));
        if (!$ids) {
            throw new RuntimeException('Nenhum cliente para enviar.');
        }
        [$where, $par] = Cliente::montarWhere(Cliente::filtrosDe([]));
        $clientes = Banco::todos("SELECT c.id, c.razao_social, c.nome_fantasia, c.telefone, c.whatsapp, c.cidade, c.uf FROM clientes c
            WHERE $where AND c.id IN (" . implode(',', array_slice($ids, 0, 5000)) . ')', $par);
        $leads = [];
        foreach ($clientes as $c) {
            $tel = preg_replace('/\D/', '', (string) ($c['telefone'] ?: $c['whatsapp']));
            if (strlen($tel) >= 10) {
                $leads[] = ['telefone' => '55' . $tel, 'nome' => $c['nome_fantasia'] ?: $c['razao_social'], 'cidade' => $c['cidade'], 'uf' => $c['uf']];
            }
        }
        if (!$leads) {
            throw new RuntimeException('Nenhum desses clientes tem telefone.');
        }
        $r = Integracoes::telefonia()->enviarLista(mb_substr($nome, 0, 100), $leads);
        Auditoria::registrar('telefonia', null, 'enviar_discador', null, ['lista' => $nome, 'clientes' => count($leads), 'ok' => $r['ok']]);
        return $r;
    }

    /** A pessoa pode ouvir a gravação desta ligação? (a própria, ou de quem ela acompanha) */
    public static function podeOuvir(array $ligacao): bool
    {
        $acompanha = array_merge(AcompanhamentoService::podeVerVendas() ? AcompanhamentoService::pessoas() : [], AcompanhamentoService::pessoasCobranca());
        return (int) $ligacao['usuario_id'] === Auth::id() || in_array((int) $ligacao['usuario_id'], array_map('intval', array_column($acompanha, 'id')), true);
    }

    /** Conteúdo da gravação (para tocar no navegador) — registra na auditoria quem ouviu */
    public static function gravacao(int $ligacaoId): ?array
    {
        $l = Banco::um('SELECT * FROM ligacoes WHERE id = ?', [$ligacaoId]);
        if (!$l || !$l['gravacao'] || !self::podeOuvir($l)) {
            return null;
        }
        // Gravação de demonstração (arquivo local) ou endereço do Ligue Talk (baixado com o token)
        $conteudo = $l['origem'] === 'demonstracao' ? (new \App\Integrations\LigueTalk\LigueTalkFake())->gravacao($l['gravacao'])
            : Integracoes::telefonia()->gravacao($l['gravacao']);
        if ($conteudo === null) {
            return null;
        }
        Auditoria::registrar('ligacao', $ligacaoId, 'ouvir_gravacao');
        $mime = str_starts_with($conteudo, 'RIFF') ? 'audio/wav' : (str_starts_with($conteudo, 'ID3') || ord($conteudo[0] ?? '') === 0xFF ? 'audio/mpeg' : 'application/octet-stream');
        return ['conteudo' => $conteudo, 'mime' => $mime];
    }

    public static function duracao(int $segundos): string
    {
        if ($segundos >= 3600) {
            return intdiv($segundos, 3600) . ' h ' . str_pad((string) intdiv($segundos % 3600, 60), 2, '0', STR_PAD_LEFT) . ' min';
        }
        return $segundos >= 60 ? intdiv($segundos, 60) . ' min ' . str_pad((string) ($segundos % 60), 2, '0', STR_PAD_LEFT) . ' s' : $segundos . ' s';
    }
}
