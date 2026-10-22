<?php
if (!defined('__MINITALK__')) exit;

$results->success = false;
if (!$this->isAdmin()) { $results->code='FORBIDDEN'; return; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    $results->code='METHOD_NOT_ALLOWED';
    return;
}
$token = $_POST['csrf_token'] ?? null;
$expected = $_SESSION['MINITALK_DATABASE_CSRF'] ?? null;
if (!is_string($token) || !is_string($expected) || !hash_equals($expected,$token)) {
    http_response_code(403);
    $results->code='INVALID_CSRF_TOKEN';
    return;
}
$hash = $_POST['schema_hash'] ?? null;
try {
    $outcome = $this->updateDatabase($hash);
    foreach ($outcome as $key=>$value) $results->$key=$value;
    if ($outcome['success']) {
        $results->message='데이터베이스 업데이트가 완료되었습니다.';
    } else {
        $messages=array('STALE_SCHEMA'=>'코드가 변경되었습니다. 페이지를 새로 열어 확인해 주세요.',
            'UPDATE_BUSY'=>'다른 관리자가 업데이트 중입니다. 잠시 후 다시 시도해 주세요.',
            'MANUAL_SCHEMA_REVIEW_REQUIRED'=>'DB 구조나 기존 데이터 확인이 필요합니다. 지원을 요청해 주세요.',
            'DATABASE_PERMISSION_REQUIRED'=>'DB 변경 권한이 필요합니다. 호스팅 관리자에게 권한 확인을 요청해 주세요.',
            'ONLINE_UPDATE_UNSUPPORTED'=>'현재 호스팅은 안전한 자동 변경을 지원하지 않습니다. 지원을 요청해 주세요.',
            'UPDATE_LOCK_UNAVAILABLE'=>'현재 호스팅에서 중복 실행 방지 기능을 사용할 수 없습니다. 지원을 요청해 주세요.');
        $results->message=$messages[$outcome['code']] ?? '업데이트를 완료하지 못했습니다. 기존 데이터는 유지되며 다시 시도할 수 있습니다.';
    }
} catch (Throwable $error) {
    error_log('Minitalk database update request failed: '.get_class($error).' code '.$error->getCode());
    $results->code='DATABASE_UPDATE_FAILED';
    $results->message='업데이트를 완료하지 못했습니다. 기존 데이터는 유지됩니다. 관리자에게 문의해 주세요.';
}
