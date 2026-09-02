<?php

declare(strict_types=1);

namespace Forumaker\ProfileCover\Tests\unit;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Forumaker\ProfileCover\CoverUploader;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;

/**
 * CoverUploader talks to the filesystem and to a Flarum\User\User model, but
 * needs neither a real disk nor a database: Flarum\User\User's `cover`/
 * `afterSave()` bookkeeping is plain in-memory Eloquent attribute/callback
 * storage (no query happens unless you call save()), and an in-memory
 * Flysystem adapter is a real, behaviorally faithful Filesystem — not a
 * hand-rolled stub — so these are genuine unit tests, not integration tests
 * in disguise.
 */
class CoverUploaderTest extends TestCase
{
    private function filesystem(): Filesystem
    {
        $adapter = new InMemoryFilesystemAdapter();

        return new FilesystemAdapter(new Flysystem($adapter), $adapter);
    }

    private function uploader(Filesystem $disk, array $settings = []): CoverUploader
    {
        $factory = new class($disk) implements Factory {
            public function __construct(private Filesystem $disk)
            {
            }

            public function disk($name = null)
            {
                return $this->disk;
            }
        };

        $settingsRepo = new class($settings) implements SettingsRepositoryInterface {
            public function __construct(private array $values)
            {
            }

            public function all(): array
            {
                return $this->values;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }

            public function delete(string $keyLike): void
            {
                unset($this->values[$keyLike]);
            }
        };

        return new CoverUploader($factory, $settingsRepo);
    }

    /** A small solid-color image, cheap to generate and to JPEG-encode. */
    private function testImage(int $width = 100, int $height = 80): \Intervention\Image\Image
    {
        return ImageManager::gd()->create($width, $height);
    }

    private function runAfterSaveCallbacks(User $user): void
    {
        foreach ($user->releaseAfterSaveCallbacks() as $callback) {
            $callback();
        }
    }

    // ── upload() ─────────────────────────────────────────────────────────

    public function test_upload_stores_a_jpg_and_sets_a_random_cover_path(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk);
        $user = new User();

        $uploader->upload($user, $this->testImage());

        $this->assertNotEmpty($user->cover);
        $this->assertStringEndsWith('.jpg', $user->cover);
        $this->assertTrue($disk->exists($user->cover));
    }

    public function test_upload_does_not_create_a_thumbnail_when_disabled(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk, ['forumaker-profile-cover.thumbnails' => 0]);
        $user = new User();

        $uploader->upload($user, $this->testImage());

        $this->assertFalse($disk->exists('thumbnails/' . $user->cover));
    }

    public function test_upload_creates_a_thumbnail_when_enabled(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk, [
            'forumaker-profile-cover.thumbnails'      => 1,
            'forumaker-profile-cover.thumbnail_width' => 50,
        ]);
        $user = new User();

        $uploader->upload($user, $this->testImage(200, 160));

        $this->assertTrue($disk->exists('thumbnails/' . $user->cover));
    }

    /**
     * Regression test: an admin clearing/zeroing forumaker-profile-cover.
     * thumbnail_width used to reach Image::scale(0), which throws — every
     * upload would fail while thumbnails stayed enabled. The uploader now
     * floors the configured width at 1px.
     */
    public function test_upload_floors_thumbnail_width_at_one_pixel_instead_of_throwing(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk, [
            'forumaker-profile-cover.thumbnails'      => 1,
            'forumaker-profile-cover.thumbnail_width' => 0,
        ]);
        $user = new User();

        $uploader->upload($user, $this->testImage());

        $this->assertTrue($disk->exists('thumbnails/' . $user->cover));
    }

    public function test_upload_replaces_a_previous_cover(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk);
        $user = new User();

        $uploader->upload($user, $this->testImage());
        $firstCover = $user->cover;
        $this->runAfterSaveCallbacks($user);

        $uploader->upload($user, $this->testImage());

        $this->assertNotSame($firstCover, $user->cover);
        $this->assertTrue($disk->exists($user->cover));
    }

    // ── uploadGif() ──────────────────────────────────────────────────────

    public function test_upload_gif_stores_the_raw_stream_with_a_gif_extension(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk);
        $user = new User();

        // Not a valid GIF — uploadGif() never decodes it, just streams the
        // bytes straight to disk, so any content proves the byte-for-byte
        // copy is correct.
        $gifBytes = 'GIF89a' . str_repeat("\0", 10);
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $gifBytes);
        rewind($resource);

        $file = new UploadedFile(new Stream($resource), strlen($gifBytes), UPLOAD_ERR_OK, 'x.gif', 'image/gif');

        $uploader->uploadGif($user, $file);

        $this->assertStringEndsWith('.gif', $user->cover);
        $this->assertSame($gifBytes, $disk->get($user->cover));
        // Thumbnails are never generated for GIFs (see
        // UserResourceFields::thumbnailUrl(), which serves the full GIF as
        // its own "thumbnail").
        $this->assertFalse($disk->exists('thumbnails/' . $user->cover));
    }

    // ── remove() ─────────────────────────────────────────────────────────

    public function test_remove_is_a_noop_for_a_user_with_no_cover(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk);
        $user = new User();

        $uploader->remove($user);

        $this->assertNull($user->cover);
        $this->assertSame([], $user->releaseAfterSaveCallbacks());
    }

    public function test_remove_defers_file_deletion_until_after_save(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk);
        $user = new User();
        $uploader->upload($user, $this->testImage());
        $this->runAfterSaveCallbacks($user); // commit the upload's own deferred (no-op, nothing to remove yet) callback
        $coverPath = $user->cover;

        $uploader->remove($user);

        // cover is cleared immediately...
        $this->assertNull($user->cover);
        // ...but the files are still there until the callback actually runs,
        // so a request that clears $user->cover and then fails to save()
        // doesn't lose the file it never committed to deleting.
        $this->assertTrue($disk->exists($coverPath));

        $this->runAfterSaveCallbacks($user);

        $this->assertFalse($disk->exists($coverPath));
    }

    public function test_remove_also_deletes_the_thumbnail(): void
    {
        $disk = $this->filesystem();
        $uploader = $this->uploader($disk, [
            'forumaker-profile-cover.thumbnails'      => 1,
            'forumaker-profile-cover.thumbnail_width' => 50,
        ]);
        $user = new User();
        $uploader->upload($user, $this->testImage(200, 160));
        $coverPath = $user->cover;
        $thumbnailPath = 'thumbnails/' . $coverPath;
        $this->assertTrue($disk->exists($thumbnailPath));

        $uploader->remove($user);
        $this->runAfterSaveCallbacks($user);

        $this->assertFalse($disk->exists($coverPath));
        $this->assertFalse($disk->exists($thumbnailPath));
    }
}
