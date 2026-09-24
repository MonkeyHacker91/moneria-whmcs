<?php
/**
 * Moneria Pagamentos — Cartão de Crédito para WHMCS
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
function moneria_creditcard_MetaData(): array
{
    return [
        'DisplayName' => 'Moneria Pagamentos — Cartão de Crédito',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
    ];
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function moneria_creditcard_config(): array
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Moneria Pagamentos — Cartão de Crédito',
            'Description' => 'Aceite cartão de crédito, PIX e boleto bancário na sua loja com a Moneria. Pagamentos simples, seguros e transparentes para você e seus clientes.',
        ],
        'visibleName' => [
            'FriendlyName' => 'Nome de Exibição (Checkout)',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'Cartão de Crédito',
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
        'maxInstallments' => [
            'FriendlyName' => 'Máximo de Parcelas',
            'Type' => 'dropdown',
            'Options' => [
                '1' => '1x (Apenas à vista)',
                '2' => '2x',
                '3' => '3x',
                '4' => '4x',
                '5' => '5x',
                '6' => '6x',
                '7' => '7x',
                '8' => '8x',
                '9' => '9x',
                '10' => '10x',
                '11' => '11x',
                '12' => '12x',
            ],
            'Default' => '12',
            'Description' => 'Quantidade máxima de parcelas permitidas no cartão.',
        ],
        'minInstallmentValue' => [
            'FriendlyName' => 'Valor Mínimo da Parcela (R$)',
            'Type' => 'text',
            'Size' => '6',
            'Default' => '5.00',
            'Description' => 'Valor mínimo para cada parcela (Ex: 5.00).',
        ],
        'debug' => [
            'FriendlyName' => 'Modo Debug / Logs',
            'Type' => 'yesno',
            'Description' => 'Registra requisições detalhadas no log de gateway do WHMCS (tblgatewaylog).',
        ],
    ];
}

/**
 * Payment link / invoice display generation for Credit Card.
 *
 * @param array $params
 * @return string
 */
function moneria_creditcard_link(array $params): string
{
    $invoiceId = (int)$params['invoiceid'];
    $amount = (float)$params['amount'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $assetsUrl = $systemUrl . '/modules/gateways/moneria/assets';
    $processUrl = $systemUrl . '/modules/gateways/callback/moneria.php';

    $clientId = $params['clientId'] ?? '';
    $clientSecret = $params['clientSecret'] ?? '';

    if (empty($clientId) || empty($clientSecret)) {
        return '<div class="alert alert-warning">Módulo Moneria Cartão de Crédito não configurado com Client ID e Client Secret.</div>';
    }

    $document = MoneriaHelper::extractDocument($params);
    $clientDetails = $params['clientdetails'] ?? [];
    $holderName = trim(($clientDetails['firstname'] ?? '') . ' ' . ($clientDetails['lastname'] ?? ''));
    $maxInstallments = (int)($params['maxInstallments'] ?? 12);
    if ($maxInstallments < 1) {
        $maxInstallments = 1;
    }

    $amountFormatted = 'R$ ' . number_format($amount, 2, ',', '.');
    $displayTitle = !empty($params['visibleName']) ? $params['visibleName'] : ($params['name'] ?? 'Cartão');
    $themeVars = MoneriaHelper::getThemeVariables($params);

    $cardToken = hash_hmac('sha256', 'cc_invoice_' . $invoiceId, $clientSecret);

    $templateData = [
        'invoiceId'        => $invoiceId,
        'cardToken'        => $cardToken,
        'displayTitle'     => $displayTitle,
        'themeStyle'       => $themeVars['themeStyle'],
        'themeVars'        => $themeVars,
        'amountFormatted'  => $amountFormatted,
        'amount'           => $amount,
        'holderName'       => $holderName,
        'holderDocument'   => $document,
        'maxInstallments'  => $maxInstallments,
        'processUrl'       => $processUrl,
        'assetsUrl'        => $assetsUrl,
        'errorMessage'     => null,
    ];

    return MoneriaHelper::render(__DIR__ . '/moneria/templates/creditcard_view.php', $templateData);
}
