<?php
/**
 * Perfis novos (V11.0) — DADOS ESSENCIAIS (idempotente):
 *  - Qualidade: orçamentos e compras, compras da empresa (lançar NF, fornecedores, documentos/laudos),
 *    ver Produção, Produtos, Depósito (recebimento) e Desenvolvimento;
 *  - Gestor de setor: o mesmo do Vendedor + ver as metas. O que é do setor (carteira, acompanhamento da equipe dele)
 *    continua vindo de "gestor(a) do setor" em Administração › Usuários › Funções.
 * Ninguém muda de perfil sozinho: o administrador escolhe em Pessoas e acessos.
 */
use App\Core\Banco;

$perfis = [
    'qualidade' => ['Qualidade', 'Orçamentos e compras, compras da empresa, fornecedores, laudos e documentos; vê Produção, Produtos e Depósito',
        ['inicio.ver', 'academia.ver', 'compras.ver', 'compras.lancar', 'producao.ver', 'produtos.ver', 'deposito.ver', 'desenvolvimento.ver', 'marketing.ver']],
    'gestor_setor' => ['Gestor de setor', 'Vende como o Vendedor e acompanha a equipe do próprio setor (marque "gestor do setor" na função da pessoa)', null],
];
foreach ($perfis as $slug => [$nome, $descricao, $permissoes]) {
    if (!Banco::valor('SELECT 1 FROM perfis WHERE slug = ?', [$slug])) {
        Banco::inserir('perfis', ['slug' => $slug, 'nome' => $nome, 'descricao' => $descricao]);
        $id = (int) Banco::valor('SELECT id FROM perfis WHERE slug = ?', [$slug]);
        if ($permissoes === null) { // Gestor de setor: as do Vendedor + ver metas
            $permissoes = array_column(Banco::todos("SELECT pp.permissao FROM perfil_permissoes pp JOIN perfis p ON p.id = pp.perfil_id WHERE p.slug = 'vendedor'"), 'permissao');
            $permissoes[] = 'metas.ver';
        }
        foreach (array_unique($permissoes) as $perm) {
            Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) VALUES (?, ?)', [$id, $perm]);
        }
    }
}
return null;
