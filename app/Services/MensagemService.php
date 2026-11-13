<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use RuntimeException;

/**
 * Mensagens prontas de WhatsApp: modelos com variáveis, preenchidos com os dados do cliente e de quem envia.
 * Variáveis: {nome} {razao_social} {cidade} {vendedor} {link_vendedor} {link_catalogo} {empresa} {dias_sem_compra} {ultima_compra}
 */
class MensagemService
{
    public const VARIAVEIS = [
        '{nome}' => 'Nome da loja (fantasia ou razão social)', '{razao_social}' => 'Razão social', '{cidade}' => 'Cidade do cliente',
        '{vendedor}' => 'Seu primeiro nome', '{link_vendedor}' => 'Seu link do catálogo (pedidos ficam com você)',
        '{link_catalogo}' => 'Link geral do catálogo', '{empresa}' => 'Nome da nossa empresa',
        '{dias_sem_compra}' => 'Dias desde a última compra', '{ultima_compra}' => 'Data da última compra',
        '{valor}' => 'Cobrança: valor em atraso', '{vencimento}' => 'Cobrança: vencimento mais antigo',
        '{dias_atraso}' => 'Cobrança: dias de atraso', '{link_pix}' => 'Cobrança: link ou chave Pix (Configurações)',
    ];

    public static function categorias(): array
    {
        return Config::get('mensagens.categorias', ['Outros']) ?: ['Outros'];
    }

    /** Modelos ativos (ou todos), na ordem das categorias */
    public static function modelos(bool $somenteAtivos = true): array
    {
        $lista = Banco::todos('SELECT * FROM mensagens_prontas' . ($somenteAtivos ? ' WHERE ativo = 1' : '') . ' ORDER BY ordem, titulo');
        $ordem = array_flip(self::categorias());
        usort($lista, fn($a, $b) => [$ordem[$a['categoria']] ?? 99, (int) $a['ordem'], $a['titulo']] <=> [$ordem[$b['categoria']] ?? 99, (int) $b['ordem'], $b['titulo']]);
        return $lista;
    }

    /** Valores das variáveis para um cliente e a pessoa logada. $caso = caso de cobrança (valor, vencimento, atraso) */
    public static function variaveis(?array $cliente, ?array $usuario, ?array $caso = null): array
    {
        $base = (isset($_SERVER['HTTP_HOST']) ? ((($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : '') . url('/catalogo');
        $codigo = $usuario ? Banco::valor('SELECT codigo_vendedor FROM usuarios WHERE id = ?', [$usuario['id']]) : null;
        $ultima = $cliente['ultima_compra_em'] ?? null;
        return [
            '{nome}' => $cliente ? ($cliente['nome_fantasia'] ?: $cliente['razao_social']) : '',
            '{razao_social}' => $cliente['razao_social'] ?? '', '{cidade}' => $cliente['cidade'] ?? '',
            '{vendedor}' => $usuario ? explode(' ', trim((string) $usuario['nome']))[0] : '',
            '{link_vendedor}' => $codigo ? "$base/v/" . rawurlencode((string) $codigo) : $base,
            '{link_catalogo}' => $base, '{empresa}' => (string) Config::get('empresa.nome', 'Perfeita Alquimia'),
            '{dias_sem_compra}' => $ultima ? (string) (int) floor((time() - strtotime($ultima)) / 86400) : '',
            '{ultima_compra}' => $ultima ? data_br($ultima) : '',
            '{valor}' => $caso ? dinheiro((float) $caso['valor_total']) : '',
            '{vencimento}' => !empty($caso['vencimento_mais_antigo']) ? data_br($caso['vencimento_mais_antigo']) : '',
            '{dias_atraso}' => !empty($caso['vencimento_mais_antigo']) ? (string) max(0, (int) ((strtotime('today') - strtotime($caso['vencimento_mais_antigo'])) / 86400)) : '',
            '{link_pix}' => $caso ? (string) Config::get('cobranca.link_pix', '') : '',
        ];
    }

    /** Modelos ativos de uma categoria já preenchidos: ['Título' => 'texto', ...] (na ordem da lista) */
    public static function daCategoria(string $categoria, array $variaveis): array
    {
        $saida = [];
        foreach (self::modelos() as $m) {
            if ($m['categoria'] === $categoria && ($texto = self::aplicar($m['texto'], $variaveis)) !== '') {
                $saida[$m['titulo']] = $texto;
            }
        }
        return $saida;
    }

    /** Troca as variáveis; as que não têm valor somem (sem deixar "{...}" nem espaço duplo) */
    public static function aplicar(string $texto, array $variaveis): string
    {
        $t = strtr($texto, $variaveis);
        $t = preg_replace('/\{[a-z_]+\}/', '', $t);
        $t = preg_replace('/[ \t]{2,}/', ' ', $t);
        $t = preg_replace('/\s+([,.!?)])/', '$1', $t);
        return trim($t);
    }

    /** Modelos ativos já preenchidos para o cliente (para a ficha) */
    public static function paraCliente(array $cliente, array $usuario): array
    {
        // Com caso de cobrança aberto, as variáveis de cobrança ({valor}, {dias_atraso}...) são preenchidas;
        // sem caso, os modelos que dependem delas não aparecem (o texto ficaria sem o valor).
        $caso = CobrancaService::casoAberto((int) $cliente['id']);
        $v = self::variaveis($cliente, $usuario, $caso);
        $deCobranca = '/\{(valor|vencimento|dias_atraso|link_pix)\}/';
        $lista = array_filter(self::modelos(), fn($m) => $caso || !preg_match($deCobranca, $m['texto']));
        return array_values(array_map(fn($m) => ['id' => (int) $m['id'], 'titulo' => $m['titulo'], 'categoria' => $m['categoria'], 'texto' => self::aplicar($m['texto'], $v)], $lista));
    }

    public static function salvar(?int $id, array $d, ?int $usuarioId): int
    {
        $dados = [
            'titulo' => mb_substr(trim((string) ($d['titulo'] ?? '')), 0, 120),
            'categoria' => (string) ($d['categoria'] ?? ''),
            'texto' => trim(str_replace("\r\n", "\n", (string) ($d['texto'] ?? ''))),
            'ativo' => !empty($d['ativo']) ? 1 : 0,
            'ordem' => (int) ($d['ordem'] ?? 0),
        ];
        if ($dados['titulo'] === '' || $dados['texto'] === '') {
            throw new RuntimeException('Preencha o título e o texto da mensagem.');
        }
        if (mb_strlen($dados['texto']) > 2000) {
            throw new RuntimeException('Mensagem muito longa (máximo 2.000 caracteres).');
        }
        if (!in_array($dados['categoria'], self::categorias(), true)) {
            throw new RuntimeException('Escolha uma categoria da lista.');
        }
        if (preg_match_all('/\{[a-z_]+\}/', $dados['texto'], $m) && ($erradas = array_diff(array_unique($m[0]), array_keys(self::VARIAVEIS)))) {
            throw new RuntimeException('Variável desconhecida: ' . implode(', ', $erradas) . '. Use os botões de variáveis.');
        }
        if ($id) {
            $antes = Banco::um('SELECT titulo, categoria, texto, ativo, ordem FROM mensagens_prontas WHERE id = ?', [$id]);
            if (!$antes) {
                throw new RuntimeException('Mensagem não encontrada.');
            }
            Banco::atualizar('mensagens_prontas', $id, $dados);
            Auditoria::registrar('mensagem_pronta', $id, 'editar', $antes, $dados);
            return $id;
        }
        $id = Banco::inserir('mensagens_prontas', $dados + ['criado_por' => $usuarioId]);
        Auditoria::registrar('mensagem_pronta', $id, 'criar', null, $dados);
        return $id;
    }

    /** Registra o uso: soma o contador e grava na linha do tempo (sem o texto inteiro — LGPD) */
    public static function registrarUso(int $modeloId, int $clienteId, int $usuarioId): void
    {
        $titulo = Banco::valor('SELECT titulo FROM mensagens_prontas WHERE id = ?', [$modeloId]);
        if (!$titulo) {
            throw new RuntimeException('Mensagem não encontrada.');
        }
        Banco::executar('UPDATE mensagens_prontas SET usos = usos + 1 WHERE id = ?', [$modeloId]);
        // Conta como contato (linha do tempo + "contatado"; o lead novo avança de etapa como num contato registrado)
        CarteiraService::registrarContato($clienteId, date('Y-m-d'), 'WhatsApp', 'Mensagem pronta: ' . $titulo, $usuarioId);
    }
}
