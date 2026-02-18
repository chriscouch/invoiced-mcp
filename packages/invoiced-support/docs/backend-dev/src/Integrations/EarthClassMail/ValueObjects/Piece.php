<?php

namespace App\Integrations\EarthClassMail\ValueObjects;

final class Piece
{
    const MEDIA_TO_IGNORE = ['enclosure'];
    const TAGS_TO_STRIP = ['color', 'bitonal', 'medium', 'large', 'small'];

    /** @var Check[] */
    public array $checks = [];
    /** @var Media[] */
    private array $media = [];

    public function __construct(
        public readonly string $created_at,
    ) {
    }

    /**
     * @param string[] $tags
     */
    public function addMedia(string $url, string $content_type, array $tags): void
    {
        // skip if matching ignored tag
        if (array_intersect($tags, self::MEDIA_TO_IGNORE)) {
            return;
        }

        // preserve ordering for proper hashing
        sort($tags);
        // leave only useful tags
        // we do array values to reset indexing
        $tags = array_values(array_diff($tags, self::TAGS_TO_STRIP));

        // if no tags to identify - we still save the item
        $hash = md5($tags ? (string) (json_encode($tags)) : microtime());
        $this->media[$hash] = new Media($url, $content_type);
    }

    /**
     * @return Media[]
     */
    public function getMedia(): array
    {
        return $this->media;
    }
}
