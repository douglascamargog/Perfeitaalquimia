<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Visao;
use App\Models\Listas;
use App\Models\Post;
use App\Services\MarketingService;

/**
 * Post do Instagram na gaveta lateral: criar, editar, mudar data/etapa, aprovar, pedir ajuste,
 * publicar e comentar. Respostas em JSON (o carteira.js recarrega a gaveta).
 */
class PostController extends Controller
{
    private function postOuErro(string $id): array
    {
        $post = Post::buscar((int) $id);
        if (!$post) {
            $this->json(['ok' => false, 'mensagem' => 'Post não encontrado.'], 404);
            exit;
        }
        return $post;
    }

    private function gaveta(?array $post): void
    {
        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');
        echo Visao::renderizar('marketing/_post', [
            'p' => $post ?? ['id' => null, 'titulo' => '', 'formato' => 'feed', 'data_publicacao' => $data, 'hora' => null, 'legenda' => '',
                'hashtags' => '', 'campanha_id' => null, 'responsavel_id' => Auth::id(), 'imagem' => null, 'etapa' => null, 'etapa_tipo' => 'rascunho'],
            'comentarios' => $post ? Post::comentarios((int) $post['id']) : [],
            'etapas' => Listas::etapasPosts(), 'campanhas' => Post::listaCampanhas(), 'responsaveis' => Post::responsaveis(),
            'podeEditar' => Auth::pode('marketing.editar'), 'podeAprovar' => Auth::pode('marketing.aprovar'),
        ]);
    }

    public function novo(): void
    {
        $this->gaveta(null);
    }

    public function ver(string $id): void
    {
        $this->gaveta($this->postOuErro($id));
    }

    public function criar(): void
    {
        [$dados, $erros] = MarketingService::validar($_POST);
        if ($erros) {
            $this->json(['ok' => false, 'mensagem' => implode(' ', $erros)], 422);
            return;
        }
        try {
            $midia = MarketingService::guardarImagem($_FILES['imagem'] ?? null, $dados['titulo']);
        } catch (\RuntimeException $erro) {
            $this->json(['ok' => false, 'mensagem' => $erro->getMessage()], 422);
            return;
        }
        $id = MarketingService::criar($dados, $midia, Auth::id());
        $this->json(['ok' => true, 'mensagem' => 'Post criado.', 'abrir' => $id]);
    }

    public function salvar(string $id): void
    {
        $post = $this->postOuErro($id);
        [$dados, $erros] = MarketingService::validar($_POST);
        if ($erros) {
            $this->json(['ok' => false, 'mensagem' => implode(' ', $erros)], 422);
            return;
        }
        try {
            $midia = MarketingService::guardarImagem($_FILES['imagem'] ?? null, $dados['titulo']);
        } catch (\RuntimeException $erro) {
            $this->json(['ok' => false, 'mensagem' => $erro->getMessage()], 422);
            return;
        }
        $this->json(['ok' => true, 'mensagem' => MarketingService::atualizar($post, $dados, $midia, Auth::id())]);
    }

    /** Arrastar no calendário */
    public function data(string $id): void
    {
        $post = $this->postOuErro($id);
        $data = $this->campo('data');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $this->json(['ok' => false, 'mensagem' => 'Data inválida.'], 422);
            return;
        }
        MarketingService::mudarData($post, $data, Auth::id());
        $this->json(['ok' => true, 'mensagem' => 'Data alterada para ' . data_br($data) . '.']);
    }

    public function etapa(string $id): void
    {
        $post = $this->postOuErro($id);
        $motivo = MarketingService::mudarEtapa($post, (int) $this->campo('etapa_id'), Auth::id());
        $this->json($motivo ? ['ok' => false, 'mensagem' => $motivo] : ['ok' => true, 'mensagem' => 'Etapa atualizada.'], $motivo ? 422 : 200);
    }

    /** Atalho do autor: "Enviar para aprovação" */
    public function enviarAprovacao(string $id): void
    {
        $post = $this->postOuErro($id);
        $etapa = MarketingService::etapaDoTipo('aprovacao');
        $motivo = $etapa ? MarketingService::mudarEtapa($post, (int) $etapa['id'], Auth::id()) : 'Não existe etapa de aprovação.';
        $this->json($motivo ? ['ok' => false, 'mensagem' => $motivo] : ['ok' => true, 'mensagem' => 'Enviado para aprovação.'], $motivo ? 422 : 200);
    }

    public function aprovar(string $id): void
    {
        $motivo = MarketingService::aprovar($this->postOuErro($id), Auth::id());
        $this->json($motivo ? ['ok' => false, 'mensagem' => $motivo] : ['ok' => true, 'mensagem' => 'Post aprovado.'], $motivo ? 422 : 200);
    }

    public function ajuste(string $id): void
    {
        $post = $this->postOuErro($id);
        $motivo = mb_substr($this->campo('motivo'), 0, 500);
        if ($motivo === '') {
            $this->json(['ok' => false, 'mensagem' => 'Escreva o que precisa ajustar.'], 422);
            return;
        }
        MarketingService::pedirAjuste($post, $motivo, Auth::id());
        $this->json(['ok' => true, 'mensagem' => 'Ajuste pedido. O responsável vê o motivo no post.']);
    }

    public function publicar(string $id): void
    {
        $post = $this->postOuErro($id);
        $link = $this->campo('link');
        if (!preg_match('#^https://(www\.)?instagram\.com/#i', $link)) {
            $this->json(['ok' => false, 'mensagem' => 'Cole o link do post no Instagram (https://www.instagram.com/...).'], 422);
            return;
        }
        $motivo = MarketingService::publicar($post, mb_substr($link, 0, 500), Auth::id());
        $this->json($motivo ? ['ok' => false, 'mensagem' => $motivo] : ['ok' => true, 'mensagem' => 'Marcado como publicado.'], $motivo ? 422 : 200);
    }

    public function comentar(string $id): void
    {
        $post = $this->postOuErro($id);
        $texto = $this->campo('texto');
        if ($texto === '') {
            $this->json(['ok' => false, 'mensagem' => 'Escreva o comentário.'], 422);
            return;
        }
        MarketingService::comentar((int) $post['id'], Auth::id(), $texto);
        $this->json(['ok' => true, 'mensagem' => 'Comentário enviado.']);
    }
}
