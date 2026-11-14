<?php
/**
 * Carteiras — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 * Cria uma carteira para cada par (vendedor(a), setor) que já tem clientes e coloca cada cliente na sua.
 * Roda de novo sem problema: só encaixa clientes que ainda estão sem carteira.
 */
use App\Core\Banco;
use App\Services\CarteirasService;

// Permissão nova: Gestor usa Gestão › Carteiras (a gestora do setor usa nas carteiras dos setores dela)
Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'carteiras.gerenciar' FROM perfis WHERE slug = 'gestor'");

$n = CarteirasService::encaixar();
return $n ? "Carteiras: $n cliente(s) colocados na carteira do(a) seu(sua) vendedor(a)." : null;
