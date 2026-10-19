<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

use TYPO3\CMS\Core\Html\SanitizerBuilderFactory;

/**
 * Sanitizes remote marketing HTML before it is assigned to Fluid templates.
 */
final class RemoteHtmlSanitizer
{
    public function __construct(
        private readonly SanitizerBuilderFactory $sanitizerBuilderFactory
    ) {}

    public function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return $this->sanitizerBuilderFactory->build('default')->build()->sanitize($html);
    }
}
