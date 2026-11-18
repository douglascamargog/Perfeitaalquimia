<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Cliente;
use App\Models\ClienteEvento;

/**
 * Corrigir ou apagar um registro da linha do tempo (V15.7): quem registrou, em até 24 horas; quem gerencia a Carteira, sempre.
 * Só os registros digitados (anotação, contato, ligação...). Tudo fica na auditoria, com o texto de antes.
 */
class EventoController extends Controller
{
    public function editar(string $clienteId, string $eventoId): void
    {
        $ev = $this->evento($clienteId, $eventoId);
        $texto = mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 2000);
        if ($texto === '') {
            Sessao::aviso('erro', 'O texto não pode ficar vazio. Para tirar o registro, use "Apagar registro".');
        } else {
            Banco::executar('UPDATE cliente_eventos SET descricao = ?, editado_em = NOW() WHERE id = ?', [$texto, $ev['id']]);
            Auditoria::registrar('cliente_evento', (int) $ev['id'], 'corrigir', ['descricao' => $ev['descricao']], ['descricao' => $texto]);
            Sessao::aviso('sucesso', 'Registro corrigido.');
        }
        redirecionar("/carteira/clientes/$clienteId");
    }

    public function apagar(string $clienteId, string $eventoId): void
    {
        $ev = $this->evento($clienteId, $eventoId);
        \App\Services\DesfazerService::guardar('evento_apagado', 'o registro voltou para a linha do tempo', ['linha' => $ev,
            'anexos' => array_map('intval', array_column(Banco::todos('SELECT id FROM cliente_anexos WHERE evento_id = ?', [$ev['id']]), 'id'))]);
        Banco::transacao(function () use ($ev) {
            Banco::executar('UPDATE cliente_anexos SET evento_id = NULL WHERE evento_id = ?', [$ev['id']]); // o print continua na loja
            Banco::executar('DELETE FROM cliente_eventos WHERE id = ?', [$ev['id']]);
        });
        Auditoria::registrar('cliente_evento', (int) $ev['id'], 'apagar', ['cliente_id' => $ev['cliente_id'], 'tipo' => $ev['tipo'], 'descricao' => $ev['descricao'], 'ocorrido_em' => $ev['ocorrido_em']], null);
        Sessao::aviso('sucesso', 'Registro apagado da linha do tempo.');
        redirecionar("/carteira/clientes/$clienteId");
    }

    private function evento(string $clienteId, string $eventoId): array
    {
        $cliente = Cliente::buscar((int) $clienteId);
        $ev = Banco::um('SELECT * FROM cliente_eventos WHERE id = ? AND cliente_id = ?', [(int) $eventoId, (int) $clienteId]);
        if (!$cliente || !$ev || !Cliente::podeVer($cliente) || !ClienteEvento::podeMexer($ev)) {
            Sessao::aviso('erro', 'Este registro não pode mais ser mudado (passou de 24 horas, é automático ou não é seu).');
            redirecionar("/carteira/clientes/$clienteId");
        }
        return $ev;
    }
}
