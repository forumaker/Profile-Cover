<?php

declare(strict_types=1);

namespace Forumaker\ProfileCover\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Illuminate\Contracts\Filesystem\Factory;

class ThumbnailActionsControllerTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('forumaker-profile-cover');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2
            ],
        ]);
    }

    private function disk()
    {
        return $this->app()->getContainer()->make(Factory::class)->disk('forumaker-profile-cover');
    }

    /**
     * Unlike the database, the configured disk isn't wrapped in a
     * per-test transaction/rollback — files written by any other test in
     * this suite (or a previous run) persist on it. deleteThumbnails()
     * deletes indiscriminately, so pin down exactly what's there first.
     */
    private function clearThumbnails(): void
    {
        foreach ($this->disk()->files('thumbnails') as $file) {
            $this->disk()->delete($file);
        }
    }

    public function test_a_normal_member_cannot_trigger_thumbnail_actions(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/forumaker-profile-cover/thumbnails/delete', ['authenticatedAs' => 2])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_a_guest_cannot_trigger_thumbnail_actions(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/forumaker-profile-cover/thumbnails/delete')
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_an_unknown_action_returns_400(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/forumaker-profile-cover/thumbnails/bogus', ['authenticatedAs' => 1])
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_admin_can_delete_all_thumbnail_files(): void
    {
        $this->clearThumbnails();
        $this->disk()->put('thumbnails/a.jpg', 'x');
        $this->disk()->put('thumbnails/b.jpg', 'x');

        $response = $this->send(
            $this->request('POST', '/api/forumaker-profile-cover/thumbnails/delete', ['authenticatedAs' => 1])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(2, $body['deleted']);
        $this->assertEquals(0, $body['failed']);
        $this->assertFalse($this->disk()->exists('thumbnails/a.jpg'));
        $this->assertFalse($this->disk()->exists('thumbnails/b.jpg'));
    }

    public function test_admin_recreate_action_queues_a_job(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/forumaker-profile-cover/thumbnails/recreate', ['authenticatedAs' => 1])
        );

        $this->assertEquals(202, $response->getStatusCode());
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertTrue($body['queued']);
    }
}
