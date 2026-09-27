<?php

declare(strict_types=1);

$incidents = require __DIR__ . '/../incidents.php';

if (!is_numeric($_GET['offset']) || !is_numeric($_GET['count'])) {
    die();
}

$prevDateGroup = null;
$entries = [];
$daysRendered = 0;

foreach ($incidents as $incident) {
    if (date('d M Y', $incident->timestamp) !== $prevDateGroup) {
        $daysRendered++;
        $entries[$daysRendered] = '
            <li class="timeline-item period">
                <div class="timeline-info"></div>
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <h2 class="timeline-title">' . date('j F Y', $incident->timestamp) . '</h2>
                </div>
            </li>';
    }

    $classes = '';

    $searchString = $incident->title . ' ' . $incident->description;
    $classes .= stripos($searchString, 'false alarm') !== false ? 'false-alarm ' : '';
    $classes .= preg_match('/small vehicle|vehicle fire|Fire Vehicle|RTC|Road Traffic Collision|Car Fire/i', $searchString) === 1 ? 'vehicle-fire ' : '';
    $classes .= preg_match('/large vehicle|vehicle large|Lorry Fire/i', $searchString) === 1 ? 'vehicle-fire-large ' : '';
    $classes .= preg_match('/locked in|Shut In|Lift Release|Release (?:of )?person|person released/i', $searchString) === 1 ? 'locked-in ' : '';
    $classes .= preg_match('/small animal|RSPCA|hamster|dog|puppy/i', $searchString) === 1 ? 'small-animal ' : '';
    $classes .= preg_match('/aircraft/i', $searchString) === 1 ? 'aircraft ' : '';
    $classes .= preg_match('/hazmat|biohazard/i', $searchString) === 1 ? 'hazmat ' : '';

    $entries[$daysRendered] .= '<li class="timeline-item">
        <div class="timeline-marker ' . $classes . '"></div>
        <div class="timeline-content">
            <h3 class="timeline-title">' . $incident->title . '</h3>

            <p>' . $incident->description . '</p>

            <a class="timeline-address" data-lat="' . $incident->location->lat . '" data-lng="' . $incident->location->lng . '">
                <svg class="icon-target" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="7"></circle>
                    <circle cx="12" cy="12" r="1.3" fill="currentColor" stroke="none"></circle>
                    <line x1="12" y1="2" x2="12" y2="5"></line>
                    <line x1="22" y1="12" x2="19" y2="12"></line>
                    <line x1="12" y1="22" x2="12" y2="19"></line>
                    <line x1="2" y1="12" x2="5" y2="12"></line>
                    <line x1="18.36" y1="5.64" x2="16.95" y2="7.05"></line>
                    <line x1="18.36" y1="18.36" x2="16.95" y2="16.95"></line>
                    <line x1="5.64" y1="18.36" x2="7.05" y2="16.95"></line>
                    <line x1="5.64" y1="5.64" x2="7.05" y2="7.05"></line>
                </svg>
                ' . $incident->location->address . '
            </a>
        </div>
    </li>';

    $prevDateGroup = date('d M Y', $incident->timestamp);
}

$entries = array_slice($entries, (int)$_GET['offset'], (int)$_GET['count']);

echo implode('', $entries);
