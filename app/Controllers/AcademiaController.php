<?php
namespace App\Controllers;

use App\Core\Banco;
use App\Core\Visao;

/**
 * Academia = Central de Televendas, agora servida pelo sistema (com login).
 *
 * - /academia          → as telas da Central (mesmo visual e funcionamento do arquivo original)
 * - /academia/dados.js → produtos e conteúdo, montados a partir do banco no formato que a Central espera
 *
 * "Meu mês", o progresso de estudo e o backup continuam salvos no navegador de cada pessoa, como antes.
 */
class AcademiaController extends Controller
{
    public function central(): void
    {
        Visao::mostrar('academia/central', ['titulo' => 'Academia Comercial'], 'academia');
    }

    public function dados(): void
    {
        // Versão dos dados: muda quando um produto ou bloco de conteúdo é alterado (o navegador guarda em cache até lá)
        $versao = md5(implode('|', [
            Banco::valor('SELECT CONCAT(COUNT(*), MAX(atualizado_em)) FROM produtos'),
            Banco::valor('SELECT CONCAT(COUNT(*), MAX(atualizado_em)) FROM academia_conteudo'),
            url_base(),
        ]));
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('ETag: "' . $versao . '"');
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), '"') === $versao) {
            http_response_code(304);
            return;
        }

        $conteudo = array_column(Banco::todos('SELECT chave, conteudo_json FROM academia_conteudo'), 'conteudo_json', 'chave');
        $bloco = fn(string $chave) => self::enderecos(json_decode($conteudo[$chave] ?? '[]', true));

        $produtos = [];
        $linhas = Banco::todos(
            'SELECT p.*, c.nome AS categoria, m.arquivo AS imagem FROM produtos p
               LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id
              WHERE p.ativo = 1 ORDER BY p.ordem, p.nome'
        );
        foreach ($linhas as $p) {
            $produtos[] = ['id' => (int) $p['ordem'], 'slug' => $p['slug'], 'name' => $p['nome'], 'pack' => $p['embalagem'], 'cat' => $p['categoria'],
                'tagline' => $p['chamada'], 'image' => $p['imagem'] ? url('/' . $p['imagem']) : ''] + self::enderecos(json_decode($p['ficha_json'], true));
        }
        $dados = ['products' => $produtos, 'modules' => $bloco('modules'), 'topics' => $bloco('topics'), 'crm' => $bloco('crm'), 'media' => $bloco('media')];

        if (str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') && !ini_get('zlib.output_compression')) {
            ob_start('ob_gzhandler'); // ~1,4 MB de texto viram ~300 KB
        }
        echo 'window.ACADEMIA_DADOS=' . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
        echo 'window.ACADEMIA_MARKETING=' . json_encode($bloco('marketing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
    }

    /** Caminhos de mídia guardados como "media/academia/x.webp" viram endereços completos do site */
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
