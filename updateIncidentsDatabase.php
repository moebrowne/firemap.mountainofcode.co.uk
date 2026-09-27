<?php

declare(strict_types=1);

ini_set('display_errors', 0);

$incidents = json_decode(file_get_contents(__DIR__ . '/incidents.json'), true, flags: JSON_THROW_ON_ERROR);

$knownUrls = [];

foreach ($incidents as $incident) {
    if (!empty($incident['url'])) {
        $knownUrls[$incident['url']] = true;
    }
}

$ch = curl_init();

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, 3);
curl_setopt($ch, CURLOPT_ENCODING, '');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: curl/7.81.0',
    'Accept: text/html',
]);

for ($page = 1; ; $page++) {
    $index = $page === 1 ? '' : (string) $page;
    $sitemapUrl = 'https://www.dwfire.org.uk/incident-sitemap' . $index . '.xml';

    echo 'Requesting sitemap ' . $sitemapUrl;

    curl_setopt($ch, CURLOPT_URL, $sitemapUrl);
    $xml = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    echo ' -> ' . $httpCode . PHP_EOL;

    if ($httpCode === 404) {
        break;
    }

    if ($httpCode !== 200 || $xml === false) {
        echo ' -> Error fetching sitemap, stopping.' . PHP_EOL;
        break;
    }

    $sitemap = simplexml_load_string($xml);
    $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $incidentUrls = array_map(strval(...), $sitemap->xpath('//s:url/s:loc'));

    foreach ($incidentUrls as $incidentUrl) {
        if (isset($knownUrls[$incidentUrl])) {
            continue;
        }

        $attempts = 0;
        while (true) {
            if ($attempts > 3) {
                break 3;
            }

            usleep(1_000_000);

            echo 'Requesting ' . $incidentUrl;

            curl_setopt($ch, CURLOPT_URL, $incidentUrl);

            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            echo ' -> ' . $httpCode;

            if ($httpCode !== 200 || $html === false) {
                sleep(15);
                echo ' -> Zzzzz' . PHP_EOL;
                continue;
            }

            break;
        }

        try {
            if (!preg_match('/var WP = ({.+})/', $html, $matches)) {
                throw new Exception('No WP context found');
            }

            /**
             * @var object{
             *     context: object{
             *         posts: array<int, object{
             *             id: int,
             *             post_title: string,
             *             description: string,
             *             post_date: string,
             *             location: ''|object{address: string, lat: string, lng: string},
             *             attending: list<string>|string,
             *         }>,
             *         stations: array<int, object{id: int, post_title: string}>,
             *     },
             * } $json
             */
            $json = json_decode($matches[1], flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            echo ' -> Error: ' . $e->getMessage() . PHP_EOL;
            continue;
        }

        $posts = (array) $json->context->posts;
        $incident = (object) reset($posts);

        $hasLocation = $incident->location !== '' && $incident->location->lat !== '' && $incident->location->lng !== '';

        if ($hasLocation === false) {
            echo ' -> Location missing';
        }

        $stationNames = [];

        foreach ((array) $json->context->stations as $station) {
            $stationNames[(string) $station->id] = $station->post_title;
        }

        if (is_array($incident->attending)) {
            $attendingStationNames = array_filter(array_map(
                static fn(string $stationId): ?string => $stationNames[$stationId] ?? null,
                $incident->attending,
            ));
        } else {
            $attendingStationNames = [];
        }

        $incidentData = new stdClass();
        $incidentData->id = $incident->id;
        $incidentData->url = $incidentUrl;
        $incidentData->title = $incident->post_title;
        $incidentData->description = $incident->description;
        $incidentData->timestamp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $incident->post_date)->getTimestamp();

        if ($hasLocation) {
            $incidentData->location = new stdClass();
            $incidentData->location->address = $incident->location->address;
            $incidentData->location->lat = $incident->location->lat;
            $incidentData->location->lng = $incident->location->lng;
        } else {
            $incidentData->location = null;
        }

        $incidentData->stations = $attendingStationNames;

        if (array_key_exists($incidentData->id, $incidents)) {
            echo ' -> Incident [' . $incidentData->id . '] already exists!' . PHP_EOL;
            $knownUrls[$incidentUrl] = true;
            continue;
        }

        $incidents[$incidentData->id] = $incidentData;
        $knownUrls[$incidentUrl] = true;

        file_put_contents(__DIR__ . '/incidents.json', json_encode($incidents, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        echo PHP_EOL;
    }
}
