<?php

declare(strict_types=1);

namespace netcup\DNS\API;

try {
    $autoloadFile = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoloadFile)) {
        throw new \RuntimeException('Missing Composer dependencies. Run "composer install --no-dev" in rootfs/app first.');
    }

    require_once $autoloadFile;
    require_once __DIR__ . '/src/Client.php';
    require_once __DIR__ . '/src/DynDNS.php';
    require_once __DIR__ . '/src/Config.php';

    if ('yes' === $_ENV['IPV4']) {
        $ipv4Services = [
            'https://v4.ident.me',
            'https://ipv4.icanhazip.com',
            'https://api.ipify.org',
            'https://v4.tnedi.me',
            'https://ipv4.wtfismyip.com/text',
        ];
        $ipv4 = getIpAddress($ipv4Services, 'IPv4', CURL_IPRESOLVE_V4, FILTER_FLAG_IPV4);
        logMessage('INFO', sprintf('detected public IPv4: %s', $ipv4));
    } else {
        $ipv4 = null;
    }

    if ('yes' === $_ENV['IPV6']) {
        $ipv6Services = [
            'https://v6.ident.me',
            'https://ipv6.icanhazip.com',
            'https://api6.ipify.org',
            'https://v6.tnedi.me',
            'https://ipv6.wtfismyip.com/text',
        ];
        $ipv6 = getIpAddress($ipv6Services, 'IPv6', CURL_IPRESOLVE_V6, FILTER_FLAG_IPV6);
        logMessage('INFO', sprintf('detected public IPv6: %s', $ipv6));
    } else {
        $ipv6 = null;
    }

    if (!$ipv4 && !$ipv6) {
        throw new \UnexpectedValueException('Neither IPv4 nor IPv6 is enabled or detectable');
    }

    // DOMAIN may hold several domains separated by commas or whitespace, e.g. "example.org, example.net".
    // The public IP is detected once; every domain is updated on its own so one failing zone does not
    // block the others.
    $domains = preg_split('/[\s,]+/', getRequiredEnv('DOMAIN'), -1, PREG_SPLIT_NO_EMPTY);
    $mode = (string) ($_ENV['MODE'] ?? '@');
    $ttl = (int) ($_ENV['TTL'] ?? 0);
    $force = (string) ($_ENV['FORCE'] ?? 'no');
    $failed = [];

    foreach ($domains as $domain) {
        try {
            $config = new Config(
                $domain,
                $mode,
                getRequiredIntEnv('CUSTOMER_ID'),
                getRequiredEnv('API_KEY'),
                getRequiredEnv('API_PASSWORD'),
                $ttl,
                'yes' === $force,
            );

            logMessage(
                'INFO',
                sprintf(
                    'starting update domain=%s mode=%s ipv4=%s ipv6=%s ttl=%d force=%s',
                    $config->getDomain(),
                    $mode,
                    $ipv4 ?? 'disabled',
                    $ipv6 ?? 'disabled',
                    $ttl,
                    $force
                )
            );

            (new DynDNS($config, $ipv4, $ipv6))->update();
        } catch (\Throwable $exception) {
            logMessage('ERROR', sprintf('domain=%s: %s', $domain, $exception->getMessage()));
            $failed[] = $domain;
        }
    }

    if ($failed !== []) {
        throw new \RuntimeException(sprintf('update failed for: %s', implode(', ', $failed)));
    }
} catch (\Throwable $exception) {
    logMessage('ERROR', $exception->getMessage());
    exit(1);
}

function logMessage(string $level, string $message): void
{
    printf('%s [%s] [updater] %s%s', gmdate('Y-m-d\TH:i:s\Z'), $level, $message, PHP_EOL);
}

/**
 * Get IP address with failover support
 * Tries multiple services until one succeeds
 *
 * @param array $services List of URLs to try
 * @param string $protocolLabel Human readable protocol label, e.g. IPv4 or IPv6
 * @param int $curlIpResolve CURL_IPRESOLVE_V4 or CURL_IPRESOLVE_V6
 * @param int $filterFlag FILTER_FLAG_IPV4 or FILTER_FLAG_IPV6
 */
function getIpAddress(array $services, string $protocolLabel, int $curlIpResolve, int $filterFlag): string
{
    $lastError = null;

    foreach ($services as $service) {
        $ch = curl_init($service);
        if ($ch === false) {
            $lastError = sprintf('request setup failed for %s', $service);
            continue;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: text/plain'],
            CURLOPT_IPRESOLVE => $curlIpResolve,
            CURLOPT_USERAGENT => 'docker-netcup-dyndns/1.0',
        ]);

        $ip = curl_exec($ch);
        if ($ip === false) {
            $lastError = sprintf('%s returned: %s', $service, curl_error($ch) ?: 'unknown cURL error');
            continue;
        }

        $ip = trim($ip);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, $filterFlag) !== false) {
            return $ip;
        }

        $lastError = sprintf('%s returned an invalid %s response', $service, $protocolLabel);
    }

    throw new \RuntimeException(sprintf(
        'Failed to detect public %s address after %d services. Check network connectivity and DNS. Last error: %s',
        $protocolLabel,
        count($services),
        $lastError ?? 'unknown error'
    ));
}

function getRequiredEnv(string $name): string
{
    $value = trim((string) ($_ENV[$name] ?? ''));
    if ($value === '') {
        throw new \InvalidArgumentException(sprintf('Missing required environment variable: %s', $name));
    }

    return $value;
}

function getRequiredIntEnv(string $name): int
{
    $value = getRequiredEnv($name);
    if (!ctype_digit($value)) {
        throw new \InvalidArgumentException(sprintf('Environment variable %s must be an unsigned integer', $name));
    }

    return (int) $value;
}
