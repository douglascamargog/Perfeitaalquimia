<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Models\ClienteEvento;
use RuntimeException;

/**
 * Caixa rápida da linha do tempo do cliente:
 *  - Anotação com prints de tela (colados com Ctrl+V) ou PDF → evento "Anotação" com os arquivos;
 *  - Agendar atividade (ligar, WhatsApp, visita, reunião...) → tarefa na Agenda + evento na linha do tempo.
 * Os arquivos ficam em storage/clientes (fora da web) e só saem pelo ClienteController, para quem vê o cliente.
 */
class ClienteAnexoService
{
    public const ATIVIDADES = ['ligar' => 'Ligar', 'whatsapp' => 'Mandar WhatsApp', 'visita' => 'Visita', 'reuniao' => 'Reunião',
        'orcamento' => 'Enviar orçamento', 'outro' => 'Outro'];
    private const MIMES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
    private const TAMANHO_MAXIMO = 10 * 1024 * 1024;
    private const MAXIMO_ARQUIVOS = 4;

    public static function pasta(): string
    {
        return RAIZ . '/storage/clientes';
    }

    /**
     * Anotação (texto e/ou arquivos). $arquivos: o $_FILES['anexos'] (vários).
     * Devolve o id do evento. Lança RuntimeException com a mensagem para a pessoa.
     */
    public static function anotar(int $clienteId, string $texto, array $arquivos, int $usuarioId): int
    {
        $texto = mb_substr(trim($texto), 0, 2000);
        $lista = self::normalizar($arquivos);
        if ($texto === '' && !$lista) {
            throw new RuntimeException('Escreva a anotação ou cole um print.');
        }
        if (count($lista) > self::MAXIMO_ARQUIVOS) {
            throw new RuntimeException('No máximo ' . self::MAXIMO_ARQUIVOS . ' arquivos por anotação.');
        }
        $guardados = [];
        try {
            foreach ($lista as $a) {
                $guardados[] = self::guardar($a);
            }
        } catch (RuntimeException $e) {
            foreach ($guardados as $g) {
                @unlink(self::pasta() . '/' . $g['arquivo']); // não deixa arquivo solto
            }
            throw $e;
        }
        return Banco::transacao(function () use ($clienteId, $texto, $guardados, $usuarioId) {
            $descricao = $texto !== '' ? $texto : (count($guardados) === 1 ? 'Print anexado' : count($guardados) . ' arquivos anexados');
            $evento = ClienteEvento::registrar($clienteId, 'nota', $descricao, $usuarioId, $guardados ? 'Com anexo' : null, $guardados ? ['anexos' => count($guardados)] : null);
            foreach ($guardados as $g) {
                Banco::inserir('cliente_anexos', $g + ['cliente_id' => $clienteId, 'evento_id' => $evento, 'usuario_id' => $usuarioId]);
            }
            Auditoria::registrar('cliente', $clienteId, 'anotacao', null, ['evento' => $evento, 'anexos' => count($guardados)]);
            return $evento;
        });
    }

    /**
     * Agenda uma atividade para o cliente. $d: atividade, data, hora, usuario_id (para quem), notas.
     * Devolve o id da tarefa.
     */
    public static function agendar(array $cliente, array $d, int $usuarioId, array $podeAgendarPara): int
    {
        $atividade = array_key_exists($d['atividade'] ?? '', self::ATIVIDADES) ? $d['atividade'] : 'outro';
        $data = (string) ($d['data'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data < date('Y-m-d')) {
            throw new RuntimeException('Escolha a data (hoje ou depois).');
        }
        $hora = preg_match('/^\d{2}:\d{2}$/', (string) ($d['hora'] ?? '')) ? $d['hora'] : null;
        $para = (int) ($d['usuario_id'] ?? 0) ?: $usuarioId;
        if ($para !== $usuarioId && !in_array($para, $podeAgendarPara, true)) {
            throw new RuntimeException('Você só pode agendar para você ou para alguém da sua equipe.');
        }
        $notas = mb_substr(trim((string) ($d['notas'] ?? '')), 0, 1000);
        $titulo = self::ATIVIDADES[$atividade] . ($notas !== '' ? ': ' . mb_strimwidth($notas, 0, 80, '…') : '');
        return Banco::transacao(function () use ($cliente, $atividade, $data, $hora, $para, $notas, $titulo, $usuarioId) {
            $id = Banco::inserir('tarefas', array_filter(['cliente_id' => $cliente['id'], 'usuario_id' => $para, 'titulo' => mb_substr($titulo, 0, 150),
                'tipo' => $atividade === 'ligar' ? 'retorno' : 'compromisso', 'vence_em' => $data, 'hora' => $hora, 'notas' => $notas ?: null], fn($v) => $v !== null));
            $quem = $para !== $usuarioId ? ' (para ' . Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [$para]) . ')' : '';
            ClienteEvento::registrar((int) $cliente['id'], 'tarefa', 'Agendou: ' . $titulo . ' · ' . data_br($data) . ($hora ? " às $hora" : '') . $quem,
                $usuarioId, null, ['tarefa_id' => $id]);
            if ($para !== $usuarioId) {
                NotificacaoService::avisar([$para], 'tarefa_agendada', 'Atividade para você: ' . $titulo, '/carteira/clientes/' . (int) $cliente['id'],
                    ['cliente_id' => (int) $cliente['id'], 'texto' => data_br($data) . ($hora ? " às $hora" : '')], $usuarioId);
            }
            return $id;
        });
    }

    /** Anexos do cliente agrupados pelo evento: [evento_id => [anexo, ...]] */
    public static function porEvento(int $clienteId): array
    {
        $r = [];
        foreach (Banco::todos('SELECT id, evento_id, nome, mime FROM cliente_anexos WHERE cliente_id = ? ORDER BY id', [$clienteId]) as $a) {
            $r[(int) $a['evento_id']][] = $a;
        }
        return $r;
    }

    public static function buscar(int $clienteId, int $id): ?array
    {
        return Banco::um('SELECT * FROM cliente_anexos WHERE id = ? AND cliente_id = ?', [$id, $clienteId]);
    }

    // ------------------------------------------------------------------

    /** $_FILES['anexos'] (name[], tmp_name[]...) → lista de arquivos; ignora campos vazios */
    private static function normalizar(array $f): array
    {
        if (!isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
        }
        $lista = [];
        foreach ($f['name'] as $i => $nome) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $lista[] = ['name' => $nome, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            }
        }
        return $lista;
    }

    private static function guardar(array $a): array
    {
        if ($a['error'] === UPLOAD_ERR_INI_SIZE || $a['error'] === UPLOAD_ERR_FORM_SIZE || (int) $a['size'] > self::TAMANHO_MAXIMO) {
            throw new RuntimeException('Cada arquivo pode ter no máximo 10 MB.');
        }
        if ($a['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($a['tmp_name'])) {
            throw new RuntimeException('Não foi possível receber o arquivo. Tente de novo.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($a['tmp_name']);
        if (!isset(self::MIMES[$mime])) {
            throw new RuntimeException('Envie imagem (print, foto) ou PDF.');
        }
        if (!is_dir(self::pasta()) && !mkdir(self::pasta(), 0750, true)) {
            throw new RuntimeException('A pasta dos anexos não pôde ser criada.');
        }
        $nome = bin2hex(random_bytes(16)) . '.' . self::MIMES[$mime];
        if (!move_uploaded_file($a['tmp_name'], self::pasta() . '/' . $nome)) {
            throw new RuntimeException('Não foi possível guardar o arquivo.');
        }
        $original = mb_substr(basename((string) $a['name']), 0, 200) ?: 'print.' . self::MIMES[$mime];
        return ['arquivo' => $nome, 'nome' => $original, 'mime' => $mime, 'tamanho' => (int) $a['size']];
    }
}
