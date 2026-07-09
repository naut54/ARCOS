<?php

declare(strict_types=1);

namespace Arcos\Tests\Unit;

use Arcos\Core\Http\UriResolverInterface;
use Arcos\Core\Routing\AutoResolver;
use Arcos\Core\Routing\Router;
use LogicException;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class AutoResolverTest extends TestCase
{
    private string $basePath;

    #[After]
    public function cleanupTempDir(): void
    {
        if (isset($this->basePath) && is_dir($this->basePath)) {
            $this->removeDirectory($this->basePath);
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        rmdir($dir);
    }

    /**
     * Writes a controller file under <basePath>/app/Controllers/<name>.php with
     * the given class body, then requires it so class_exists() finds it — this
     * mirrors how Composer's autoloader would make it available in a real project.
     */
    private function writeController(string $basePath, string $name, string $classBody): void
    {
        $dir = $basePath . '/app/Controllers';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $namespace = 'App\\Controllers';
        $code      = "<?php\ndeclare(strict_types=1);\nnamespace {$namespace};\n"
            . "use Arcos\\Core\\Http\\Request;\n"
            . "use Arcos\\Core\\Http\\Response;\n"
            . "use Arcos\\Core\\Routing\\Attributes\\Routable;\n"
            . "class {$name} {\n{$classBody}\n}\n";

        $path = $dir . "/{$name}.php";
        file_put_contents($path, $code);
        require $path;
    }

    private function tempBasePath(string $suffix): string
    {
        $this->basePath = sys_get_temp_dir() . '/arcos-autoresolver-test-' . $suffix . '-' . uniqid();
        mkdir($this->basePath, 0755, true);

        return $this->basePath;
    }

    public function test_registers_a_routable_method_with_the_correct_uri(): void
    {
        $basePath = $this->tempBasePath('valid');
        $this->writeController($basePath, 'ProductsController1', <<<'PHP'
            #[Routable(methods: ['GET'])]
            public function getAllProducts(Request $request): Response
            {
                return new Response([], 200);
            }
        PHP);

        // Goes through the real boot() path (not a bare AutoResolver call) since
        // boot() is what wraps auto-resolution in a subdomain() context in
        // production — see the regression this guards in Router::boot().
        $router = new Router();
        $router->registerSubdomain(
            'api',
            $this->fakeResolver(),
            autoResolve: true,
            controllersNamespace: 'App\\Controllers',
        );
        $router->setActiveSubdomain('api');
        $router->boot($basePath);

        $dump = $router->dumpRoutes();
        $match = array_values(array_filter($dump, fn($r) => $r['action'] === 'getAllProducts'));

        $this->assertNotEmpty($match);
        $this->assertSame('GET', $match[0]['method']);
        $this->assertSame('/products1/getAllProducts', $match[0]['uri']);
        $this->assertSame('auto', $match[0]['source']);
    }

    public function test_throws_when_routable_method_has_wrong_parameter_count(): void
    {
        $basePath = $this->tempBasePath('bad-params');
        $this->writeController($basePath, 'BadParamsController', <<<'PHP'
            #[Routable(methods: ['GET'])]
            public function index(Request $request, int $extra): Response
            {
                return new Response([], 200);
            }
        PHP);

        $router = new Router();
        $router->registerSubdomain('api', $this->fakeResolver());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must accept exactly one parameter');

        (new AutoResolver($router, 'App\\Controllers', $basePath, 'api'))->resolve();
    }

    public function test_throws_when_routable_method_return_type_is_not_response(): void
    {
        $basePath = $this->tempBasePath('bad-return');
        $this->writeController($basePath, 'BadReturnController', <<<'PHP'
            #[Routable(methods: ['GET'])]
            public function index(Request $request): array
            {
                return [];
            }
        PHP);

        $router = new Router();
        $router->registerSubdomain('api', $this->fakeResolver());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must declare a return type');

        (new AutoResolver($router, 'App\\Controllers', $basePath, 'api'))->resolve();
    }

    public function test_throws_when_a_constructor_parameter_is_untyped(): void
    {
        $basePath = $this->tempBasePath('untyped-ctor');
        $this->writeController($basePath, 'UntypedCtorController', <<<'PHP'
            public function __construct($something) {}

            #[Routable(methods: ['GET'])]
            public function index(Request $request): Response
            {
                return new Response([], 200);
            }
        PHP);

        $router = new Router();
        $router->registerSubdomain('api', $this->fakeResolver());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has no type hint');

        (new AutoResolver($router, 'App\\Controllers', $basePath, 'api'))->resolve();
    }

    public function test_directory_not_existing_throws(): void
    {
        $router = new Router();
        $router->registerSubdomain('api', $this->fakeResolver());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not exist');

        (new AutoResolver($router, 'App\\Controllers', sys_get_temp_dir() . '/arcos-does-not-exist-' . uniqid(), 'api'))->resolve();
    }

    public function test_methods_without_the_routable_attribute_are_skipped(): void
    {
        $basePath = $this->tempBasePath('non-routable');
        $this->writeController($basePath, 'NonRoutableController', <<<'PHP'
            public function helperMethod(Request $request): Response
            {
                return new Response([], 200);
            }
        PHP);

        $router = new Router();
        $router->registerSubdomain('api', $this->fakeResolver());
        $router->setActiveSubdomain('api');

        (new AutoResolver($router, 'App\\Controllers', $basePath, 'api'))->resolve();

        $this->assertSame([], $router->dumpRoutes());
    }

    private function fakeResolver(): UriResolverInterface
    {
        return new class implements UriResolverInterface {
            public function resolve(): string
            {
                return '/';
            }
        };
    }
}
