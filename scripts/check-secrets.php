<?php

// Checks staged/tracked files against this machine's secret values without printing them.
// Supplement this focused check with your organization's repository secret scanner.
$root = dirname(__DIR__);
chdir($root);
$output = [];
$code = 0;
exec('git ls-files', $output, $code);
if ($code !== 0) {
    fwrite(STDERR, "Run this inside the initialized repository.\n");
    exit(1);
}
$env = is_file('.env') ? file_get_contents('.env') : '';
$secrets = [];
foreach (['APP_KEY', 'DB_PASSWORD', 'PIXELFORGE_API_KEY', 'PIXELFORGE_WEBHOOK_SECRET'] as $key) {
    if (preg_match('/^'.$key.'=(.+)$/m', $env, $match)) {
        $value = trim($match[1], " \t\n\r\0\x0B\"'");
        if (strlen($value) >= 12) {
            $secrets[] = $value;
        }
    }
}
$violations = [];
foreach ($output as $path) {
    if ($path === '.env' || str_starts_with($path, '.runtime/')) {
        $violations[] = $path;

        continue;
    }
    if (! is_file($path)) {
        continue;
    }
    $body = file_get_contents($path);
    foreach ($secrets as $secret) {
        if (str_contains($body, $secret)) {
            $violations[] = $path;
            break;
        }
    }
    if (preg_match('/-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----/', $body)) {
        $violations[] = $path;
    }
}
if ($violations) {
    fwrite(STDERR, 'Potential secret in: '.implode(', ', array_unique($violations))."\n");
    exit(1);
}
echo "Tracked files contain none of the configured secret values; private runtime files are untracked.\n";
