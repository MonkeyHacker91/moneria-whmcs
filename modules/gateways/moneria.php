<?php
/**
 * Moneria Pagamentos para WHMCS
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
function moneria_MetaData(): array
{
    return [
        'DisplayName' => 'Moneria Pagamentos (Pix e Boleto)',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
    ];
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function moneria_config(): array
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Moneria Pagamentos (Pix e Boleto)',
            'Description' => 'Aceite cartão de crédito, PIX e boleto bancário na sua loja com a Moneria. Pagamentos simples, seguros e transparentes para você e seus clientes.',
        ],
        'visibleName' => [
            'FriendlyName' => 'Nome de Exibição (Checkout)',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'PIX / Boleto',
            'Description' => 'Nome personalizado do método exibido para o cliente no checkout e no topo da fatura.',
        ],
        'clientId' => [
            'FriendlyName' => 'Client ID',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Client ID da Chave de API gerada no painel da Moneria.',
        ],
        'clientSecret' => [
            'FriendlyName' => 'Client Secret',
            'Type' => 'password',
            'Size' => '60',
            'Default' => '',
            'Description' => 'Client Secret da Chave de API gerada no painel da Moneria.',
        ],
        'environment' => [
            'FriendlyName' => 'URL da API',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'https://api.moneria.com.br',
            'Description' => 'URL base da API Moneria (Padrão: https://api.moneria.com.br)',
        ],
        'paymentMethods' => [
            'FriendlyName' => 'Métodos Ativos',
            'Type' => 'dropdown',
            'Options' => [
                'PIX_BOLETO' => 'PIX e Boleto Bancário',
                'PIX_ONLY'   => 'Apenas PIX',
                'BOLETO_ONLY'=> 'Apenas Boleto Bancário',
            ],
            'Default' => 'PIX_BOLETO',
            'Description' => 'Selecione as formas de pagamento disponíveis na tela da fatura.',
        ],
        'defaultTab' => [
            'FriendlyName' => 'Aba Padrão Inicial',
            'Type' => 'dropdown',
            'Options' => [
                'pix' => 'PIX (Recomendado)',
                'boleto' => 'Boleto Bancário',
            ],
            'Default' => 'pix',
            'Description' => 'Qual aba deve abrir selecionada por padrão ao abrir a fatura.',
        ],
    ] + MoneriaHelper::getThemeConfigFields() + [
        'customFieldDoc' => [
            'FriendlyName' => 'Campo de CPF/CNPJ',
            'Type' => 'text',
            'Size' => '30',
            'Default' => 'CNPJ/CPF',
            'Description' => 'Nome do Campo Personalizado do cliente (Custom Field) usado para CPF ou CNPJ.',
        ],
        'applyFine' => [
            'FriendlyName' => 'Aplicar Multa por Atraso',
            'Type' => 'yesno',
            'Description' => 'Marque para cobrar multa por atraso no boleto após a data de vencimento.',
        ],
        'percentFineValue' => [
            'FriendlyName' => 'Percentual de Multa (%)',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '2.00',
            'Description' => 'Percentual de multa cobrado após o vencimento (Ex: 2.00 para 2%).',
        ],
        'quantityFineDays' => [
            'FriendlyName' => 'Carência da Multa (Dias)',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '1',
            'Description' => 'Quantidade de dias corridos após o vencimento para aplicar a multa (Padrão: 1).',
        ],
        'applyInterest' => [
            'FriendlyName' => 'Aplicar Juros de Mora',
            'Type' => 'yesno',
            'Description' => 'Marque para cobrar juros de mora diários no boleto após o vencimento.',
        ],
        'overdueInterestPercentage' => [
            'FriendlyName' => 'Juros Mensal (%)',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '1.00',
            'Description' => 'Percentual de juros ao mês calculado por dia de atraso (Ex: 1.00 para 1% ao mês).',
        ],
        'expirationDays' => [
            'FriendlyName' => 'Validade da Cobrança (Dias)',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '30',
            'Description' => 'Quantidade de dias após o vencimento em que o boleto pode ser pago antes de expirar.',
        ],
        'cancelOnInvoiceCancelled' => [
            'FriendlyName' => 'Cancelar na Moneria ao Cancelar Fatura',
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'Cancela a cobrança na Moneria automaticamente quando a fatura for Cancelada no WHMCS.',
        ],
        'attachBoletoPdf' => [
            'FriendlyName' => 'Anexar Boleto PDF nos E-mails',
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'Anexa o PDF oficial do boleto gerado pelo banco nos e-mails de fatura enviados pelo WHMCS.',
        ],
        'replaceInvoicePdf' => [
            'FriendlyName' => 'Substituir PDF do WHMCS',
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'Substitui o anexo padrão de fatura do WHMCS pelo PDF oficial do boleto da Moneria.',
        ],
        'showAdminPaymentBox' => [
            'FriendlyName' => 'Painel Rápido na Fatura (Admin)',
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'Exibe caixa com Linha Digitável, Pix Copia e Cola e Link Direto no painel admin da fatura para envio rápido via WhatsApp.',
        ],
        'debug' => [
            'FriendlyName' => 'Modo Debug / Logs',
            'Type' => 'yesno',
            'Description' => 'Registra requisições detalhadas no log de gateway do WHMCS (tblgatewaylog).',
        ],
    ];
}

/**
 * Payment link / invoice display generation.
 *
 * @param array $params
 * @return string
 */
function moneria_link(array $params): string
{
    $invoiceId = (int)$params['invoiceid'];
    $amount = number_format((float)$params['amount'], 2, '.', '');
    $currencyCode = $params['currency'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $assetsUrl = $systemUrl . '/modules/gateways/moneria/assets';

    $clientId = $params['clientId'] ?? '';
    $clientSecret = $params['clientSecret'] ?? '';
    $baseUrl = !empty($params['environment']) ? $params['environment'] : 'https://api.moneria.com.br';
    $debug = !empty($params['debug']);

    if (empty($clientId) || empty($clientSecret)) {
        return '<div class="alert alert-warning">Módulo Moneria não configurado com Client ID e Client Secret.</div>';
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
                . '<strong>CPF/CNPJ obrigatório:</strong> Por favor, atualize seus dados cadastrais informando seu CPF ou CNPJ para gerar a cobrança via Moneria.'
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
                MoneriaHelper::clearCachedBoletoPdf($invoiceId);
                $charge = null; // Recreate charge
            }
        }

        if (!$charge) {
            // 1. Find or create Customer in Moneria
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

            // 2. Determine allowed payment methods
            $methodsConfig = $params['paymentMethods'] ?? 'PIX_BOLETO';
            $paymentMethods = [];
            if ($methodsConfig === 'PIX_ONLY') {
                $paymentMethods = ['PIX'];
            } elseif ($methodsConfig === 'BOLETO_ONLY') {
                $paymentMethods = ['BOLETO'];
            } else {
                $paymentMethods = ['PIX', 'BOLETO'];
            }

            // 3. Due Date
            $dueDate = $expectedDueDate;

            // 4. Build Charge Request
            $chargePayload = [
                'customerId' => $customerId,
                'name' => 'Fatura #' . $invoiceId,
                'description' => 'Pagamento da Fatura #' . $invoiceId . ' - ' . ($params['companyname'] ?? 'WHMCS'),
                'amount' => $amount,
                'paymentMethods' => $paymentMethods,
                'dueDate' => $dueDate,
                'postBackUrl' => $postBackUrl,
                'expirationDays' => (int)($params['expirationDays'] ?? 30),
            ];

            // Multa
            if (!empty($params['applyFine'])) {
                $chargePayload['applyFine'] = true;
                $chargePayload['percentFineValue'] = (float)($params['percentFineValue'] ?? 2.0);
                $chargePayload['quantityFineDays'] = (int)($params['quantityFineDays'] ?? 1);
            }

            // Juros de mora
            if (!empty($params['applyInterest'])) {
                $chargePayload['overdueInterestPercentage'] = (float)($params['overdueInterestPercentage'] ?? 1.0);
                $chargePayload['overdueInterestDays'] = 1;
            }

            $charge = $client->createCharge($chargePayload);

            if (!empty($charge['id'])) {
                MoneriaHelper::saveInvoiceCharge($invoiceId, $charge);
            }
        }

        // Extract Pix and Boleto transaction data from charge response
        $pixQrCode = null;
        $pixQrCodeBase64 = null;
        $boletoBarCode = null;
        $boletoUrl = null;

        if (!empty($charge['invoices']) && is_array($charge['invoices'])) {
            foreach ($charge['invoices'] as $inv) {
                if (!empty($inv['transactions']) && is_array($inv['transactions'])) {
                    foreach ($inv['transactions'] as $tx) {
                        $type = $tx['type'] ?? '';
                        if ($type === 'PIX_QRCODE' || $type === 'PIX') {
                            $pixQrCode = $tx['pixQrCode'] ?? ($tx['emv'] ?? null);
                            $pixQrCodeBase64 = $tx['pixQrCodeBase64'] ?? null;
                        }
                        if ($type === 'BOLETO') {
                            $boletoBarCode = $tx['boletoBarCode'] ?? null;
                            $boletoUrl = $tx['boletoUrl'] ?? null;
                            if (empty($pixQrCode) && !empty($tx['pixQrCode'])) {
                                $pixQrCode = $tx['pixQrCode'];
                                $pixQrCodeBase64 = $tx['pixQrCodeBase64'] ?? null;
                            }
                        }
                    }
                }
            }
        }

        // If we have Pix code but no base64 image, generate fallback QR Code image URL
        if (!empty($pixQrCode) && empty($pixQrCodeBase64)) {
            $pixQrCodeBase64 = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($pixQrCode);
        }

        // Format amount for display
        $amountFormatted = 'R$ ' . number_format((float)$amount, 2, ',', '.');
        $dueDateFormatted = date('d/m/Y', strtotime($charge['dueDate'] ?? date('Y-m-d')));

        $methodsConfig = $params['paymentMethods'] ?? 'PIX_BOLETO';
        $hasPix = ($methodsConfig !== 'BOLETO_ONLY') && (!empty($pixQrCode) || !empty($pixQrCodeBase64));
        $hasBoleto = ($methodsConfig !== 'PIX_ONLY') && (!empty($boletoBarCode) || !empty($boletoUrl));

        $themeVars = MoneriaHelper::getThemeVariables($params);

        $templateData = [
            'invoiceId'        => $invoiceId,
            'displayTitle'     => $displayTitle,
            'themeStyle'       => $themeVars['themeStyle'],
            'themeVars'        => $themeVars,
            'defaultTab'       => $params['defaultTab'] ?? 'pix',
            'amountFormatted'  => $amountFormatted,
            'dueDateFormatted' => $dueDateFormatted,
            'hasPix'           => $hasPix,
            'pixQrCodeBase64'  => $pixQrCodeBase64,
            'pixQrCode'        => $pixQrCode,
            'hasBoleto'        => $hasBoleto,
            'boletoBarCode'    => $boletoBarCode,
            'boletoUrl'        => $boletoUrl,
            'checkUrl'         => $checkUrl,
            'assetsUrl'        => $assetsUrl,
            'errorMessage'     => null,
        ];

        return MoneriaHelper::render(__DIR__ . '/moneria/templates/payment_view.php', $templateData);

    } catch (\Exception $e) {
        if ($debug && function_exists('logTransaction')) {
            logTransaction('moneria', ['invoiceid' => $invoiceId, 'error' => $e->getMessage()], 'Error in moneria_link');
        }

        $themeVars = MoneriaHelper::getThemeVariables($params);

        $templateData = [
            'invoiceId'        => $invoiceId,
            'displayTitle'     => $params['name'] ?? 'PIX / Boleto',
            'themeStyle'       => $themeVars['themeStyle'],
            'themeVars'        => $themeVars,
            'amountFormatted'  => 'R$ ' . number_format((float)$amount, 2, ',', '.'),
            'dueDateFormatted' => date('d/m/Y'),
            'hasPix'           => false,
            'pixQrCodeBase64'  => null,
            'pixQrCode'        => null,
            'hasBoleto'        => false,
            'boletoBarCode'    => null,
            'boletoUrl'        => null,
            'checkUrl'         => $checkUrl,
            'assetsUrl'        => $assetsUrl,
            'errorMessage'     => 'Erro ao processar cobrança na Moneria: ' . $e->getMessage(),
        ];

        return MoneriaHelper::render(__DIR__ . '/moneria/templates/payment_view.php', $templateData);
    }
}
