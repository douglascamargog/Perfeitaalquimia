<?php
namespace App\Core;

/**
 * Configurações comerciais editáveis pelo admin (tabela `configuracoes`).
 *
 * Diferente do config/config.php (senhas, banco), aqui ficam as REGRAS DE NEGÓCIO que mudam com o tempo:
 * dias de inatividade, tempo de sessão, etapa do pedido no Omie etc.
 * Nada disso fica fixo no código: o código pede o valor e usa um padrão se não existir.
 *
 *     $dias = Config::get('carteira.dias_inatividade', 90);
 */
class Config
{
    private static ?array $cache = null;

    /** Carrega todas as configurações uma vez por requisição */
    private static function carregar(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Banco::todos('SELECT chave, valor, tipo FROM configuracoes') as $linha) {
                    self::$cache[$linha['chave']] = self::converter($linha['valor'], $linha['tipo']);
                }
            } catch (\PDOException) {
                // Tabela ainda não existe (antes da instalação): usa os padrões
            }
        }
        return self::$cache;
    }

    public static function get(string $chave, mixed $padrao = null): mixed
    {
        return self::carregar()[$chave] ?? $padrao;
    }

    /** Lista para a tela de configurações, agrupada */
    public static function todas(): array
    {
        return Banco::todos('SELECT * FROM configuracoes ORDER BY grupo, ordem, chave');
    }

    /** Grava um valor (com registro na auditoria) */
    public static function definir(string $chave, string $valor): void
    {
        $atual = Banco::um('SELECT id, valor FROM configuracoes WHERE chave = ?', [$chave]);
        if (!$atual || $atual['valor'] === $valor) {
            return;
        }
        Banco::atualizar('configuracoes', (int) $atual['id'], ['valor' => $valor]);
        Auditoria::registrar('configuracao', (int) $atual['id'], 'editar', ['chave' => $chave, 'valor' => $atual['valor']], ['chave' => $chave, 'valor' => $valor]);
        self::$cache = null;
    }

    /** Converte o texto guardado no banco para o tipo certo */
    private static function converter(?string $valor, string $tipo): mixed
    {
        return match ($tipo) {
            'numero'   => is_numeric($valor) ? $valor + 0 : 0,
            'booleano' => $valor === '1',
            'lista'    => array_values(array_filter(array_map('trim', explode(',', (string) $valor)), 'strlen')),
            default    => (string) $valor,
        };
    }
}
