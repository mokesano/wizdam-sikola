<?php

return [
    // ORCID OAuth2
    'orcid' => [
        'client_id'     => $_ENV['ORCID_CLIENT_ID']     ?? '',
        'client_secret' => $_ENV['ORCID_CLIENT_SECRET'] ?? '',
        'redirect_uri'  => $_ENV['ORCID_REDIRECT_URI']  ?? '',
        'base_url'      => 'https://orcid.org',
        'api_url'       => 'https://pub.orcid.org/v3.0',
        'sandbox'       => filter_var($_ENV['ORCID_SANDBOX'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    // Sangia AI Engine
    'sangia' => [
        'base_url' => $_ENV['SANGIA_API_URL'] ?? 'https://api.sangia.org',
        'api_key'  => $_ENV['SANGIA_API_KEY'] ?? '',
        'timeout'  => (int) ($_ENV['SANGIA_TIMEOUT'] ?? 30),
    ],

    // Scopus API (Elsevier)
    'scopus' => [
        'api_key' => $_ENV['SCOPUS_API_KEY'] ?? '',
        'base_url' => 'https://api.elsevier.com/content',
    ],

    // SINTA (Kemenristek)
    'sinta' => [
        'base_url' => 'https://sinta.kemdikbud.go.id',
    ],

    // Crawler Receiver Token
    'crawler_token' => $_ENV['CRAWLER_RECEIVER_TOKEN'] ?? '',
];
