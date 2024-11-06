<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__ . '/../routes/api.php', //uncomment if use api
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // $middleware->alias([
        //     'abilities' => CheckAbilities::class,
        //     'ability' => CheckForAnyAbility::class,
        // ]); //uncomment if use auth
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
