<?php

declare(strict_types=1);

namespace netcup\DNS\API;

final class Client
{
    private const APIURL = 'https://ccp.netcup.net/run/webservice/servers/endpoint.php?JSON';

    private int $customerId;

    private string $apiKey;

    private string $apiPassword;

    public function __construct(int $customerId, string $apiKey, string $apiPassword)
    {
        $this->customerId = $customerId;
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
    }

    public function login(): string
    {
        $request = [
            'action' => 'login',
            'param' =>
                [
                    'customernumber' => $this->customerId,
                    'apikey' => $this->apiKey,
                    'apipassword' => $this->apiPassword,
                ],
        ];

        return $this->request($request)->responsedata->apisessionid;
    }

    public function logout(string $sid): void
    {
        $request = [
            'action' => 'logout',
            'param' =>
                [
                    'customernumber' => $this->customerId,
                    'apikey' => $this->apiKey,
                    'apisessionid' => $sid,
                ],
        ];

        $this->request($request);
    }

    public function infoDnsRecords(string $sid, string $domainname): object
    {
        $request = [
            'action' => 'infoDnsRecords',
            'param' =>
                [
                    'domainname' => $domainname,
                    'customernumber' => $this->customerId,
                    'apikey' => $this->apiKey,
                    'apisessionid' => $sid,
                ],
        ];

        return $this->request($request);
    }

    public function updateDnsRecords(string $sid, string $domainname, array $record): object
    {
        $request = [
            'action' => 'updateDnsRecords',
            'param' =>
                [
                    'domainname' => $domainname,
                    'customernumber' => $this->customerId,
                    'apikey' => $this->apiKey,
                    'apisessionid' => $sid,
                    'dnsrecordset' => [
                        'dnsrecords' => $record,
                    ],
                ],
        ];

        return $this->request($request);
    }

    public function infoDnsZone(string $sid, string $domainname): object
    {
        $request = [
            'action' => 'infoDnsZone',
            'param' =>
                [
                    'domainname' => $domainname,
                    'customernumber' => $this->customerId,
                    'apikey' => $this->apiKey,
                    'apisessionid' => $sid,
                ],
        ];

        return $this->request($request);
    }

    public function updateDnsZone(string $sid, string $domainname, object $zone): object
    {
        $request = [
            'action' => 'updateDnsZone',
            'param' =>
                [
                    'domainname' => $domainname,
                    'customernumber' => $this->customerId,
                    'apikey' => $this->apiKey,
                    'apisessionid' => $sid,
                    'dnszone' => $zone,
                ],
        ];

        return $this->request($request);
    }

    private function request(array $request): ?object
    {
        $action = (string)($request['action'] ?? 'unknown');
        $ch = curl_init(self::APIURL);

        if ($ch === false) {
            throw new \RuntimeException(sprintf('Failed to initialize cURL for %s', $action));
        }

        try {
            $curlOptions = [
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($request, JSON_THROW_ON_ERROR),
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT => 'docker-netcup-dyndns/1.0',
            ];

            curl_setopt_array($ch, $curlOptions);

            $result = curl_exec($ch);

            if ($result === false) {
                $error = curl_error($ch);
                throw new \RuntimeException(sprintf('cURL error during %s: %s', $action, $error));
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(sprintf('Unexpected HTTP status code during %s: %d', $action, $statusCode));
            }
        } finally {
            curl_close($ch);
        }

        try {
            $response = json_decode($result, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(sprintf('Invalid JSON response during %s: %s', $action, $exception->getMessage()), previous: $exception);
        }

        if (!isset($response->statuscode, $response->longmessage)) {
            throw new \RuntimeException(sprintf('Unexpected API response payload during %s', $action));
        }

        if (2000 !== (int) $response->statuscode) {
            throw new \RuntimeException(sprintf('API %s failed: %s', $action, (string) $response->longmessage));
        }

        return $response;
    }
}