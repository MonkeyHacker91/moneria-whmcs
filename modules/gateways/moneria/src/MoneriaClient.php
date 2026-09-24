<?php

namespace Moneria\Gateway;

use Exception;

/**
 * Class MoneriaClient
 * 
 * Handles all communication with the Moneria REST API.
 */
class MoneriaClient
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private bool $debug;
    private ?string $accessToken = null;

    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $baseUrl
     * @param bool $debug
     */
    public function __construct(string $clientId, string $clientSecret, string $baseUrl = 'https://api.moneria.com.br', bool $debug = false)
    {
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->debug = $debug;
    }

    /**
     * Obtains or refreshes an OAuth2 Access Token from Moneria.
     * Uses WHMCS storage / cache to avoid authenticating on every request.
     *
     * @return string
     * @throws Exception
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        // Try getting cached token from helper/database
        $cached = MoneriaHelper::getCachedToken($this->clientId);
        if ($cached) {
            $this->accessToken = $cached;
            return $this->accessToken;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Credenciais da Moneria (Client ID ou Client Secret) não configuradas.');
        }

        $url = $this->baseUrl . '/oauth/token';
        $basicAuth = base64_encode($this->clientId . ':' . $this->clientSecret);

        $headers = [
            'Authorization: Basic ' . $basicAuth,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $response = $this->request('POST', $url, [], $headers, false);

        if (empty($response['accessToken'])) {
            $errorMsg = $response['message'] ?? ($response['error'] ?? 'Falha ao autenticar na Moneria.');
            if (is_array($errorMsg)) {
                $errorMsg = implode(', ', $errorMsg);
            }
            throw new Exception('Erro de autenticação Moneria: ' . $errorMsg);
        }

        $this->accessToken = $response['accessToken'];

        // Cache token for 13 minutes (Moneria token expires in 15min)
        MoneriaHelper::setCachedToken($this->clientId, $this->accessToken, 780);

        return $this->accessToken;
    }

    /**
     * Searches for a customer by document or email, or creates a new one.
     *
     * @param array $customerData
     * @return array
     * @throws Exception
     */
    public function findOrCreateCustomer(array $customerData): array
    {
        $token = $this->getAccessToken();
        $doc = MoneriaHelper::onlyNumbers($customerData['document'] ?? '');
        $email = trim($customerData['email'] ?? '');

        // 1. Try finding existing customer by document
        $existingCustomer = null;
        if (!empty($doc)) {
            $search = $this->searchCustomer(['document' => $doc]);
            if (!empty($search['data']) && is_array($search['data']) && count($search['data']) > 0) {
                $existingCustomer = $search['data'][0];
            }
        }

        // 2. Try finding existing customer by email
        if (!$existingCustomer && !empty($email)) {
            $search = $this->searchCustomer(['email' => $email]);
            if (!empty($search['data']) && is_array($search['data']) && count($search['data']) > 0) {
                $existingCustomer = $search['data'][0];
            }
        }

        // Build payload
        $payload = [
            'name' => !empty($customerData['name']) ? trim($customerData['name']) : 'Cliente WHMCS',
            'document' => $doc,
            'email' => $email,
        ];

        $phone = MoneriaHelper::formatPhone($customerData['phone'] ?? '');
        if (!empty($phone)) {
            $payload['phone'] = $phone;
        }

        if (!empty($customerData['address'])) {
            $cep = MoneriaHelper::onlyNumbers($customerData['address']['postalCode'] ?? '');
            if (strlen($cep) === 8) {
                $rawNumber = $customerData['address']['number'] ?? 'S/N';
                $street = !empty($customerData['address']['street']) ? $customerData['address']['street'] : 'Rua Principal';
                $sanitizedNumber = MoneriaHelper::extractAddressNumber($street, $rawNumber);

                $payload['address'] = [
                    'postalCode'   => MoneriaHelper::formatCep($cep),
                    'street'       => substr($street, 0, 100),
                    'number'       => substr($sanitizedNumber, 0, 10),
                    'complement'   => substr($customerData['address']['complement'] ?? '', 0, 60),
                    'neighborhood' => substr(!empty($customerData['address']['neighborhood']) ? $customerData['address']['neighborhood'] : 'Centro', 0, 60),
                    'city'         => substr(!empty($customerData['address']['city']) ? $customerData['address']['city'] : 'São Paulo', 0, 60),
                    'state'        => substr(!empty($customerData['address']['state']) ? strtoupper(substr($customerData['address']['state'], 0, 2)) : 'SP', 0, 2),
                    'country'      => 'Brasil',
                ];
            }
        }

        // If existing customer exists, sync with latest WHMCS data
        if ($existingCustomer && !empty($existingCustomer['id'])) {
            $updated = $this->updateCustomer($existingCustomer['id'], $payload);
            return !empty($updated['id']) ? $updated : ($payload + ['id' => $existingCustomer['id']]);
        }

        // 3. Create new customer
        $url = $this->baseUrl . '/customer';
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $response = $this->request('POST', $url, $payload, $headers);

        if (!empty($response['id'])) {
            return $response;
        }

        $msg = $response['message'] ?? ($response['error'] ?? 'Erro desconhecido ao criar cliente na Moneria.');
        if (is_array($msg)) {
            $msg = implode(' | ', $msg);
        }
        throw new Exception('Erro ao cadastrar cliente na Moneria: ' . $msg);
    }

    /**
     * Updates an existing customer in Moneria.
     *
     * @param string $customerId
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function updateCustomer(string $customerId, array $payload): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/customer/me/' . urlencode($customerId);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $response = $this->request('PUT', $url, $payload, $headers);

        if (!empty($response['id'])) {
            return $response;
        }

        return $payload + ['id' => $customerId];
    }

    /**
     * Search customers with query parameters.
     *
     * @param array $queryParams
     * @return array
     * @throws Exception
     */
    public function searchCustomer(array $queryParams): array
    {
        $token = $this->getAccessToken();
        $query = http_build_query($queryParams);
        $url = $this->baseUrl . '/customer/me?' . $query;

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];

        return $this->request('GET', $url, [], $headers);
    }

    /**
     * Creates a new Charge in Moneria.
     *
     * @param array $chargeData
     * @return array
     * @throws Exception
     */
    public function createCharge(array $chargeData): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/charge';

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $response = $this->request('POST', $url, $chargeData, $headers);

        if (!empty($response['id'])) {
            return $response;
        }

        $msg = $response['message'] ?? ($response['error'] ?? 'Falha ao gerar cobrança na Moneria.');
        if (is_array($msg)) {
            $msg = implode(' | ', $msg);
        }
        throw new Exception('Erro ao criar cobrança na Moneria: ' . $msg);
    }

    /**
     * Fetches details of a single Charge by ID.
     *
     * @param string $chargeId
     * @return array
     * @throws Exception
     */
    public function getCharge(string $chargeId): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/charge/' . urlencode($chargeId);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];

        return $this->request('GET', $url, [], $headers);
    }

    /**
     * Cancels a charge on Moneria.
     *
     * @param string $chargeId
     * @return array
     * @throws Exception
     */
    public function cancelCharge(string $chargeId): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/charge/' . urlencode($chargeId);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        return $this->request('DELETE', $url, [], $headers);
    }

    /**
     * Authorizes and charges a credit card transaction.
     *
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function authorizeCreditCard(array $payload): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/transactions/authorize';

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $response = $this->request('POST', $url, $payload, $headers);

        if (!empty($response['id']) || !empty($response['status'])) {
            return $response;
        }

        $msg = $response['message'] ?? ($response['error'] ?? 'Falha ao autorizar pagamento no cartão.');
        if (is_array($msg)) {
            $msg = implode(' | ', $msg);
        }
        throw new Exception('Erro ao processar cartão na Moneria: ' . $msg);
    }

    /**
     * Fetches details of a single Transaction by ID.
     *
     * @param string $transactionId
     * @return array
     * @throws Exception
     */
    public function getTransaction(string $transactionId): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/transactions/' . urlencode($transactionId);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];

        return $this->request('GET', $url, [], $headers);
    }

    /**
     * Authorize / Direct Credit Card transaction.
     *
     * @param array $cardData
     * @return array
     * @throws Exception
     */
    public function chargeCreditCard(array $cardData): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/transactions/authorize';

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $response = $this->request('POST', $url, $cardData, $headers);

        if (!empty($response['id'])) {
            return $response;
        }

        $msg = $response['message'] ?? ($response['error'] ?? 'Falha ao autorizar cartão de crédito.');
        if (is_array($msg)) {
            $msg = implode(' | ', $msg);
        }
        throw new Exception('Erro no pagamento com cartão de crédito: ' . $msg);
    }

    /**
     * Executes HTTP cURL request.
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param bool $logDebug
     * @return array
     * @throws Exception
     */
    private function request(string $method, string $url, array $data = [], array $headers = [], bool $logDebug = true): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $method = strtoupper($method);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $body = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            $body = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            $body = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        if ($curlErrno) {
            $err = "Erro de conexão cURL ({$curlErrno}): {$curlError}";
            if ($this->debug && function_exists('logTransaction')) {
                logTransaction('moneria', ['url' => $url, 'method' => $method, 'data' => $data], 'cURL Error: ' . $err);
            }
            throw new Exception($err);
        }

        $decoded = json_decode($rawResponse, true);

        if ($this->debug && $logDebug && function_exists('logTransaction')) {
            $safeData = $data;
            if (isset($safeData['card']['number'])) {
                $safeData['card']['number'] = substr($safeData['card']['number'], 0, 6) . '******' . substr($safeData['card']['number'], -4);
            }
            if (isset($safeData['card']['cvv'])) {
                $safeData['card']['cvv'] = '***';
            }
            logTransaction('moneria', [
                'request_url' => $url,
                'request_method' => $method,
                'request_payload' => $safeData,
                'response_code' => $httpCode,
                'response_raw' => $rawResponse,
            ], "HTTP {$httpCode} - {$method} {$url}");
        }

        if ($httpCode >= 400) {
            $errorMessage = 'Erro HTTP ' . $httpCode;
            if (is_array($decoded)) {
                if (!empty($decoded['message'])) {
                    $errorMessage = is_array($decoded['message']) ? implode('; ', $decoded['message']) : $decoded['message'];
                } elseif (!empty($decoded['error'])) {
                    $errorMessage = is_array($decoded['error']) ? implode('; ', $decoded['error']) : $decoded['error'];
                }
            }
            return $decoded ?: ['error' => $errorMessage, 'http_code' => $httpCode];
        }

        return is_array($decoded) ? $decoded : ['raw' => $rawResponse];
    }
}
