<?php
namespace App\Controllers;

use App\Services\OrganogramaService;

/** Organograma: interligações entre os setores e a organização da empresa (áreas, funções e pessoas) */
class OrganogramaController extends Controller
{
    public function index(): void
    {
        $this->tela('organograma/index', [
            'titulo' => 'Organograma',
            'interligacoes' => OrganogramaService::interligacoes(),
            'organizacao' => OrganogramaService::organizacao(),
            'numeros' => OrganogramaService::podeVerNumeros(),
            'estilos' => ['organograma.css'],
            'scripts' => ['organograma.js'],
        ]);
    }
}
