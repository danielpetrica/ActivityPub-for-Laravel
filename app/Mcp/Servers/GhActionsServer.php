<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetActionVersions;
use App\Mcp\Tools\GetPopularActions;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('gh-actions')]
#[Version('1.0.0')]
#[Instructions(
    'This server provides tools for looking up GitHub Action versions. '
    .'Use get-action-versions to find the latest version of any action. '
    .'Use get-popular-actions to browse curated popular actions by category.'
)]
final class GhActionsServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        GetActionVersions::class,
        GetPopularActions::class,
    ];
}
