<?php

namespace Spatie\ResponseCache\Test;

use File;
use Route;
use Illuminate\Routing\Router;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Symfony\Component\HttpFoundation\Response;
use Spatie\ResponseCache\Middlewares\CacheResponse;
use Spatie\ResponseCache\ResponseCacheServiceProvider;
use Spatie\ResponseCache\Middlewares\DoNotCacheResponse;

abstract class TestCase extends Orchestra
{
    public function setUp()
    {
        parent::setUp();

        $this->app['responsecache']->flush();

        $this->initializeDirectory($this->getTempDirectory());

        $this->setUpDatabase($this->app);

        $this->setUpRoutes($this->app);

        $this->setUpMiddleware();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ResponseCacheServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'ResponseCache' => \Spatie\ResponseCache\ResponseCacheFacade::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $this->getTempDirectory().'/database.sqlite',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', '6rE9Nz59bGRbeMATftriyQjrpF7DcOQm');
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        file_put_contents($this->getTempDirectory().'/database.sqlite', null);

        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password', 60);
            $table->rememberToken();
            $table->timestamps();
        });

        foreach (range(1, 10) as $index) {
            User::create(
                [
                    'name' => "user{$index}",
                    'email' => "user{$index}@spatie.be",
                    'password' => "password{$index}",
                ]
            );
        }
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpRoutes($app)
    {
        Route::any('/', function () {
            return 'home of '.(auth()->check() ? auth()->user()->id : 'anonymous');
        });

        Route::any('login/{id}', function ($id) {
            auth()->login(User::find($id));

            return redirect('/');
        });

        Route::any('logout', function () {
            auth()->logout();

            return redirect('/');
        });

        Route::any('/random', function () {
            return str_random();
        });

        Route::any('/redirect', function () {
            return redirect('/');
        });

        Route::any('/uncacheable', ['middleware' => 'doNotCacheResponse', function () {
            return 'uncacheable '.str_random();
        }]);
    }

    public function getTempDirectory($suffix = '')
    {
        return __DIR__.'/temp'.($suffix == '' ? '' : '/'.$suffix);
    }

    protected function initializeDirectory($directory)
    {
        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }
        File::makeDirectory($directory);
    }

    protected function assertCachedResponse(Response $response)
    {
        self::assertThat($response->headers->has('laravel-responsecache'), self::isTrue(), 'Failed to assert that the response has been cached');
    }

    protected function assertRegularResponse(Response $response)
    {
        self::assertThat($response->headers->has('laravel-responsecache'), self::isFalse(), 'Failed to assert that the response was not cached');
    }

    protected function assertSameResponse(Response $firstResponse, Response $secondResponse)
    {
        self::assertThat($firstResponse->getContent() == $secondResponse->getContent(), self::isTrue(), 'Failed to assert that two response are the same');
    }

    protected function assertDifferentResponse(Response $firstResponse, Response $secondResponse)
    {
        self::assertThat($firstResponse->getContent() != $secondResponse->getContent(), self::isTrue(), 'Failed to assert that two response are the same');
    }

    protected function setUpMiddleware()
    {
        $this->app[Kernel::class]->pushMiddleware(CacheResponse::class);
        $this->app[Router::class]->aliasMiddleware('doNotCacheResponse', DoNotCacheResponse::class);
    }
}
