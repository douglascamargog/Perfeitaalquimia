<?php
namespace App\Core;

/**
 * Atualização automática do banco.
 *
 * Problema que resolve: depois de extrair uma versão nova, o código novo já procura colunas/tabelas novas,
 * mas elas só existiam depois do clique em "Aplicar atualização" — e a própria tela de Atualização podia
 * quebrar antes disso (erro 500 no site inteiro).
 *
 * Agora, na primeira visita depois de extrair os arquivos, o sistema:
 *   1. vê que há migrations/seeds novos (comparação rápida pelo nome dos arquivos, guardada em storage/cache);
 *   2. faz o BACKUP automático do banco (storage/backups);
 *   3. aplica só o que falta (nunca dados de demonstração; as migrations só acrescentam).
 * Uma pessoa por vez (trava em arquivo); quem chegar junto espera alguns segundos.
 * Se der erro, mostra uma página simples de manutenção e registra no log (o site não fica com tela em branco).
 */
class AtualizacaoAutomatica
{
    private const MARCA = RAIZ . '/storage/cache/banco-em-dia.txt';
    private const TRAVA = RAIZ . '/storage/cache/atualizando.lock';

    public static function verificar(): void
    {
        $assinatura = self::assinatura();
        if (is_file(self::MARCA) && trim((string) file_get_contents(self::MARCA)) === $assinatura) {
            return; // banco em dia (o caso de quase todas as visitas: só lê um arquivo pequeno)
        }
        if (!is_file(RAIZ . '/config/config.php') || !self::instalado()) {
            return; // sistema ainda não instalado: quem cuida é a tela /instalar
        }
        if (!is_dir(dirname(self::TRAVA))) {
            @mkdir(dirname(self::TRAVA), 0750, true);
        }
        $trava = fopen(self::TRAVA, 'c');
        if (!$trava || !flock($trava, LOCK_EX)) {
            return;
        }
        try {
            $instalador = new Instalador();
            $faltam = $instalador->pendentes(false);
            if ($faltam['migrations'] || $faltam['seeds']) {
                @set_time_limit(300);
                $backup = \App\Services\BackupBanco::automatico(); // cópia de segurança ANTES de mexer
                $instalador->executar(false);
                Auditoria::registrar('sistema', null, 'atualizar_automatico', null, [
                    'versao' => \App\Services\Versoes::nome(), 'backup' => basename($backup), 'aplicado' => $instalador->log,
                ]);
            }
            file_put_contents(self::MARCA, $assinatura);
        } catch (\Throwable $e) {
            error_log('Atualização automática: ' . $e);
            self::paginaManutencao();
        } finally {
            flock($trava, LOCK_UN);
            fclose($trava);
        }
    }

    /** Nomes de todos os arquivos de banco desta versão: mudou = tem coisa nova para conferir */
    private static function assinatura(): string
    {
        $arquivos = array_merge(glob(RAIZ . '/database/migrations/*.sql') ?: [], glob(RAIZ . '/database/seeds/*.php') ?: []);
        return sha1(implode('|', array_map('basename', $arquivos)));
    }

    private static function instalado(): bool
    {
        try {
            return (bool) Banco::valor('SELECT COUNT(*) FROM migracoes');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Página simples, sem banco e sem layout (que poderiam ser justamente o que está com problema) */
    private static function paginaManutencao(): never
    {
        http_response_code(503);
        header('Retry-After: 60');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>One em manutenção</title><body style="font-family:system-ui,sans-serif;background:#f4f6f3;color:#182218;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px">'
            . '<main style="background:#fff;border-radius:20px;padding:28px;max-width:520px;box-shadow:0 8px 30px #0001">'
            . '<h1 style="margin:0 0 8px;font-size:1.4rem">O One está sendo atualizado</h1>'
            . '<p>A atualização do banco não terminou. Tente de novo em alguns minutos.</p>'
            . '<p style="color:#758078;font-size:.9rem">Para o administrador: o erro está em storage/logs/php-erros.log e o backup feito antes '
            . 'da atualização está em storage/backups. Se continuar, restaure o zip da versão anterior e fale com o suporte.</p></main></body></html>';
        exit;
    }
}
