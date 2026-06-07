<?php
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../db/DeliveriesRepository.php';
require_once __DIR__ . '/../db/DriversRepository.php';
require_once __DIR__ . '/../db/VehiclesRepository.php';
require_once __DIR__ . '/../db/AssignmentsRepository.php';

function assignments_auto_assign(): void
{
    header('Content-Type: application/json; charset=utf-8');

    require_once __DIR__ . '/../../algorithm/AssignmentEngine.php';

    $engine  = new AssignmentEngine();
    $results = $engine->run();
    $flags   = $engine->getFlags();

    echo json_encode(['assigned' => $results, 'flagged' => $flags]);
}
