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
        $tagData = Arr::get($event->data, 'relationships.tags.data', []);

        if (!empty($tagData)) {
            foreach ($tagData as $tagNode) {
                $tagId = $tagNode['id'];
                $tag = Tag::find($tagId);

                if ($tag && (bool) $this->settings->get("linkposter.tags.{$tag->slug}")) {
                    $isAnyTagEnabled = true;
                    break;
                }
            }
        } else if ($discussion->exists) {
            foreach ($discussion->tags as $tag) {
                if ((bool) $this->settings->get("linkposter.tags.{$tag->slug}")) {
                    $isAnyTagEnabled = true;
                    break;
                }
            }
        }

        // Om taggen inte är aktiverad för Linkposter, avbryt och fortsätt som vanligt
        if (!$isAnyTagEnabled) {
            return;
        }

        $title = Arr::get($data, 'attributes.title');

        if ($title && filter_var($title, FILTER_VALIDATE_URL)) {
            $url = $title;

            try {
                $webscraper = new PHPScraper();
                $webscraper->setConfig([
                    'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0'
                ]);
                $webscraper->go($url);

                // Hämta riktig titel från webbplatsen
                $fetchedTitle = $webscraper->title;

                if (!empty($fetchedTitle)) {
                    $discussion->title = $fetchedTitle;
                }

                $discussion->linkposter_description = $webscraper->description();
                $discussion->linkposter_url = $url;
                $image_url = $webscraper->image() ?? ($webscraper->openGraph['og:image'] ?? null);

                if ($image_url) {
                    $clean_name = basename(parse_url($image_url, PHP_URL_PATH));
                    if (!preg_match('/\.jpg$/i', $clean_name)) {
                        $clean_name .= '.jpg';
                    }
                    $filename = time() . '_' . $clean_name;

                    try {
                        $client = new Client(['timeout' => 5.0]);
                        $response = $client->get($image_url);
                        $image_content = $response->getBody()->getContents();
                        $manager = new ImageManager(new GdDriver());
                        $image = $manager->read($image_content);
                        $thumbnail = $image->cover(1024, 1024);
                        $thumbnail_encoded = $thumbnail->toJpeg()->toString();

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
                // Om skrapningen misslyckas kan du välja att kasta ett fel eller låta URL:en vara kvar som titel
                throw new ValidationException([
                    'title' => 'Kunde inte hämta information från länken. Kontrollera att URL:en är korrekt.'
                ]);
            }
        }
    }
}
