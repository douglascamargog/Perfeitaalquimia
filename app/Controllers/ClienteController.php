<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Core\Visao;
use App\Models\Cliente;
use App\Models\ClienteEvento;
use App\Models\Listas;
use App\Models\Tarefa;
use App\Services\CarteiraService;
use App\Services\ConflitoException;
use App\Services\Normalizar;

/**
 * Ficha do cliente (drawer), cadastro e ações: salvar, registrar contato, ligação, mudar etapa.
 * As ações chamadas pelo JavaScript respondem em JSON; os formulários comuns redirecionam.
 */
class ClienteController extends Controller
{
    /** Carrega o cliente e confere se o usuário pode vê-lo (senão: 404/403 e para) */
    /**
     * Para quem a pessoa pode passar este cliente: quem tem "Transferir cliente" escolhe qualquer vendedor;
     * o(a) gestor(a) do setor passa os clientes do setor para alguém da equipe do setor.
     */
    private static function paraTransferir(array $cliente): array
    {
        if (Auth::pode('carteira.transferir')) {
            return Listas::vendedores();
        }
        return in_array((int) ($cliente['funcao_id'] ?? 0), \App\Services\Setores::gerenciados(), true) ? \App\Services\Setores::equipeGerenciada() : [];
    }

    private function clienteOuErro(string $id): array
    {
        $cliente = Cliente::buscar((int) $id);
        if (!$cliente) {
            $this->json(['ok' => false, 'mensagem' => 'Cliente não encontrado.'], 404);
            exit;
        }
        if (!Cliente::podeVer($cliente)) {
            http_response_code(403);
            $this->json(['ok' => false, 'mensagem' => 'Este cliente não está na sua carteira.'], 403);
            exit;
        }
        return $cliente;
    }

    /** HTML da ficha para o drawer */
    public function ficha(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        \App\Services\NotificacaoService::marcarDoCliente((int) Auth::id(), (int) $cliente['id']); // abriu a ficha: novidades vistas
        echo Visao::renderizar('carteira/_ficha', [
            'c'           => $cliente,
            'eventos'     => ClienteEvento::doCliente((int) $cliente['id'], 40),
            'retorno'     => Tarefa::proximoRetorno((int) $cliente['id']),
            'etapas'      => Listas::etapas(),
            'prioridades' => Listas::prioridades(),
            'vendedores'  => self::paraTransferir($cliente),
            'canais'      => array_keys(CarteiraService::CANAIS),
            'cobranca'    => \App\Models\Cobranca::doCliente((int) $cliente['id']),
            'pedidos'     => \App\Models\Pedido::doCliente((int) $cliente['id'], 5),
            'acesso'      => \App\Core\Banco::um('SELECT senha_hash IS NOT NULL AS tem_senha, ultimo_acesso_em FROM cliente_acessos WHERE cliente_id = ?', [$cliente['id']]),
            'codigoCatalogo' => \App\Core\Sessao::get('codigo_catalogo_' . $cliente['id']),
        ]);
        \App\Core\Sessao::remover('codigo_catalogo_' . $cliente['id']); // o código aparece uma vez só
    }

    /**
     * Página do cliente: tudo da empresa numa tela só (a gaveta continua como acesso rápido).
     * Mesma ficha da gaveta (com os mesmos formulários) + números de 12 meses, todos os pedidos,
     * produtos mais comprados, produtos com a marca do cliente e a linha do tempo completa.
     */
    public function pagina(string $id): void
    {
        $cliente = Cliente::buscar((int) $id);
        if (!$cliente || !Cliente::podeVer($cliente)) {
            http_response_code($cliente ? 403 : 404);
            Visao::mostrar('erros/erro', ['titulo' => 'Cliente', 'codigo' => $cliente ? 403 : 404,
                'mensagem' => $cliente ? 'Este cliente não está na sua carteira.' : 'Cliente não encontrado.'], 'base');
            return;
        }
        $cid = (int) $cliente['id'];
        \App\Services\NotificacaoService::marcarDoCliente((int) Auth::id(), $cid);
        $de = date('Y-m-d', strtotime('-12 months'));
        $resumo = \App\Core\Banco::um("SELECT COALESCE(SUM(total), 0) AS faturado, COUNT(*) AS pedidos, MIN(faturado_em) AS primeira
            FROM pedidos WHERE cliente_id = ? AND situacao = 'faturado' AND faturado_em >= ?", [$cid, $de]);
        $this->tela('carteira/cliente', [
            'titulo' => $cliente['nome_fantasia'] ?: $cliente['razao_social'],
            'c' => $cliente, 'resumo' => $resumo,
            'primeiraCompra' => \App\Core\Banco::valor("SELECT MIN(faturado_em) FROM pedidos WHERE cliente_id = ? AND situacao = 'faturado'", [$cid]),
            'todosPedidos' => \App\Models\Pedido::doCliente($cid, 50),
            'maisComprados' => \App\Core\Banco::todos("SELECT i.nome, COALESCE(i.codigo, pr.codigo) AS codigo, SUM(i.quantidade) AS quantidade, SUM(i.total) AS valor, MAX(p.faturado_em) AS ultima
                FROM pedido_itens i JOIN pedidos p ON p.id = i.pedido_id LEFT JOIN produtos pr ON pr.id = i.produto_id
                WHERE p.cliente_id = ? AND p.situacao = 'faturado' AND p.faturado_em >= ? GROUP BY i.nome, COALESCE(i.codigo, pr.codigo) ORDER BY valor DESC LIMIT 12", [$cid, $de]),
            'marca' => \App\Services\TerceirizacaoService::produtos($cid),
            'tarefas' => \App\Core\Banco::todos('SELECT t.*, u.nome AS usuario FROM tarefas t LEFT JOIN usuarios u ON u.id = t.usuario_id
                WHERE t.cliente_id = ? AND t.concluida_em IS NULL ORDER BY t.vence_em, t.hora', [$cid]),
            'linhaDoTempo' => ClienteEvento::doCliente($cid, 200),
            // a ficha (mesma da gaveta), sem o cabeçalho
            'ficha' => Visao::renderizar('carteira/_ficha', [
                'c' => $cliente, 'eventos' => [], 'retorno' => Tarefa::proximoRetorno($cid), 'etapas' => Listas::etapas(),
                'prioridades' => Listas::prioridades(), 'vendedores' => self::paraTransferir($cliente),
                'canais' => array_keys(CarteiraService::CANAIS), 'cobranca' => \App\Models\Cobranca::doCliente($cid), 'pedidos' => [],
                'acesso' => \App\Core\Banco::um('SELECT senha_hash IS NOT NULL AS tem_senha, ultimo_acesso_em FROM cliente_acessos WHERE cliente_id = ?', [$cid]),
                'codigoCatalogo' => \App\Core\Sessao::get('codigo_catalogo_' . $cid), 'pagina' => true,
            ]),
            'cobranca' => \App\Models\Cobranca::doCliente($cid),
            'estilos' => ['carteira.css', 'cliente.css'], 'scripts' => ['carteira.js', 'busca_contatos.js'],
        ]);
        \App\Core\Sessao::remover('codigo_catalogo_' . $cid);
    }

    /**
     * Gera um código de acesso ao catálogo para o vendedor mandar pelo WhatsApp
     * (primeiro acesso ou esqueceu a senha). O código aparece na ficha uma vez só.
     */
    public function acessoCatalogo(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        if (!$cliente['cnpj']) {
            $this->json(['ok' => false, 'mensagem' => 'Cadastre o CNPJ do cliente primeiro: o login do catálogo é pelo CNPJ.'], 422);
            return;
        }
        \App\Core\Sessao::set('codigo_catalogo_' . $cliente['id'], \App\Services\CatalogoAcesso::gerarCodigo((int) $cliente['id']));
        \App\Core\Auditoria::registrar('cliente', (int) $cliente['id'], 'codigo_catalogo');
        $this->json(['ok' => true, 'mensagem' => 'Código gerado. Envie pelo WhatsApp.']);
    }

    /** Salva os campos da ficha (gestão comercial) — chamado pelo drawer */
    public function salvar(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        $dados = array_intersect_key($_POST, array_flip(['etapa_id', 'prioridade', 'responsavel_id', 'proximo_contato', 'observacoes']));
        try {
            $podeTransferir = Auth::pode('carteira.transferir')
                || in_array((int) ($dados['responsavel_id'] ?? 0), array_map('intval', array_column(self::paraTransferir($cliente), 'id')), true);
            CarteiraService::salvar((int) $cliente['id'], $dados, (string) ($_POST['atualizado_em'] ?? ''), Auth::id(), $podeTransferir);
            $this->json(['ok' => true, 'mensagem' => 'Salvo para toda a equipe.']);
        } catch (ConflitoException $e) {
            $this->json(['ok' => false, 'conflito' => true, 'mensagem' => $e->getMessage()], 409);
        }
    }

    public function contato(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->campo('data')) ? $this->campo('data') : date('Y-m-d');
        if ($data > date('Y-m-d')) {
            $this->json(['ok' => false, 'mensagem' => 'A data do contato não pode ser no futuro.'], 422);
            return;
        }
        $canal = array_key_exists($this->campo('canal'), CarteiraService::CANAIS) ? $this->campo('canal') : 'Outro';
        Banco::transacao(function () use ($cliente, $data, $canal) {
            CarteiraService::registrarContato((int) $cliente['id'], $data, $canal, mb_substr($this->campo('resumo'), 0, 2000), Auth::id());
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->campo('proximo_contato'))) {
                Tarefa::definirRetorno((int) $cliente['id'], (int) $cliente['responsavel_id'], $this->campo('proximo_contato'));
            }
        });
        $this->json(['ok' => true, 'mensagem' => 'Contato registrado.']);
    }

    /** Ligação com 1 clique */
    public function ligacao(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        $resultados = ['Atendeu', 'Não atendeu', 'Caixa postal', 'Número errado'];
        $resultado = in_array($this->campo('resultado'), $resultados, true) ? $this->campo('resultado') : 'Atendeu';
        CarteiraService::registrarLigacao((int) $cliente['id'], 'Ligação: ' . mb_strtolower($resultado), Auth::id());
        $this->json(['ok' => true, 'mensagem' => 'Ligação registrada.']);
    }

    /** Muda a etapa (arrastar no funil) */
    public function etapa(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        $etapaId = (int) $this->campo('etapa_id');
        if (!Banco::valor('SELECT 1 FROM funil_etapas WHERE id = ? AND ativa = 1', [$etapaId])) {
            $this->json(['ok' => false, 'mensagem' => 'Etapa inválida.'], 422);
            return;
        }
        CarteiraService::mudarEtapa((int) $cliente['id'], $etapaId, Auth::id());
        $this->json(['ok' => true]);
    }

    // ---------------- Cadastro completo (formulário em página) ----------------

    public function novo(): void
    {
        $this->formulario(null);
    }

    public function editar(string $id): void
    {
        $cliente = Cliente::buscar((int) $id);
        if (!$cliente || !Cliente::podeVer($cliente)) {
            Sessao::aviso('erro', 'Cliente não encontrado na sua carteira.');
            redirecionar('/carteira/lista');
        }
        $this->formulario($cliente);
    }

    private function formulario(?array $cliente): void
    {
        $this->tela('carteira/form', [
            'titulo'     => $cliente ? 'Editar cadastro' : 'Novo estabelecimento',
            'c'          => $cliente,
            'segmentos'  => Listas::segmentos(),
            'origens'    => Listas::origens(),
            'tabelas'    => Listas::tabelasPreco(),
            'ufs'        => Listas::ufs(),
            'prioridades' => Listas::prioridades(),
            'estilos'    => ['carteira.css'],
        ]);
    }

    public function criar(): void
    {
        [$dados, $erros] = $this->dadosCadastro(null);
        if ($erros) {
            $this->voltarComErros('/carteira/clientes/novo', $erros);
        }
        $dados['prioridade'] = $this->campo('prioridade');
        // Vendedor cadastrando: o cliente já fica com ele. Gestor: segue a regra da função de novos leads.
        if (!Auth::pode('carteira.ver_todas')) {
            $dados['responsavel_id'] = Auth::id();
        }
        $id = Banco::transacao(fn() => CarteiraService::criar($dados, Auth::id(), 'Cadastro manual'));
        Auditoria::registrar('cliente', $id, 'criar', null, $dados);
        Sessao::aviso('sucesso', 'Estabelecimento cadastrado.');
        redirecionar('/carteira/lista?abrir=' . $id);
    }

    public function atualizar(string $id): void
    {
        $cliente = Cliente::buscar((int) $id);
        if (!$cliente || !Cliente::podeVer($cliente)) {
            redirecionar('/carteira/lista');
        }
        [$dados, $erros] = $this->dadosCadastro((int) $id);
        if ($erros) {
            $this->voltarComErros("/carteira/clientes/$id/editar", $erros);
        }
        $dados['prioridade'] = $this->campo('prioridade');
        try {
            CarteiraService::salvar((int) $id, $dados, $this->campo('atualizado_em'), Auth::id(), false);
        } catch (ConflitoException $e) {
            $this->voltarComErros("/carteira/clientes/$id/editar", [$e->getMessage()]);
        }
        Sessao::aviso('sucesso', 'Cadastro atualizado.');
        redirecionar('/carteira/lista?abrir=' . (int) $id);
    }

    /** Lê e valida os campos do formulário de cadastro */
    private function dadosCadastro(?int $id): array
    {
        $erros = [];
        $cnpjDigitado = preg_replace('/\D/', '', $this->campo('cnpj'));
        $cnpj = Normalizar::cnpj($cnpjDigitado);
        if ($cnpjDigitado !== '' && !$cnpj) {
            $erros[] = 'CNPJ inválido.';
        }
        if ($cnpj && ($outro = Banco::valor('SELECT razao_social FROM clientes WHERE cnpj = ? AND id <> ? AND excluido_em IS NULL', [$cnpj, $id ?? 0]))) {
            $erros[] = "Este CNPJ já está cadastrado ($outro).";
        }
        $dados = [
            'razao_social'  => Normalizar::texto($this->campo('razao_social'), 200),
            'nome_fantasia' => Normalizar::texto($this->campo('nome_fantasia'), 200),
            'cnpj'          => $cnpj,
            'endereco'      => Normalizar::texto($this->campo('endereco')),
            'bairro'        => Normalizar::texto($this->campo('bairro'), 120),
            'cep'           => preg_replace('/\D/', '', $this->campo('cep')) ?: null,
            'cidade'        => Normalizar::texto($this->campo('cidade'), 120),
            'uf'            => in_array($this->campo('uf'), Listas::ufs(), true) ? $this->campo('uf') : null,
            'segmento_id'   => (int) $this->campo('segmento_id') ?: null,
            'telefone'      => Normalizar::telefone($this->campo('telefone')),
            'whatsapp'      => Normalizar::telefone($this->campo('whatsapp')),
            'email'         => Normalizar::texto($this->campo('email'), 160),
            'instagram'     => Normalizar::texto($this->campo('instagram'), 120),
            'facebook'      => Normalizar::texto($this->campo('facebook')),
            'site'          => Normalizar::texto($this->campo('site')),
            'mix_produtos'  => Normalizar::texto($this->campo('mix_produtos'), 2000),
            'tabela_preco_id' => (int) $this->campo('tabela_preco_id') ?: null,
            'origem_id'     => (int) $this->campo('origem_id') ?: null,
            'observacoes'   => Normalizar::texto($this->campo('observacoes'), 5000),
        ];
        if (!$dados['razao_social']) {
            $erros[] = 'Informe o nome do estabelecimento.';
        }
        if (!$dados['cidade'] || !$dados['uf']) {
            $erros[] = 'Informe a cidade e a UF.';
        }
        if ($dados['email'] && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido.';
        }
        return [$dados, $erros];
    }
}
