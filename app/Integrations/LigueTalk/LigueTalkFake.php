<?php
namespace App\Integrations\LigueTalk;

/**
 * Ligue Talk SIMULADO (ambiente de demonstração): o "Ligar" termina na hora com um resultado sorteado
 * (atendida, não atendida ou ocupado), uma duração e uma gravação de exemplo (bipes, sem voz).
 * Os arquivos ficam em storage/gravacoes (fora da pasta pública) e só tocam pelo sistema.
 */
class LigueTalkFake extends LigueTalkManual
{
    public function automatico(): bool
    {
        return true;
    }

    public function ligar(string $ramal, string $numero): array
    {
        $sorteio = random_int(1, 100);
        $situacao = $sorteio <= 65 ? 'atendida' : ($sorteio <= 90 ? 'nao_atendida' : 'ocupado');
        $duracao = $situacao === 'atendida' ? random_int(40, 420) : 0;
        $id = 'sim-' . bin2hex(random_bytes(6));
        return [
            'id_externo' => $id, 'situacao' => $situacao, 'duracao' => $duracao,
            'gravacao' => $situacao === 'atendida' ? self::gerarGravacao($id) : null,
            'mensagem' => ['atendida' => 'Ligação atendida (simulada).', 'nao_atendida' => 'Não atendeu (simulado).', 'ocupado' => 'Linha ocupada (simulado).'][$situacao],
        ];
    }

    public function enviarLista(string $nome, array $leads): array
    {
        return ['ok' => true, 'mensagem' => count($leads) . ' cliente(s) enviados para o discador na lista "' . $nome . '" (simulado).'];
    }

    public function gravacao(string $referencia): ?string
    {
        $arquivo = self::pasta() . '/' . basename($referencia);
        return is_file($arquivo) ? (string) file_get_contents($arquivo) : null;
    }

    public static function pasta(): string
    {
        return RAIZ . '/storage/gravacoes';
    }

    /** Gera um áudio curto (WAV 8 kHz) com bipes, como "gravação" de exemplo. Devolve o nome do arquivo. */
    public static function gerarGravacao(string $id, int $segundos = 4): string
    {
        if (!is_dir(self::pasta())) {
            mkdir(self::pasta(), 0750, true);
        }
        $taxa = 8000;
        $amostras = '';
        for ($i = 0; $i < $taxa * $segundos; $i++) {
            $t = $i / $taxa;
            $ligado = fmod($t, 1.0) < 0.35; // bipe de 0,35 s a cada segundo
            $amostras .= chr($ligado ? (int) (128 + 60 * sin(2 * M_PI * 440 * $t)) : 128);
        }
        $cabecalho = 'RIFF' . pack('V', 36 + strlen($amostras)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $taxa, $taxa, 1, 8)
            . 'data' . pack('V', strlen($amostras));
        $nome = preg_replace('/[^a-z0-9-]/', '', $id) . '.wav';
        file_put_contents(self::pasta() . '/' . $nome, $cabecalho . $amostras);
        return $nome;
    }
}
