<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\DesfazerService;

/** "↶ Desfazer" da última ação (V15.9) — só a própria pessoa, até 30 segundos depois */
class DesfazerController extends Controller
{
    public function desfazer(): void
    {
        $msg = DesfazerService::desfazer((int) Auth::id());
        Sessao::aviso(str_starts_with($msg, 'Desfeito') ? 'sucesso' : 'aviso', $msg);
        $voltar = (string) ($_POST['voltar'] ?? '');
        redirecionar(preg_match('#^/[a-z0-9/_\-]*$#i', $voltar) ? $voltar : '/');
    }
}
