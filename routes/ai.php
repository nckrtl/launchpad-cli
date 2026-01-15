<?php

declare(strict_types=1);

use App\Mcp\Servers\OrbitServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| Register MCP servers for AI tool integration. The 'orbit' server
| provides access to Docker infrastructure, site management, and
| environment configuration.
|
| Usage: orbit mcp:start orbit
|
*/

Mcp::local('orbit', OrbitServer::class);
