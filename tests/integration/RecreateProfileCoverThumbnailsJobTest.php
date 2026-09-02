<?php

declare(strict_types=1);

namespace Forumaker\ProfileCover\Tests\integration;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use Forumaker\ProfileCover\Job\RecreateProfileCoverThumbnailsJob;
use Illuminate\Contracts\Filesystem\Factory;
use Intervention\Image\ImageManager;
use Psr\Log\NullLogger;

/**
 * Exercises the job directly (handle() takes every dependency as a method
 * parameter, so Laravel's job-handling method-injection is easy to replicate
 * here) against the real database and a real Flarum\User\User — no queue
 * worker involved, which is fine: the job's own dispatch/queueing is stock
 * Laravel plumbing, not this extension's logic.
 */
class RecreateProfileCoverThumbnailsJobTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('forumaker-profile-cover');
    }

    private function disk()
    {
        return $this->app()->getContainer()->make(Factory::class)->disk('forumaker-profile-cover');
    }

    private function putCover(int $userId, string $filename, string $contents): void
    {
        $this->database()->table('users')->insert([
            'id' => $userId,
            'username' => "user{$userId}",
            'email' => "user{$userId}@machine.local",
            'is_email_confirmed' => 1,
            'cover' => $filename,
        ]);
        $this->disk()->put($filename, $contents);
    }

    private function runJob(int $thumbnailWidth = 500): void
    {
        $container = $this->app()->getContainer();
        $container->make(SettingsRepositoryInterface::class)->set('forumaker-profile-cover.thumbnail_width', $thumbnailWidth);

        (new RecreateProfileCoverThumbnailsJob())->handle(
            $container->make(Factory::class),
            $container->make(ImageManager::class),
            $container->make(SettingsRepositoryInterface::class),
            new NullLogger()
        );
    }

    private function validJpegBytes(int $width = 40, int $height = 30): string
    {
        return $this->app()->getContainer()->make(ImageManager::class)->create($width, $height)->toJpg()->toString();
    }

    /**
     * Regression test: User::...->chunk(100, ...) used to have no orderBy(),
     * risking skipped/duplicate rows if the table changed mid-run. 105 users
     * (just over one 100-row chunk boundary) all getting a thumbnail proves
     * chunking now covers every row across the boundary.
     */
    public function test_every_user_across_a_chunk_boundary_gets_a_thumbnail(): void
    {
        // Base id 100: the base test install already seeds an admin user
        // with id 1.
        $bytes = $this->validJpegBytes();
        for ($i = 100; $i <= 204; $i++) {
            $this->putCover($i, "cover{$i}.jpg", $bytes);
        }

        $this->runJob();

        for ($i = 100; $i <= 204; $i++) {
            $this->assertTrue($this->disk()->exists("thumbnails/cover{$i}.jpg"), "User {$i} is missing a thumbnail");
        }
    }

    public function test_gif_covers_are_skipped(): void
    {
        $this->putCover(100, 'cover1.gif', 'not-decoded-anyway');

        $this->runJob();

        $this->assertFalse($this->disk()->exists('thumbnails/cover1.gif'));
    }

    public function test_a_cover_missing_from_disk_is_skipped_without_error(): void
    {
        $this->database()->table('users')->insert([
            'id' => 100, 'username' => 'user1', 'email' => 'user1@machine.local',
            'is_email_confirmed' => 1, 'cover' => 'missing.jpg',
        ]);

        $this->runJob();

        $this->assertFalse($this->disk()->exists('thumbnails/missing.jpg'));
    }

    /**
     * Regression test for the audit finding that a cover already on disk was
     * never guaranteed to pass today's dimension limit (it may predate the
     * check). The job now applies the same CoverImageLimits::
     * MAX_IMAGE_DIMENSION guard as a fresh upload before decoding.
     */
    public function test_an_oversized_cover_on_disk_is_skipped_instead_of_crashing_the_worker(): void
    {
        $oversized = $this->app()->getContainer()->make(ImageManager::class)
            ->create(10001, 5)
            ->toJpg()
            ->toString();
        $this->putCover(100, 'huge.jpg', $oversized);

        $this->runJob();

        $this->assertFalse($this->disk()->exists('thumbnails/huge.jpg'));
    }

    /**
     * Regression test: forumaker-profile-cover.thumbnail_width = 0 used to
     * reach Image::scale(0), which throws — every single user's thumbnail
     * would silently fail. The job now floors the configured width at 1px
     * instead of leaving every run broken.
     */
    public function test_a_zeroed_thumbnail_width_setting_does_not_break_every_thumbnail(): void
    {
        $this->putCover(100, 'cover1.jpg', $this->validJpegBytes());

        $this->runJob(thumbnailWidth: 0);

        $this->assertTrue($this->disk()->exists('thumbnails/cover1.jpg'));
    }
}
