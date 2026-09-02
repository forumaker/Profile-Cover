<?php

declare(strict_types=1);

namespace Forumaker\ProfileCover\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Forumaker\ProfileCover\Tests\integration\MakesUploadedCoverFiles;
use Illuminate\Contracts\Filesystem\Factory;

class DeleteCoverTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use MakesUploadedCoverFiles;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('forumaker-profile-cover');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2
                ['id' => 3, 'username' => 'third', 'email' => 'third@machine.local', 'is_email_confirmed' => 1],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMakesUploadedCoverFiles();
        parent::tearDown();
    }

    private function disk()
    {
        return $this->app()->getContainer()->make(Factory::class)->disk('forumaker-profile-cover');
    }

    public function test_member_can_delete_their_own_cover_and_the_file_is_removed_from_disk(): void
    {
        $upload = $this->send(
            $this->request('POST', '/api/users/2/cover', ['authenticatedAs' => 2])
                ->withUploadedFiles(['cover' => $this->uploadedPng(300, 200)])
        );
        $coverPath = json_decode($upload->getBody()->__toString(), true)['data']['attributes']['cover'];
        $this->assertNotEmpty($coverPath);

        $filename = basename(parse_url($coverPath, PHP_URL_PATH));
        $this->assertTrue($this->disk()->exists($filename));

        $response = $this->send(
            $this->request('DELETE', '/api/users/2/cover', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $user = $this->database()->table('users')->find(2);
        $this->assertNull($user->cover);
        $this->assertFalse($this->disk()->exists($filename));
    }

    public function test_member_cannot_delete_someone_elses_cover(): void
    {
        $this->database()->table('users')->where('id', 3)->update(['cover' => 'existing.jpg']);

        $response = $this->send(
            $this->request('DELETE', '/api/users/3/cover', ['authenticatedAs' => 2])
        );

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertSame('existing.jpg', $this->database()->table('users')->find(3)->cover);
    }

    public function test_deleting_a_cover_that_does_not_exist_is_a_harmless_noop(): void
    {
        $response = $this->send(
            $this->request('DELETE', '/api/users/2/cover', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($this->database()->table('users')->find(2)->cover);
    }
}
