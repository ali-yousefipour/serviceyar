<?php
declare(strict_types=1);
require_once __DIR__.'/../src/bootstrap.php';

// Regression for CONTRACT_REQUIRED_DATA_MISSING: an empty optional field
// in a populated modal must never wipe the authoritative persisted value.
$method = new ReflectionMethod(\ServiceYar\Domain\ContractDocuments::class, 'resolvedOverrides');
$overrides = $method->invoke(null, [
    'guardianName' => '  ',
    'guardianNationalCode' => '',
    'guardianMobile' => " 09123456789 ",
    'managerName' => '  مدیر شرکت ',
]);
if ($overrides !== ['guardianMobile' => '09123456789', 'managerName' => 'مدیر شرکت']) {
    throw new RuntimeException('Empty override erased a verified contract field');
}
try {
    $method->invoke(null, ['notAnAllowedField'=>'value']);
    throw new RuntimeException('Unapproved override accepted');
} catch (\ServiceYar\Auth\AuthException $e) {
    if ($e->status !== 422) throw $e;
}
echo "Parent contract blank-overrides regression passed\n";
