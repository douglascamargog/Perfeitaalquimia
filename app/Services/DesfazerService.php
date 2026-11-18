<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Sessao;

/**
 * "↶ Desfazer" (V15.9): depois de concluir ou excluir uma tarefa, ou apagar um registro da linha do tempo,
 * a próxima tela mostra por 30 segundos o botão Desfazer. Fica na sessão da própria pessoa (ninguém mais desfaz).
 */
class DesfazerService
{
    private const SEGUNDOS = 30;

    /** Guarda como voltar atrás. $tipo: tarefa_concluida | tarefa_excluida | evento_apagado; $dados: o que precisa para restaurar */
    public static function guardar(string $tipo, string $texto, array $dados): void
    {
        Sessao::set('desfazer', ['tipo' => $tipo, 'texto' => $texto, 'dados' => $dados, 'ate' => time() + self::SEGUNDOS]);
    }

    /** O que pode ser desfeito agora, ou null */
    public static function pendente(): ?array
    {
        $d = Sessao::get('desfazer');
        if (!$d || $d['ate'] < time()) {
            Sessao::remover('desfazer');
            return null;
        }
        return $d;
    }

    /**
     * Para o layout: o botão aparece UMA vez, na tela logo depois da ação (junto do aviso de sucesso).
     * Na tela seguinte ele some, para não desfazer algo antigo sem querer.
     */
    public static function paraMostrar(): ?array
    {
        $d = self::pendente();
        if (!$d || !empty($d['mostrado'])) {
            Sessao::remover('desfazer');
            return null;
        }
        Sessao::set('desfazer', ['mostrado' => true] + $d);
        return $d;
    }

    /** Desfaz a última ação. Devolve a mensagem para a pessoa. */
    public static function desfazer(int $usuarioId): string
    {
        $d = self::pendente();
        Sessao::remover('desfazer');
        if (!$d) {
            return 'Passou o tempo para desfazer (30 segundos).';
        }
        $x = $d['dados'];
        switch ($d['tipo']) {
            case 'tarefa_concluida':
                Banco::executar('UPDATE tarefas SET concluida_em = NULL WHERE id = ?', [$x['id']]);
                if (!empty($x['evento_id'])) {
                    Banco::executar('DELETE FROM cliente_eventos WHERE id = ?', [$x['evento_id']]);
                }
                break;
            case 'tarefa_excluida':
            case 'evento_apagado':
                $tabela = $d['tipo'] === 'tarefa_excluida' ? 'tarefas' : 'cliente_eventos';
                $linha = array_intersect_key($x['linha'], array_flip(array_column(Banco::todos("SHOW COLUMNS FROM $tabela"), 'Field')));
                Banco::inserir($tabela, $linha); // volta com o mesmo número (id)
                foreach ((array) ($x['anexos'] ?? []) as $anexoId) {
                    Banco::executar('UPDATE cliente_anexos SET evento_id = ? WHERE id = ?', [$linha['id'], $anexoId]);
                }
                break;
            default:
                return 'Nada para desfazer.';
        }
        Auditoria::registrar('desfazer', null, $d['tipo'], null, ['texto' => $d['texto']]);
        return 'Desfeito: ' . $d['texto'] . '.';
    }
}
