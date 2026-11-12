<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use App\Core\Email;
use App\Core\Sessao;

/**
 * Acesso do CLIENTE ao catálogo (separado do login da equipe).
 *  - Entra com o CNPJ do cadastro + senha.
 *  - Primeiro acesso / esqueceu a senha: código de 6 dígitos (vale X minutos) enviado ao e-mail do cadastro,
 *    ou gerado pelo vendedor na ficha do cliente e enviado pelo WhatsApp.
 *  - Errou a senha N vezes → bloqueia por M minutos (mesmas regras do login da equipe).
 * A sessão do cliente fica em $_SESSION['catalogo'] (não se mistura com a da equipe).
 */
class CatalogoAcesso
{
    /** Cliente pelo CNPJ (só clientes ativos e não excluídos) */
    public static function clientePorCnpj(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        return strlen($cnpj) === 14 ? Banco::um('SELECT * FROM clientes WHERE cnpj = ? AND excluido_em IS NULL', [$cnpj]) : null;
    }

    private static function acesso(int $clienteId): array
    {
        Banco::executar('INSERT IGNORE INTO cliente_acessos (cliente_id) VALUES (?)', [$clienteId]);
        return Banco::um('SELECT * FROM cliente_acessos WHERE cliente_id = ?', [$clienteId]);
    }

    private static function bloqueado(array $acesso): ?string
    {
        if ($acesso['bloqueado_ate'] && strtotime($acesso['bloqueado_ate']) > time()) {
            return 'Muitas tentativas. Aguarde ' . (int) ceil((strtotime($acesso['bloqueado_ate']) - time()) / 60) . ' minuto(s) e tente de novo.';
        }
        return null;
    }

    private static function errou(array $acesso): void
    {
        $max = max(3, (int) Config::get('seguranca.login_max_tentativas', 5));
        $tentativas = (int) $acesso['tentativas'] + 1;
        $dados = ['tentativas' => $tentativas];
        if ($tentativas >= $max) {
            $dados = ['tentativas' => 0, 'bloqueado_ate' => date('Y-m-d H:i:s', time() + 60 * (int) Config::get('seguranca.login_bloqueio_minutos', 15))];
        }
        Banco::atualizar('cliente_acessos', (int) $acesso['id'], $dados);
    }

    /** CNPJ + senha. Devolve null (entrou) ou a mensagem de erro (sem dizer se o CNPJ existe) */
    public static function entrar(string $cnpj, string $senha): ?string
    {
        $cliente = self::clientePorCnpj($cnpj);
        $acesso = $cliente ? self::acesso((int) $cliente['id']) : null;
        if ($acesso && ($motivo = self::bloqueado($acesso))) {
            return $motivo;
        }
        $hash = $acesso['senha_hash'] ?? null;
        if (!$cliente || !$hash || !password_verify($senha, $hash)) {
            if ($acesso) {
                self::errou($acesso);
            } else {
                password_verify($senha, '$2y$10$LCYV8N7emDp60jzWBBMolecjAZG6MxcCP48tKxmp.CMoQMzw1P3c2'); // mesmo tempo de resposta
            }
            return $acesso && !$hash ? 'Este CNPJ ainda não tem senha. Use "Primeiro acesso" para receber o código.' : 'CNPJ ou senha incorretos.';
        }
        self::logar($cliente, $acesso);
        return null;
    }

    private static function logar(array $cliente, array $acesso): void
    {
        Banco::atualizar('cliente_acessos', (int) $acesso['id'], ['tentativas' => 0, 'bloqueado_ate' => null, 'ultimo_acesso_em' => date('Y-m-d H:i:s')]);
        Sessao::renovar();
        $sessao = $_SESSION['catalogo'] ?? [];
        $_SESSION['catalogo'] = ['cliente_id' => (int) $cliente['id'], 'carrinho' => $sessao['carrinho'] ?? [], 'vendedor_id' => $sessao['vendedor_id'] ?? null];
        Auditoria::registrar('cliente', (int) $cliente['id'], 'login_catalogo');
    }

    /** Gera um código novo (6 dígitos) e devolve em texto (para mostrar ao vendedor / enviar) */
    public static function gerarCodigo(int $clienteId): string
    {
        $acesso = self::acesso($clienteId);
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutos = max(5, (int) Config::get('catalogo.codigo_validade_minutos', 10));
        Banco::atualizar('cliente_acessos', (int) $acesso['id'], ['codigo_hash' => password_hash($codigo, PASSWORD_DEFAULT),
            'codigo_expira_em' => date('Y-m-d H:i:s', time() + $minutos * 60)]);
        return $codigo;
    }

    /**
     * Primeiro acesso / esqueci a senha pelo e-mail do cadastro.
     * Devolve [mensagem para a tela, código (só no modo demonstração, para aparecer na tela)].
     * A mensagem é a mesma exista ou não o CNPJ (não revela quem é cliente).
     */
    public static function enviarCodigo(string $cnpj): array
    {
        $mensagem = 'Se o CNPJ estiver cadastrado, enviamos um código para o e-mail do cadastro. Não recebeu? Peça o código ao seu vendedor pelo WhatsApp.';
        $cliente = self::clientePorCnpj($cnpj);
        if (!$cliente || ($acesso = self::acesso((int) $cliente['id'])) && self::bloqueado($acesso)) {
            return [$mensagem, null];
        }
        $codigo = self::gerarCodigo((int) $cliente['id']);
        $minutos = (int) Config::get('catalogo.codigo_validade_minutos', 10);
        if ($cliente['email']) {
            Email::enviar($cliente['email'], 'Seu código de acesso ao catálogo',
                "Olá!\n\nSeu código para acessar o catálogo da " . Config::get('empresa.nome', 'Perfeita Alquimia') . " é: $codigo\n\n"
                . "Ele vale por $minutos minutos. Se não foi você que pediu, ignore esta mensagem.");
        }
        return [$mensagem, config('modo_demo') ? $codigo : null];
    }

    /** Confere o código e grava a senha nova. Devolve null (entrou) ou a mensagem de erro */
    public static function definirSenha(string $cnpj, string $codigo, string $senha): ?string
    {
        if (mb_strlen($senha) < 8) {
            return 'A senha precisa ter pelo menos 8 caracteres.';
        }
        $cliente = self::clientePorCnpj($cnpj);
        $acesso = $cliente ? self::acesso((int) $cliente['id']) : null;
        if ($acesso && ($motivo = self::bloqueado($acesso))) {
            return $motivo;
        }
        $valido = $acesso && $acesso['codigo_hash'] && strtotime((string) $acesso['codigo_expira_em']) > time()
            && password_verify(preg_replace('/\D/', '', $codigo), $acesso['codigo_hash']);
        if (!$valido) {
            if ($acesso) {
                self::errou($acesso);
            }
            return 'Código inválido ou vencido. Peça um código novo.';
        }
        Banco::atualizar('cliente_acessos', (int) $acesso['id'], ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'codigo_hash' => null, 'codigo_expira_em' => null]);
        Auditoria::registrar('cliente', (int) $cliente['id'], 'senha_catalogo');
        self::logar($cliente, Banco::um('SELECT * FROM cliente_acessos WHERE id = ?', [$acesso['id']]));
        return null;
    }

    // ------------------------------------------------------------------
    // Sessão do cliente
    // ------------------------------------------------------------------

    public static function cliente(): ?array
    {
        $id = (int) ($_SESSION['catalogo']['cliente_id'] ?? 0);
        $cliente = $id ? Banco::um('SELECT * FROM clientes WHERE id = ? AND excluido_em IS NULL', [$id]) : null;
        if ($id && !$cliente) {
            self::sair();
        }
        return $cliente;
    }

    public static function sair(): void
    {
        $vendedor = $_SESSION['catalogo']['vendedor_id'] ?? null;
        $_SESSION['catalogo'] = ['vendedor_id' => $vendedor, 'carrinho' => []];
        Sessao::renovar();
    }

    /** Vendedor do link /catalogo/v/{codigo} */
    public static function vendedor(): ?array
    {
        $id = (int) ($_SESSION['catalogo']['vendedor_id'] ?? 0);
        return $id ? Banco::um('SELECT id, nome, telefone FROM usuarios WHERE id = ? AND ativo = 1', [$id]) : null;
    }

    // ------------------------------------------------------------------
    // Carrinho (na sessão): [produto_id => quantidade]
    // ------------------------------------------------------------------

    public static function carrinho(): array
    {
        return array_map('intval', $_SESSION['catalogo']['carrinho'] ?? []);
    }

    public static function definirQuantidade(int $produtoId, int $quantidade): void
    {
        $carrinho = self::carrinho();
        if ($quantidade > 0) {
            $carrinho[$produtoId] = min(9999, $quantidade);
        } else {
            unset($carrinho[$produtoId]);
        }
        $_SESSION['catalogo']['carrinho'] = $carrinho;
    }

    public static function limparCarrinho(): void
    {
        $_SESSION['catalogo']['carrinho'] = [];
    }
}
