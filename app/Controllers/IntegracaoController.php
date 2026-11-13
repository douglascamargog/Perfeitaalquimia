<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Sessao;
use App\Integrations\Integracoes;
use App\Services\ErpSync;

/**
 * Administração › Integração Omie: o que vem e o que vai para o ERP, situação da conexão,
 * números da sincronização e o botão "Sincronizar agora" (o mesmo que o cron faz a cada 30 min).
 * As chaves do Omie ficam no config.php do servidor (fora do banco e do git), nunca na tela.
 */
class IntegracaoController extends Controller
{
    public function index(): void
    {
        $chaves = (string) config('integracoes.omie.app_key', '') !== '' && (string) config('integracoes.omie.app_secret', '') !== '';
        $this->tela('integracao/index', [
            'titulo' => 'Integração Omie', 'simulado' => Integracoes::erpSimulado(), 'chaves' => $chaves, 'ultima' => ErpSync::ultima(),
            'numeros' => Banco::um("SELECT
                    SUM(omie_codigo IS NULL AND situacao IN ('aguardando_aprovacao', 'aprovado')) AS sem_envio,
                    SUM(situacao = 'aguardando_aprovacao') AS aguardando,
                    SUM(situacao = 'aprovado') AS a_faturar,
                    SUM(situacao = 'faturado' AND faturado_em >= CURDATE() - INTERVAL 29 DAY) AS faturados_30d
                FROM pedidos"),
            'produtos' => (int) Banco::valor('SELECT COUNT(DISTINCT produto_id) FROM produto_precos'),
            'estoque' => (int) Banco::valor('SELECT COUNT(*) FROM produto_estoque'),
            'estilos' => ['carteira.css', 'inicio.css', 'painel.css', 'prospeccao.css'],
        ]);
    }

    public function testar(): void
    {
        $r = Integracoes::erp()->testarConexao();
        Sessao::aviso($r['ok'] ? 'sucesso' : 'erro', $r['mensagem']);
        redirecionar('/integracao');
    }

    public function sincronizar(): void
    {
        try {
            $r = ErpSync::tudo();
            Auditoria::registrar('integracao', null, 'sincronizar', null, $r);
            Sessao::aviso('sucesso', "Sincronizado: {$r['precos']} preço(s), {$r['estoque']} estoque(s), {$r['enviados']} pedido(s) enviado(s), {$r['faturados']} faturado(s).");
        } catch (\Throwable $e) {
            error_log('Sincronizar ERP: ' . $e->getMessage());
            Sessao::aviso('erro', 'A sincronização falhou. Tente de novo em alguns minutos (o cron também tenta a cada 30 min).');
        }
        redirecionar('/integracao');
    }
}
