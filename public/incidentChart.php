<?php

declare(strict_types=1);

/** @var stdClass[] $incidents */

$monthlyCounts = [];

foreach ($incidents as $incident) {
    $monthKey = date('Y-n', $incident->timestamp);
    $monthlyCounts[$monthKey] = ($monthlyCounts[$monthKey] ?? 0) + 1;
}

$oldestTimestamp = end($incidents)->timestamp;
$newestTimestamp = $incidents[0]->timestamp;

$months = [];
$cursor = mktime(0, 0, 0, (int)date('n', $oldestTimestamp), 1, (int)date('Y', $oldestTimestamp));
$lastMonth = mktime(0, 0, 0, (int)date('n', $newestTimestamp), 1, (int)date('Y', $newestTimestamp));

while ($cursor <= $lastMonth) {
    $months[] = (object)[
        'year' => (int)date('Y', $cursor),
        'month' => (int)date('n', $cursor),
        'label' => date('M', $cursor),
        'count' => $monthlyCounts[date('Y-n', $cursor)] ?? 0,
    ];

    $cursor = strtotime('+1 month', $cursor);
}

$maxMonthlyCount = max(array_map(fn (stdClass $month) => $month->count, $months)) ?: 1;

$chartSlotWidth = 10;
$chartBarWidth = 7;
$chartHeight = 64;
$chartMaxBarHeight = 60;

?>
<div class="incident-chart">
    <svg class="incident-chart-svg" viewBox="0 0 <?= count($months) * $chartSlotWidth; ?> <?= $chartHeight; ?>" preserveAspectRatio="none">
        <?php foreach ($months as $i => $month) : ?>
            <?php
            $barHeight = $month->count > 0
                ? max(2, (int)round(($month->count / $maxMonthlyCount) * $chartMaxBarHeight))
                : 0;
            $barX = $i * $chartSlotWidth + ($chartSlotWidth - $chartBarWidth) / 2;
            $tooltipLabel = htmlspecialchars($month->label . ' ' . $month->year, ENT_QUOTES);
            ?>
            <rect
                class="chart-hit"
                x="<?= $i * $chartSlotWidth; ?>"
                y="0"
                width="<?= $chartSlotWidth; ?>"
                height="<?= $chartHeight; ?>"
            ><title><?= $tooltipLabel; ?> - <?= $month->count; ?> incident<?= $month->count === 1 ? '' : 's'; ?></title></rect>
            <?php if ($barHeight > 0) : ?>
                <rect
                    class="chart-bar"
                    x="<?= $barX; ?>"
                    y="<?= $chartHeight - $barHeight; ?>"
                    width="<?= $chartBarWidth; ?>"
                    height="<?= $barHeight; ?>"
                ></rect>
            <?php endif; ?>
        <?php endforeach; ?>
    </svg>
    <div class="incident-chart-axis">
        <?php foreach ($months as $i => $month) : ?>
            <div class="chart-axis-month">
                <span class="chart-pip"></span>
                <?php if ($month->month === 1 || $i === 0) : ?>
                    <span class="chart-year-label"><?= $month->year; ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
