<?php
namespace App\Services;

use App\Core\Banco;
use App\Core\Config;

/**
 * Leitura do conteúdo da Academia (produtos, trilha, aulas, páginas) e do progresso de estudo.
 * O conteúdo vem das tabelas produtos e academia_conteudo (migrado da Central de Televendas V2).
 */
class AcademiaService
{
    private static array $cache = [];

    // ------------------------------------------------------------------
    // Conteúdo
    // ------------------------------------------------------------------

    /** Um bloco de conteúdo (modules, topics, crm, media, marketing, pagina_empresa...) com as mídias já em endereço do site */
    public static function conteudo(string $chave): array
    {
        return self::$cache["c:$chave"] ??= self::enderecos(json_decode(
            (string) Banco::valor('SELECT conteudo_json FROM academia_conteudo WHERE chave = ?', [$chave]) ?: '[]', true
        ));
    }

    /** Módulos da trilha, na ordem */
    public static function modulos(): array
    {
        return self::conteudo('modules');
    }

    public static function modulo(string $id): ?array
    {
        foreach (self::modulos() as $i => $m) {
            if ($m['id'] === $id) {
                return $m + ['indice' => $i];
            }
        }
        return null;
    }

    /** Aulas "Como usar o One" (no lugar das aulas do Leads2b) */
    public static function aulasOne(): array
    {
        return self::conteudo('sistema_one');
    }

    /** Aulas antigas de CRM (Leads2b) — guardadas, não aparecem mais no menu */
    public static function aulasCrm(): array
    {
        return self::conteudo('crm');
    }

    /** Link configurável: catalogo, grupo_marketing, site, instagram */
    public static function link(string $nome): string
    {
        return (string) Config::get('academia.link_' . $nome, '');
    }

    // ------------------------------------------------------------------
    // Produtos
    // ------------------------------------------------------------------

    /** Todos os produtos ativos, com a ficha completa, indexados pelo slug */
    public static function produtos(): array
    {
        if (!isset(self::$cache['produtos'])) {
            self::$cache['produtos'] = [];
            $linhas = Banco::todos(
                'SELECT p.*, c.nome AS categoria, m.arquivo AS imagem FROM produtos p
                   LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id
                  WHERE p.ativo = 1 ORDER BY p.ordem, p.nome'
            );
            foreach ($linhas as $p) {
                $ficha = json_decode($p['ficha_json'], true) ?: [];
                self::$cache['produtos'][$p['slug']] = [
                    'slug' => $p['slug'], 'codigo' => $p['codigo'] ?? null, 'nome' => $p['nome'], 'embalagem' => $p['embalagem'], 'categoria' => $p['categoria'],
                    'chamada' => $p['chamada'], 'imagem' => $p['imagem'] ? url('/' . $p['imagem']) : null,
                ] + $ficha;
            }
        }
        return self::$cache['produtos'];
    }

    public static function produto(string $slug): ?array
    {
        return self::produtos()[$slug] ?? null;
    }

    /** Categorias (apresentações) com a quantidade de produtos */
    public static function categorias(): array
    {
        $contagem = [];
        foreach (self::produtos() as $p) {
            $contagem[$p['categoria']] = ($contagem[$p['categoria']] ?? 0) + 1;
        }
        return $contagem;
    }

    /** Temas de busca (emagrecimento, sono, digestão...) */
    public static function temas(): array
    {
        return self::conteudo('topics');
    }

    /**
     * Busca como na Central: se o texto é um tema conhecido (ex.: "sono", "perder peso"), traz os produtos ligados ao tema;
     * senão procura no nome, apresentação, chamada, ingredientes e benefícios.
     * Devolve [produtos, tema encontrado ou null].
     */
    public static function buscar(string $texto, string $categoria = '', string $temaId = ''): array
    {
        $q = Normalizar::semAcento($texto);
        $tema = null;
        foreach (self::temas() as $t) {
            if ($temaId === $t['id'] || ($q !== '' && (Normalizar::semAcento($t['label']) === $q || in_array($q, array_map([Normalizar::class, 'semAcento'], $t['aliases']), true)))) {
                $tema = $t;
                break;
            }
        }
        $resultado = array_filter(self::produtos(), function ($p) use ($q, $categoria, $tema) {
            if ($categoria !== '' && $p['categoria'] !== $categoria) {
                return false;
            }
            if ($tema) {
                return isset($p['topics'][$tema['id']]);
            }
            if ($q === '') {
                return true;
            }
            $onde = Normalizar::semAcento(implode(' ', [$p['codigo'] ?? '', $p['nome'], $p['embalagem'], $p['categoria'], $p['chamada'], $p['ingredients'] ?? '', implode(' ', $p['benefits'] ?? [])]));
            foreach (explode(' ', $q) as $palavra) {
                if (!str_contains($onde, $palavra)) {
                    return false;
                }
            }
            return true;
        });
        return [array_values($resultado), $tema];
    }

    // ------------------------------------------------------------------
    // Progresso de estudo (por usuário, no servidor)
    // ------------------------------------------------------------------

    /** Itens concluídos pela pessoa: ['modulo:empresa' => '2026-11-12 10:00:00', ...] */
    public static function progresso(int $usuarioId): array
    {
        return array_column(Banco::todos('SELECT item, concluido_em FROM academia_progresso WHERE usuario_id = ?', [$usuarioId]), 'concluido_em', 'item');
    }

    /** Todos os itens que contam no progresso: módulos da trilha + aulas "Como usar o One" */
    public static function itensDeEstudo(): array
    {
        return [
            ...array_map(fn($m) => 'modulo:' . $m['id'], self::modulos()),
            ...array_map(fn($a) => 'one:' . $a['id'], self::aulasOne()),
        ];
    }

    public static function marcar(int $usuarioId, string $item, bool $concluido): void
    {
        if (!in_array($item, self::itensDeEstudo(), true)) {
            return;
        }
        $concluido
            ? Banco::executar('INSERT IGNORE INTO academia_progresso (usuario_id, item) VALUES (?, ?)', [$usuarioId, $item])
            : Banco::executar('DELETE FROM academia_progresso WHERE usuario_id = ? AND item = ?', [$usuarioId, $item]);
    }

    /** Caminhos "media/academia/x.webp" viram endereços completos do site */
    private static function enderecos(mixed $valor): mixed
    {
        if (is_string($valor) && str_starts_with($valor, 'media/')) {
            return url('/' . $valor);
        }
        if (is_array($valor)) {
            foreach ($valor as $chave => $item) {
                $valor[$chave] = self::enderecos($item);
            }
        }
        return $valor;
    }
}
