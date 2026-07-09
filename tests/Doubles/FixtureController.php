<?php

declare(strict_types=1);

namespace Arcos\Tests\Doubles;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;

class FixtureController
{
    public function index(Request $request): Response
    {
        return new Response(['success' => true, 'data' => []], 200);
    }

    public function show(Request $request): Response
    {
        return new Response(['success' => true, 'data' => ['id' => $request->input('id')]], 200);
    }
}
