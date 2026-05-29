<?php

use Flarum\Database\Migration;

return Migration::addColumns('discussions', [
    'linkposter_url' => ['string', 'length' => 255, 'nullable' => true],
    'linkposter_description' => ['string', 'length' => 255, 'nullable' => true],
    'linkposter_thumbnail' => ['string', 'length' => 255, 'nullable' => true],
]);
