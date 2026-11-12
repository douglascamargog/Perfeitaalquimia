<?php
namespace App\Services;

/**
 * Erro lançado quando duas pessoas editam o mesmo registro ao mesmo tempo.
 * A tela recarrega os dados mais recentes em vez de sobrescrever o trabalho do outro.
 */
class ConflitoException extends \RuntimeException
{
}
