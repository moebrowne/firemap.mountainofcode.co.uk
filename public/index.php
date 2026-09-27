<?php

declare(strict_types=1);

ini_set('display_errors', 0);

$incidents = require __DIR__ . '/../incidents.php';

$meanLat = array_sum(array_map(fn (stdClass $incident) => $incident->location->lat, $incidents)) / count($incidents);
$meanLng = array_sum(array_map(fn (stdClass $incident) => $incident->location->lng, $incidents)) / count($incidents);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fire Map</title>
    <link type="text/css" rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="/css/leaflet.css" />
    <script src="/js/leaflet.js"></script>
    <script src="/js/leaflet-heat.js"></script>
</head>
<body>

<script>
    let map;

    document.addEventListener('DOMContentLoaded', function() {
        map = L
            .map('map', {minZoom: 9})
            .setView([<?= $meanLat; ?>, <?= $meanLng; ?>], 9);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            opacity: 0.8,
        })
        .addTo(map);

        L.heatLayer(
            [
                <?php foreach ($incidents as $incident) : ?>
                [<?= $incident->location->lat; ?>, <?= $incident->location->lng; ?>, 1],
                <?php endforeach; ?>
            ],
            {
                radius: 5,
                blur: 4,
                maxZoom: 14,
            }
        )
        .addTo(map);
    });
</script>

<div id="map" style="width: 49vw; height: 100vh; float: left; background-color: #000"></div>

<div id="incident-list" style="width: 49vw; height: 100vh; overflow-y: scroll; float: right;">
    <div>
        <h1>Dorset and Wiltshire Fire Service</h1>
        <div>
            <ul class="timeline"></ul>
        </div>
    </div>
    <div class="spinner">
        <div class="rect1"></div>
        <div class="rect2"></div>
        <div class="rect3"></div>
        <div class="rect4"></div>
        <div class="rect5"></div>
    </div>
</div>
<script>
    var incidentListContainer = document.getElementById('incident-list');
    var incidentList = document.querySelector('#incident-list .timeline');
    var currentlyRenderedDays = 0;
    var oppPending = false;

    const renderTimeline = function (count = 1) {
        if (oppPending) {
            return;
        }

        const scrollBottomThreshold = 10;

        if (incidentListContainer.offsetHeight + incidentListContainer.scrollTop < incidentListContainer.scrollHeight - scrollBottomThreshold) {
            return;
        }

        oppPending = true;
        showSpinner();

        fetch("renderTimeline.php?offset=" + currentlyRenderedDays + "&count=" + count)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                return response.text();
            })
            .then(html => {
                incidentList.innerHTML += html;
                currentlyRenderedDays += count;
                oppPending = false;
                hideSpinner();
            });
    };

    incidentListContainer.addEventListener('scroll', () => renderTimeline());

    incidentList.addEventListener('click', function (event) {
        const address = event.target.closest('.timeline-address');

        if (!address || !map) {
            return;
        }

        map.flyTo([parseFloat(address.dataset.lat), parseFloat(address.dataset.lng)], 16);
    });

    renderTimeline(3);

    function showSpinner() {
        document.querySelector('.spinner').style.visibility = 'visible';
    }

    function hideSpinner() {
        document.querySelector('.spinner').style.visibility = 'hidden';
    }
</script>
</body>
</html>