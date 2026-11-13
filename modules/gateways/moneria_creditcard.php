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
        'DisableLocalCreditCardInput' => false,
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
        'autoBilling' => [
            'FriendlyName' => 'Salvar Cartão / Cobrança Automática',
            'Type' => 'yesno',
            'Description' => 'Permite ao cliente salvar o cartão (token de bandeira na Moneria, sem guardar número nem CVV) e cobra as próximas faturas automaticamente pelo cron do WHMCS.',
        ],
        'autoBillingClients' => [
            'FriendlyName' => 'Liberar só para os IDs de cliente',
            'Type' => 'text',
            'Size' => '40',
            'Default' => '',
            'Description' => 'Opcional, para testes. IDs separados por vírgula (Ex: 12, 345). Vazio = todos os clientes.',
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
    $dueDate = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('duedate');
    $dueDateFormatted = date('d/m/Y', $dueDate ? strtotime($dueDate) : time());
    $displayTitle = !empty($params['visibleName']) ? $params['visibleName'] : ($params['name'] ?? 'Cartão');
    $themeVars = MoneriaHelper::getThemeVariables($params);

    $cardToken = hash_hmac('sha256', 'cc_invoice_' . $invoiceId, $clientSecret);

    $userId = (int)($clientDetails['userid'] ?? ($clientDetails['id'] ?? 0));
    $allowSaveCard = MoneriaHelper::isCardSavingEnabled($userId, $params);
    $savedCards = [];
    if ($allowSaveCard) {
        foreach (MoneriaHelper::getSavedCards($userId) as $card) {
            unset($card['token']);
            $savedCards[] = $card;
        }
    }

    $templateData = [
        'invoiceId'        => $invoiceId,
        'cardToken'        => $cardToken,
        'displayTitle'     => $displayTitle,
        'themeStyle'       => $themeVars['themeStyle'],
        'themeVars'        => $themeVars,
        'amountFormatted'  => $amountFormatted,
        'dueDateFormatted' => $dueDateFormatted,
        'allowSaveCard'    => $allowSaveCard,
        'savedCards'       => $savedCards,
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

/**
 * Builds holder name, document and billing address from WHMCS client details.
 *
 * @param array $params
 * @return array{name:string, document:string, email:string, phone:string, address:array}
 */
function moneria_creditcard_holderData(array $params): array
{
    $clientDetails = $params['clientdetails'] ?? [];
    $address1 = trim($clientDetails['address1'] ?? '');
    $streetNumber = MoneriaHelper::extractAddressNumber($address1);

    return [
        'name'     => trim(($clientDetails['firstname'] ?? '') . ' ' . ($clientDetails['lastname'] ?? '')),
        'document' => MoneriaHelper::extractDocument($params),
        'email'    => trim($clientDetails['email'] ?? ''),
        'phone'    => MoneriaHelper::formatPhone($clientDetails['phonenumber'] ?? '') ?? '',
        'address'  => [
            'postalCode'   => MoneriaHelper::formatCep($clientDetails['postcode'] ?? ''),
            'street'       => substr($address1 ?: 'Rua Principal', 0, 100),
            'number'       => substr($streetNumber, 0, 10),
            'complement'   => '',
            'neighborhood' => substr(($clientDetails['address2'] ?? '') ?: 'Centro', 0, 60),
            'city'         => substr(trim(($clientDetails['city'] ?? '') ?: 'São Paulo'), 0, 60),
            'state'        => !empty($clientDetails['state']) ? strtoupper(substr(trim($clientDetails['state']), 0, 2)) : 'SP',
            'country'      => 'Brasil',
        ],
    ];
}

/**
 * Tokenizes cards added through WHMCS native Pay Methods (client area / admin).
 * Returns the Moneria token id, stored by WHMCS as the Pay Method remote token.
 *
 * @param array $params
 * @return array
 */
function moneria_creditcard_storeremote(array $params): array
{
    $action = $params['action'] ?? '';
    $gatewayId = $params['gatewayid'] ?? '';

    // Network tokens are kept up to date by the card brand and have no delete endpoint on the API.
    if ($action === 'delete' || ($action === '' && empty($params['cardnum']) && !empty($gatewayId))) {
        return ['status' => 'success', 'rawdata' => 'Cartão removido do WHMCS.'];
    }
    if ($action === 'update' && !empty($gatewayId)) {
        return ['status' => 'success', 'gatewayid' => $gatewayId, 'rawdata' => 'Token mantido.'];
    }

    $userId = (int)($params['clientdetails']['userid'] ?? ($params['clientdetails']['id'] ?? 0));
    if (!MoneriaHelper::isCardSavingEnabled($userId, $params)) {
        return ['status' => 'error', 'rawdata' => 'Salvamento de cartões desativado no gateway Moneria Cartão de Crédito.'];
    }
    if (empty($params['cardnum']) || empty($params['cccvv'])) {
        return ['status' => 'error', 'rawdata' => 'Número do cartão e CVV são obrigatórios para salvar o cartão na Moneria.'];
    }

    try {
        $holder = moneria_creditcard_holderData($params);
        $cardExp = MoneriaHelper::onlyNumbers($params['cardexp'] ?? '');
        $address = $holder['address'];
        unset($address['country']);

        $tokenized = MoneriaHelper::createClient($params)->tokenizeCard([
            'number'          => MoneriaHelper::onlyNumbers($params['cardnum']),
            'holderName'      => strtoupper($holder['name']),
            'holderDocument'  => $holder['document'],
            'holderEmail'     => $holder['email'],
            'holderPhone'     => $holder['phone'],
            'expirationMonth' => (int)substr($cardExp, 0, 2),
            'expirationYear'  => 2000 + (int)substr($cardExp, -2),
            'cvv'             => (string)$params['cccvv'],
            'ipAddress'       => $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1'),
            'billingAddress'  => $address,
        ]);

        return ['status' => 'success', 'gatewayid' => $tokenized['id'], 'rawdata' => $tokenized];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'rawdata' => $e->getMessage()];
    }
}

/**
 * Charges a saved card. Called by the WHMCS cron (automatic billing) and when the
 * client pays with a saved card through the native WHMCS checkout.
 *
 * @param array $params
 * @return array
 */
function moneria_creditcard_capture(array $params): array
{
    if (empty($params['clientId']) || empty($params['clientSecret'])) {
        return ['status' => 'error', 'rawdata' => 'Missing API Credentials'];
    }

    $cardTokenId = (string)($params['gatewayid'] ?? '');
    if ($cardTokenId === '') {
        return ['status' => 'error', 'rawdata' => 'Nenhum cartão Moneria salvo (token) para este cliente.'];
    }

    try {
        $client = MoneriaHelper::createClient($params);
        $holder = moneria_creditcard_holderData($params);

        $moneriaCustomer = $client->findOrCreateCustomer($holder);
        $moneriaCustomerId = $moneriaCustomer['id'] ?? '';
        if (empty($moneriaCustomerId)) {
            return ['status' => 'error', 'rawdata' => 'Falha ao buscar/criar cliente na Moneria'];
        }

        $authResponse = $client->authorizeWithCardToken($cardTokenId, [
            'amount'       => (float)$params['amount'],
            'installments' => 1,
            'customerId'   => $moneriaCustomerId,
            'metadata'     => ['invoice_id' => (int)$params['invoiceid']],
        ]);
        $txStatus = strtoupper($authResponse['status'] ?? '');
        $txId = $authResponse['id'] ?? ($authResponse['transactionId'] ?? '');

        if (in_array($txStatus, ['APPROVED', 'CONFIRMED', 'PAID', 'AUTHORIZED', 'SUCCESS'], true)) {
            return [
                'status'  => 'success',
                'transid' => $txId,
                'rawdata' => $authResponse,
                'fee'     => (float)($authResponse['totalFee'] ?? 0),
            ];
        }

        return ['status' => 'declined', 'transid' => $txId, 'rawdata' => $authResponse];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'rawdata' => $e->getMessage()];
    }
}
