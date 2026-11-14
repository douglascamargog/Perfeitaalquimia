<?php
namespace App\Integrations\LigueTalk;

/**
 * Ligue Talk REAL — API v1 (documentação: coleção Postman/Insomnia "Liguetalk API v1").
 *   Endereço: https://sistema.liguetalk.com.br/index.php/API_control/<recurso>?company_id=<id>
 *   Autenticação: cabeçalho X-LTK-VND: <token> + company_id na URL.
 *   Limite: 10 requisições / 60 s por empresa (importar lista: 1 a cada 20 s) → o sistema espera entre as chamadas.
 * Usado aqui:
 *   - recordings_report_v1 (gravações; no máximo 3 dias por consulta) → ligações e gravações do Acompanhamento;
 *   - mailing_list_import_v1 → mandar clientes para o discador (lista);
 *   - users_report_v1 → conferir os usuários do Ligue Talk (para ligar com as pessoas do sistema).
 * Os nomes dos campos das respostas não vêm na documentação: a leitura aceita os nomes mais comuns
 * (veja CAMPOS) e guarda só os NOMES dos campos recebidos (nunca os valores) para ajuste.
 * Chaves no config.php do servidor (integracoes.ligue_talk), nunca no banco nem na tela.
 */
class LigueTalkClient extends LigueTalkManual
{
    /** Possíveis nomes de cada informação nas respostas (o primeiro que existir vale) */
    private const CAMPOS = [
        'id_externo'      => ['uniqueid', 'id_chamada', 'call_id', 'id', 'linkedid'],
        'usuario_externo' => ['usr_id', 'user_id', 'usuario_id', 'id_usuario', 'agente', 'operador', 'usuario', 'user', 'ramal'],
        'origem_num'      => ['src', 'origem', 'numero_origem', 'from'],
        'destino_num'     => ['dst', 'dts', 'destino', 'numero_destino', 'telefone', 'to'],
        'inicio'          => ['calldate', 'data_hora', 'data', 'inicio', 'start', 'created_at', 'date'],
        'duracao'         => ['billsec', 'duracao', 'tempo_falado', 'duration', 'tempo'],
        'situacao'        => ['disposition', 'status', 'situacao'],
        'gravacao'        => ['gravacao', 'url_gravacao', 'recording', 'recording_url', 'url', 'link', 'arquivo', 'audio'],
        'tipo'            => ['tipomailing', 'tipo', 'type'],
    ];

    public function __construct(private string $url, private string $empresa, private string $token)
    {
    }

    public function automatico(): bool
    {
        return true;
    }

    /** A API v1 não disca: o "Ligar" abre o telefone/softphone e a chamada aparece depois pela sincronização */
    public function ligar(string $ramal, string $numero): array
    {
        return ['id_externo' => null, 'situacao' => 'manual', 'duracao' => 0, 'gravacao' => null,
            'mensagem' => 'Ligue pelo Ligue Talk. A gravação aparece no Acompanhamento na próxima sincronização.'];
    }

    /** Chamadas com gravação do período (a API aceita no máximo 3 dias por consulta: o período é dividido) */
    public function listarChamadas(string $desde, ?string $ate = null): array
    {
        $ate ??= date('Y-m-d');
        $inicio = new \DateTimeImmutable(substr($desde, 0, 10));
        $fim = new \DateTimeImmutable(substr($ate, 0, 10));
        $chamadas = [];
        for ($d = $inicio; $d <= $fim; $d = $d->modify('+3 days')) {
            $ateBloco = min($d->modify('+2 days'), $fim);
            $resposta = $this->chamar('GET', 'recordings_report_v1', ['start_date' => $d->format('Y-m-d'), 'end_date' => $ateBloco->format('Y-m-d')]);
            foreach (self::registros($resposta) as $r) {
                if (is_array($r)) {
                    $chamadas[] = self::normalizar($r);
                }
            }
        }
        return $chamadas;
    }

    public function enviarLista(string $nome, array $leads): array
    {
        $dados = ['list_name' => mb_substr($nome, 0, 100), 'list_remove_method' => 'remove_existing', 'infer_extra_date' => false, 'sheet_has_metadata' => false,
            'leads' => array_map(fn($l) => array_filter(['cml_telefone' => $l['telefone'], 'cml_nome' => $l['nome'], 'cml_cidade' => $l['cidade'] ?? null, 'cml_uf' => $l['uf'] ?? null]), $leads)];
        $resposta = $this->chamar('POST', 'mailing_list_import_v1', [], $dados);
        return $resposta !== null
            ? ['ok' => true, 'mensagem' => count($leads) . ' cliente(s) enviados para o discador do Ligue Talk na lista "' . $nome . '".']
            : ['ok' => false, 'mensagem' => 'O Ligue Talk não aceitou a lista agora (limite: 1 envio a cada 20 segundos). Tente de novo em instantes.'];
    }

    /** Usuários do Ligue Talk (para conferir o "usuário no Ligue Talk" de cada pessoa) */
    public function usuarios(): array
    {
        return array_values(array_filter(self::registros($this->chamar('GET', 'users_report_v1')), 'is_array'));
    }

    /** A gravação vem como endereço do Ligue Talk: o sistema baixa com o token (o endereço nunca vai para o navegador) */
    public function gravacao(string $referencia): ?string
    {
        if (!preg_match('#^https?://#', $referencia)) {
            return null;
        }
        $ch = curl_init($referencia);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => ['X-LTK-VND: ' . $this->token]]);
        $corpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $codigo === 200 && $corpo !== '' ? (string) $corpo : null;
    }

    /** Teste de conexão (Configurações): lista os usuários */
    public function testar(): array
    {
        $r = $this->chamar('GET', 'users_report_v1');
        return $r === null ? ['ok' => false, 'mensagem' => 'Sem resposta do Ligue Talk: confira o company_id e o token no config.php.']
            : ['ok' => true, 'mensagem' => 'Conectado ao Ligue Talk: ' . count(self::registros($r)) . ' usuário(s) na empresa.'];
    }

    // ------------------------------------------------------------------

    private function chamar(string $metodo, string $recurso, array $params = [], ?array $corpo = null): ?array
    {
        if ($this->url === '' || $this->empresa === '' || $this->token === '') {
            return null;
        }
        $endereco = rtrim($this->url, '/') . '/' . $recurso . '?' . http_build_query(['company_id' => $this->empresa] + $params);
        for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
            $ch = curl_init($endereco);
            $opcoes = [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['X-LTK-VND: ' . $this->token, 'Content-Type: application/json']];
            if ($corpo !== null) {
                $opcoes[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_UNICODE);
            }
            curl_setopt_array($ch, $opcoes);
            $resposta = curl_exec($ch);
            $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($codigo === 429 && $tentativa === 1) {
                sleep(20); // limite de requisições: espera e tenta mais uma vez
                continue;
            }
            if ($codigo >= 200 && $codigo < 300) {
                $dados = json_decode((string) $resposta, true);
                return is_array($dados) ? $dados : [];
            }
            error_log("Ligue Talk: $recurso respondeu HTTP $codigo"); // sem dados de cliente no log (LGPD)
            return null;
        }
        return null;
    }

    /** A lista de registros pode vir na raiz ou dentro de "data", "dados", "result"... */
    private static function registros(?array $resposta): array
    {
        if (!$resposta) {
            return [];
        }
        foreach (['data', 'dados', 'result', 'results', 'registros', 'records', 'items'] as $chave) {
            if (isset($resposta[$chave]) && is_array($resposta[$chave])) {
                return array_is_list($resposta[$chave]) ? $resposta[$chave] : array_values($resposta[$chave]);
            }
        }
        return array_is_list($resposta) ? $resposta : [];
    }

    /** Data do Ligue Talk (aceita aaaa-mm-dd e dd/mm/aaaa, com ou sem hora) → Y-m-d H:i:s */
    private static function data(string $texto): string
    {
        $texto = trim($texto);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})(.*)$#', $texto, $m)) {
            $texto = "$m[3]-$m[2]-$m[1]$m[4]";
        }
        $t = $texto !== '' ? strtotime($texto) : false;
        return date('Y-m-d H:i:s', $t ?: time());
    }

    /** Registro do Ligue Talk → formato do sistema. Guarda os NOMES dos campos recebidos (sem valores) para conferência. */
    public static function normalizar(array $r): array
    {
        $baixo = array_change_key_case($r, CASE_LOWER);
        \App\Core\Config::definir('telefonia.campos_recebidos', mb_substr(implode(', ', array_keys($baixo)), 0, 500));
        $valor = function (string $campo) use ($baixo) {
            foreach (self::CAMPOS[$campo] as $nome) {
                if (isset($baixo[$nome]) && $baixo[$nome] !== '' && !is_array($baixo[$nome])) {
                    return (string) $baixo[$nome];
                }
            }
            return null;
        };
        $duracao = (string) ($valor('duracao') ?? '0');
        $segundos = str_contains($duracao, ':') ? array_reduce(explode(':', $duracao), fn($t, $p) => $t * 60 + (int) $p, 0) : (int) $duracao;
        $situacao = mb_strtolower((string) $valor('situacao'));
        return [
            'id_externo' => $valor('id_externo') ?? sha1(json_encode($r)),
            'usuario_externo' => $valor('usuario_externo'),
            'origem_num' => preg_replace('/\D/', '', (string) $valor('origem_num')),
            'destino_num' => preg_replace('/\D/', '', (string) $valor('destino_num')),
            'inicio' => self::data((string) $valor('inicio')),
            'duracao' => $segundos,
            'situacao' => match (true) {
                str_contains($situacao, 'busy') || str_contains($situacao, 'ocup') => 'ocupado',
                str_contains($situacao, 'no answer') || str_contains($situacao, 'nao') || str_contains($situacao, 'não') => 'nao_atendida',
                str_contains($situacao, 'fail') || str_contains($situacao, 'falh') => 'falha',
                default => $segundos > 0 ? 'atendida' : 'nao_atendida',
            },
            'gravacao' => $valor('gravacao'),
            'tipo' => $valor('tipo'),
        ];
    }
}
