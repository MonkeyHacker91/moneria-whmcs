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
                . MoneriaHelper::trans('doc_required')
                . '</div>';
        }

        // Delegate to central MoneriaHelper (handles concurrency lock, duplicate prevention, and caching)
        $charge = MoneriaHelper::generateChargeForInvoice($invoiceId, $params);
        if (!$charge) {
            $charge = MoneriaHelper::getInvoiceCharge($invoiceId);
        }

        if (!$charge) {
            return '<div class="alert alert-danger" style="margin: 15px 0;">'
                . MoneriaHelper::trans('charge_error')
                . '</div>';
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
