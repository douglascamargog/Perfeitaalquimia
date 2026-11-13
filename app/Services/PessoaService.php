<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use RuntimeException;

/**
 * Perfil das pessoas ("tipo rede social"): o que cada um conta sobre si para os colegas se conhecerem.
 * Aparece para toda a equipe. Aniversário: só dia e mês (nunca o ano). Para os colegas aparece o NOME (o apelido é só da pessoa).
 */
class PessoaService
{
    /** Campos de texto do perfil público e o tamanho máximo de cada um */
    public const CAMPOS = ['sobre_mim' => 600, 'hobbies' => 300, 'comida' => 120, 'musica' => 120, 'filme_serie' => 120,
        'time_futebol' => 80, 'lugar_favorito' => 120, 'familia' => 300, 'pets' => 200, 'instagram' => 160, 'linkedin' => 200];

    /** Equipe (ativos), com funções, para o diretório */
    public static function diretorio(string $busca = ''): array
    {
        $p = [];
        $w = 'u.ativo = 1';
        if ($busca !== '') {
            $w .= ' AND (u.nome LIKE ? OR EXISTS (SELECT 1 FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = u.id AND f.nome LIKE ?))';
            $p = ['%' . $busca . '%', '%' . $busca . '%'];
        }
        return Banco::todos("SELECT u.id, u.nome, u.foto, u.data_nascimento, up.sobre_mim, up.capa,
                (SELECT GROUP_CONCAT(f.nome ORDER BY f.nome SEPARATOR ', ') FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = u.id) AS funcoes
            FROM usuarios u LEFT JOIN usuario_perfil up ON up.usuario_id = u.id WHERE $w ORDER BY u.nome", $p);
    }

    /** Perfil completo de uma pessoa (ou null) */
    public static function perfil(int $id): ?array
    {
        $u = Banco::um("SELECT u.id, u.nome, u.foto, u.whatsapp, u.data_nascimento, u.data_admissao, u.ativo, p.nome AS perfil_nome, up.*,
                (SELECT GROUP_CONCAT(f.nome ORDER BY f.nome SEPARATOR ', ') FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = u.id) AS funcoes
            FROM usuarios u JOIN perfis p ON p.id = u.perfil_id LEFT JOIN usuario_perfil up ON up.usuario_id = u.id WHERE u.id = ?", [$id]);
        if (!$u || !$u['ativo']) {
            return null;
        }
        $u['id'] = $id; // o LEFT JOIN de up.* não pode apagar o id
        return $u;
    }

    /** Grava o perfil público da própria pessoa ($arquivos: capa, pet_foto) */
    public static function salvar(int $usuarioId, array $post, array $arquivos = []): void
    {
        $antes = Banco::um('SELECT * FROM usuario_perfil WHERE usuario_id = ?', [$usuarioId]) ?: [];
        $dados = [];
        foreach (self::CAMPOS as $campo => $max) {
            $dados[$campo] = mb_substr(trim((string) ($post[$campo] ?? '')), 0, $max) ?: null;
        }
        foreach (['instagram', 'linkedin'] as $rede) {
            $dados[$rede] = $dados[$rede] !== null ? self::linkRede($dados[$rede], $rede) : null;
        }
        // Imagens (capa larga e foto do pet)
        foreach (['capa' => [1200, 360], 'pet_foto' => [400, 400]] as $campo => [$w, $h]) {
            if (!empty($post['remover_' . $campo])) {
                EquipeService::apagarArquivo($antes[$campo] ?? null);
                $dados[$campo] = null;
            } elseif (!empty($arquivos[$campo]) && ($arquivos[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $dados[$campo] = EquipeService::gravarImagem($arquivos[$campo], $w, $h, $usuarioId . '-' . str_replace('_', '', $campo));
                EquipeService::apagarArquivo($antes[$campo] ?? null);
            }
        }
        $colunas = array_keys($dados);
        Banco::executar('INSERT INTO usuario_perfil (usuario_id, ' . implode(', ', $colunas) . ') VALUES (?' . str_repeat(', ?', count($colunas)) . ')
            ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $colunas)), [$usuarioId, ...array_values($dados)]);
        Auditoria::registrar('usuario', $usuarioId, 'perfil_publico', array_intersect_key($antes, $dados), $dados);
    }

    /** "@fulano" ou link → link https seguro; texto que não é rede → recusado */
    public static function linkRede(string $valor, string $rede): ?string
    {
        $valor = trim($valor);
        if ($rede === 'instagram' && preg_match('/^@?([A-Za-z0-9._]{1,30})$/', $valor, $m)) {
            return 'https://www.instagram.com/' . $m[1] . '/';
        }
        if (!preg_match('#^https?://#i', $valor)) {
            $valor = 'https://' . $valor;
        }
        $host = strtolower((string) parse_url($valor, PHP_URL_HOST));
        $permitido = $rede === 'instagram' ? ['instagram.com', 'www.instagram.com'] : ['linkedin.com', 'www.linkedin.com', 'br.linkedin.com'];
        if (!filter_var($valor, FILTER_VALIDATE_URL) || !in_array($host, $permitido, true)) {
            throw new RuntimeException($rede === 'instagram' ? 'Instagram: use @seuusuario ou o link do perfil.' : 'LinkedIn: cole o link do seu perfil (linkedin.com/in/...).');
        }
        return $valor;
    }

    /** "13/11" (nunca o ano) */
    public static function aniversario(?string $data): ?string
    {
        return $data ? substr($data, 8, 2) . '/' . substr($data, 5, 2) : null;
    }
}
