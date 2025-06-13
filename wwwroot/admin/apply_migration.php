<?php

include_once "/home/dh_fbrdk3/db.marbletrack3.com/prepend.php";

header("Content-Type: application/json");

if (!$is_logged_in->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['migration'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing migration identifier"]);
    exit;
}

try {
    $dbExistaroo->applyMigration($input['migration']);
    echo json_encode(["status" => "success", "applied" => $input['migration']]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
