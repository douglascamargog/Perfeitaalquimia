<?php
namespace App\Integrations\LigueTalk;

/**
 * CONTRATO com a telefonia (Ligue Talk). O sistema só conversa com a telefonia por aqui.
 *   - LigueTalkManual: sem API (o "Ligar" abre o telefone e a pessoa registra o resultado com 1 clique);
 *   - LigueTalkFake: demonstração (ligações e gravações simuladas);
 *   - LigueTalkClient: API v1 do Ligue Talk (relatórios de gravações/tempo falado e listas do discador).
 * Trocar de um para outro é só mudar Configurações › Ligue Talk › Modo (Integracoes::telefonia()).
 *
 * A API v1 do Ligue Talk NÃO tem "discar para um número": a ligação sai pelo softphone/discador do Ligue Talk.
 * O sistema (1) manda listas de clientes para o discador e (2) busca as chamadas e gravações para o Acompanhamento.
 */
interface TelefoniaInterface
{
    /** Registra uma ligação na linha do tempo do cliente. Devolve o id do evento criado. */
    public function registrarLigacao(int $clienteId, int $usuarioId, string $resultado, ?string $observacao = null): int;

    /**
     * Chamadas do período, já no formato do sistema:
     * [['id_externo','usuario_externo','origem_num','destino_num','situacao','inicio','duracao','gravacao','tipo'], ...]
     */
    public function listarChamadas(string $desde, ?string $ate = null): array;

    /** true se as chamadas chegam sozinhas (API ou simulação); false se a pessoa registra à mão */
    public function automatico(): bool;

    /**
     * "Ligar" a partir da ficha. Sem discagem pela API: devolve 'situacao' => 'manual' (o sistema abre tel:)
     * ou, na demonstração, o resultado simulado ['id_externo','situacao','duracao','gravacao','mensagem'].
     */
    public function ligar(string $ramal, string $numero): array;

    /** Envia clientes como uma lista para o discador. $leads = [['telefone','nome','cidade','uf'], ...]. Devolve ['ok','mensagem'] */
    public function enviarLista(string $nome, array $leads): array;

    /** Conteúdo (bytes) da gravação a partir do que ficou guardado em ligacoes.gravacao, ou null */
    public function gravacao(string $referencia): ?string;
}
