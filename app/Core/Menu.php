<?php
namespace App\Core;

/** Ajuda com o menu lateral (app/menu.php) */
class Menu
{
    /** Telas que não estão no menu, mas têm nome */
    private const OUTRAS = ['/meu-perfil' => 'Meu perfil', '/novidades' => 'Novidades', '/minha-senha' => 'Meu perfil', '/notificacoes' => 'Notificações'];

    /** O item do menu da página (o mais específico: /producao/fichas → "Fichas técnicas") ou null */
    public static function itemDe(string $caminho): ?array
    {
        $caminho = '/' . trim($caminho, '/');
        $achado = null;
        foreach (require RAIZ . '/app/menu.php' as $item) {
            $ok = $item['url'] === '/' ? $caminho === '/' : str_starts_with($caminho . '/', $item['url'] . '/');
            if ($ok && strlen($item['url']) > strlen((string) ($achado['url'] ?? ''))) {
                $achado = $item;
            }
        }
        return $achado;
    }

    /** Nome da aba para mostrar ("Carteira", "Pedidos"...). Nunca devolve ids nem dados da página. */
    public static function abaDe(string $caminho): string
    {
        $caminho = '/' . trim($caminho, '/');
        foreach (self::OUTRAS as $url => $nome) {
            if (str_starts_with($caminho . '/', $url . '/')) {
                return $nome;
            }
        }
        return self::itemDe($caminho)['titulo'] ?? 'One';
    }
}
