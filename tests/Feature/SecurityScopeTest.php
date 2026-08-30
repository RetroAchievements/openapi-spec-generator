<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use LaravelJsonApi\OpenApiSpec\Facades\GeneratorFacade;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders\DatabaseSeeder;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Middleware\DeclaresReadScope;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;

class SecurityScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // The fixture routes carry the `api` middleware, so match the scheme to it.
        config()->set('openapi.servers.v1.securitySchemes', [
            'OAuth2' => [
                'middleware' => ['api'],
                'type' => 'oauth2',
                'flows' => [
                    'authorizationCode' => [
                        'authorizationUrl' => '/oauth/authorize',
                        'tokenUrl' => '/oauth/token',
                        'scopes' => [
                            'data:read' => 'Read data',
                            'follows:read' => 'Read follows',
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function securityFor(string $path): array
    {
        $document = json_decode(GeneratorFacade::generate('v1', 'json'), true);

        return $document['paths'][$path]['get']['security'];
    }

    /**
     * Adds a middleware to every route already registered for the fixture server.
     */
    private function pushMiddleware(string $middleware): void
    {
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'v1.')) {
                $route->middleware($middleware);
            }
        }
    }

    public function test_no_scopes_are_attached_when_no_middleware_declares_one(): void
    {
        $this->assertEquals([['OAuth2' => []]], $this->securityFor('/posts'));
    }

    /**
     * Passport lists several scopes in one parameter, separated by commas.
     */
    public function test_passport_style_middleware_contributes_each_of_its_scopes(): void
    {
        $this->pushMiddleware('Laravel\Passport\Http\Middleware\CheckToken:data:read,follows:read');

        $this->assertEquals([['OAuth2' => ['data:read', 'follows:read']]], $this->securityFor('/posts'));
    }

    /**
     * An application's own middleware takes a single scope, which may itself
     * contain a colon. Splitting on the wrong colon yields a scope that does not
     * exist, and the intersection then silently discards it.
     */
    public function test_application_middleware_carrying_one_scope_is_recognised(): void
    {
        $this->pushMiddleware('App\Api\Middleware\RequireOAuthTokenWithScope:follows:read');

        $this->assertEquals([['OAuth2' => ['follows:read']]], $this->securityFor('/posts'));
    }

    /**
     * Guard names, log channels, and throttle rates all take parameters too.
     */
    public function test_parameters_that_are_not_declared_scopes_are_discarded(): void
    {
        $this->pushMiddleware('throttle:60,1');
        $this->pushMiddleware('auth:api-token-header,oauth');

        $this->assertEquals([['OAuth2' => []]], $this->securityFor('/posts'));
    }

    public function test_a_scope_the_scheme_does_not_declare_is_discarded(): void
    {
        $this->pushMiddleware('App\Api\Middleware\RequireOAuthTokenWithScope:game-lists:read');

        $this->assertEquals([['OAuth2' => []]], $this->securityFor('/posts'));
    }

    /**
     * A gate that resolves its scope internally leaves no marker in the route
     * definition, so without this the operation claims it needs no scope.
     */
    public function test_middleware_can_declare_the_scope_it_enforces(): void
    {
        $this->pushMiddleware(DeclaresReadScope::class);

        $this->assertEquals([['OAuth2' => ['data:read']]], $this->securityFor('/posts'));
    }

    /**
     * The declaring middleware sees the whole list, so a gate that stands down
     * at runtime reaches the same conclusion in the document.
     */
    public function test_a_declaring_middleware_can_stand_down_for_another_scope(): void
    {
        $this->pushMiddleware(DeclaresReadScope::class);
        $this->pushMiddleware('App\Api\Middleware\RequireOAuthTokenWithScope:follows:read');

        $this->assertEquals([['OAuth2' => ['follows:read']]], $this->securityFor('/posts'));
    }

    public function test_middleware_without_the_declaration_contributes_nothing(): void
    {
        $this->pushMiddleware('Illuminate\Auth\Middleware\Authenticate');

        $this->assertEquals([['OAuth2' => []]], $this->securityFor('/posts'));
    }

    public function test_scanning_can_be_turned_off_for_a_scheme(): void
    {
        config()->set('openapi.servers.v1.securitySchemes.OAuth2.scanForScopes', false);
        $this->pushMiddleware('App\Api\Middleware\RequireOAuthTokenWithScope:follows:read');

        $this->assertEquals([['OAuth2' => []]], $this->securityFor('/posts'));
    }

    public function test_the_former_config_key_still_turns_scanning_off(): void
    {
        config()->set('openapi.servers.v1.securitySchemes.OAuth2.scanForPassportScopes', false);
        $this->pushMiddleware('App\Api\Middleware\RequireOAuthTokenWithScope:follows:read');

        $this->assertEquals([['OAuth2' => []]], $this->securityFor('/posts'));
    }

    public function test_a_scheme_can_exclude_routes_by_middleware(): void
    {
        config()->set('openapi.servers.v1.securitySchemes.ApiKey', [
            'middleware' => ['api'],
            'excludeMiddleware' => [DeclaresReadScope::class],
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-Key',
        ]);

        $this->pushMiddleware(DeclaresReadScope::class.':follows:read');

        $schemes = array_merge(...array_map('array_keys', $this->securityFor('/posts')));
        $this->assertNotContains('ApiKey', $schemes);
        $this->assertContains('OAuth2', $schemes);
    }
}
