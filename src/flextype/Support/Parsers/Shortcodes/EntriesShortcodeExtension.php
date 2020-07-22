<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

use Thunder\Shortcode\Shortcode\ShortcodeInterface;

// Shortcode: [entries_fetch id="entry-id" field="field-name" default="default-value"]
$flextype['shortcode']->add('entries_fetch', function (ShortcodeInterface $s) use ($flextype) {
    return array_get($flextype['entries']->fetch($s->getParameter('id')), $s->getParameter('field'), $s->getParameter('default'));
});
