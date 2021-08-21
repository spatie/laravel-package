<?php

namespace Spatie\ResponseCache\Test;

use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Event;
use Spatie\ResponseCache\Events\CacheMissed;
use Spatie\ResponseCache\Events\ResponseCacheHit;
use Spatie\ResponseCache\Facades\ResponseCache;

class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_will_cache_a_get_request()
    {
        $firstResponse = $this->get('/random');
        $secondResponse = $this->get('/random');

        $this->assertRegularResponse($firstResponse);
        $this->assertCachedResponse($secondResponse);

        $this->assertSameResponse($firstResponse, $secondResponse);
    }

    /** @test */
    public function it_will_fire_an_event_when_responding_without_cache()
    {
        Event::fake();

        $this->get('/random');

        Event::assertDispatched(CacheMissed::class);
    }

    /** @test */
    public function it_will_fire_an_event_when_responding_from_cache()
    {
        Event::fake();

        $this->get('/random');
        $this->get('/random');

        Event::assertDispatched(ResponseCacheHit::class);
    }

    /** @test */
    public function it_will_cache_redirects()
    {
        $firstResponse = $this->get('/redirect');
        $secondResponse = $this->get('/redirect');

        $this->assertRegularResponse($firstResponse);
        $this->assertCachedResponse($secondResponse);

        $this->assertSameResponse($firstResponse, $secondResponse);
    }

    /** @test */
    public function it_will_not_cache_errors()
    {
        $firstResponse = $this->get('/notfound');
        $secondResponse = $this->get('/notfound');

        $this->assertRegularResponse($firstResponse);
        $this->assertRegularResponse($secondResponse);
    }

    /** @test */
    public function it_will_not_cache_a_post_request()
    {
        $firstResponse = $this->call('POST', '/random');
        $secondResponse = $this->call('POST', '/random');

        $this->assertRegularResponse($firstResponse);
        $this->assertRegularResponse($secondResponse);

        $this->assertDifferentResponse($firstResponse, $secondResponse);
    }

    /** @test */
    public function it_can_forget_a_specific_cached_request()
    {
        config()->set('app.url', 'http://spatie.be');

        $firstResponse = $this->get('/random');
        $this->assertRegularResponse($firstResponse);

        ResponseCache::forget('/random');

        $secondResponse = $this->get('/random');
        $this->assertRegularResponse($secondResponse);

        $this->assertDifferentResponse($firstResponse, $secondResponse);
    }

    /** @test */
    public function it_can_forget_several_specific_cached_requests_at_once()
    {
        $firstResponseFirstCall = $this->get('/random/1');
        $this->assertRegularResponse($firstResponseFirstCall);

        $secondResponseFirstCall = $this->get('/random/2');
        $this->assertRegularResponse($secondResponseFirstCall);

        ResponseCache::forget(['/random/1', '/random/2']);

        $firstResponseSecondCall = $this->get('/random/1');
        $this->assertRegularResponse($firstResponseSecondCall);

        $secondResponseSecondCall = $this->get('/random/2');
        $this->assertRegularResponse($secondResponseSecondCall);

        $this->assertDifferentResponse($firstResponseFirstCall, $firstResponseSecondCall);
        $this->assertDifferentResponse($secondResponseFirstCall, $secondResponseSecondCall);
    }

    /** @test */
    public function it_will_cache_responses_for_each_logged_in_user_separately()
    {
        $this->get('/login/1');
        $firstUserFirstCall = $this->get('/');
        $firstUserSecondCall = $this->get('/');
        $this->get('logout');

        $this->get('/login/2');
        $secondUserFirstCall = $this->get('/');
        $secondUserSecondCall = $this->get('/');
        $this->get('logout');

        $this->assertRegularResponse($firstUserFirstCall);
        $this->assertCachedResponse($firstUserSecondCall);

        $this->assertRegularResponse($secondUserFirstCall);
        $this->assertCachedResponse($secondUserSecondCall);

        $this->assertSameResponse($firstUserFirstCall, $firstUserSecondCall);
        $this->assertSameResponse($secondUserFirstCall, $secondUserSecondCall);

        $this->assertDifferentResponse($firstUserFirstCall, $secondUserSecondCall);
        $this->assertDifferentResponse($firstUserSecondCall, $secondUserSecondCall);
    }

    /** @test */
    public function it_will_not_cache_routes_with_the_doNotCacheResponse_middleware()
    {
        $firstResponse = $this->get('/uncacheable');
        $secondResponse = $this->get('/uncacheable');

        $this->assertRegularResponse($firstResponse);
        $this->assertRegularResponse($secondResponse);

        $this->assertDifferentResponse($firstResponse, $secondResponse);
    }

    /** @test */
    public function it_will_not_cache_request_when_the_package_is_not_enable()
    {
        $this->app['config']->set('responsecache.enabled', false);

        $firstResponse = $this->get('/random');
        $secondResponse = $this->get('/random');

        $this->assertRegularResponse($firstResponse);
        $this->assertRegularResponse($secondResponse);

        $this->assertDifferentResponse($firstResponse, $secondResponse);
    }

    /** @test */
    public function it_will_not_serve_cached_requests_when_it_is_disabled_in_the_config_file()
    {
        $firstResponse = $this->get('/random');

        $this->app['config']->set('responsecache.enabled', false);

        $secondResponse = $this->get('/random');

        $this->assertRegularResponse($firstResponse);
        $this->assertRegularResponse($secondResponse);

        $this->assertDifferentResponse($firstResponse, $secondResponse);
    }

    /** @test */
    public function it_will_cache_file_responses()
    {
        $firstResponse = $this->get('/image');
        $secondResponse = $this->get('/image');

        $this->assertRegularResponse($firstResponse);
        $this->assertCachedResponse($secondResponse);

        $this->assertSameResponse($firstResponse, $secondResponse);
    }

    /** @test */
    public function it_wont_cache_if_lifetime_is_0()
    {
        $this->app['config']->set('responsecache.cache_lifetime_in_seconds', 0);

        $firstResponse = $this->get('/');
        $secondResponse = $this->get('/');

        $this->assertRegularResponse($firstResponse);
        $this->assertRegularResponse($secondResponse);
    }

    /** @test */
    public function it_will_cache_response_for_given_lifetime_which_is_defined_as_middleware_parameter()
    {
        // Set default lifetime as 0 to check if it will cache for given lifetime
        $this->app['config']->set('responsecache.cache_lifetime_in_seconds', 0);

        $firstResponse = $this->get('/cache-for-given-lifetime');
        $secondResponse = $this->get('/cache-for-given-lifetime');

        $this->assertRegularResponse($firstResponse);
        $this->assertCachedResponse($secondResponse);
    }

    /** @test */
    public function it_will_reproduce_cache_if_given_lifetime_is_expired()
    {
        // Set default lifetime as 0 to disable middleware that is already pushed to Kernel
        $this->app['config']->set('responsecache.cache_lifetime_in_seconds', 0);

        Carbon::setTestNow(Carbon::now()->subMinutes(6));
        $firstResponse = $this->get('/cache-for-given-lifetime');
        $this->assertRegularResponse($firstResponse);

        $secondResponse = $this->get('/cache-for-given-lifetime');
        $this->assertCachedResponse($secondResponse);

        Carbon::setTestNow();
        $thirdResponse = $this->get('/cache-for-given-lifetime');
        $this->assertRegularResponse($thirdResponse);
    }

    /** @test */
    public function it_can_add_a_cache_time_header()
    {
        $this->app['config']->set('responsecache.add_cache_time_header', true);
        $this->app['config']->set('responsecache.cache_time_header_name', 'X-Cached-At');

        $firstResponse = $this->get('/random');
        $secondResponse = $this->get('/random');

        $this->assertFalse($firstResponse->headers->has('X-Cached-At'));
        $this->assertTrue($secondResponse->headers->has('X-Cached-At'));
        $this->assertInstanceOf(DateTime::class, $secondResponse->headers->getDate('X-Cached-At'));

        $this->assertSameResponse($firstResponse, $secondResponse);
    }
}
