<?php
declare(strict_types=1);

/**
 * Discovery RSS feeds — whitelist-aligned overseas sources with real article URLs.
 * @return list<array{name:string,url:string}>
 */
return [
    ['name' => 'Reuters', 'url' => 'https://feeds.reuters.com/reuters/worldNews'],
    ['name' => 'Reuters Business', 'url' => 'https://feeds.reuters.com/reuters/businessNews'],
    ['name' => 'AP News', 'url' => 'https://apnews.com/index.rss'],
    ['name' => 'BBC World', 'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml'],
    ['name' => 'BBC Business', 'url' => 'https://feeds.bbci.co.uk/news/business/rss.xml'],
    ['name' => 'BBC Technology', 'url' => 'https://feeds.bbci.co.uk/news/technology/rss.xml'],
    ['name' => 'NPR News', 'url' => 'https://feeds.npr.org/1001/rss.xml'],
    ['name' => 'Guardian World', 'url' => 'https://www.theguardian.com/world/rss'],
    ['name' => 'Guardian Business', 'url' => 'https://www.theguardian.com/uk/business/rss'],
    ['name' => 'Al Jazeera', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml'],
    ['name' => 'DW News', 'url' => 'https://rss.dw.com/xml/rss-en-world'],
    ['name' => 'France24', 'url' => 'https://www.france24.com/en/rss'],
];
