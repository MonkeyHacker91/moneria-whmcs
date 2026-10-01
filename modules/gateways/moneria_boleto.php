<?php
/**
 * Moneria Pagamentos — Boleto Bancário para WHMCS
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
function moneria_boleto_MetaData(): array
{
    return [
        'DisplayName' => 'Moneria Pagamentos — Boleto Bancário',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
    ];
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function moneria_boleto_config(): array
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Moneria Pagamentos — Boleto Bancário',
            'Description' => 'Aceite cartão de crédito, PIX e boleto bancário na sua loja com a Moneria. Pagamentos simples, seguros e transparentes para você e seus clientes.',
        ],
        'visibleName' => [
            'FriendlyName' => 'Nome de Exibição (Checkout)',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'Boleto Bancário',
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
            'FriendlyName' => 'Validade do Boleto (Dias)',
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
            'Description' => 'Anexa o PDF oficial do boleto gerado pelo banco nos e-mails de cobrança enviados pelo WHMCS.',
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
 * Payment link / invoice display generation for Boleto.
 *
 * @param array $params
 * @return string
 */
function moneria_boleto_link(array $params): string
{
    $invoiceId = (int)$params['invoiceid'];
    $amount = number_format((float)$params['amount'], 2, '.', '');
    $systemUrl = rtrim($params['systemurl'], '/');
    $clientId = $params['clientId'] ?? '';
    $clientSecret = $params['clientSecret'] ?? '';
    $baseUrl = !empty($params['environment']) ? $params['environment'] : 'https://api.moneria.com.br';
    $debug = !empty($params['debug']);

    if (empty($clientId) || empty($clientSecret)) {
        return '<div class="alert alert-warning">Módulo Moneria Boleto não configurado com Client ID e Client Secret.</div>';
    }

    $checkToken = hash_hmac('sha256', (string)$invoiceId, $clientSecret);
    $checkUrl = $systemUrl . '/modules/gateways/callback/moneria.php?action=check_status&token=' . urlencode($checkToken);
    $postBackUrl = $systemUrl . '/modules/gateways/callback/moneria.php';

    try {
        $client = new MoneriaClient($clientId, $clientSecret, $baseUrl, $debug);

        $document = MoneriaHelper::extractDocument($params);
        if (empty($document)) {
            return '<div class="alert alert-warning" style="margin: 15px 0;">'
                . '<strong>CPF/CNPJ obrigatório:</strong> Por favor, atualize seus dados cadastrais informando seu CPF ou CNPJ para emitir o Boleto Bancário.'
                . '</div>';
        }

        // Delegate to central MoneriaHelper (handles concurrency lock, duplicate prevention, and caching)
        $charge = MoneriaHelper::generateChargeForInvoice($invoiceId, $params);
        if (!$charge) {
            $charge = MoneriaHelper::getInvoiceCharge($invoiceId);
        }

        if (!$charge) {
            return '<div class="alert alert-danger" style="margin: 15px 0;">'
                . '<strong>Não foi possível emitir o Boleto Bancário na Moneria.</strong> Por favor, tente novamente ou entre em contato com o suporte.'
                . '</div>';
        }

        // Extract Boleto data
        $boletoBarCode = null;
        $boletoUrl = null;
        $pixQrCode = null;

        if (!empty($charge['invoices']) && is_array($charge['invoices'])) {
            foreach ($charge['invoices'] as $inv) {
                if (!empty($inv['transactions']) && is_array($inv['transactions'])) {
                    foreach ($inv['transactions'] as $tx) {
                        $type = $tx['type'] ?? '';
                        if ($type === 'BOLETO') {
                            $boletoBarCode = $tx['boletoBarCode'] ?? null;
                            $boletoUrl = $tx['boletoUrl'] ?? null;
                            $pixQrCode = $tx['pixQrCode'] ?? null;
                            break 2;
                        }
                    }
                }
            }
        }

        $amountFormatted = 'R$ ' . number_format((float)$amount, 2, ',', '.');
        $dueDateFormatted = date('d/m/Y', strtotime($charge['dueDate'] ?? date('Y-m-d')));
        $displayTitle = !empty($params['visibleName']) ? $params['visibleName'] : ($params['name'] ?? 'Boleto');
        $themeVars = MoneriaHelper::getThemeVariables($params);

        $templateData = [
            'invoiceId'        => $invoiceId,
            'displayTitle'     => $displayTitle,
            'themeStyle'       => $themeVars['themeStyle'],
            'themeVars'        => $themeVars,
            'amountFormatted'  => $amountFormatted,
            'dueDateFormatted' => $dueDateFormatted,
            'boletoBarCode'    => $boletoBarCode,
            'boletoUrl'        => $boletoUrl,
            'pixQrCode'        => $pixQrCode,
            'checkUrl'         => $checkUrl,
            'assetsUrl'        => $assetsUrl,
            'errorMessage'     => null,
        ];

        return MoneriaHelper::render(__DIR__ . '/moneria/templates/boleto_view.php', $templateData);

    } catch (\Exception $e) {
        if ($debug && function_exists('logTransaction')) {
            logTransaction('moneria_boleto', ['invoiceid' => $invoiceId, 'error' => $e->getMessage()], 'Error in moneria_boleto_link');
        }

        $themeVars = MoneriaHelper::getThemeVariables($params);

        $templateData = [
            'invoiceId'        => $invoiceId,
            'displayTitle'     => $params['name'] ?? 'Boleto',
            'themeStyle'       => $themeVars['themeStyle'],
            'themeVars'        => $themeVars,
            'amountFormatted'  => 'R$ ' . number_format((float)$amount, 2, ',', '.'),
            'dueDateFormatted' => date('d/m/Y'),
            'boletoBarCode'    => null,
            'boletoUrl'        => null,
            'pixQrCode'        => null,
            'checkUrl'         => $checkUrl,
            'assetsUrl'        => $assetsUrl,
            'errorMessage'     => 'Erro ao gerar Boleto na Moneria: ' . $e->getMessage(),
        ];

        return MoneriaHelper::render(__DIR__ . '/moneria/templates/boleto_view.php', $templateData);
    }
}
