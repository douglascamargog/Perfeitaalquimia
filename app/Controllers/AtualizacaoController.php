<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Instalador;
use App\Core\Sessao;
use App\Services\BackupBanco;

/**
 * Administração › Atualização do sistema (só admin).
 *
 * Depois de enviar os arquivos de uma versão nova para a hospedagem, o admin abre esta tela e clica em
 * "Aplicar atualização": o banco recebe só o que é NOVO (tabelas/campos/configurações). Nenhum dado é apagado
 * e nenhum dado de demonstração é misturado aos dados reais.
 */
class AtualizacaoController extends Controller
{
    public function index(): void
    {
        $instalador = new Instalador();
        $this->tela('atualizacao/index', [
            'titulo'     => 'Atualização do sistema',
            'versao'     => self::versao(),
            'pendentes'  => $instalador->pendentes(false),
            'historico'  => array_slice($instalador->historico(), 0, 30),
            'log'        => Sessao::get('log_atualizacao', []),
            'ultimoBackup' => \App\Services\BackupBanco::ultimo(),
        ]);
        Sessao::remover('log_atualizacao');
    }

    public function aplicar(): void
    {
        $instalador = new Instalador();
        try {
            $instalador->executar(false); // atualização: nunca carrega dados de demonstração
            Auditoria::registrar('sistema', null, 'atualizar', null, ['versao' => self::versao(), 'aplicado' => $instalador->log]);
            Sessao::aviso('sucesso', 'Atualização aplicada.');
        } catch (\Throwable $e) {
            error_log((string) $e);
            Sessao::aviso('erro', 'A atualização parou com erro: ' . $e->getMessage() . ' — restaure o backup se algo estiver errado.');
        }
        Sessao::set('log_atualizacao', $instalador->log);
        redirecionar('/sistema/atualizacao');
    }

    /** Baixa a cópia de segurança do banco (.sql.gz) */
    public function backup(): void
    {
        @set_time_limit(300);
        $arquivo = BackupBanco::gerar();
        Auditoria::registrar('sistema', null, 'backup_banco', null, ['tamanho_kb' => round(filesize($arquivo) / 1024)]);
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="one-backup-' . date('Y-m-d-His') . '.sql.gz"');
        header('Content-Length: ' . filesize($arquivo));
        readfile($arquivo);
        unlink($arquivo); // não fica cópia no servidor
    }

    /** Versão instalada, ex.: ONE_V2.0 (a lista das versões fica em app/versoes.php) */
    public static function versao(): string
    {
        return \App\Services\Versoes::nome();
    }
}
