<?php

namespace Forumaker\ProfileCover;

/**
 * Shared between UploadCoverHandler (checked against the freshly uploaded
 * file) and RecreateProfileCoverThumbnailsJob (checked against covers
 * already on disk) so both guards enforce the same limit before ever
 * decoding an image into memory.
 */
final class CoverImageLimits
{
    /**
     * Maximum allowed width/height (in pixels) for a cover image. Guards
     * against decoding pathologically large images into memory (a
     * maximally-compressed image can have a huge pixel buffer despite a
     * small file size).
     */
    public const MAX_IMAGE_DIMENSION = 10000;
}
