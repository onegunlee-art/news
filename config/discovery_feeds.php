<?php
declare(strict_types=1);

/**
 * Discovery RSS feeds — verified working sources (probed 2026-08-01).
 * Only includes feeds that actually return valid RSS/Atom with items.
 * @return list<array{name:string,url:string}>
 */
return [
    // === Major News Outlets (verified working) ===
    
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

    // Al Jazeera
    ['name' => 'Al Jazeera', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml'],

    // NPR
    ['name' => 'NPR News', 'url' => 'https://feeds.npr.org/1001/rss.xml'],
    ['name' => 'NPR World', 'url' => 'https://feeds.npr.org/1004/rss.xml'],

    // DW
    ['name' => 'DW World', 'url' => 'https://rss.dw.com/xml/rss-en-world'],
    ['name' => 'DW Business', 'url' => 'https://rss.dw.com/xml/rss-en-bus'],

    // France24
    ['name' => 'France24', 'url' => 'https://www.france24.com/en/rss'],

    // Ars Technica (tech)
    ['name' => 'Ars Technica', 'url' => 'https://feeds.arstechnica.com/arstechnica/index'],

    // === Government & Central Banks (verified working via probe) ===
    
    // US Government
    ['name' => 'State Dept', 'url' => 'https://www.state.gov/rss-feed/press-releases/feed/'],
    ['name' => 'Fed Reserve', 'url' => 'https://www.federalreserve.gov/feeds/press_all.xml'],

    // International Organizations
    ['name' => 'UN News', 'url' => 'https://news.un.org/feed/subscribe/en/news/all/rss.xml'],
    ['name' => 'EU Newsroom', 'url' => 'https://ec.europa.eu/commission/presscorner/api/rss'],
    ['name' => 'WHO News', 'url' => 'https://www.who.int/rss-feeds/news-english.xml'],
    ['name' => 'ECB Press', 'url' => 'https://www.ecb.europa.eu/rss/press.html'],

    // UK Government
    ['name' => 'UK Foreign Office', 'url' => 'https://www.gov.uk/government/organisations/foreign-commonwealth-development-office.atom'],

    // === Think Tanks (verified working via probe) ===
    ['name' => 'Atlantic Council', 'url' => 'https://www.atlanticcouncil.org/feed/'],
    ['name' => 'RAND', 'url' => 'https://www.rand.org/news/press.xml'],
    ['name' => 'War on Rocks', 'url' => 'https://warontherocks.com/feed/'],

    // === NOT WORKING (kept for reference, DO NOT UNCOMMENT) ===
    // White House: 404
    // Treasury: 404
    // Defense.gov: 403
    // IMF: 403
    // World Bank: returns HTML
    // Japan MOFA: 403
    // CSIS, Brookings, Chatham House, CFR, Carnegie: all fail
    // Reuters, AP: feed URLs deprecated/blocked
];
