<?php
/**
 * Ferramenta de uso único: separa o CÓDIGO da Central de Televendas original em arquivos do sistema.
 *
 *     php ferramentas/gerar_academia.php
 *
 * Gera (e estes arquivos vão para o git):
 *   public/assets/academia/academia.css   → estilos da Central
 *   public/assets/academia/academia.js    → programa da Central (lendo os dados do sistema)
 *   app/Views/academia/central.php         → telas da Central
 *
 * Os DADOS (produtos, trilha, imagens) não ficam aqui: entram no banco pelo seed database/seeds/004_academia.php.
 * Só rode de novo se receber uma versão nova da Central (e confira as diferenças antes do commit).
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo terminal.');
}
require __DIR__ . '/../app/bootstrap.php';

$central = new App\Services\CentralTelevendas(RAIZ . '/referencias/Central_TelevendasPA_V2.html');
[$css, $js, $corpo] = $central->extrairCodigo();

$cabecalho = "/* Gerado por ferramentas/gerar_academia.php a partir da Central de Televendas V2 (versão 14.2). */\n";
file_put_contents(RAIZ . '/public/assets/academia/academia.css', $cabecalho . $css);
file_put_contents(RAIZ . '/public/assets/academia/academia.js', "// Gerado por ferramentas/gerar_academia.php a partir da Central de Televendas V2 (versão 14.2).\n"
    . "// Os dados vêm de /academia/dados.js (produtos e conteúdo cadastrados no banco do sistema).\n" . $js);

// Template PHP: protege qualquer "<?" do conteúdo e troca as imagens pelo endereço do arquivo
$corpo = str_replace('<?', '<?= \'<?\' ?>', $corpo);
$corpo = preg_replace_callback('/\{\{MIDIA:([^}]+)\}\}/', fn($m) => "<?= url('/" . $m[1] . "') ?>", $corpo);
$template = "<?php\n/**\n * Telas da Central de Televendas (Academia), geradas por ferramentas/gerar_academia.php.\n"
    . " * Para mudar textos fixos daqui, edite este arquivo; produtos e trilha ficam no banco.\n */\n?>\n" . trim($corpo) . "\n";
file_put_contents(RAIZ . '/app/Views/academia/central.php', $template);

echo "Gerados: academia.css (" . round(strlen($css) / 1024) . " KB), academia.js (" . round(strlen($js) / 1024) . " KB), central.php (" . round(strlen($template) / 1024) . " KB)\n";
