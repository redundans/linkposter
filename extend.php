<?php

/*
 * This file is part of redundans/linkposter.
 *
 * Copyright (c) 2026 redundans.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace redundans\Linkposter;

use Flarum\Extend;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Discussion\Discussion;
use Illuminate\Support\Arr;
use Flarum\Post\Event\Saving;
use Flarum\Api\Schema;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\Discussion\Search\DiscussionSearcher;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ApiResource(DiscussionResource::class))
    ->fields(fn () => [
        // Exponera attributet i API-responsen
        'linkposterUrl' => Schema\Str::make('linkposter_url')
            ->get(fn ($discussion) => $discussion->linkposter_url),
        'linkposterDescription' => Schema\Str::make('linkposter_description')
            ->get(fn ($discussion) => $discussion->linkposter_description),
        'linkposterThumbnail' => Schema\Str::make('linkposter_thumbnail')
            ->get(fn ($discussion) => $discussion->linkposter_thumbnail),
    ]),

    (new Extend\Event)
        ->subscribe(Listener\LinkposterEventSubscriber::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addFilter(DiscussionSearcher::class, Filter\LinkposterUrlFilter::class),

    (new Extend\Model(Discussion::class))
        ->cast('linkposter_url', 'string')
        ->cast('linkposter_description', 'string')
        ->cast('linkposter_thumbnail', 'string'),
];
