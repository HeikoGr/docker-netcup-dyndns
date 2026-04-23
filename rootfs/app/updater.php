<?php

declare(strict_types=1);

namespace netcup\DNS\API;

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoloadFile)) {
    throw new \RuntimeException('Missing Composer dependencies. Run "composer install --no-dev" in rootfs/app first.');
}

require_once $autoloadFile;
require_once __DIR__ . '/src/Client.php';
require_once __DIR__ . '/src/DynDNS.php';
require_once __DIR__ . '/src/Config.php';

/**
 * Get IP address with failover support
 * Tries multiple services until one succeeds
 *
 * @param array $services List of URLs to try
 * @param int $curlIpResolve CURL_IPRESOLVE_V4 or CURL_IPRESOLVE_V6
 * @param int $filterFlag FILTER_FLAG_IPV4 or FILTER_FLAG_IPV6
 */
function getIpAddress(array $services, int $curlIpResolve, int $filterFlag): ?string {
    foreach ($services as $service) {
        $ch = curl_init($service);
        if ($ch === false) {
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
        curl_close($ch);

        if (is_string($ip) && $ip !== '') {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, $filterFlag) !== false) {
                return $ip;
            }
        }
    }

    return null;
}

function getRequiredEnv(string $name): string {
    $value = trim((string) ($_ENV[$name] ?? ''));
    if ($value === '') {
        throw new \InvalidArgumentException(sprintf('Missing required environment variable: %s', $name));
    }

    return $value;
}

function getRequiredIntEnv(string $name): int {
    $value = getRequiredEnv($name);
    if (!ctype_digit($value)) {
        throw new \InvalidArgumentException(sprintf('Environment variable %s must be an unsigned integer', $name));
    }

    return (int) $value;
}

if ('yes' === $_ENV['IPV4']) {
    $ipv4Services = [
        'https://v4.ident.me',
        'https://ipv4.icanhazip.com',
        'https://api.ipify.org',
        'https://v4.tnedi.me',
        'https://ipv4.wtfismyip.com/text',
    ];
    $ipv4 = getIpAddress($ipv4Services, CURL_IPRESOLVE_V4, FILTER_FLAG_IPV4);
    if (!$ipv4) {
        throw new \RuntimeException('Failed to detect IPv4 address from all services');
    }
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
    $ipv6 = getIpAddress($ipv6Services, CURL_IPRESOLVE_V6, FILTER_FLAG_IPV6);
    if (!$ipv6) {
        throw new \RuntimeException('Failed to detect IPv6 address from all services');
    }
} else {
    $ipv6 = null;
}

if (!$ipv4 && !$ipv6) {
    throw new \UnexpectedValueException('ehm?');
}

$config = new Config(
    getRequiredEnv('DOMAIN'),
    (string) ($_ENV['MODE'] ?? '@'),
    getRequiredIntEnv('CUSTOMER_ID'),
    getRequiredEnv('API_KEY'),
    getRequiredEnv('API_PASSWORD'),
    (int) ($_ENV['TTL'] ?? 0),
    'yes' === ($_ENV['FORCE'] ?? 'no'),
);

(new DynDNS($config, $ipv4, $ipv6))->update();
