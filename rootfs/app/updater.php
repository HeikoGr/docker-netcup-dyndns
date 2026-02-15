<?php

declare(strict_types=1);

namespace netcup\DNS\API;

require_once __DIR__ . '/src/Client.php';
require_once __DIR__ . '/src/DynDNS.php';
require_once __DIR__ . '/src/Config.php';

/**
 * Get IP address with failover support
 * Tries multiple services until one succeeds
 *
 * @param array $services List of URLs to try
 * @param int $filterFlag FILTER_FLAG_IPV4 or FILTER_FLAG_IPV6
 */
function getIpAddress(array $services, int $filterFlag): ?string {
    foreach ($services as $service) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => false,
                ],
            ]);
            $ip = @file_get_contents($service, false, $context);
            if ($ip !== false && !empty($ip)) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, $filterFlag) !== false) {
                    return $ip;
                }
            }
        } catch (\Exception $e) {
            // Try next service on error
            continue;
        }
    }
    return null;
}

if ('yes' === $_ENV['IPV4']) {
    $ipv4Services = [
        'https://v4.ident.me',
        'https://ipv4.icanhazip.com',
        'https://api.ipify.org',
        'https://v4.tnedi.me',
        'https://ipv4.wtfismyip.com/text',
    ];
    $ipv4 = getIpAddress($ipv4Services, FILTER_FLAG_IPV4);
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
    $ipv6 = getIpAddress($ipv6Services, FILTER_FLAG_IPV6);
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
    $_ENV['DOMAIN'],
    $_ENV['MODE'],
    (int)$_ENV['CUSTOMER_ID'],
    $_ENV['API_KEY'],
    $_ENV['API_PASSWORD'],
    (int)($_ENV['TTL'] ?? 0),
    'yes' === $_ENV['FORCE'],
);

(new DynDNS($config, $ipv4, $ipv6))->update();
