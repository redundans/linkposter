<?php

namespace redundans\Linkposter\Listener;

use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\Tags\Tag;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Spekulatius\PHPScraper\PHPScraper;

class LinkposterEventSubscriber
{
    protected $assetsDisk;

    public function __construct(FilesystemFactory $filesystem, SettingsRepositoryInterface $settings)
    {
        $this->assetsDisk = $filesystem->disk('flarum-assets');
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

                    $discussion->title = $link->title ?? 'Link';
                    $discussion->linkposter_description = $link->description();
                    $discussion->linkposter_url = $url;
                    $image_url = $link->image() ?? ($link->openGraph['og:image'] ?? null);

                    if ($image_url) {
                        // Skapa ett säkert lokalt filnamn baserat på tidsstämpel och URL-namn
                        $clean_name = basename(parse_url($image_url, PHP_URL_PATH));
                        $filename = time() . '_' . (preg_replace('/[^a-zA-Z0-9_.-]/', '', $clean_name) ?: 'thumb.jpg');

                        try {
                            // 3. Ladda ner bilden från internet via Guzzle
                            $client = new Client(['timeout' => 5.0]);
                            $response = $client->get($image_url);
                            $image_content = $response->getBody()->getContents();
                            $manager = new ImageManager(new GdDriver());
                            $image = $manager->read($image_content);
                            $thumbnail = $image->cover(150, 150);
                            $thumbnail_encoded = $thumbnail->toJpeg()->toString();
                            $this->assetsDisk->put("linkposter/{$filename}", $thumbnail_encoded);
                            $discussion->linkposter_thumbnail = $filename;
                        } catch (\Exception $e) {
                            resolve('log')->error('Linkposter downloading of thumbnail did not succeed: ' . $e->getMessage());
                            $discussion->linkposter_thumbnail = null;
                        }
                    } else {
                        $discussion->linkposter_thumbnail = null;
                    }
                } else {
                    throw new ValidationException([
                        'discussion' => "The title must be an URL: '{$title}'."
                    ]);
                }
            }
        }
    }
}
