<?php
namespace App\Services;

use App\Core\Banco;
use RuntimeException;

/**
 * Ficha do funcionário: foto, aniversário e contatos da equipe (o RH continua fora do sistema).
 *  - Foto: JPG, PNG ou WebP até 5 MB, recortada no centro e reduzida para 400×400 em WebP (fica em public/media/equipe/).
 *  - Aniversariantes: só dia e mês aparecem para a equipe (o ano de nascimento nunca é mostrado).
 */
class EquipeService
{
    private const PASTA = 'media/equipe';
    private const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

    /** Campos da ficha vindos do formulário (já limpos) */
    public static function dadosFicha(array $post, bool $completo = true): array
    {
        $data = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) && strtotime((string) $v) && $v <= date('Y-m-d') && $v >= '1900-01-01' ? (string) $v : null;
        $dados = [
            'apelido'         => mb_substr(trim((string) ($post['apelido'] ?? '')), 0, 60) ?: null,
            'data_nascimento' => $data($post['data_nascimento'] ?? ''),
            'whatsapp'        => Normalizar::telefone((string) ($post['whatsapp'] ?? '')),
            'telefone'        => Normalizar::telefone((string) ($post['telefone'] ?? '')),
        ];
        if ($completo) { // só o admin mexe nestes
            $dados['data_admissao'] = $data($post['data_admissao'] ?? '');
            $dados['observacoes'] = trim((string) ($post['observacoes'] ?? '')) ?: null;
        }
        return $dados;
    }

    /**
     * Grava a foto enviada ($_FILES['foto']) e devolve o caminho novo (ou null se nada foi enviado).
     * Erro de arquivo vira RuntimeException com a mensagem para a tela.
     */
    public static function salvarFoto(int $usuarioId, ?array $arquivo): ?string
    {
        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($arquivo['error'] !== UPLOAD_ERR_OK || $arquivo['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('A foto precisa ter até 5 MB.');
        }
        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new RuntimeException('Envie a foto em JPG, PNG ou WebP.');
        }
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('O servidor está sem a extensão de imagens (GD) do PHP. Peça para ativar no painel da hospedagem.');
        }
        $origem = match ($tipo) {
            'image/png' => @imagecreatefrompng($arquivo['tmp_name']),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($arquivo['tmp_name']) : false,
            default => @imagecreatefromjpeg($arquivo['tmp_name']),
        };
        if (!$origem) {
            throw new RuntimeException('Não consegui abrir essa imagem. Tente outra foto.');
        }
        // Foto de celular vem "deitada" com a informação de giro (EXIF): endireita antes de recortar
        if ($tipo === 'image/jpeg' && function_exists('exif_read_data')) {
            $giro = [3 => 180, 6 => -90, 8 => 90][(int) (@exif_read_data($arquivo['tmp_name'])['Orientation'] ?? 1)] ?? 0;
            if ($giro) {
                $origem = imagerotate($origem, $giro, 0) ?: $origem;
            }
        }
        // Recorte quadrado no centro → 400×400
        $w = imagesx($origem);
        $h = imagesy($origem);
        $lado = min($w, $h);
        $foto = imagecreatetruecolor(400, 400);
        imagecopyresampled($foto, $origem, 0, 0, (int) (($w - $lado) / 2), (int) (($h - $lado) / 2), 400, 400, $lado, $lado);
        $pasta = RAIZ . '/public/' . self::PASTA;
        if ((!is_dir($pasta) && !@mkdir($pasta, 0755, true)) || !is_writable($pasta)) {
            throw new RuntimeException('A pasta das fotos (public/media/equipe) está sem permissão de gravação no servidor.');
        }
        // WebP (menor); se o servidor não tiver suporte a WebP, grava em JPG
        $webp = function_exists('imagewebp');
        $caminho = self::PASTA . '/' . $usuarioId . '-' . bin2hex(random_bytes(6)) . ($webp ? '.webp' : '.jpg');
        $gravou = $webp ? @imagewebp($foto, RAIZ . '/public/' . $caminho, 82) : @imagejpeg($foto, RAIZ . '/public/' . $caminho, 85);
        if (!$gravou) {
            throw new RuntimeException('Não consegui salvar a foto no servidor.');
        }
        imagedestroy($foto);
        imagedestroy($origem);
        self::apagarFotoAntiga($usuarioId);
        Banco::executar('UPDATE usuarios SET foto = ? WHERE id = ?', [$caminho, $usuarioId]);
        return $caminho;
    }

    public static function removerFoto(int $usuarioId): void
    {
        self::apagarFotoAntiga($usuarioId);
        Banco::executar('UPDATE usuarios SET foto = NULL WHERE id = ?', [$usuarioId]);
    }

    private static function apagarFotoAntiga(int $usuarioId): void
    {
        $antiga = (string) Banco::valor('SELECT foto FROM usuarios WHERE id = ?', [$usuarioId]);
        if ($antiga !== '' && str_starts_with($antiga, self::PASTA . '/') && is_file(RAIZ . '/public/' . $antiga)) {
            @unlink(RAIZ . '/public/' . $antiga);
        }
    }

    /** Iniciais para quando não há foto ("Ana Souza" → "AS") */
    public static function iniciais(string $nome): string
    {
        $partes = array_values(array_filter(explode(' ', trim(preg_replace('/\(.*?\)/', '', $nome)))));
        $ini = mb_substr($partes[0] ?? '?', 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');
        return mb_strtoupper($ini);
    }

    /**
     * Como chamar a PRÓPRIA pessoa (topo, saudações): o apelido dela ("Como prefere ser chamado(a)") ou o primeiro nome.
     * Para os OUTROS (aniversariantes, online, ranking, avisos) aparece sempre o nome do funcionário.
     */
    public static function comoChamar(?array $u): string
    {
        return trim((string) ($u['apelido'] ?? '')) ?: explode(' ', trim((string) ($u['nome'] ?? '')))[0];
    }

    /** HTML do avatar (foto ou iniciais do nome). $tamanho em px */
    public static function avatar(array $u, int $tamanho = 36): string
    {
        $nome = (string) ($u['nome'] ?? '');
        if (!empty($u['foto'])) {
            return '<img class="avatar" src="' . e(url('/' . $u['foto'])) . '" alt="' . e($nome) . '" width="' . $tamanho . '" height="' . $tamanho . '" loading="lazy">';
        }
        return '<span class="avatar avatar-iniciais" style="width:' . $tamanho . 'px;height:' . $tamanho . 'px;font-size:' . round($tamanho * .38) . 'px" aria-hidden="true">' . e(self::iniciais($nome)) . '</span>';
    }

    /** Aniversariantes do mês (ativos), em ordem de dia, marcando quem faz aniversário hoje. Nunca devolve o ano. */
    public static function aniversariantes(?int $mes = null): array
    {
        $mes ??= (int) date('n');
        $lista = Banco::todos('SELECT id, nome, apelido, foto, DAY(data_nascimento) AS dia FROM usuarios
            WHERE ativo = 1 AND data_nascimento IS NOT NULL AND MONTH(data_nascimento) = ? ORDER BY DAY(data_nascimento), nome', [$mes]);
        $hoje = (int) date('j');
        foreach ($lista as &$a) {
            $a['dia'] = (int) $a['dia'];
            $a['hoje'] = $mes === (int) date('n') && $a['dia'] === $hoje;
            $a['passou'] = $mes === (int) date('n') && $a['dia'] < $hoje;
        }
        unset($a);
        return $lista;
    }
}
