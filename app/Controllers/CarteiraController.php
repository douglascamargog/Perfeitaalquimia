<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Config;
use App\Models\Cliente;
use App\Models\Listas;
use App\Models\Tarefa;

/**
 * Telas da Carteira: visão geral, lista, funil e mapa (+ dados do mapa e exportação CSV).
 * O escopo (vendedor vê só os próprios clientes) é aplicado dentro do model Cliente.
 */
class CarteiraController extends Controller
{
    /** Dados comuns a todas as abas: filtros atuais e listas para os campos de filtro */
    private function base(string $aba, string $titulo): array
    {
        $filtros = Cliente::filtrosDe($_GET);
        return [
            'titulo'   => $titulo,
            'aba'      => $aba,
            'filtros'  => $filtros,
            'opcoes'   => [
                'ufs'         => Listas::ufsComClientes(),
                'cidades'     => Listas::cidadesComClientes($filtros['uf']),
                'regioes'     => Listas::regioes(),
                'segmentos'   => Listas::segmentos(),
                'etapas'      => Listas::etapas(),
                'prioridades' => Listas::prioridades(),
                'vendedores'  => Auth::pode('carteira.ver_todas') ? Listas::vendedores() : [],
                'faixas'      => Config::get('carteira.faixas_sem_compra', [30, 60, 90]),
                'origens'     => Listas::origens(),
            ],
            'estilos'  => ['carteira.css'],
            'scripts'  => ['carteira.js', 'busca_contatos.js'],
        ];
    }

    /** Endereço antigo da "campanha em destaque": hoje tudo fica em Comercial › Prospecção */
    public function campanhaAtual(): void
    {
        redirecionar('/prospeccao');
    }

    public function visaoGeral(): void
    {
        $d = $this->base('visao', 'Carteira');
        $f = $d['filtros'];

        // Atividade dos últimos 7 dias, dia a dia (dias sem movimento aparecem zerados)
        $porDia = array_column(Cliente::atividade($f, 7), null, 'dia');
        $semana = [];
        for ($i = 6; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-$i days"));
            $semana[] = ['dia' => $dia, 'contatos' => (int) ($porDia[$dia]['contatos'] ?? 0), 'pedidos' => (int) ($porDia[$dia]['pedidos'] ?? 0)];
        }
        $usuarioAgenda = Auth::pode('carteira.ver_todas') ? null : Auth::id();

        $d += [
            'resumo'     => Cliente::resumo($f),
            'segmentos'  => Cliente::contarPor($f, 'segmento'),
            'cidades'    => Cliente::contarPor($f, 'cidade', 5),
            'semana'     => $semana,
            'pendentes'  => Tarefa::pendentes($usuarioAgenda),
            'agenda'     => Tarefa::agenda($usuarioAgenda),
        ];
        $d['estilos'][] = 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css';
        array_unshift($d['scripts'], 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js');
        $d['scripts'][] = 'mapa.js';
        $this->tela('carteira/visao_geral', $d);
    }

    public function lista(): void
    {
        $d = $this->base('lista', 'Carteira');
        $ordem = (string) ($_GET['ordem'] ?? 'nome');
        $porPagina = max(10, min(200, (int) Config::get('carteira.itens_por_pagina', 50)));
        $d['ordem'] = array_key_exists($ordem, Cliente::ORDENS) ? $ordem : 'nome';
        $d['resultado'] = Cliente::listar($d['filtros'], (int) ($_GET['pagina'] ?? 1), $porPagina, $d['ordem']);
        $this->tela('carteira/lista', $d);
    }

    public function funil(): void
    {
        $d = $this->base('funil', 'Carteira');
        // Cada coluna mostra até 30 cards (os mais recentes); a contagem total aparece no topo da coluna
        $colunas = [];
        $totais = array_column(Cliente::contarPor($d['filtros'], 'etapa'), 'total', 'chave');
        foreach (Listas::etapas() as $etapa) {
            $f = ['etapa' => (string) $etapa['id']] + $d['filtros'];
            $f['etapa'] = (string) $etapa['id'];
            $colunas[] = ['etapa' => $etapa, 'total' => (int) ($totais[$etapa['id']] ?? 0), 'cards' => Cliente::listar($f, 1, 30, 'recentes')['itens']];
        }
        $d['colunas'] = $colunas;
        $d['scripts'][] = 'funil.js';
        $this->tela('carteira/funil', $d);
    }

    public function mapa(): void
    {
        $d = $this->base('mapa', 'Carteira');
        $d['resumo'] = Cliente::resumo($d['filtros']);
        $d['segmentos'] = Cliente::contarPor($d['filtros'], 'segmento');
        $d['estilos'][] = 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css';
        array_unshift($d['scripts'], 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js');
        $d['scripts'][] = 'mapa.js';
        $this->tela('carteira/mapa', $d);
    }

    /** JSON para o mapa: ?nivel=uf|cidade|ponto&caixa=oeste,sul,leste,norte + filtros */
    public function mapaDados(): void
    {
        $nivel = in_array($_GET['nivel'] ?? '', ['uf', 'cidade', 'ponto'], true) ? $_GET['nivel'] : 'uf';
        $caixa = null;
        if (preg_match('/^-?[\d.]+,-?[\d.]+,-?[\d.]+,-?[\d.]+$/', (string) ($_GET['caixa'] ?? ''))) {
            $caixa = array_map('floatval', explode(',', $_GET['caixa']));
        }
        $dados = Cliente::mapa(Cliente::filtrosDe($_GET), $nivel, $caixa);
        if ($nivel === 'uf') {
            foreach ($dados as &$linha) {
                [$linha['lat'], $linha['lng']] = \App\Models\Cidade::CENTRO_UF[$linha['uf']] ?? [null, null];
            }
        }
        $this->json(['nivel' => $nivel, 'itens' => $dados]);
    }

    /** Exporta os clientes filtrados em CSV (abre no Excel). Registrado na auditoria (LGPD). */
    public function exportar(): void
    {
        $filtros = Cliente::filtrosDe($_GET);
        [$where, $p] = Cliente::montarWhere($filtros);
        $sql = "SELECT c.id, c.razao_social, c.nome_fantasia, c.cnpj, c.cidade, c.uf, c.endereco, s.nome AS segmento, e.nome AS etapa,
                       c.prioridade, c.telefone, c.whatsapp, c.email, c.instagram, c.site, u.nome AS responsavel, f.nome AS funcao,
                       c.ultimo_contato_em, c.ultima_compra_em, o.nome AS origem, c.origem_detalhe, c.observacoes
                  FROM clientes c
                  LEFT JOIN segmentos s ON s.id = c.segmento_id LEFT JOIN funil_etapas e ON e.id = c.etapa_id
                  LEFT JOIN usuarios u ON u.id = c.responsavel_id LEFT JOIN funcoes f ON f.id = c.funcao_id
                  LEFT JOIN origens o ON o.id = c.origem_id
                 WHERE $where ORDER BY c.razao_social";
        Auditoria::registrar('carteira', null, 'exportar_csv', null, ['filtros' => array_filter($filtros)]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="carteira_' . date('Y-m-d') . '.csv"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF"); // BOM: o Excel reconhece os acentos
        fputcsv($saida, ['ID', 'Razão social', 'Nome fantasia', 'CNPJ', 'Cidade', 'UF', 'Endereço', 'Segmento', 'Etapa', 'Prioridade',
            'Telefone', 'WhatsApp', 'E-mail', 'Instagram', 'Site', 'Responsável', 'Função', 'Último contato', 'Última compra', 'Origem',
            'Detalhe da origem', 'Observações'], ';');
        $stmt = \App\Core\Banco::consulta($sql, $p);
        while ($linha = $stmt->fetch()) {
            $linha['ultimo_contato_em'] = $linha['ultimo_contato_em'] ? data_br($linha['ultimo_contato_em']) : '';
            $linha['ultima_compra_em'] = $linha['ultima_compra_em'] ? data_br($linha['ultima_compra_em']) : '';
            fputcsv($saida, $linha, ';');
        }
        fclose($saida);
    }
}
