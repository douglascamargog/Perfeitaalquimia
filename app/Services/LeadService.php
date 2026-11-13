<?php
namespace App\Services;

use App\Core\Banco;
use App\Core\Config;
use App\Models\ClienteEvento;
use App\Models\Tarefa;

/**
 * Captura de leads do site e dos anúncios.
 *
 *  - Recebe o formulário (do nosso /seja-revendedor ou do site da empresa pelo endpoint /leads/receber).
 *  - Anti-robô: campo escondido (honeypot), tempo mínimo de preenchimento e limite por hora do mesmo IP.
 *    O IP nunca é gravado: só um "hash" (impressão digital) com um segredo do servidor (LGPD).
 *  - Já existe cliente com o mesmo CNPJ, WhatsApp ou e-mail? Não duplica: registra o novo contato e avisa o responsável.
 *  - Senão cria o lead no funil (origem Site ou Anúncio, com as UTMs) para a função de novos leads.
 */
class LeadService
{
    private const CAMPOS_UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    private const MEIOS_PAGOS = ['cpc', 'ppc', 'paid', 'ads', 'paid_social', 'paidsocial', 'display', 'cpm'];

    /**
     * $d = campos do formulário; $ip = IP de quem enviou. $quando = data (só para a demonstração).
     * Retorna ['ok' => bool, 'resultado' => criado|existente|bloqueado|invalido, 'mensagem' => texto, 'status' => HTTP, 'cliente_id' => ?int]
     */
    public static function receber(array $d, string $ip, ?string $quando = null): array
    {
        $ipHash = self::hashIp($ip);
        $t = fn(string $campo, int $max = 200) => mb_substr(trim(strip_tags((string) ($d[$campo] ?? ''))), 0, $max);
        $dados = [
            'nome' => $t('nome', 120), 'empresa' => $t('empresa', 200), 'cnpj' => preg_replace('/\D/', '', $t('cnpj', 20)),
            'whatsapp' => Normalizar::telefone($t('whatsapp', 30) ?: $t('telefone', 30)), 'email' => mb_strtolower($t('email', 160)),
            'cidade' => $t('cidade', 120), 'uf' => strtoupper(mb_substr($t('uf', 2), 0, 2)), 'mensagem' => $t('mensagem', 1000),
            'pagina' => $t('pagina', 255),
        ];
        $utm = [];
        foreach (self::CAMPOS_UTM as $campo) {
            $utm[$campo] = mb_substr(preg_replace('/[^\w\-\.\s]/u', '', $t($campo, 120)), 0, 120) ?: null;
        }
        $pago = in_array(strtolower((string) $utm['utm_medium']), self::MEIOS_PAGOS, true) || !empty($d['gclid']) || !empty($d['fbclid']);
        $registro = ['nome' => $dados['nome'] ?: null, 'empresa' => $dados['empresa'] ?: null, 'cidade' => $dados['cidade'] ?: null,
            'uf' => $dados['uf'] ?: null, 'origem' => $pago ? 'anuncio' : 'site', 'pagina' => $dados['pagina'] ?: null, 'ip_hash' => $ipHash] + $utm;

        // ---------- Anti-robô ----------
        if (trim((string) ($d['site_url'] ?? '')) !== '') { // honeypot: campo escondido que só robô preenche
            return self::registrar($registro, 'bloqueado', 'Campo escondido preenchido (robô)', null, $quando) + ['ok' => true, 'status' => 200, 'mensagem' => self::obrigado()];
        }
        $inicio = (int) ($d['inicio'] ?? 0); // quando a pessoa abriu o formulário (segundos; o script do site manda)
        if ($inicio > 1000000000 && time() - ($inicio > 9999999999 ? intdiv($inicio, 1000) : $inicio) < 3 && $quando === null) {
            return self::registrar($registro, 'bloqueado', 'Preenchido rápido demais (robô)', null, $quando) + ['ok' => false, 'status' => 422, 'mensagem' => 'Confira os dados e envie de novo.'];
        }
        $limite = max(1, (int) Config::get('leads.limite_por_hora', 5));
        if ($quando === null && (int) Banco::valor("SELECT COUNT(*) FROM leads_recebidos WHERE ip_hash = ? AND criado_em >= NOW() - INTERVAL 1 HOUR", [$ipHash]) >= $limite) {
            return self::registrar($registro, 'bloqueado', 'Limite por hora', null, $quando) + ['ok' => false, 'status' => 429, 'mensagem' => 'Recebemos vários envios seguidos. Tente de novo mais tarde.'];
        }

        // ---------- Validação ----------
        $erros = [];
        if (mb_strlen($dados['nome']) < 2) {
            $erros[] = 'Informe seu nome.';
        }
        if (!$dados['whatsapp'] && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Informe um WhatsApp ou e-mail para contato.';
        }
        if ($dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido.';
        }
        if ($dados['cnpj'] !== '' && !Normalizar::cnpjValido($dados['cnpj'])) {
            $erros[] = 'CNPJ inválido.';
        }
        if ($erros) {
            return self::registrar($registro, 'invalido', mb_substr(implode(' ', $erros), 0, 120), null, $quando) + ['ok' => false, 'status' => 422, 'mensagem' => implode(' ', $erros)];
        }

        // ---------- Já existe? ----------
        $existente = self::procurar($dados);
        $resumo = trim('Formulário ' . ($pago ? 'de anúncio' : 'do site') . ($utm['utm_campaign'] ? ' · campanha ' . $utm['utm_campaign'] : '')
            . ' · ' . $dados['nome'] . ($dados['mensagem'] !== '' ? ': "' . mb_strimwidth($dados['mensagem'], 0, 300, '…') . '"' : ''));
        if ($existente) {
            ClienteEvento::registrar((int) $existente['id'], 'contato', 'Novo contato pelo ' . $resumo, null, $pago ? 'Anúncio' : 'Site', null, $quando);
            self::tarefa((int) $existente['id'], (int) $existente['responsavel_id'], $quando);
            self::registrar($registro, 'existente', null, (int) $existente['id'], $quando);
            return ['ok' => true, 'resultado' => 'existente', 'status' => 200, 'mensagem' => self::obrigado(), 'cliente_id' => (int) $existente['id']];
        }

        $id = Banco::transacao(function () use ($dados, $utm, $pago, $resumo, $quando, $registro) {
            $id = CarteiraService::criar([
                'tipo' => 'lead', 'razao_social' => $dados['empresa'] ?: $dados['nome'],
                'cnpj' => $dados['cnpj'] ?: null, 'whatsapp' => $dados['whatsapp'], 'telefone' => $dados['whatsapp'],
                'email' => $dados['email'] ?: null, 'cidade' => $dados['cidade'] ?: null, 'uf' => preg_match('/^[A-Z]{2}$/', $dados['uf']) ? $dados['uf'] : null,
                'origem' => $pago ? 'Anúncio (tráfego pago)' : 'Site', 'etapa' => (string) Config::get('leads.etapa_inicial', 'Não abordado'),
                'origem_detalhe' => mb_substr('Contato: ' . $dados['nome'] . ($dados['pagina'] ? ' · ' . $dados['pagina'] : ''), 0, 160),
                'observacoes' => $dados['mensagem'] !== '' ? 'Mensagem do formulário: ' . $dados['mensagem'] : null,
                'validacao' => 'Lead do ' . ($pago ? 'anúncio' : 'site') . ': confirmar dados da loja', 'prioridade' => 'Alta',
            ] + ($quando ? ['criado_em' => $quando] : []) + $utm, null, 'Lead recebido pelo ' . $resumo);
            self::tarefa($id, (int) Banco::valor('SELECT responsavel_id FROM clientes WHERE id = ?', [$id]), $quando);
            self::registrar($registro, 'criado', null, $id, $quando);
            return $id;
        });
        return ['ok' => true, 'resultado' => 'criado', 'status' => 200, 'mensagem' => self::obrigado(), 'cliente_id' => $id];
    }

    public static function obrigado(): string
    {
        return (string) Config::get('leads.mensagem_obrigado', 'Recebemos seu contato!');
    }

    /** Domínios que podem chamar o endpoint (CORS). Vazio = qualquer um */
    public static function origemPermitida(string $origem): bool
    {
        $lista = array_map(fn($d) => rtrim(strtolower(trim($d)), '/'), (array) Config::get('leads.dominios_permitidos', []));
        $lista = array_filter($lista);
        return !$lista || in_array(rtrim(strtolower($origem), '/'), $lista, true);
    }

    /** Cliente com o mesmo CNPJ, WhatsApp/telefone ou e-mail */
    private static function procurar(array $d): ?array
    {
        if ($d['cnpj'] !== '' && ($c = Banco::um('SELECT id, responsavel_id FROM clientes WHERE cnpj = ? AND excluido_em IS NULL', [$d['cnpj']]))) {
            return $c;
        }
        if ($d['whatsapp'] && ($c = Banco::um('SELECT id, responsavel_id FROM clientes WHERE (whatsapp = ? OR telefone = ?) AND excluido_em IS NULL ORDER BY id LIMIT 1', [$d['whatsapp'], $d['whatsapp']]))) {
            return $c;
        }
        if ($d['email'] !== '' && ($c = Banco::um('SELECT id, responsavel_id FROM clientes WHERE email = ? AND excluido_em IS NULL ORDER BY id LIMIT 1', [$d['email']]))) {
            return $c;
        }
        return null;
    }

    /** Tarefa "Retornar lead" para hoje (lead respondido no mesmo dia converte muito mais) */
    private static function tarefa(int $clienteId, int $responsavelId, ?string $quando): void
    {
        if ($responsavelId && $quando === null && Config::get('leads.criar_tarefa', true)) {
            Tarefa::definirRetorno($clienteId, $responsavelId, date('Y-m-d'), 'Retornar lead do site/anúncio');
        }
    }

    private static function registrar(array $registro, string $resultado, ?string $motivo, ?int $clienteId, ?string $quando): array
    {
        Banco::inserir('leads_recebidos', array_filter($registro + ['resultado' => $resultado, 'motivo' => $motivo, 'cliente_id' => $clienteId, 'criado_em' => $quando],
            fn($v) => $v !== null && $v !== ''));
        return ['resultado' => $resultado, 'cliente_id' => $clienteId];
    }

    /** sha256(IP + segredo do servidor): dá para contar envios do mesmo IP sem guardar o IP (LGPD) */
    private static function hashIp(string $ip): string
    {
        $arquivo = RAIZ . '/storage/segredo_leads.key';
        if (!is_file($arquivo)) {
            @file_put_contents($arquivo, bin2hex(random_bytes(32)));
            @chmod($arquivo, 0600);
        }
        $segredo = is_file($arquivo) ? trim((string) file_get_contents($arquivo)) : (string) config('db.senha', 'one');
        return hash('sha256', $ip . '|' . $segredo);
    }

    // ------------------------------------------------------------------
    // Tela "Leads do site"
    // ------------------------------------------------------------------

    /** Leads recebidos no período (com a situação do cliente hoje) */
    public static function recebidos(string $de, string $ate, string $resultado = '', int $limite = 200): array
    {
        $w = 'l.criado_em >= ? AND l.criado_em < ? + INTERVAL 1 DAY';
        $par = [$de, $ate];
        if ($resultado !== '') {
            $w .= ' AND l.resultado = ?';
            $par[] = $resultado;
        }
        return Banco::todos("SELECT l.*, c.tipo AS cliente_tipo, c.razao_social, e.nome AS etapa, e.cor AS etapa_cor, u.nome AS responsavel
            FROM leads_recebidos l LEFT JOIN clientes c ON c.id = l.cliente_id LEFT JOIN funil_etapas e ON e.id = c.etapa_id
            LEFT JOIN usuarios u ON u.id = c.responsavel_id WHERE $w ORDER BY l.criado_em DESC LIMIT $limite", $par);
    }

    /** Números do período e a tabela por campanha (leads → clientes → faturamento) */
    public static function resumo(string $de, string $ate): array
    {
        $par = [$de, $ate];
        $n = Banco::um("SELECT COUNT(*) AS total, SUM(resultado = 'criado') AS novos, SUM(resultado = 'existente') AS existentes,
                SUM(resultado = 'bloqueado') AS bloqueados, SUM(resultado = 'invalido') AS invalidos,
                COUNT(DISTINCT CASE WHEN resultado IN ('criado', 'existente') THEN cliente_id END) AS pessoas
            FROM leads_recebidos WHERE criado_em >= ? AND criado_em < ? + INTERVAL 1 DAY", $par);
        $campanhas = Banco::todos("SELECT COALESCE(l.utm_source, '(direto)') AS fonte, COALESCE(l.utm_campaign, '—') AS campanha, l.origem,
                COUNT(*) AS leads, COUNT(DISTINCT l.cliente_id) AS pessoas,
                COUNT(DISTINCT CASE WHEN c.tipo = 'cliente' THEN c.id END) AS clientes,
                COALESCE(SUM(f.valor), 0) AS faturado
            FROM leads_recebidos l LEFT JOIN clientes c ON c.id = l.cliente_id
            LEFT JOIN (SELECT p.cliente_id, SUM(p.total) AS valor, MIN(p.faturado_em) AS primeiro FROM pedidos p WHERE p.situacao = 'faturado' GROUP BY p.cliente_id) f
                   ON f.cliente_id = l.cliente_id AND l.resultado = 'criado'
            WHERE l.resultado IN ('criado', 'existente') AND l.criado_em >= ? AND l.criado_em < ? + INTERVAL 1 DAY
            GROUP BY fonte, campanha, l.origem ORDER BY leads DESC", $par);
        $clientes = (int) Banco::valor("SELECT COUNT(DISTINCT c.id) FROM leads_recebidos l JOIN clientes c ON c.id = l.cliente_id
            WHERE l.resultado = 'criado' AND c.tipo = 'cliente' AND l.criado_em >= ? AND l.criado_em < ? + INTERVAL 1 DAY", $par);
        return array_map('intval', $n) + ['campanhas' => $campanhas, 'clientes' => $clientes];
    }

    /** Leads novos ainda sem contato na carteira da pessoa (One) */
    public static function semRetorno(int $usuarioId): int
    {
        return (int) Banco::valor("SELECT COUNT(DISTINCT c.id) FROM leads_recebidos l JOIN clientes c ON c.id = l.cliente_id
            WHERE l.resultado = 'criado' AND c.contatado = 0 AND c.excluido_em IS NULL AND c.responsavel_id = ? AND l.criado_em >= NOW() - INTERVAL 14 DAY", [$usuarioId]);
    }
}
