<?php

declare(strict_types=1);

namespace netcup\DNS\API;

final class DynDNS
{
    private Config $config;

    private Client $client;

    private ?string $ipv4;
    private ?string $ipv6;

    public function __construct(Config $config, ?string $ipv4, ?string $ipv6)
    {
        $this->config = $config;

        $this->ipv4 = $ipv4;
        $this->ipv6 = $ipv6;

        $this->client = new Client(
            $config->getCustomerId(),
            $config->getApiKey(),
            $config->getApiPassword(),
        );

    }

    public function update(): void
    {
        $sid = $this->client->login();

        $this->doLog('api login successful');

        try {
            $zoneName = $this->resolveZoneName($sid);
            $targetHostnames = $this->config->getTargetHostnames($zoneName);

            $this->doLog(sprintf('managed dns zone resolved to %s', $zoneName));

            if ($ttl = $this->config->getTtl()) {
                $zone = $this->client->infoDnsZone($sid, $zoneName);

                if ($ttl !== (int)$zone->responsedata->ttl) {
                    $zone->responsedata->ttl = $ttl;
                    $this->client->updateDnsZone($sid, $zoneName, $zone->responsedata);

                    $this->doLog(sprintf('ttl for %s set to %s', $zoneName, $ttl));
                } else {
                    $this->doLog(sprintf('ttl for %s already set to %s', $zoneName, $ttl));
                }
            }

            $records = $this->client->infoDnsRecords($sid, $zoneName);

            $changes = false;

            foreach ($records->responsedata->dnsrecords as $record) {
                if (!in_array($record->hostname, $targetHostnames, true)) {
                    continue;
                }

                $fqdn = $this->config->formatHostnameForZone($record->hostname, $zoneName);

                // update A Record if exists and IP has changed
                if ('A' === $record->type && $this->ipv4 &&
                    (
                        $this->config->isForce() ||
                        $record->destination !== $this->ipv4
                    )
                ) {
                    $record->destination = $this->ipv4;
                    $this->doLog(sprintf('IPv4 for %s set to %s', $fqdn, $this->ipv4));
                    $changes = true;
                }

                // update AAAA Record if exists and IP has changed
                if ('AAAA' === $record->type && $this->ipv6 &&
                    (
                        $this->config->isForce()
                        || $record->destination !== $this->ipv6
                    )
                ) {
                    $record->destination = $this->ipv6;
                    $this->doLog(sprintf('IPv6 for %s set to %s', $fqdn, $this->ipv6));
                    $changes = true;
                }
            }

            if (true === $changes) {
                $this->client->updateDnsRecords(
                    $sid,
                    $zoneName,
                    $records->responsedata->dnsrecords
                );

                $this->doLog('dns recordset updated');
            } else {
                $this->doLog('dns recordset NOT updated (no changes)');
            }
        } finally {
            try {
                $this->client->logout($sid);
                $this->doLog('api logout successful');
            } catch (\Throwable $exception) {
                $this->doLog('api logout failed: ' . $exception->getMessage());
            }
        }
    }

    private function doLog(string $msg)
    {
        printf('%s %s', $msg, PHP_EOL);
    }

    private function resolveZoneName(string $sid): string
    {
        $lastException = null;

        foreach ($this->config->getZoneCandidates() as $candidate) {
            try {
                $this->client->infoDnsZone($sid, $candidate);
                return $candidate;
            } catch (\RuntimeException $exception) {
                $lastException = $exception;
            }
        }

        throw new \RuntimeException(
            sprintf('Failed to resolve a managed DNS zone for %s', $this->config->getDomain()),
            previous: $lastException,
        );
    }
}
