<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;

/**
 * Zerar os dados do One (Administração › Zerar dados) — para recomeçar com os dados reais (ex.: importação do Nota10).
 *
 * APAGA tudo o que a equipe criou no dia a dia: clientes, roteiros de visita, pedidos, cobrança, produção, fórmulas, projetos, compras,
 * marketing, prospecção e avisos.
 * MANTÉM o que faz o sistema funcionar: usuários e acessos, produtos e catálogo (com preços e estoque),
 * configurações e chaves, listas (funis, segmentos, etapas...), Academia, treinamento, mensagens prontas,
 * e-commerce (vem da Tray) e a auditoria.
 *
 * Toda tabela do banco precisa estar em UMA das duas listas abaixo (o teste automático confere).
 * Ao criar uma tabela nova, decida aqui se ela é "dado do dia a dia" (APAGAR) ou "parte do sistema" (MANTER).
 * Tabela que não está em nenhuma lista nunca é apagada.
 *
 * Antes de apagar, sempre faz um backup completo em storage/backups (one-antes-de-zerar-....sql.gz).
 */
class ZerarDadosService
{
    /** Frase que o admin precisa digitar para confirmar */
    public const FRASE = 'ZERAR';

    /** O que é apagado, por grupo (o nome do grupo aparece na tela) */
    public const APAGAR = [
        'Carteira e clientes' => ['cliente_eventos', 'tarefas', 'cliente_anexos', 'cliente_acessos', 'buscas_contato', 'ligacoes',
            'importacao_linhas', 'importacoes', 'carteiras', 'roteiro_paradas', 'roteiros', 'cliente_contatos', 'clientes'],
        'Pedidos, orçamentos e metas' => ['pedido_historico', 'pedido_item_lotes', 'pedido_embalagens', 'pedido_itens', 'pedidos',
            'orcamento_historico', 'orcamento_itens', 'orcamentos', 'pedido_modelo_itens', 'pedido_modelos',
            'vendas_registradas', 'meu_mes', 'metas', 'metas_equipe', 'metas_equipe_lancamentos'],
        'Cobrança' => ['cobranca_titulos', 'cobrancas', 'titulos_receber'],
        'Produção e matérias-primas' => ['mp_movimentos', 'ordem_mp_lotes', 'mp_lotes', 'mp_documentos', 'ficha_tecnica', 'ordens_producao',
            'producao_perdas', 'acabado_movimentos', 'estoque_acabado', 'materias_primas', 'mp_fornecedores'],
        'Fórmulas' => ['formula_historico', 'formula_testes', 'formula_itens', 'formulas'],
        'Projetos de produtos novos' => ['dp_historico', 'dp_aprovacoes', 'dp_projeto_etapas', 'dp_projetos'],
        'Compras' => ['compra_historico', 'compra_cotacoes', 'compras', 'compras_lancadas_itens', 'compras_lancadas'],
        'Marketing e campanhas' => ['mkt_post_comentarios', 'mkt_posts', 'mkt_campanhas', 'mkt_tarefa_historico', 'mkt_tarefas',
            'campanha_faixas', 'campanha_produtos', 'campanhas'],
        'Terceirização' => ['terceirizacao_rotulos_mov', 'terceirizacao_produtos'],
        'Prospecção e leads' => ['prospeccao_envios', 'leads_recebidos'],
        'Avisos e recados' => ['notificacoes', 'comunicado_leituras', 'comunicado_funcoes', 'comunicados', 'perfil_recados',
            'perfil_elogios', 'aniversario_mensagens', 'one_perguntas'],
    ];

    /** O que fica (parte do sistema) */
    public const MANTER = [
        // usuários e acessos
        'usuarios', 'usuario_funcoes', 'usuario_permissoes', 'usuario_perfil', 'perfis', 'perfil_permissoes', 'funcoes', 'tentativas_login',
        // produtos e catálogo
        'produtos', 'categorias', 'midias', 'produto_precos', 'produto_estoque', 'produto_tecnico', 'produto_documentos', 'produto_nutricional',
        // configurações e listas
        'configuracoes', 'segmentos', 'origens', 'funil_etapas', 'regioes', 'regiao_ufs', 'tabelas_preco', 'formas_pagamento',
        'condicoes_pagamento', 'cidades', 'cobranca_etapas', 'mkt_post_etapas', 'mkt_tipos', 'dp_etapas', 'mensagens_prontas',
        'one_especialistas', 'prospeccao_empresas',
        // Academia, treinamento e o progresso de cada pessoa
        'academia_conteudo', 'academia_progresso', 'tutorial_telas', 'tutorial_progresso', 'trein_trilhas', 'trein_modulos',
        'trein_perguntas', 'trein_progresso',
        // e-commerce (o One busca de novo na Tray), auditoria e controle das versões
        'ecommerce_pedidos', 'ecommerce_pedido_itens', 'ecommerce_resumo_diario', 'ecommerce_produtos_mes', 'auditoria', 'migracoes',
        // importação do Nota10: o registro do backup e as ligações escolhidas (as tabelas n10_ da área separada nunca são apagadas aqui)
        'nota10_importacoes', 'nota10_mapas',
    ];

    /** Pastas de arquivos anexados aos dados apagados (fora da web). A pasta fica, o conteúdo sai. */
    private const PASTAS = ['storage/clientes', 'storage/compras', 'storage/importacoes', 'storage/documentos/itens', 'storage/gravacoes',
        'storage/marketing', 'storage/omie_fake'];

    /** Quantos registros cada grupo tem hoje: ['Carteira e clientes' => 1234, ...] */
    public static function contagem(): array
    {
        $existentes = self::tabelasDoBanco();
        $total = [];
        foreach (self::APAGAR as $grupo => $tabelas) {
            $total[$grupo] = 0;
            foreach ($tabelas as $t) {
                if (in_array($t, $existentes, true)) {
                    $total[$grupo] += (int) Banco::valor("SELECT COUNT(*) FROM `$t`");
                }
            }
        }
        return $total;
    }

    /** Tabelas do banco que não estão em nenhuma lista (devem ser classificadas aqui; enquanto isso, ficam) */
    public static function semClassificacao(): array
    {
        $conhecidas = array_merge(self::MANTER, ...array_values(self::APAGAR));
        return array_values(array_diff(self::tabelasDoBanco(), $conhecidas));
    }

    /**
     * Faz o backup e apaga. Devolve ['backup' => nome do arquivo, 'apagados' => [grupo => n], 'arquivos' => n].
     * Se o backup falhar, nada é apagado. Se der erro no meio, o banco volta como estava (transação).
     */
    public static function zerar(int $usuarioId): array
    {
        @set_time_limit(600);
        $antes = self::contagem();

        // 1. Backup completo ANTES (fica em storage/backups e não é apagado pela limpeza automática dos backups diários)
        $pasta = RAIZ . '/storage/backups';
        if (!is_dir($pasta)) {
            mkdir($pasta, 0750, true);
        }
        $backup = $pasta . '/one-antes-de-zerar-' . date('Y-m-d-His') . '.sql.gz';
        rename(BackupBanco::gerar(), $backup);
        @chmod($backup, 0640);
        if (!is_file($backup) || filesize($backup) < 100) {
            throw new \RuntimeException('Não consegui fazer o backup. Nada foi apagado.');
        }

        // 2. Apaga tudo de uma vez: se algo falhar, nada é apagado
        $existentes = self::tabelasDoBanco();
        $pdo = Banco::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            Banco::transacao(function () use ($existentes) {
                foreach (self::APAGAR as $tabelas) {
                    foreach ($tabelas as $t) {
                        if (in_array($t, $existentes, true)) {
                            // DELETE (e não TRUNCATE): funciona com o usuário comum do banco da hospedagem.
                            // Os números (id) continuam de onde estavam: nada novo herda o histórico de um registro apagado.
                            Banco::executar("DELETE FROM `$t`");
                        }
                    }
                }
            });
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        // 3. Arquivos anexados (prints, cotações, documentos, gravações)
        $arquivos = 0;
        foreach (self::PASTAS as $p) {
            $arquivos += self::esvaziar(RAIZ . '/' . $p);
        }

        Auditoria::registrar('sistema', null, 'zerar_dados', null, ['backup' => basename($backup), 'apagados' => $antes, 'arquivos' => $arquivos]);
        return ['backup' => basename($backup), 'apagados' => $antes, 'arquivos' => $arquivos];
    }

    private static function tabelasDoBanco(): array
    {
        // a área separada do Nota10 (n10_...) é uma cópia do backup: fica de fora
        return array_values(array_filter(array_map(fn($l) => $l[0], Banco::pdo()->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM)),
            fn($t) => !str_starts_with($t, 'n10_')));
    }

    /** Apaga o conteúdo da pasta (mantém a própria pasta, o .htaccess e o .gitkeep). Devolve quantos arquivos saíram. */
    private static function esvaziar(string $pasta): int
    {
        if (!is_dir($pasta)) {
            return 0;
        }
        $n = 0;
        $itens = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($itens as $item) {
            if (in_array($item->getFilename(), ['.htaccess', '.gitkeep'], true)) {
                continue;
            }
            if ($item->isDir()) {
                @rmdir($item->getPathname()); // só sai se ficou vazia
            } elseif (@unlink($item->getPathname())) {
                $n++;
            }
        }
        return $n;
    }
}
