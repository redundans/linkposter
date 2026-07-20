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
    protected $settings;

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
        $data = $event->data;
        $isAnyTagEnabled = false;

        // 1. Kontrollera taggar (både nya taggar i anropet och redan sparade taggar om det är en uppdatering)
        $tagData = Arr::get($event->data, 'relationships.tags.data', []);

        if (!empty($tagData)) {
            // Kolla taggarna som skickas med i nuvarande API-begäran
            foreach ($tagData as $tagNode) {
                $tagId = $tagNode['id'];
                $tag = Tag::find($tagId);

                if ($tag && (bool) $this->settings->get("linkposter.tags.{$tag->slug}")) {
                    $isAnyTagEnabled = true;
                    break;
                }
            }
        } else if ($discussion->exists) {
            // Om inga nya taggar skickades med, kolla trådens befintliga taggar (vid uppdatering)
            foreach ($discussion->tags as $tag) {
                if ((bool) $this->settings->get("linkposter.tags.{$tag->slug}")) {
                    $isAnyTagEnabled = true;
                    break;
                }
            }
        }

        // 2. Om rätt tagg är aktiv, kör logiken
        if ($isAnyTagEnabled) {
            $url = null;
            $urlPattern = '/^(https?:\/\/[^\s]+)/';

            // Kontrollera om en ny URL skickas med i titeln
            if (isset($data['attributes']['title'])) {
                $title = $data['attributes']['title'];
                preg_match($urlPattern, $title, $matches);
                if (!empty($matches)) {
                    $url = $matches[0];
                } else if (!$discussion->exists) {
                    // Om det är en helt ny tråd MÅSTE titeln vara en URL
                    throw new ValidationException([
                        'discussion' => "The title must be an URL: '{$title}'."
                    ]);
                }
            }

            // Om ingen ny URL skickades i titeln vid en uppdatering, använd den som redan finns sparad
            if (!$url && $discussion->exists && $discussion->linkposter_url) {
                $url = $discussion->linkposter_url;
            }

            // 3. Om vi har en URL (ny eller befintlig), hämta/uppdatera datan
            if ($url) {
                try {
                    $link = new PHPScraper();
                    $link->go($url);

                    // Uppdatera bara titeln om den faktiskt skickades med som en URL i anropet
                    if (isset($data['attributes']['title']) && preg_match($urlPattern, $data['attributes']['title'])) {
                        $discussion->title = $link->title ?? $link->openGraph['og:title'] ?? 'Missing title';
                    }

                    $discussion->linkposter_description = $link->description();
                    $discussion->linkposter_url = $url;
                    $image_url = $link->image() ?? ($link->openGraph['og:image'] ?? null);

                    if ($image_url) {
                        $clean_name = basename(parse_url($image_url, PHP_URL_PATH));
                        $filename = time() . '_' . (preg_replace('/[^a-zA-Z0-9_.-]/', '', $clean_name) ?: 'thumb.jpg');

                        try {
                            $client = new Client(['timeout' => 5.0]);
                            $response = $client->get($image_url);
                            $image_content = $response->getBody()->getContents();
                            $manager = new ImageManager(new GdDriver());
                            $image = $manager->read($image_content);
                            $thumbnail = $image->cover(150, 150);
                            $thumbnail_encoded = $thumbnail->toJpeg()->toString();

                            // Ta bort den gamla tumnagelbilden om den finns för att inte skräpa ner
                            if ($discussion->linkposter_thumbnail && $this->assetsDisk->has("linkposter/{$discussion->linkposter_thumbnail}")) {
                                $this->assetsDisk->delete("linkposter/{$discussion->linkposter_thumbnail}");
                            }

                            $this->assetsDisk->put("linkposter/{$filename}", $thumbnail_encoded);
                            $discussion->linkposter_thumbnail = $filename;
                        } catch (\Exception $e) {
                            resolve('log')->error('Linkposter downloading of thumbnail did not succeed: ' . $e->getMessage());
                            // Behåll den gamla bilden om den nya nedladdningen misslyckas vid en uppdatering
                            if (!$discussion->exists) {
                                $discussion->linkposter_thumbnail = null;
                            }
                        }
                    } else if (!$discussion->exists) {
                        $discussion->linkposter_thumbnail = null;
                    }
                } catch (\Exception $e) {
                    // Logga om PHPScraper misslyckas med att läsa webbplatsen vid uppdatering
                    resolve('log')->error('Linkposter scraper failed: ' . $e->getMessage());
                }
            }
        }
    }
}
