<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\Nota10\Conferencia;
use App\Services\Nota10\Leitor;

/**
 * Administração › Importar Nota10 (só o Admin: permissão "sistema.nota10", que nenhum outro perfil recebe).
 * Etapa 1 (V16.2): enviar o backup, ler para a área separada (n10_) e conferir. Ainda NÃO grava nada nos dados do One.
 */
class Nota10Controller extends Controller
{
    public function index(): void
    {
        $imp = Banco::um('SELECT * FROM nota10_importacoes ORDER BY id DESC LIMIT 1');
        $dados = ['titulo' => 'Importar Nota10', 'imp' => $imp, 'soltos' => Leitor::arquivosSoltos(),
            'limite' => self::limiteEnvio(), 'estilos' => ['nota10.css'], 'scripts' => ['nota10.js']];
        if ($imp && $imp['situacao'] === 'lido') {
            $cli = Conferencia::clientes();
            $notas = Conferencia::notas();
            $itens = Conferencia::itens();
            $prod = Conferencia::produtos();
            $dados += ['tabelas' => Conferencia::tabelas(), 'cli' => $cli, 'notas' => $notas, 'itens' => $itens, 'prod' => $prod,
                'titulos' => Conferencia::titulos(), 'outros' => Conferencia::outros(),
                'problemas' => Conferencia::problemas($cli, $notas, $itens, $prod), 'ligar' => Conferencia::paraLigar(),
                'usuarios' => Banco::todos('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome'),
                'tabelasOne' => Banco::todos('SELECT id, nome FROM tabelas_preco WHERE ativa = 1 ORDER BY id'),
                'formasOne' => Banco::todos('SELECT id, nome FROM formas_pagamento WHERE ativa = 1 ORDER BY ordem, id'),
                'segmentosOne' => Banco::todos('SELECT id, nome FROM segmentos WHERE ativo = 1 ORDER BY ordem, nome')];
            $dados['scripts'] = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'nota10.js'];
        }
        $this->tela('sistema/nota10', $dados);
    }

    /** Recebe o arquivo enviado pela tela */
    public function enviar(): void
    {
        $f = $_FILES['backup'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $erro = match ($f['error'] ?? UPLOAD_ERR_NO_FILE) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo passou do limite de envio da hospedagem (' . self::limiteEnvio() . '). Envie compactado em .zip, ou coloque o arquivo na pasta storage/nota10 pelo Gerenciador de arquivos do cPanel.',
                UPLOAD_ERR_NO_FILE => 'Escolha o arquivo do backup.',
                default => 'O envio não terminou. Tente de novo.',
            };
            Sessao::aviso('erro', $erro);
            redirecionar('/sistema/nota10');
        }
        try {
            $id = Leitor::registrar($f['tmp_name'], (string) $f['name'], (int) Auth::id());
        } catch (\Throwable $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar('/sistema/nota10');
        }
        Auditoria::registrar('configuracoes', $id, 'nota10_backup_enviado', null, ['arquivo' => mb_substr((string) $f['name'], 0, 120)]);
        Sessao::aviso('sucesso', 'Backup recebido. Clique em "Ler o backup" para começar.');
        redirecionar('/sistema/nota10');
    }

    /** Usa um arquivo que já está na pasta storage/nota10 (enviado por FTP ou pelo cPanel) */
    public function usarArquivo(): void
    {
        $nome = basename((string) ($_POST['arquivo'] ?? ''));
        $caminho = Leitor::pasta() . '/' . $nome;
        if ($nome === '' || !is_file($caminho)) {
            Sessao::aviso('erro', 'Arquivo não encontrado na pasta storage/nota10.');
            redirecionar('/sistema/nota10');
        }
        try {
            $id = Leitor::registrar($caminho, $nome, (int) Auth::id());
        } catch (\Throwable $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar('/sistema/nota10');
        }
        Auditoria::registrar('configuracoes', $id, 'nota10_backup_enviado', null, ['arquivo' => $nome]);
        Sessao::aviso('sucesso', 'Arquivo pronto. Clique em "Ler o backup" para começar.');
        redirecionar('/sistema/nota10');
    }

    /** Lê mais uma parte do backup (a tela chama de novo até terminar). Responde JSON com o progresso. */
    public function ler(string $id): void
    {
        try {
            $imp = Leitor::lerParte((int) $id);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $this->json(['erro' => 'Falha ao ler: ' . mb_substr($e->getMessage(), 0, 300)], 500);
            return;
        }
        if (!$imp) {
            $this->json(['erro' => 'Importação não encontrada.'], 404);
            return;
        }
        $tam = max(1, (int) $imp['tamanho']);
        $this->json(['situacao' => $imp['situacao'], 'pct' => min(100, round((int) $imp['posicao'] / $tam * 100, 1)),
            'comandos' => (int) $imp['comandos'], 'erro' => $imp['erro']]);
    }

    /** Recomeça a leitura do zero (depois de um erro, ou para ler o mesmo arquivo de novo) */
    public function recomecar(string $id): void
    {
        Banco::atualizar('nota10_importacoes', (int) $id, ['posicao' => 0, 'situacao' => 'enviado', 'erro' => null, 'comandos' => 0, 'ignorados' => 0]);
        redirecionar('/sistema/nota10');
    }

    /** Começa a importação de verdade (o backup precisa estar lido) */
    public function importar(string $id): void
    {
        $imp = Banco::um('SELECT * FROM nota10_importacoes WHERE id = ?', [(int) $id]);
        if (!$imp || $imp['situacao'] !== 'lido') {
            Sessao::aviso('erro', 'Leia o backup antes de importar.');
            redirecionar('/sistema/nota10');
        }
        if (mb_strtoupper(trim($this->campo('confirmar'))) !== 'IMPORTAR') {
            Sessao::aviso('erro', 'Para começar, digite IMPORTAR.');
            redirecionar('/sistema/nota10#importar');
        }
        \App\Services\Nota10\Importador::iniciar((int) $id, (int) Auth::id());
        redirecionar('/sistema/nota10#importar');
    }

    /** Roda mais uma parte da importação (a tela chama de novo até terminar). Responde JSON. */
    public function importarParte(string $id): void
    {
        try {
            $r = \App\Services\Nota10\Importador::parte((int) $id);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $this->json(['erro' => 'Falha na importação: ' . mb_substr($e->getMessage(), 0, 400)], 500);
            return;
        }
        $this->json($r);
    }

    /** Grava as ligações escolhidas (vendedores, tabelas, formas, segmentos, tipos de cadastro) */
    public function mapas(): void
    {
        // As escolhas chegam num campo só (JSON, montado pelo navegador); sem JavaScript, nos campos mapa[...] de sempre
        $json = json_decode((string) ($_POST['mapa_json'] ?? ''), true);
        $n = Conferencia::salvarMapas(is_array($json) && $json ? $json : (array) ($_POST['mapa'] ?? []));
        Auditoria::registrar('configuracoes', null, 'nota10_ligacoes', null, ['ligacoes' => $n]);
        Sessao::aviso('sucesso', 'Ligações guardadas. Elas serão usadas na importação.');
        redirecionar('/sistema/nota10#ligacoes');
    }

    /** Apaga a área separada e o arquivo do backup do servidor (LGPD: não deixar cópia parada) */
    public function descartar(string $id): void
    {
        $imp = Banco::um('SELECT * FROM nota10_importacoes WHERE id = ?', [(int) $id]);
        if ($imp && $imp['importado_em']) {
            @unlink(Leitor::PASTA . '/' . basename($imp['arquivo']));
            Auditoria::registrar('configuracoes', (int) $id, 'nota10_arquivo_apagado', null, ['arquivo' => $imp['nome_original']]);
            Sessao::aviso('sucesso', 'Arquivo do backup apagado do servidor. Os dados do Nota10 continuam guardados no One.');
            redirecionar('/sistema/nota10');
        }
        if ($imp) {
            Leitor::limparArea();
            @unlink(Leitor::PASTA . '/' . basename($imp['arquivo']));
            Banco::executar('DELETE FROM nota10_importacoes WHERE id = ?', [(int) $id]);
            Auditoria::registrar('configuracoes', (int) $id, 'nota10_backup_descartado', null, ['arquivo' => $imp['nome_original']]);
        }
        Sessao::aviso('sucesso', 'Backup e área separada apagados do servidor.');
        redirecionar('/sistema/nota10');
    }

    private static function limiteEnvio(): string
    {
        $bytes = fn(string $v) => (int) $v * (['k' => 1 << 10, 'm' => 1 << 20, 'g' => 1 << 30][strtolower(substr(trim($v), -1))] ?? 1);
        $menor = min($bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX);
        return number_format($menor / (1 << 20), 0, ',', '.') . ' MB';
    }
}
