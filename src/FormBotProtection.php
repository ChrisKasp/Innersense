<?php

declare(strict_types=1);

/**
 * Returns true if the submission frequency for an IP/form pair is acceptable.
 */
function isFormRateLimitAllowed(string $formKey, string $ipAddress, int $maxAttempts, int $windowSeconds): bool
{
    $tmpDir = dirname(__DIR__) . '/tmp';
    if (!is_dir($tmpDir)) {
        return true;
    }

    $filePath = $tmpDir . '/form_rate_limit.json';
    $now = time();
    $windowStart = $now - $windowSeconds;
    $bucketKey = $formKey . '|' . $ipAddress;

    $fp = @fopen($filePath, 'c+');
    if ($fp === false) {
        return true;
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return true;
        }

        $raw = stream_get_contents($fp);
        $entries = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $entries = $decoded;
            }
        }

        foreach ($entries as $key => $timestamps) {
            if (!is_array($timestamps)) {
                unset($entries[$key]);
                continue;
            }

            $filtered = [];
            foreach ($timestamps as $timestamp) {
                if (is_int($timestamp) && $timestamp >= $windowStart) {
                    $filtered[] = $timestamp;
                }
            }

            if ($filtered === []) {
                unset($entries[$key]);
                continue;
            }

            $entries[$key] = $filtered;
        }

        $attempts = isset($entries[$bucketKey]) && is_array($entries[$bucketKey]) ? $entries[$bucketKey] : [];
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        $attempts[] = $now;
        $entries[$bucketKey] = $attempts;

        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return true;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function getClientIpAddress(): string
{
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($remoteAddress) || $remoteAddress === '') {
        return 'unknown';
    }

    return $remoteAddress;
}

function isTurnstileConfigured(?string $siteKey, ?string $secretKey): bool
{
    return trim((string) $siteKey) !== '' && trim((string) $secretKey) !== '';
}

function validateTurnstileToken(?string $token, string $secretKey, string $ipAddress): bool
{
    $trimmedToken = trim((string) $token);
    if ($trimmedToken === '' || trim($secretKey) === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => $secretKey,
        'response' => $trimmedToken,
        'remoteip' => $ipAddress,
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 8,
        ],
    ]);

    $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if (!is_string($response) || $response === '') {
        return false;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) && !empty($decoded['success']);
}

/**
 * @param array<string, mixed> $postData
 */
function validateBotProtection(array $postData, string $formKey, int $minFillSeconds = 3): bool
{
    $honeypotValue = trim((string) ($postData['website'] ?? ''));
    if ($honeypotValue !== '') {
        return false;
    }

    $startedAtRaw = (string) ($postData['_started_at'] ?? '');
    if ($startedAtRaw === '' || ctype_digit($startedAtRaw) === false) {
        return false;
    }

    $startedAt = (int) $startedAtRaw;
    $elapsed = time() - $startedAt;
    if ($elapsed < $minFillSeconds || $elapsed > 7200) {
        return false;
    }

    return isFormRateLimitAllowed($formKey, getClientIpAddress(), 5, 600);
}
