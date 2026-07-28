<?php

namespace iti\craftitiplugin\seo;

final class SeoData
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonicalUrl,
        public readonly array $structuredData,
    ) {
    }
}
