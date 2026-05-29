<?php

namespace redundans\Linkposter\Listener;

use PostEventSubscriber;
use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\Tags\Tag;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Spekulatius\PHPScraper\PHPScraper;

class LinkposterEventSubscriber
{
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function subscribe($events)
    {
        $events->listen(Saving::class, [$this, 'handleSaving']);
    }

    public function handleSaving(Saving $event)
    {
        $discussion = $event->discussion;
        $tags = $discussion->tags;
        $tag = $event->tag;
        $data = $event->data;
        $isAnyTagEnabled = false;

        $tagData = Arr::get($event->data, 'relationships.tags.data', []);
        foreach ($tagData as $tagNode) {
            $tagId = $tagNode['id'];
            $tag = Tag::find($tagId);

            if ($tag && (bool) $this->settings->get("linkposter.tags.{$tag->slug}")) {
                $isAnyTagEnabled = true;
                break;
            }
        }

        if ($isAnyTagEnabled) {
            if (isset($data['attributes']['title'])) {
                $urlPattern = '/^(https?:\/\/[^\s]+)/';
                $title = $data['attributes']['title'];
                preg_match($urlPattern, $title, $matches);

                if (!empty($matches)) {
                    $url = $matches[0];
                    $link = new PHPScraper();
                    $link->go($url);

                    $discussion->title = $link->title;
                    $discussion->linkposter_description = $link->description();
                    $discussion->linkposter_thumbnail = $link->image() ?? $link->openGraph['og:image'];
                    $discussion->linkposter_url = $matches[0];
                } else {
                    throw new ValidationException([
                        'discussion' => "Titeln måste vara en URL och inte '{$title}'."
                    ]);
                }
            }
        }
    }
}
