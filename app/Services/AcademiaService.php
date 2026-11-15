<?php
namespace App\Services;

use App\Core\Banco;
use App\Core\Config;

/**
 * Biblioteca de produtos para estudar (fichas, temas, categorias) — conteúdo das tabelas produtos e
 * academia_conteudo (migrado da Central de Televendas V2). O treinamento por setor fica em TreinamentoService.
 */
class AcademiaService
{
    private static array $cache = [];

    // ------------------------------------------------------------------
    // Conteúdo
    // ------------------------------------------------------------------

    /** Um bloco de conteúdo (topics, media, marketing...) com as mídias já em endereço do site */
    public static function conteudo(string $chave): array
    {
        return self::$cache["c:$chave"] ??= self::enderecos(json_decode(
            (string) Banco::valor('SELECT conteudo_json FROM academia_conteudo WHERE chave = ?', [$chave]) ?: '[]', true
        ));
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
