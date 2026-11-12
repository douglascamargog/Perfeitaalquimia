<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Models\ClienteEvento;
use App\Models\Listas;

/**
 * Importação de carteiras (CSV, XLSX ou Backup JSON da Carteira V4) em DUAS etapas:
 *   1. prever(): lê o arquivo, normaliza e classifica cada linha (novo, atualizar, duplicado, erro). Nada entra na carteira.
 *   2. confirmar(): grava os "novo" e completa os "atualizar". Duplicados e erros são ignorados.
 *
 * Deduplicação: pelo CNPJ; sem CNPJ, por nome + cidade (sem acentos e pontuação).
 * Atualização só PREENCHE campos vazios do cadastro existente — nunca apaga o que a equipe já registrou.
 */
class ImportacaoService
{
    /**
     * Nomes de coluna aceitos (sem acento, minúsculas) → campo do sistema.
     * Para aceitar um cabeçalho novo, basta acrescentá-lo aqui.
     */
    private const COLUNAS = [
        'razao_social'  => ['razao social', 'razao', 'nome', 'estabelecimento', 'cliente', 'empresa', 'name'],
        'nome_fantasia' => ['nome fantasia', 'fantasia'],
        'cnpj'          => ['cnpj', 'cnpj/cpf', 'documento'],
        'endereco'      => ['endereco', 'logradouro', 'address', 'rua'],
        'bairro'        => ['bairro'],
        'cep'           => ['cep'],
        'cidade'        => ['cidade', 'municipio', 'city'],
        'uf'            => ['uf', 'estado'],
        'segmento'      => ['segmento', 'ramo', 'atividade', 'segment'],
        'telefone'      => ['telefone', 'fone', 'phone', 'telefone 1'],
        'whatsapp'      => ['whatsapp', 'celular', 'whats'],
        'email'         => ['email', 'e-mail'],
        'instagram'     => ['instagram'],
        'facebook'      => ['facebook'],
        'site'          => ['site', 'website'],
        'etapa'         => ['etapa', 'etapa comercial', 'status', 'situacao'],
        'prioridade'    => ['prioridade', 'priority'],
        'observacoes'   => ['observacoes', 'observacao', 'obs', 'notas'],
        'vendedor'      => ['vendedor', 'responsavel', 'codigo vendedor', 'e-mail do vendedor'],
    ];

    /** Campos que a atualização pode preencher (quando estão vazios no cadastro) */
    private const PREENCHIVEIS = ['nome_fantasia', 'endereco', 'bairro', 'cep', 'telefone', 'whatsapp', 'email', 'instagram', 'facebook', 'site'];

    /** Etapa 1: lê, normaliza e classifica. Devolve o id da importação. */
    public static function prever(string $arquivo, string $nomeOriginal, int $usuarioId): int
    {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if ($extensao === 'json') {
            $formato = 'backup';
            $originais = is_file(RAIZ . '/referencias/dashboard.html') ? CarteiraV4::lojasDoHtml(RAIZ . '/referencias/dashboard.html') : [];
            $linhas = array_map([CarteiraV4::class, 'paraCliente'], CarteiraV4::lojasDoBackup((string) file_get_contents($arquivo), $originais));
        } else {
            $formato = $extensao === 'xlsx' ? 'xlsx' : 'csv';
            $linhas = array_map([self::class, 'mapearLinha'], LeitorPlanilha::ler($arquivo, $formato));
        }
        if (!$linhas) {
            throw new \RuntimeException('Não encontrei linhas com dados no arquivo. A primeira linha precisa ter os nomes das colunas.');
        }

        return Banco::transacao(function () use ($linhas, $nomeOriginal, $formato, $usuarioId) {
            $id = Banco::inserir('importacoes', ['usuario_id' => $usuarioId, 'arquivo' => mb_substr($nomeOriginal, 0, 200), 'formato' => $formato, 'total' => count($linhas)]);

            // Índices do que já existe, para achar duplicados rapidamente
            $porCnpj = $porNome = [];
            foreach (Banco::consulta('SELECT id, cnpj, razao_social, nome_fantasia, cidade FROM clientes WHERE excluido_em IS NULL')->fetchAll() as $c) {
                if ($c['cnpj']) {
                    $porCnpj[$c['cnpj']] = (int) $c['id'];
                }
                foreach (array_filter([$c['razao_social'], $c['nome_fantasia']]) as $nome) {
                    $porNome[Normalizar::chaveNomeCidade($nome, (string) $c['cidade'])] = (int) $c['id'];
                }
            }
            $vistosNoArquivo = [];
            $contagem = ['novo' => 0, 'atualizar' => 0, 'duplicado' => 0, 'erro' => 0];

            foreach ($linhas as $i => $d) {
                [$acao, $clienteId, $mensagem] = self::classificar($d, $porCnpj, $porNome, $vistosNoArquivo, $i + 2, $formato);
                $contagem[$acao]++;
                Banco::inserir('importacao_linhas', [
                    'importacao_id' => $id, 'linha' => $i + 2, 'acao' => $acao, 'cliente_id' => $clienteId, 'mensagem' => $mensagem,
                    'dados_json' => json_encode($d, JSON_UNESCAPED_UNICODE),
                ]);
            }
            Banco::atualizar('importacoes', $id, ['novos' => $contagem['novo'], 'atualizados' => $contagem['atualizar'], 'duplicados' => $contagem['duplicado'], 'erros' => $contagem['erro']]);
            return $id;
        });
    }

    /** Decide o que fazer com uma linha: [ação, id do cliente existente, mensagem] */
    private static function classificar(array $d, array $porCnpj, array $porNome, array &$vistos, int $numeroLinha, string $formato): array
    {
        if (!$d['razao_social'] || !$d['cidade']) {
            return ['erro', null, 'Falta o nome ou a cidade.'];
        }
        if (!$d['uf'] || !in_array($d['uf'], Listas::ufs(), true)) {
            return ['erro', null, 'UF ausente ou inválida.'];
        }
        $chave = Normalizar::chaveNomeCidade($d['razao_social'], $d['cidade']);
        $chaveArquivo = $d['cnpj'] ?: $chave;
        if (isset($vistos[$chaveArquivo])) {
            return ['duplicado', null, 'Repetido dentro do próprio arquivo (linha ' . $vistos[$chaveArquivo] . ').'];
        }
        $vistos[$chaveArquivo] = $numeroLinha;

        $existente = ($d['cnpj'] && isset($porCnpj[$d['cnpj']])) ? $porCnpj[$d['cnpj']] : ($porNome[$chave] ?? null);
        // Backup da Carteira V4: loja que já existe, mas tem trabalho da equipe (etapa, contatos, histórico) → aplica esse trabalho
        if ($formato === 'backup' && $existente && self::temTrabalhoDaEquipe($d)) {
            return ['atualizar', $existente, 'Aplica o trabalho da equipe na V4 (etapa, contatos, histórico).'];
        }
        if ($d['cnpj'] && isset($porCnpj[$d['cnpj']])) {
            return ['atualizar', $porCnpj[$d['cnpj']], 'CNPJ já cadastrado: completa os campos vazios.'];
        }
        if (isset($porNome[$chave])) {
            return ['duplicado', $porNome[$chave], 'Já existe um estabelecimento com este nome nesta cidade.'];
        }
        return ['novo', null, $d['segmento_original'] && $d['segmento_original'] !== $d['segmento'] ? "Segmento \"{$d['segmento_original']}\" → {$d['segmento']}" : null];
    }

    /** A loja do backup tem algo que a equipe registrou na V4? */
    private static function temTrabalhoDaEquipe(array $d): bool
    {
        return !empty($d['historico']) || !empty($d['contatado'])
            || str_contains((string) ($d['observacoes'] ?? ''), 'Responsável na carteira antiga')
            || !in_array($d['etapa'] ?? '', ['', 'Validar cadastro', 'Não abordado'], true);
    }

    /** Converte uma linha de planilha (cabeçalhos livres) para os campos do sistema */
    public static function mapearLinha(array $linha): array
    {
        $valores = [];
        foreach ($linha as $cabecalho => $valor) {
            $c = Normalizar::semAcento((string) $cabecalho);
            foreach (self::COLUNAS as $campo => $apelidos) {
                if (!isset($valores[$campo]) && in_array($c, $apelidos, true)) {
                    $valores[$campo] = $valor;
                }
            }
        }
        $v = fn(string $campo) => $valores[$campo] ?? '';
        $observacoes = Normalizar::texto($v('observacoes'), 5000);
        return [
            'razao_social'      => Normalizar::texto($v('razao_social') ?: $v('nome_fantasia'), 200),
            'nome_fantasia'     => Normalizar::texto($v('nome_fantasia'), 200),
            'cnpj'              => Normalizar::cnpj($v('cnpj')),
            'endereco'          => Normalizar::texto($v('endereco')),
            'bairro'            => Normalizar::texto($v('bairro'), 120),
            'cep'               => preg_replace('/\D/', '', $v('cep')) ?: null,
            'cidade'            => Normalizar::texto($v('cidade'), 120),
            'uf'                => strtoupper(Normalizar::texto($v('uf'), 2) ?? '') ?: null,
            'segmento'          => Normalizar::segmento($v('segmento')),
            'segmento_original' => Normalizar::texto($v('segmento'), 120),
            'telefone'          => Normalizar::telefone($v('telefone')),
            'whatsapp'          => Normalizar::telefone($v('whatsapp')),
            'email'             => filter_var($v('email'), FILTER_VALIDATE_EMAIL) ? mb_strtolower($v('email')) : null,
            'instagram'         => Normalizar::texto($v('instagram'), 120),
            'facebook'          => Normalizar::texto($v('facebook')),
            'site'              => Normalizar::texto($v('site')),
            'etapa'             => Normalizar::texto($v('etapa'), 80),
            'prioridade'        => Normalizar::texto($v('prioridade'), 10),
            'observacoes'       => $observacoes,
            'vendedor'          => Normalizar::texto($v('vendedor'), 160),
            'historico'         => [],
        ];
    }

    /** Etapa 2: grava os novos e completa os existentes. Devolve [novos, atualizados]. */
    public static function confirmar(int $importacaoId, int $usuarioId): array
    {
        $importacao = Banco::um("SELECT * FROM importacoes WHERE id = ? AND status = 'previa'", [$importacaoId]);
        if (!$importacao) {
            throw new \RuntimeException('Esta importação já foi concluída ou cancelada.');
        }
        return Banco::transacao(function () use ($importacao, $usuarioId) {
            $novos = $atualizados = 0;
            $origemImportacao = Listas::idPorNome(Listas::origens(), 'Carteira importada');
            $linhas = Banco::todos("SELECT * FROM importacao_linhas WHERE importacao_id = ? AND acao IN ('novo','atualizar') ORDER BY linha", [$importacao['id']]);
            foreach ($linhas as $linha) {
                $d = json_decode($linha['dados_json'], true);
                if ($linha['acao'] === 'novo') {
                    $d['responsavel_id'] = self::vendedorDaPlanilha($d['vendedor'] ?? null);
                    $d['origem_id'] = $origemImportacao;
                    $d['origem_detalhe'] = mb_substr(($d['origem_detalhe'] ?? null) ?: 'Arquivo ' . $importacao['arquivo'], 0, 160);
                    $id = CarteiraService::criar($d, $usuarioId, 'Importado do arquivo ' . $importacao['arquivo']);
                    self::importarHistorico($id, $d['historico'] ?? []);
                    $novos++;
                } else {
                    $atual = Banco::um('SELECT * FROM clientes WHERE id = ?', [$linha['cliente_id']]);
                    $preencher = [];
                    foreach (self::PREENCHIVEIS as $campo) {
                        if (empty($atual[$campo]) && !empty($d[$campo])) {
                            $preencher[$campo] = $d[$campo];
                        }
                    }
                    if ($preencher) {
                        Banco::atualizar('clientes', (int) $atual['id'], $preencher);
                        ClienteEvento::registrar((int) $atual['id'], 'importacao', 'Dados completados pelo arquivo ' . $importacao['arquivo'] . ': ' . implode(', ', array_keys($preencher)), $usuarioId);
                    }
                    $aplicouTrabalho = $importacao['formato'] === 'backup' && self::aplicarTrabalhoDaEquipe($atual, $d, $usuarioId);
                    if ($preencher || $aplicouTrabalho) {
                        $atualizados++;
                    }
                }
            }
            Banco::atualizar('importacoes', (int) $importacao['id'], ['status' => 'concluida', 'concluida_em' => date('Y-m-d H:i:s')]);
            Auditoria::registrar('importacao', (int) $importacao['id'], 'confirmar', null, ['arquivo' => $importacao['arquivo'], 'novos' => $novos, 'atualizados' => $atualizados]);
            return [$novos, $atualizados];
        });
    }

    /**
     * Backup da V4 em loja já existente: avança a etapa (nunca volta), marca contato, acrescenta observação
     * e traz o histórico (sem repetir o que já foi importado). Devolve true se mudou algo.
     */
    private static function aplicarTrabalhoDaEquipe(array $atual, array $d, int $usuarioId): bool
    {
        $mudou = false;
        $dados = [];
        $etapaNova = Banco::um('SELECT id, ordem FROM funil_etapas WHERE nome = ?', [$d['etapa'] ?? '']);
        $ordemAtual = (int) Banco::valor('SELECT ordem FROM funil_etapas WHERE id = ?', [$atual['etapa_id']]);
        if ($etapaNova && (int) $etapaNova['ordem'] > $ordemAtual) {
            CarteiraService::mudarEtapa((int) $atual['id'], (int) $etapaNova['id'], $usuarioId);
            $mudou = true;
        }
        if (!empty($d['contatado']) && !$atual['contatado']) {
            $dados['contatado'] = 1;
        }
        if (!empty($d['ultimo_contato_em']) && $d['ultimo_contato_em'] > (string) $atual['ultimo_contato_em']) {
            $dados['ultimo_contato_em'] = $d['ultimo_contato_em'];
        }
        if (!empty($d['observacoes']) && !str_contains((string) $atual['observacoes'], $d['observacoes'])) {
            $dados['observacoes'] = trim($atual['observacoes'] . "\n" . $d['observacoes']);
        }
        if ($dados) {
            Banco::atualizar('clientes', (int) $atual['id'], $dados);
            $mudou = true;
        }
        return self::importarHistorico((int) $atual['id'], $d['historico'] ?? []) || $mudou;
    }

    /** Histórico da Carteira V4 vira eventos na linha do tempo (pula os que já existem) */
    private static function importarHistorico(int $clienteId, array $historico): bool
    {
        $importou = false;
        foreach ($historico as $h) {
            $texto = trim(($h['type'] ?? '') . ': ' . ($h['text'] ?? ''), ': ');
            $quando = preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($h['at'] ?? '')) ? substr($h['at'], 0, 10) . ' 12:00:00' : date('Y-m-d H:i:s');
            if ($texto === '' || Banco::valor('SELECT 1 FROM cliente_eventos WHERE cliente_id = ? AND descricao = ? AND ocorrido_em = ?', [$clienteId, $texto, $quando])) {
                continue;
            }
            ClienteEvento::registrar($clienteId, 'contato', $texto, null, 'Carteira V4', null, $quando);
            $importou = true;
        }
        return $importou;
    }

    /** Coluna "vendedor" da planilha: aceita e-mail ou código de vendedor. Nome de pessoa NÃO é usado como regra. */
    private static function vendedorDaPlanilha(?string $valor): ?int
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        $id = Banco::valor('SELECT id FROM usuarios WHERE ativo = 1 AND (email = ? OR codigo_vendedor = ?)', [mb_strtolower($valor), strtoupper($valor)]);
        return $id ? (int) $id : null;
    }
}
