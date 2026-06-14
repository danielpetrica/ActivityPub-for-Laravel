<?php

use App\Mcp\Servers\GhActionsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/gh-actions', GhActionsServer::class);
