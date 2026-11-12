<?php
namespace App\Integrations\Tray;

/** Uso interno do TrayClient: a Tray recusou o token (renovar e tentar de novo) */
class TrayNaoAutorizado extends \RuntimeException
{
}
