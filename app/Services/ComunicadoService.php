<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use RuntimeException;

/**
 * Comunicados: quem tem comunicados.emitir manda uma mensagem para setores (funções) ou para toda a empresa.
 * Aparece em destaque no topo da tela até a pessoa clicar em "Li e entendi" (ou até a data de fim / ser encerrado).
 */
class ComunicadoService
{
    /** Condição SQL: comunicado (alias c) válido agora */
    private const VALIDO = 'c.encerrado_em IS NULL AND c.inicio <= NOW() AND (c.fim IS NULL OR c.fim >= NOW())';

    /** Condição SQL: comunicado (alias c) é para o usuário ? (por função), ou é de toda a empresa, ou foi ele que mandou */
    private const PARA_QUEM = '(c.toda_empresa = 1 OR c.criado_por = ? OR EXISTS (SELECT 1 FROM comunicado_funcoes cf
        JOIN usuario_funcoes uf ON uf.funcao_id = cf.funcao_id WHERE cf.comunicado_id = c.id AND uf.usuario_id = ?))';

    /** Não lidos e válidos da pessoa (o mais recente primeiro; importantes antes) */
    public static function ativosPara(int $usuarioId): array
    {
        try {
            return Banco::todos('SELECT c.*, u.nome AS autor FROM comunicados c LEFT JOIN usuarios u ON u.id = c.criado_por
                WHERE ' . self::VALIDO . ' AND ' . self::PARA_QUEM . '
                  AND NOT EXISTS (SELECT 1 FROM comunicado_leituras l WHERE l.comunicado_id = c.id AND l.usuario_id = ?)
                ORDER BY c.prioridade = "importante" DESC, c.inicio DESC', [$usuarioId, $usuarioId, $usuarioId]);
        } catch (\Throwable) {
            return []; // banco ainda sem a tabela (atualização sendo aplicada)
        }
    }

    /** Histórico do que a pessoa recebeu (lidos e não lidos) */
    public static function recebidos(int $usuarioId, int $limite = 50): array
    {
        return Banco::todos('SELECT c.*, u.nome AS autor, l.lida_em FROM comunicados c LEFT JOIN usuarios u ON u.id = c.criado_por
            LEFT JOIN comunicado_leituras l ON l.comunicado_id = c.id AND l.usuario_id = ?
            WHERE c.inicio <= NOW() AND ' . self::PARA_QUEM . ' ORDER BY c.inicio DESC LIMIT ' . (int) $limite, [$usuarioId, $usuarioId, $usuarioId]);
    }

    /** Destinatários (usuários ativos) de um comunicado */
    public static function destinatarios(int $id): array
    {
        $c = Banco::um('SELECT toda_empresa FROM comunicados WHERE id = ?', [$id]);
        if (!$c) {
            return [];
        }
        if ($c['toda_empresa']) {
            return array_map('intval', array_column(Banco::todos('SELECT id FROM usuarios WHERE ativo = 1'), 'id'));
        }
        return array_map('intval', array_column(Banco::todos('SELECT DISTINCT u.id FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id
            JOIN comunicado_funcoes cf ON cf.funcao_id = uf.funcao_id WHERE cf.comunicado_id = ? AND u.ativo = 1', [$id]), 'id'));
    }

    /**
     * Emite. $d: titulo, texto, prioridade, toda_empresa, funcoes[] (ids), inicio, fim (AAAA-MM-DD ou AAAA-MM-DDTHH:MM, opcionais)
     * $avisar: também manda o aviso no sino de cada destinatário.
     */
    public static function emitir(array $d, ?int $usuarioId, bool $avisar = true): int
    {
        $titulo = trim((string) ($d['titulo'] ?? ''));
        $texto = trim((string) ($d['texto'] ?? ''));
        $todos = !empty($d['toda_empresa']);
        $funcoes = array_values(array_unique(array_filter(array_map('intval', (array) ($d['funcoes'] ?? [])))));
        if ($titulo === '' || mb_strlen($titulo) > 140) {
            throw new RuntimeException('Escreva o título do comunicado (até 140 letras).');
        }
        if ($texto === '' || mb_strlen($texto) > 5000) {
            throw new RuntimeException('Escreva o texto do comunicado (até 5.000 letras).');
        }
        if (!$todos && !$funcoes) {
            throw new RuntimeException('Escolha os setores que recebem, ou "Toda a empresa".');
        }
        $data = function ($v, bool $fimDoDia) {
            $v = trim((string) $v);
            if ($v === '') {
                return null;
            }
            $v = str_replace('T', ' ', $v);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                $v .= $fimDoDia ? ' 23:59:59' : ' 00:00:00';
            }
            return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v) ? $v : null;
        };
        $inicio = $data($d['inicio'] ?? '', false) ?? date('Y-m-d H:i:s');
        $fim = $data($d['fim'] ?? '', true);
        if ($fim !== null && $fim < $inicio) {
            throw new RuntimeException('A data de fim precisa ser depois do início.');
        }
        $dados = ['titulo' => $titulo, 'texto' => $texto, 'prioridade' => ($d['prioridade'] ?? '') === 'importante' ? 'importante' : 'normal',
            'toda_empresa' => (int) $todos, 'inicio' => $inicio, 'fim' => $fim, 'criado_por' => $usuarioId];
        $id = Banco::transacao(function () use ($dados, $funcoes, $todos) {
            $id = Banco::inserir('comunicados', array_filter($dados, fn($v) => $v !== null));
            if (!$todos) {
                foreach ($funcoes as $f) {
                    Banco::executar('INSERT IGNORE INTO comunicado_funcoes (comunicado_id, funcao_id) SELECT ?, id FROM funcoes WHERE id = ?', [$id, $f]);
                }
            }
            return $id;
        });
        Auditoria::registrar('comunicado', $id, 'emitir', null, $dados + ['funcoes' => $todos ? 'toda a empresa' : implode(',', $funcoes)]);
        if ($avisar && $inicio <= date('Y-m-d H:i:s')) {
            NotificacaoService::avisar(self::destinatarios($id), 'comunicado', 'Comunicado: ' . $titulo, '/comunicados#c' . $id, [], $usuarioId);
        }
        return $id;
    }

    public static function marcarLido(int $id, int $usuarioId): void
    {
        Banco::executar('INSERT IGNORE INTO comunicado_leituras (comunicado_id, usuario_id) VALUES (?, ?)', [$id, $usuarioId]);
    }

    public static function encerrar(int $id, ?int $usuarioId): void
    {
        Banco::executar('UPDATE comunicados SET encerrado_em = NOW() WHERE id = ? AND encerrado_em IS NULL', [$id]);
        Auditoria::registrar('comunicado', $id, 'encerrar', null, ['encerrado_por' => $usuarioId]);
    }

    /** Emitidos, com a leitura: X de Y leram e quem falta */
    public static function emitidos(int $limite = 30): array
    {
        $lista = Banco::todos('SELECT c.*, u.nome AS autor FROM comunicados c LEFT JOIN usuarios u ON u.id = c.criado_por ORDER BY c.inicio DESC LIMIT ' . (int) $limite);
        foreach ($lista as &$c) {
            $para = self::destinatarios((int) $c['id']);
            $leram = array_map('intval', array_column(Banco::todos('SELECT usuario_id FROM comunicado_leituras WHERE comunicado_id = ?', [$c['id']]), 'usuario_id'));
            $faltam = array_values(array_diff($para, $leram));
            $c['total'] = count($para);
            $c['leram'] = count(array_intersect($para, $leram));
            $c['faltam'] = $faltam ? array_column(Banco::todos('SELECT nome FROM usuarios WHERE id IN (' . implode(',', $faltam) . ') ORDER BY nome'), 'nome') : [];
            $c['setores'] = $c['toda_empresa'] ? 'Toda a empresa' : implode(', ', array_column(Banco::todos('SELECT f.nome FROM comunicado_funcoes cf
                JOIN funcoes f ON f.id = cf.funcao_id WHERE cf.comunicado_id = ? ORDER BY f.nome', [$c['id']]), 'nome'));
            $c['situacao'] = $c['encerrado_em'] ? 'encerrado' : ($c['inicio'] > date('Y-m-d H:i:s') ? 'agendado' : ($c['fim'] && $c['fim'] < date('Y-m-d H:i:s') ? 'vencido' : 'no ar'));
        }
        return $lista;
    }
}
