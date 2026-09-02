<?php

declare(strict_types=1);

namespace Forumaker\ProfileCover\Tests\integration;

use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Builds real, on-disk temp files for cover-upload requests. A file path
 * (rather than an in-memory stream) matters here: the code under test reads
 * dimensions via getimagesize($path) and guesses the mime type via
 * MimeTypes::guessMimeType($path), both of which need a real path, not just
 * bytes in memory.
 */
trait MakesUploadedCoverFiles
{
    /** @var string[] temp files to clean up in tearDown() */
    private array $tempFiles = [];

    protected function tearDownMakesUploadedCoverFiles(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function uploadedPng(int $width, int $height, string $clientFilename = 'cover.png'): UploadedFileInterface
    {
        $path = tempnam(sys_get_temp_dir(), 'cover-test-') . '.png';
        $this->tempFiles[] = $path;

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, filesize($path), UPLOAD_ERR_OK, $clientFilename, 'image/png');
    }

    private function uploadedGif(string $clientFilename = 'cover.gif'): UploadedFileInterface
    {
        $path = tempnam(sys_get_temp_dir(), 'cover-test-') . '.gif';
        $this->tempFiles[] = $path;

        $image = imagecreatetruecolor(20, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 100, 50));
        imagegif($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, filesize($path), UPLOAD_ERR_OK, $clientFilename, 'image/gif');
    }

    private function uploadedNotAnImage(string $clientFilename = 'cover.txt'): UploadedFileInterface
    {
        $path = tempnam(sys_get_temp_dir(), 'cover-test-') . '.txt';
        $this->tempFiles[] = $path;

        file_put_contents($path, 'not an image');

        return new UploadedFile($path, filesize($path), UPLOAD_ERR_OK, $clientFilename, 'text/plain');
    }
}
