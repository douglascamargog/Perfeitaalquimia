<?php
/**
 * Conteúdo da ACADEMIA (Central de Televendas V2): 161 produtos, trilha, temas de busca, aulas e mídias.
 * Lido de referencias/Central_TelevendasPA_V2.html (envie a pasta referencias/ por FTP antes de instalar).
 * As imagens e vídeos vão para public/media/academia/ e ficam registrados na tabela midias.
 */
$arquivo = RAIZ . '/referencias/Central_TelevendasPA_V2.html';
if (!is_file($arquivo)) {
    return 'AVISO: referencias/Central_TelevendasPA_V2.html não encontrado — a Academia ficou vazia.';
}
[$produtos, $blocos, $midias] = (new App\Services\CentralTelevendas($arquivo))->importarDados();
return "Academia: $produtos produtos, $blocos blocos de conteúdo, $midias mídias.";
