<?php
namespace App\Services;

use App\Core\Banco;

/**
 * Migração da Central de Televendas (referencias/Central_TelevendasPA_V2.html) para o sistema.
 *
 * O arquivo original é um app inteiro dentro de um HTML de 11 MB:
 *   - estilos (<style>), telas (<body>), o programa (<script>) e
 *   - dois blocos de dados JSON: "app-data" (produtos, trilha, temas, aulas) e "marketing-media",
 *   - com 177 imagens/vídeos embutidos em base64.
 *
 * Esta classe separa as partes:
 *   - extrairCodigo(): estilos, telas e programa → arquivos versionados (ferramentas/gerar_academia.php)
 *   - importarDados(): mídias → /public/media/academia; produtos → tabela produtos; resto → academia_conteudo (seed)
 */
class CentralTelevendas
{
    public const PASTA_MIDIA = 'media/academia';
    private const ORIGEM = 'Central de Televendas V2';

    private string $html;

    public function __construct(string $arquivo)
    {
        if (!is_file($arquivo)) {
            throw new \RuntimeException("Arquivo da Central não encontrado: $arquivo");
        }
        $this->html = (string) file_get_contents($arquivo);
    }

    /** Conteúdo de um bloco <script id="..." type="application/json"> já convertido */
    private function blocoJson(string $id): array
    {
        if (!preg_match('#<script id="' . preg_quote($id, '#') . '" type="application/json">#', $this->html, $m, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException("Bloco de dados \"$id\" não encontrado na Central.");
        }
        $inicio = $m[0][1] + strlen($m[0][0]);
        return json_decode(substr($this->html, $inicio, strpos($this->html, '</script>', $inicio) - $inicio), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Grava uma mídia base64 como arquivo (uma vez só por conteúdo) e devolve o caminho público.
     * Ex.: "data:image/webp;base64,AAAA" → "media/academia/3f2a...c1.webp"
     */
    public static function salvarMidia(string $dataUri, ?string $alt = null): string
    {
        if (!preg_match('#^data:([a-z]+/[a-z0-9.+-]+);base64,(.+)$#s', $dataUri, $m)) {
            return $dataUri; // não é mídia embutida: mantém como está
        }
        $binario = base64_decode($m[2]);
        $hash = sha1($binario);
        $extensao = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4'][$m[1]] ?? 'bin';
        $caminho = self::PASTA_MIDIA . "/$hash.$extensao";
        $arquivo = RAIZ . '/public/' . $caminho;
        if (!is_file($arquivo)) {
            if (!is_dir(dirname($arquivo))) {
                mkdir(dirname($arquivo), 0775, true);
            }
            file_put_contents($arquivo, $binario);
        }
        if (!Banco::valor('SELECT 1 FROM midias WHERE arquivo = ?', [$caminho])) {
            Banco::inserir('midias', ['arquivo' => $caminho, 'tipo' => $m[1], 'tamanho' => strlen($binario), 'hash' => $hash,
                'alt' => $alt ? mb_substr($alt, 0, 255) : null, 'origem' => self::ORIGEM]);
        }
        return $caminho;
    }

    /** Percorre um array (de qualquer profundidade) trocando mídias embutidas por arquivos */
    private static function trocarMidias(mixed $valor): mixed
    {
        if (is_string($valor)) {
            return str_starts_with($valor, 'data:') && str_contains(substr($valor, 0, 60), ';base64,') ? self::salvarMidia($valor) : $valor;
        }
        if (is_array($valor)) {
            foreach ($valor as $chave => $item) {
                $valor[$chave] = self::trocarMidias($item);
            }
        }
        return $valor;
    }

    /**
     * Importa produtos, conteúdo e mídias para o banco (usado pelo seed).
     * Devolve um resumo: [produtos, blocos de conteúdo, mídias].
     */
    public function importarDados(): array
    {
        $dados = self::trocarMidias($this->blocoJson('app-data'));
        $marketing = self::trocarMidias($this->blocoJson('marketing-media'));

        // Categorias, na ordem em que aparecem
        $categorias = [];
        foreach ($dados['products'] as $p) {
            $nome = $p['cat'] ?? 'Outros';
            $categorias[$nome] ??= Banco::valor('SELECT id FROM categorias WHERE nome = ?', [$nome])
                ?: Banco::inserir('categorias', ['nome' => $nome, 'ordem' => count($categorias) + 1]);
        }

        // Produtos: campos principais em colunas; o restante da ficha em ficha_json
        foreach ($dados['products'] as $ordem => $p) {
            $imagemId = !empty($p['image']) ? Banco::valor('SELECT id FROM midias WHERE arquivo = ?', [$p['image']]) : null;
            $ficha = array_diff_key($p, array_flip(['id', 'slug', 'name', 'pack', 'cat', 'tagline', 'image']));
            $linha = [
                'slug' => $p['slug'], 'nome' => $p['name'], 'embalagem' => $p['pack'] ?? null, 'categoria_id' => $categorias[$p['cat'] ?? 'Outros'],
                'chamada' => isset($p['tagline']) ? mb_substr($p['tagline'], 0, 255) : null, 'imagem_id' => $imagemId ?: null,
                'ficha_json' => json_encode($ficha, JSON_UNESCAPED_UNICODE), 'ordem' => $ordem,
            ];
            $existente = Banco::valor('SELECT id FROM produtos WHERE slug = ?', [$p['slug']]);
            $existente ? Banco::atualizar('produtos', (int) $existente, $linha) : Banco::inserir('produtos', $linha);
            if ($imagemId) {
                Banco::executar('UPDATE midias SET alt = COALESCE(alt, ?) WHERE id = ?', ['Embalagem de ' . $p['name'], $imagemId]);
            }
        }

        // Demais blocos de conteúdo
        $blocos = ['modules' => $dados['modules'], 'topics' => $dados['topics'], 'crm' => $dados['crm'], 'media' => $dados['media'], 'marketing' => $marketing];
        foreach ($blocos as $chave => $conteudo) {
            Banco::executar(
                'INSERT INTO academia_conteudo (chave, conteudo_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE conteudo_json = VALUES(conteudo_json)',
                [$chave, json_encode($conteudo, JSON_UNESCAPED_UNICODE)]
            );
        }
        // A imagem embutida nas telas (logo da Central) também vira arquivo
        preg_replace_callback('#data:image/[a-z]+;base64,[A-Za-z0-9+/=]+#', fn($m) => self::salvarMidia($m[0], 'Logo Perfeita Alquimia'), $this->corpoBruto());

        return [count($dados['products']), count($blocos), (int) Banco::valor('SELECT COUNT(*) FROM midias')];
    }

    /** Telas (conteúdo do <body>) sem os blocos <script> */
    private function corpoBruto(): string
    {
        $inicio = strpos($this->html, '<body>') + 6;
        $corpo = substr($this->html, $inicio, strrpos($this->html, '</body>') - $inicio);
        // Remove os blocos <script> (os dados e o programa ficam em outros arquivos).
        // Feito com busca de texto, porque o arquivo é grande demais para expressão regular.
        while (($abre = strpos($corpo, '<script')) !== false) {
            $fecha = strpos($corpo, '</script>', $abre);
            $corpo = substr($corpo, 0, $abre) . substr($corpo, $fecha + 9);
        }
        return $corpo;
    }

    /**
     * Separa o código da Central (usado por ferramentas/gerar_academia.php):
     * [css, js, corpo]. As imagens do corpo viram "{{MIDIA:caminho}}" para o template trocar pelo endereço certo.
     */
    public function extrairCodigo(): array
    {
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $this->html, $estilos);
        $css = implode("\n", $estilos[1]);

        $inicioJs = strpos($this->html, "'use strict';\nconst data=JSON.parse");
        $js = substr($this->html, $inicioJs, strpos($this->html, '</script>', $inicioJs) - $inicioJs);
        // Os dados agora vêm do sistema (window.ACADEMIA_DADOS, carregado por /academia/dados.js)
        $js = str_replace("const data=JSON.parse(document.getElementById('app-data').textContent)", 'const data=window.ACADEMIA_DADOS', $js);
        $js = str_replace("const marketingMedia=JSON.parse(document.getElementById('marketing-media').textContent)", 'const marketingMedia=window.ACADEMIA_MARKETING', $js);

        $corpo = preg_replace_callback('#data:image/([a-z]+);base64,([A-Za-z0-9+/=]+)#', function ($m) {
            $extensao = ['jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'][$m[1]] ?? 'bin';
            return '{{MIDIA:' . self::PASTA_MIDIA . '/' . sha1(base64_decode($m[2])) . '.' . $extensao . '}}';
        }, $this->corpoBruto());
        return [$css, $js, $corpo];
    }
}
