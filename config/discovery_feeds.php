<?php
declare(strict_types=1);

/**
 * Discovery RSS feeds — whitelist-aligned overseas sources with real article URLs.
 * Note: Some feeds (Reuters, AP) use redirect URLs; we now include direct feeds where available.
 * @return list<array{name:string,url:string}>
 */
return [
    // BBC — reliable, direct URLs
    ['name' => 'BBC World', 'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml'],
    ['name' => 'BBC Business', 'url' => 'https://feeds.bbci.co.uk/news/business/rss.xml'],
    ['name' => 'BBC Technology', 'url' => 'https://feeds.bbci.co.uk/news/technology/rss.xml'],
    ['name' => 'BBC Science', 'url' => 'https://feeds.bbci.co.uk/news/science_and_environment/rss.xml'],

    // Guardian — reliable, direct URLs
    ['name' => 'Guardian World', 'url' => 'https://www.theguardian.com/world/rss'],
    ['name' => 'Guardian Business', 'url' => 'https://www.theguardian.com/business/rss'],
    ['name' => 'Guardian Technology', 'url' => 'https://www.theguardian.com/technology/rss'],
    ['name' => 'Guardian Environment', 'url' => 'https://www.theguardian.com/environment/rss'],

    // Al Jazeera — reliable
    ['name' => 'Al Jazeera', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml'],

    // NPR — reliable
    ['name' => 'NPR News', 'url' => 'https://feeds.npr.org/1001/rss.xml'],
    ['name' => 'NPR World', 'url' => 'https://feeds.npr.org/1004/rss.xml'],

    // DW — reliable
    ['name' => 'DW World', 'url' => 'https://rss.dw.com/xml/rss-en-world'],
    ['name' => 'DW Business', 'url' => 'https://rss.dw.com/xml/rss-en-bus'],

    // France24
    ['name' => 'France24', 'url' => 'https://www.france24.com/en/rss'],

    // Reuters — may use redirect URLs, try anyway
    ['name' => 'Reuters World', 'url' => 'https://www.reutersagency.com/feed/?best-regions=global&post_type=best'],
    ['name' => 'Reuters', 'url' => 'https://feeds.reuters.com/reuters/worldNews'],
    ['name' => 'Reuters Business', 'url' => 'https://feeds.reuters.com/reuters/businessNews'],

    // AP News — may use redirect URLs
    ['name' => 'AP News', 'url' => 'https://apnews.com/index.rss'],
    ['name' => 'AP World', 'url' => 'https://apnews.com/world-news.rss'],

    // Science/Tech specialized
    ['name' => 'Nature News', 'url' => 'https://www.nature.com/nature.rss'],
    ['name' => 'Ars Technica', 'url' => 'https://feeds.arstechnica.com/arstechnica/index'],
];
