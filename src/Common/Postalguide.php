<?php

$source = POSTAL_DATA_SOURCE;
$json = [ 'status' => 0 ];
$columns = ['pref','city','street'];
try {
    $db = new PDO("sqlite:$source");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $col = implode(',', $columns);
    $statement = $db->prepare("SELECT {$col} FROM postalcode WHERE code = ?");

    $query = str_replace('-', '', mb_convert_kana(($_GET['q'] ?? ''), 'a'));
    $statement->execute([$query]);

    $json['records'] = $statement->fetchAll();
} catch (Exception $e) {
    //
    $json = [ 'status' => 1 ];
}

header('Content-type: application/json; charset=utf-8');
echo json_encode($json, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK);
exit;
