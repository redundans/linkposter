<?php

namespace redundans\Linkposter\Filter;

use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;

class LinkposterUrlFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'linkposter_url';
    }

    // Ändrad för att matcha Flarum 2.0:s exakta krav (array|string samt : void)
    public function filter(SearchState $state, array|string $value, bool $negate): void
    {
        // Om värdet skickas som en array tar vi första värdet, annars strängen direkt
        $searchValue = is_array($value) ? head($value) : $value;

        $state->getQuery()->where('linkposter_url', $negate ? '!=' : '=', $searchValue);
    }
}
