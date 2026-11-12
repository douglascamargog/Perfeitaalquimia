<?php
namespace App\Controllers;

use App\Core\Auth;

/**
 * Tela inicial em blocos: saudação do One, previsão do tempo, agenda, metas, carteira, cobrança,
 * funil, sugestões do One e os módulos disponíveis para o perfil.
 */
class InicioController extends Controller
{
    public function index(): void
    {
        $modulos = array_filter(require RAIZ . '/app/menu.php', fn($m) => $m['url'] !== '/' && self::visivel($m));
        $this->tela('inicio/index', [
            'titulo'   => 'Início',
            'usuario'  => Auth::usuario(),
            'modulos'  => $modulos,
            // One, o ajudante: resumo do dia
            'saudacao' => \App\Services\OneAssistente::saudacao(),
            'falas'    => \App\Services\OneAssistente::falas('/'),
            // Blocos do painel (tempo, agenda, metas, carteira, cobrança...)
            'painel'   => \App\Services\PainelInicio::montar(),
            'estilos'  => ['inicio.css'],
            'scripts'  => ['inicio.js'],
        ]);
    }

    /** O item do menu aparece para este usuário? */
    public static function visivel(array $item): bool
    {
        foreach ((array) $item['permissao'] as $permissao) {
            if ($permissao === 'logado' || Auth::pode($permissao)) {
                return true;
            }
        }
        return false;
    }
}
