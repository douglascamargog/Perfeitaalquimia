<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\ZerarDadosService as Z;

/**
 * Administração › Zerar dados: apaga os dados do dia a dia (com backup antes) para recomeçar com os dados reais.
 * Só o perfil Admin (permissão "sistema.zerar", que nenhum outro perfil recebe). Pede a senha e a palavra ZERAR.
 */
class ZerarDadosController extends Controller
{
    public function index(): void
    {
        $this->tela('sistema/zerar', [
            'titulo' => 'Zerar dados',
            'contagem' => Z::contagem(),
            'semClassificacao' => Z::semClassificacao(),
            'resultado' => Sessao::get('zerar_resultado'),
        ]);
        Sessao::remover('zerar_resultado');
    }

    public function zerar(): void
    {
        $hash = (string) Banco::valor('SELECT senha_hash FROM usuarios WHERE id = ?', [Auth::id()]);
        if (!password_verify((string) ($_POST['senha'] ?? ''), $hash)) {
            Sessao::aviso('erro', 'Senha incorreta. Nada foi apagado.');
            redirecionar('/sistema/zerar');
        }
        if (mb_strtoupper(trim((string) ($_POST['confirmar'] ?? ''))) !== Z::FRASE) {
            Sessao::aviso('erro', 'Para confirmar, digite ' . Z::FRASE . '. Nada foi apagado.');
            redirecionar('/sistema/zerar');
        }
        try {
            $r = Z::zerar((int) Auth::id());
        } catch (\Throwable $e) {
            error_log((string) $e);
            Sessao::aviso('erro', 'Não foi possível zerar: ' . mb_substr($e->getMessage(), 0, 300) . ' Nada foi apagado.');
            redirecionar('/sistema/zerar');
        }
        Sessao::remover('pendencias');
        Sessao::set('zerar_resultado', $r);
        Sessao::aviso('sucesso', number_format(array_sum($r['apagados']), 0, ',', '.') . ' registro(s) e ' . $r['arquivos'] . ' arquivo(s) apagados.');
        redirecionar('/sistema/zerar');
    }
}
