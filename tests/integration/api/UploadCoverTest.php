<?php

declare(strict_types=1);

namespace Forumaker\ProfileCover\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Forumaker\ProfileCover\Tests\integration\MakesUploadedCoverFiles;
use Illuminate\Contracts\Filesystem\Factory;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\UploadedFile;

class UploadCoverTest extends TestCase
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

    private function uploadCover(int $userId, $file, ?int $authenticatedAs = 2, ?array $crop = null)
    {
        $options = ['authenticatedAs' => $authenticatedAs];
        if ($authenticatedAs === null) {
            unset($options['authenticatedAs']);
        }

        $request = $this->request('POST', "/api/users/{$userId}/cover", $options)
            ->withUploadedFiles(['cover' => $file]);

        if ($crop !== null) {
            $request = $request->withParsedBody(['crop' => json_encode($crop)]);
        }

        return $this->send($request);
    }

    public function test_member_can_upload_their_own_cover(): void
    {
        $response = $this->uploadCover(2, $this->uploadedPng(300, 200));

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertNotEmpty($body['data']['attributes']['cover']);

        $user = $this->database()->table('users')->find(2);
        $this->assertNotEmpty($user->cover);
        $this->assertStringEndsWith('.webp', $user->cover);
    }

    public function test_upload_crops_the_selected_pixels_before_encoding_and_centres_the_new_cover(): void
    {
        $this->database()->table('users')->where('id', 2)->update(['cover_position' => 90]);
        $path = tempnam(sys_get_temp_dir(), 'cover-crop-');

        try {
            $image = imagecreatetruecolor(300, 200);
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
            imagefilledrectangle($image, 150, 90, 289, 149, imagecolorallocate($image, 0, 200, 0));
            imagepng($image, $path);
            $file = new UploadedFile($path, filesize($path), UPLOAD_ERR_OK, 'cover.png', 'image/png');

            $response = $this->uploadCover(2, $file, crop: ['x' => 150, 'y' => 90, 'width' => 140, 'height' => 60]);
        } finally {
            unlink($path);
        }

        $this->assertSame(200, $response->getStatusCode());
        $user = $this->database()->table('users')->find(2);
        $disk = $this->app()->getContainer()->make(Factory::class)->disk('forumaker-profile-cover');
        $output = $disk->get($user->cover);
        $this->assertSame([140, 60, IMAGETYPE_WEBP], array_slice(getimagesizefromstring($output), 0, 3));
        $pixel = imagecolorat(imagecreatefromstring($output), 70, 30);
        $this->assertGreaterThan(170, ($pixel >> 8) & 255);
        $this->assertLessThan(30, ($pixel >> 16) & 255);
        $this->assertSame(50, $user->cover_position);
    }

    public function test_crop_outside_the_image_leaves_the_current_cover_in_place(): void
    {
        $this->uploadCover(2, $this->uploadedPng(300, 200));
        $oldCover = $this->database()->table('users')->find(2)->cover;

        $response = $this->uploadCover(2, $this->uploadedPng(300, 200), crop: ['x' => 200, 'y' => 90, 'width' => 140, 'height' => 60]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($oldCover, $this->database()->table('users')->find(2)->cover);
    }

    public function test_cropping_a_gif_keeps_its_animation(): void
    {
        $manager = $this->app()->getContainer()->make(ImageManager::class);
        $animation = $manager->animate(function ($animation) use ($manager) {
            $animation->add($manager->create(28, 20)->fill('red'), 0.2);
            $animation->add($manager->create(28, 20)->fill('blue'), 0.2);
        });
        $path = tempnam(sys_get_temp_dir(), 'cover-gif-crop-');

        try {
            $animation->toGif()->save($path);
            $file = new UploadedFile($path, filesize($path), UPLOAD_ERR_OK, 'cover.gif', 'image/gif');
            $response = $this->uploadCover(2, $file, crop: ['x' => 3, 'y' => 4, 'width' => 21, 'height' => 9]);
        } finally {
            unlink($path);
        }

        $this->assertSame(200, $response->getStatusCode());
        $user = $this->database()->table('users')->find(2);
        $disk = $this->app()->getContainer()->make(Factory::class)->disk('forumaker-profile-cover');
        $output = $disk->get($user->cover);
        $this->assertSame([21, 9, IMAGETYPE_GIF], array_slice(getimagesizefromstring($output), 0, 3));
        $this->assertTrue($manager->read($output)->isAnimated());
    }

    public function test_member_cannot_upload_a_cover_for_someone_else(): void
    {
        $response = $this->uploadCover(3, $this->uploadedPng(300, 200));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNull($this->database()->table('users')->find(3)->cover);
    }

    public function test_a_user_with_edit_permission_can_upload_a_cover_for_another_user(): void
    {
        // Group 4 is Flarum's stock Moderator group — core doesn't grant it
        // 'user.edit' by default, so grant it explicitly to simulate a forum
        // that's configured to let moderators edit member profiles.
        $this->database()->table('group_user')->insert(['user_id' => 2, 'group_id' => 4]);
        $this->database()->table('group_permission')->insert(['group_id' => 4, 'permission' => 'user.edit']);

        $response = $this->uploadCover(3, $this->uploadedPng(300, 200), authenticatedAs: 2);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($this->database()->table('users')->find(3)->cover);
    }

    public function test_guest_cannot_upload_a_cover(): void
    {
        // No 'authenticatedAs' means no CSRF token, so this trips
        // CheckCsrfToken (400) before the permission check is ever reached —
        // see SendFriendRequestTest::test_guest_cannot_send_a_friend_request
        // in the Friendship extension for the same pattern.
        $response = $this->uploadCover(2, $this->uploadedPng(300, 200), authenticatedAs: null);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_cannot_upload_without_the_setProfileCover_permission(): void
    {
        $this->database()->table('group_permission')->where('permission', 'setProfileCover')->delete();

        $response = $this->uploadCover(2, $this->uploadedPng(300, 200));

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Regression test for the audit finding that assertDimensionsWithinLimit()
     * lives in UploadCoverHandler (src/Command/UploadCoverHandler.php) and
     * enforces CoverImageLimits::MAX_IMAGE_DIMENSION (10000px) before ever
     * decoding the image into memory.
     */
    public function test_an_oversized_image_is_rejected(): void
    {
        $response = $this->uploadCover(2, $this->uploadedPng(10001, 5));

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNull($this->database()->table('users')->find(2)->cover);
    }

    public function test_a_gif_is_stored_without_going_through_the_dimension_or_decode_path(): void
    {
        $response = $this->uploadCover(2, $this->uploadedGif());

        $this->assertEquals(200, $response->getStatusCode());

        $user = $this->database()->table('users')->find(2);
        $this->assertStringEndsWith('.gif', $user->cover);
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $response = $this->uploadCover(2, $this->uploadedNotAnImage());

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNull($this->database()->table('users')->find(2)->cover);
    }

    public function test_cover_position_is_clamped_to_0_100_via_the_api(): void
    {
        $this->uploadCover(2, $this->uploadedPng(300, 200));

        $response = $this->send(
            $this->request('PATCH', '/api/users/2', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['type' => 'users', 'id' => '2', 'attributes' => ['cover_position' => 500]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(100, $this->database()->table('users')->find(2)->cover_position);
    }
}
