<?php
/**
 * Moneria Pagamentos — PIX Instantâneo para WHMCS
 * 
 * Aceite cartão de crédito, PIX e boleto bancário na sua loja com a Moneria. 
 * Pagamentos simples, seguros e transparentes para você e seus clientes.
 *
 * @version 1.1.0
 * @author Moneria
 * @link https://moneria.com.br
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/moneria/src/MoneriaClient.php';
require_once __DIR__ . '/moneria/src/MoneriaHelper.php';

use Moneria\Gateway\MoneriaClient;
use Moneria\Gateway\MoneriaHelper;
use WHMCS\Database\Capsule;

/**
 * Define gateway metadata.
 *
 * @return array
 */
function moneria_pix_MetaData(): array
{
    return [
        'DisplayName' => 'Moneria Pagamentos — PIX Instantâneo',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
    ];
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function moneria_pix_config(): array
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Moneria Pagamentos — PIX Instantâneo',
            'Description' => 'Aceite cartão de crédito, PIX e boleto bancário na sua loja com a Moneria. Pagamentos simples, seguros e transparentes para você e seus clientes.',
        ],
        'visibleName' => [
            'FriendlyName' => 'Nome de Exibição (Checkout)',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'PIX Instantâneo',
            'Description' => 'Nome personalizado do método exibido para o cliente no checkout e no topo da fatura.',
        ],
        'clientId' => [
            'FriendlyName' => 'Client ID',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Client ID da Chave de API da Moneria.',
        ],
        'clientSecret' => [
            'FriendlyName' => 'Client Secret',
            'Type' => 'password',
            'Size' => '60',
            'Default' => '',
            'Description' => 'Client Secret da Chave de API da Moneria.',
        ],
        'environment' => [
            'FriendlyName' => 'URL da API',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'https://api.moneria.com.br',
            'Description' => 'URL base da API Moneria (Padrão: https://api.moneria.com.br)',
        ],
    ] + MoneriaHelper::getThemeConfigFields() + [
        'customFieldDoc' => [
            'FriendlyName' => 'Campo de CPF/CNPJ',
            'Type' => 'text',
            'Size' => '30',
            'Default' => 'CNPJ/CPF',
            'Description' => 'Nome do Campo Personalizado do cliente (Custom Field) usado para CPF ou CNPJ.',
        ],
        'expirationDays' => [
            'FriendlyName' => 'Dias para Expiração',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '30',
            'Description' => 'Quantidade de dias após o vencimento em que a cobrança Pix expira.',
        ],
        'cancelOnInvoiceCancelled' => [
            'FriendlyName' => 'Cancelar na Moneria ao Cancelar Fatura',
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'Cancela a cobrança na Moneria automaticamente quando a fatura for Cancelada no WHMCS.',
        ],
        'showAdminPaymentBox' => [
            'FriendlyName' => 'Painel Rápido na Fatura (Admin)',
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'Exibe caixa com Chave Pix Copia e Cola e Link Direto no painel admin da fatura para envio rápido via WhatsApp.',
        ],
        'debug' => [
            'FriendlyName' => 'Modo Debug / Logs',
            'Type' => 'yesno',
            'Description' => 'Registra requisições detalhadas no log de gateway do WHMCS (tblgatewaylog).',
        ],
    ];
}

/**
 * Payment link / invoice display generation for PIX.
 *
 * @param array $params
 * @return string
 */
function moneria_pix_link(array $params): string
{
    $invoiceId = (int)$params['invoiceid'];
    $amount = number_format((float)$params['amount'], 2, '.', '');
    $systemUrl = rtrim($params['systemurl'], '/');
    $assetsUrl = $systemUrl . '/modules/gateways/moneria/assets';

    $clientId = $params['clientId'] ?? '';
    $clientSecret = $params['clientSecret'] ?? '';
    $baseUrl = !empty($params['environment']) ? $params['environment'] : 'https://api.moneria.com.br';
    $debug = !empty($params['debug']);

    if (empty($clientId) || empty($clientSecret)) {
        return '<div class="alert alert-warning">Módulo Moneria PIX não configurado com Client ID e Client Secret.</div>';
    }

    $checkToken = hash_hmac('sha256', (string)$invoiceId, $clientSecret);
    $checkUrl = $systemUrl . '/modules/gateways/callback/moneria.php?action=check_status&token=' . urlencode($checkToken);
    $postBackUrl = $systemUrl . '/modules/gateways/callback/moneria.php';

    try {
        $client = new MoneriaClient($clientId, $clientSecret, $baseUrl, $debug);

        // Extract and validate document
        $document = MoneriaHelper::extractDocument($params);
        if (empty($document)) {
            return '<div class="alert alert-warning" style="margin: 15px 0;">'
                . '<strong>CPF/CNPJ obrigatório:</strong> Por favor, atualize seus dados cadastrais informando seu CPF ou CNPJ para gerar o pagamento via Pix.'
                . '</div>';
        }

        // Check if charge already exists in cache/database for this invoice
        $existingCharge = MoneriaHelper::getInvoiceCharge($invoiceId);
        $charge = null;

        $rawInvDueDate = !empty($params['duedate']) ? MoneriaHelper::sanitizeDate($params['duedate']) : date('Y-m-d', strtotime('+3 days'));
        $expectedDueDate = $rawInvDueDate;
        if (strtotime($expectedDueDate) < strtotime(date('Y-m-d'))) {
            $expectedDueDate = date('Y-m-d', strtotime('+1 day'));
        }

        if ($existingCharge && !empty($existingCharge['id'])) {
            $charge = $existingCharge;
            $rawExistingDueDate = $charge['dueDate'] ?? ($charge['invoices'][0]['dueDate'] ?? ($charge['invoices'][0]['due_date'] ?? null));
            $existingDueDate = MoneriaHelper::sanitizeDate($rawExistingDueDate);
            $existingAmount = (float)($charge['amount'] ?? ($charge['total'] ?? 0));
            $existingStatus = strtoupper($charge['status'] ?? '');

            $isStatusValid = !in_array($existingStatus, ['CANCELED', 'CANCELLED', 'EXPIRED', 'FAILED'], true);
            $isDueDateMatch = (!empty($existingDueDate) && $existingDueDate === $expectedDueDate);
            $isAmountMatch = ($existingAmount > 0 && abs($existingAmount - (float)$amount) < 0.01);

            if (!$isStatusValid || !$isDueDateMatch || !$isAmountMatch) {
                try {
                    $client->cancelCharge($existingCharge['id']);
                } catch (\Exception $e) {
                    // Ignore
                }
                $charge = null; // Recreate charge
            }
        }

        if (!$charge) {
            // Find or create Customer in Moneria
            $clientDetails = $params['clientdetails'] ?? [];
            $userId = $clientDetails['userid'] ?? ($clientDetails['id'] ?? 0);

            if ((!$userId || empty($clientDetails['email'])) && class_exists('WHMCS\Database\Capsule')) {
                $dbInvoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
                if ($dbInvoice) {
                    $dbClient = Capsule::table('tblclients')->where('id', $dbInvoice->userid)->first();
                    if ($dbClient) {
                        $clientDetails = (array)$dbClient;
                    }
                }
            }

            $address1 = trim($clientDetails['address1'] ?? '');
            $address2 = trim($clientDetails['address2'] ?? '');
            $streetNumber = MoneriaHelper::extractAddressNumber($address1);
            if ($streetNumber !== 'S/N') {
                $cleanStreet = trim(preg_replace('/,?\s*\b' . preg_quote($streetNumber, '/') . '\b\s*$/', '', $address1));
                if (!empty($cleanStreet)) {
                    $address1 = $cleanStreet;
                }
            }

            $customerPayload = [
                'name'     => trim(($clientDetails['firstname'] ?? '') . ' ' . ($clientDetails['lastname'] ?? '')),
                'document' => $document,
                'email'    => trim($clientDetails['email'] ?? ''),
                'phone'    => $clientDetails['phonenumber'] ?? '',
                'address'  => [
                    'postalCode'   => $clientDetails['postcode'] ?? '',
                    'street'       => $address1 ?: 'Rua Principal',
                    'number'       => $streetNumber,
                    'complement'   => '',
                    'neighborhood' => $address2 ?: 'Centro',
                    'city'         => trim($clientDetails['city'] ?? 'São Paulo'),
                    'state'        => !empty($clientDetails['state']) ? strtoupper(substr(trim($clientDetails['state']), 0, 2)) : 'SP',
                    'country'      => 'Brasil',
                ],
            ];

            $moneriaCustomer = $client->findOrCreateCustomer($customerPayload);
            $customerId = $moneriaCustomer['id'] ?? '';

            if (empty($customerId)) {
                throw new \Exception('Não foi possível sincronizar o cliente com a Moneria.');
            }

            $dueDate = $expectedDueDate;

            $chargePayload = [
                'customerId'     => $customerId,
                'name'           => 'Fatura #' . $invoiceId,
                'description'    => 'Pagamento PIX da Fatura #' . $invoiceId . ' - ' . ($params['companyname'] ?? 'WHMCS'),
                'amount'         => $amount,
                'paymentMethods' => ['PIX'],
                'dueDate'        => $dueDate,
                'postBackUrl'    => $postBackUrl,
                'expirationDays' => (int)($params['expirationDays'] ?? 30),
            ];

            $charge = $client->createCharge($chargePayload);

            if (!empty($charge['id'])) {
                MoneriaHelper::saveInvoiceCharge($invoiceId, $charge);
            }
        }

        // Extract Pix data
        $pixQrCode = null;
        $pixQrCodeBase64 = null;

        if (!empty($charge['invoices']) && is_array($charge['invoices'])) {
            foreach ($charge['invoices'] as $inv) {
                if (!empty($inv['transactions']) && is_array($inv['transactions'])) {
                    foreach ($inv['transactions'] as $tx) {
                        $type = $tx['type'] ?? '';
                        if ($type === 'PIX_QRCODE' || $type === 'PIX' || !empty($tx['pixQrCode'])) {
                            $pixQrCode = $tx['pixQrCode'] ?? ($tx['emv'] ?? null);
                            $pixQrCodeBase64 = $tx['pixQrCodeBase64'] ?? null;
                            break 2;
                        }
                    }
                }
            }
        }

        if (!empty($pixQrCode) && empty($pixQrCodeBase64)) {
            $pixQrCodeBase64 = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($pixQrCode);
        }

        $amountFormatted = 'R$ ' . number_format((float)$amount, 2, ',', '.');
        $displayTitle = !empty($params['visibleName']) ? $params['visibleName'] : ($params['name'] ?? 'PIX');
        $themeVars = MoneriaHelper::getThemeVariables($params);

        $templateData = [
            'invoiceId'        => $invoiceId,
            'displayTitle'     => $displayTitle,
            'themeStyle'       => $themeVars['themeStyle'],
            'themeVars'        => $themeVars,
            'amountFormatted'  => $amountFormatted,
            'pixQrCodeBase64'  => $pixQrCodeBase64,
            'pixQrCode'        => $pixQrCode,
            'checkUrl'         => $checkUrl,
            'assetsUrl'        => $assetsUrl,
            'errorMessage'     => null,
        ];

        return MoneriaHelper::render(__DIR__ . '/moneria/templates/pix_view.php', $templateData);

    } catch (\Exception $e) {
        if ($debug && function_exists('logTransaction')) {
            logTransaction('moneria_pix', ['invoiceid' => $invoiceId, 'error' => $e->getMessage()], 'Error in moneria_pix_link');
        }

        $themeVars = MoneriaHelper::getThemeVariables($params);

        $templateData = [
            'invoiceId'        => $invoiceId,
            'displayTitle'     => $params['name'] ?? 'PIX',
            'themeStyle'       => $themeVars['themeStyle'],
            'themeVars'        => $themeVars,
            'amountFormatted'  => 'R$ ' . number_format((float)$amount, 2, ',', '.'),
            'pixQrCodeBase64'  => null,
            'pixQrCode'        => null,
            'checkUrl'         => $checkUrl,
            'assetsUrl'        => $assetsUrl,
            'errorMessage'     => 'Erro ao gerar Pix na Moneria: ' . $e->getMessage(),
        ];

        return MoneriaHelper::render(__DIR__ . '/moneria/templates/pix_view.php', $templateData);
    }
}
