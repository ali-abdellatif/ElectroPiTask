<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Declare the Sanctum bearer token on the generated OpenAPI document,
        // so the docs page can actually authenticate instead of only describing
        // endpoints it cannot call.
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $document) {
                $document->secure(
                    SecurityScheme::http('bearer')
                        ->setDescription('Token returned by `POST /api/v1/register` or `POST /api/v1/login`.')
                );
            })
            // Securing the document applies the token to every operation, which
            // would misdescribe register and login as requiring one. Clear it on
            // the routes that are genuinely public, deciding from the middleware
            // they actually carry rather than from a hardcoded list of paths.
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
                $requiresToken = collect($routeInfo->route->gatherMiddleware())
                    ->contains(fn ($middleware) => str_starts_with((string) $middleware, 'auth:'));

                if (! $requiresToken) {
                    $operation->security = [];
                }
            });
    }
}
