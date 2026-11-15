<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\TreinamentoService as T;
use App\Services\TutorialService;

/**
 * Treinamento (V13.0): o treinamento de cada pessoa (Integração + o setor dela + áreas vizinhas),
 * módulo com prova, todas as trilhas (consulta), Equipe (gestor) e edição (Admin e Marketing).
 * Também recebe o "Aprendi tudo desta tela" do tutorial do One.
 */
class TreinamentoController extends Controller
{
    private const ESTILOS = ['treinamento.css'];

    /** Meu treinamento */
    public function index(): void
    {
        $usuario = Auth::usuario();
        $blocos = T::daPessoa($usuario);
        $progresso = T::progresso((int) $usuario['id']);
        [$feitos, $total, $proximo] = T::resumo($blocos, $progresso);
        [$telasFeitas, $telasTotal] = TutorialService::progresso();
        $this->tela('treinamento/index', ['titulo' => 'Meu treinamento', 'aba' => 'meu', 'blocos' => $blocos, 'progresso' => $progresso,
            'feitos' => $feitos, 'total' => $total, 'proximo' => $proximo, 'telasFeitas' => $telasFeitas, 'telasTotal' => $telasTotal,
            'semSetor' => !T::funcoesDe($usuario), 'concluidas' => T::trilhasConcluidas((int) $usuario['id']), 'estilos' => self::ESTILOS]);
    }

    /** Todas as trilhas (consulta) */
    public function trilhas(): void
    {
        $trilhas = T::trilhas();
        foreach ($trilhas as &$t) {
            $t['modulos'] = T::modulos((int) $t['id']);
        }
        $this->tela('treinamento/trilhas', ['titulo' => 'Treinamento · Todas as trilhas', 'aba' => 'trilhas', 'trilhas' => $trilhas,
            'progresso' => T::progresso((int) Auth::id()), 'estilos' => self::ESTILOS]);
    }

    /** Ler um módulo (marca como lido) */
    public function modulo(string $id): void
    {
        $m = T::modulo((int) $id);
        if (!$m || !$m['ativo'] || !T::podeAbrir(Auth::usuario(), (int) $id)) {
            Sessao::aviso('erro', 'Módulo não encontrado.');
            redirecionar('/treinamento');
        }
        T::marcarLido((int) Auth::id(), (int) $id);
        $this->mostrarModulo($m, null);
    }

    /** Corrige a prova e mostra o resultado na mesma tela */
    public function prova(string $id): void
    {
        $m = T::modulo((int) $id);
        if (!$m || !$m['ativo'] || !T::podeAbrir(Auth::usuario(), (int) $id)) {
            redirecionar('/treinamento');
        }
        $resultado = T::corrigir((int) Auth::id(), (int) $id, (array) ($_POST['resposta'] ?? []));
        if ($resultado['trilha_concluida']) {
            \App\Services\OneTela::festejar('Trilha concluída! 🎓', 'Você terminou "' . $resultado['trilha_concluida'] . '". O selo já está no seu perfil.');
            Sessao::aviso('sucesso', 'Parabéns! Você concluiu a trilha "' . $resultado['trilha_concluida'] . '".');
        }
        $this->mostrarModulo($m, $resultado);
    }

    private function mostrarModulo(array $m, ?array $resultado): void
    {
        $usuario = Auth::usuario();
        // Anterior e próximo dentro do treinamento da pessoa (ou da trilha, quando está consultando outro setor)
        $sequencia = [];
        foreach (T::daPessoa($usuario) as $b) {
            foreach ($b['modulos'] as $x) {
                $sequencia[] = $x;
            }
        }
        $ids = array_map(fn($x) => (int) $x['id'], $sequencia);
        $pos = array_search((int) $m['id'], $ids, true);
        if ($pos === false) {
            $sequencia = T::modulos((int) $m['trilha_id']);
            $ids = array_map(fn($x) => (int) $x['id'], $sequencia);
            $pos = array_search((int) $m['id'], $ids, true);
        }
        $progresso = T::progresso((int) $usuario['id'])[(int) $m['id']] ?? null;
        $this->tela('treinamento/modulo', ['titulo' => 'Treinamento · ' . $m['titulo'], 'aba' => 'meu', 'm' => $m,
            'perguntas' => T::perguntas((int) $m['id']), 'resultado' => $resultado, 'meu' => $progresso,
            'anterior' => $pos !== false && $pos > 0 ? $sequencia[$pos - 1] : null,
            'proximo' => $pos !== false ? ($sequencia[$pos + 1] ?? null) : null,
            'notaMinima' => T::notaMinima(), 'estilos' => self::ESTILOS]);
    }

    /** Equipe: progresso de cada pessoa */
    public function equipe(): void
    {
        $funcao = preg_replace('/[^a-z_]/', '', (string) ($_GET['setor'] ?? ''));
        $this->tela('treinamento/equipe', ['titulo' => 'Treinamento · Equipe', 'aba' => 'equipe', 'pessoas' => T::equipe($funcao), 'setor' => $funcao,
            'funcoes' => Banco::todos('SELECT slug, nome FROM funcoes WHERE ativa = 1 ORDER BY ordem'), 'telasTotal' => count(TutorialService::telas()), 'estilos' => self::ESTILOS]);
    }

    public function reiniciarTutorial(string $id): void
    {
        $nome = Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [(int) $id]);
        if ($nome) {
            TutorialService::reiniciar((int) $id);
            Sessao::aviso('sucesso', 'Tutorial reiniciado: na próxima vez que ' . $nome . ' abrir cada tela, o One mostra o passo a passo de novo.');
        }
        redirecionar('/treinamento/equipe');
    }

    // ------------------------------------------------------------------ tutorial (JSON)

    /** "Aprendi tudo desta tela" */
    public function aprendi(): void
    {
        [$feitas, $total, $terminou] = TutorialService::aprendi((string) ($_POST['rota'] ?? ''));
        $this->json(['ok' => true, 'feitas' => $feitas, 'total' => $total, 'terminou' => $terminou]);
    }

    // ------------------------------------------------------------------ edição (Admin e Marketing)

    public function editar(): void
    {
        $trilhas = T::trilhas();
        foreach ($trilhas as &$t) {
            $t['modulos'] = Banco::todos('SELECT m.id, m.titulo, m.tipo, m.ativo, m.editado_em, (SELECT COUNT(*) FROM trein_perguntas p WHERE p.modulo_id = m.id) perguntas
                FROM trein_modulos m WHERE m.trilha_id = ? ORDER BY m.ordem, m.id', [$t['id']]);
        }
        $this->tela('treinamento/editar', ['titulo' => 'Treinamento · Editar', 'aba' => 'editar', 'trilhas' => $trilhas,
            'tutoriais' => Banco::todos('SELECT id, rota, titulo, ativo, JSON_LENGTH(passos_json) passos, editado_em FROM tutorial_telas ORDER BY ordem, id'),
            'estilos' => self::ESTILOS]);
    }

    public function editarModulo(string $id): void
    {
        $m = T::modulo((int) $id);
        if (!$m) {
            redirecionar('/treinamento/editar');
        }
        $this->tela('treinamento/editar_modulo', ['titulo' => 'Editar módulo', 'aba' => 'editar', 'm' => $m, 'perguntas' => T::perguntas((int) $id, true), 'estilos' => self::ESTILOS]);
    }

    public function salvarModulo(string $id): void
    {
        if (!T::modulo((int) $id)) {
            redirecionar('/treinamento/editar');
        }
        T::salvarModulo((int) $id, $_POST);
        Sessao::aviso('sucesso', 'Módulo salvo.');
        redirecionar('/treinamento/editar/modulo/' . (int) $id);
    }

    public function novoModulo(string $trilha): void
    {
        if (!Banco::valor('SELECT 1 FROM trein_trilhas WHERE id = ?', [(int) $trilha])) {
            redirecionar('/treinamento/editar');
        }
        $id = T::novoModulo((int) $trilha, (string) ($_POST['tipo'] ?? 'setor'));
        redirecionar('/treinamento/editar/modulo/' . $id);
    }

    public function editarTutorial(string $id): void
    {
        $t = Banco::um('SELECT * FROM tutorial_telas WHERE id = ?', [(int) $id]);
        if (!$t) {
            redirecionar('/treinamento/editar#tutoriais');
        }
        $t['passos'] = (array) json_decode($t['passos_json'], true);
        $this->tela('treinamento/editar_tutorial', ['titulo' => 'Editar tutorial', 'aba' => 'editar', 't' => $t, 'estilos' => self::ESTILOS]);
    }

    public function salvarTutorial(string $id): void
    {
        if (Banco::valor('SELECT 1 FROM tutorial_telas WHERE id = ?', [(int) $id])) {
            TutorialService::salvar((int) $id, (string) ($_POST['titulo'] ?? ''), (string) ($_POST['passos'] ?? ''), !empty($_POST['ativo']));
            Sessao::aviso('sucesso', 'Tutorial salvo.');
        }
        redirecionar('/treinamento/editar/tutorial/' . (int) $id);
    }
}
