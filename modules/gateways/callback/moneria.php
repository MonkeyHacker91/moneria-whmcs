<?php
/**
 * Moneria Payment Gateway Callback / Webhook Handler
 * 
 * Handles incoming webhooks from Moneria to mark WHMCS invoices as Paid,
 * handles AJAX status checks for real-time invoice auto-refresh,
 * and handles credit card direct authorizations.
 *
 * @version 1.2.0
 * @author Moneria
 * @link https://moneria.com.br
 */

// Require WHMCS init & gateway functions
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

require_once __DIR__ . '/../moneria/src/MoneriaClient.php';
require_once __DIR__ . '/../moneria/src/MoneriaHelper.php';

use Moneria\Gateway\MoneriaClient;
use Moneria\Gateway\MoneriaHelper;
use WHMCS\Database\Capsule;

// Helper to safely find active Moneria gateway params without throwing fatal exceptions
function moneriaGetActiveParams() {
    $activeGateways = Capsule::table('tblpaymentgateways')
        ->where('setting', 'visible')
        ->where('value', 'on')
        ->pluck('gateway')
        ->toArray();

    // 1. Try unified 'moneria'
    if (in_array('moneria', $activeGateways, true)) {
        try {
            $p = getGatewayVariables('moneria');
            if (!empty($p['clientId'])) {
                return ['moneria', $p];
            }
        } catch (\Throwable $t) {}
    }

    // 2. Try credit card module if active
    $modules = ['moneria_creditcard'];
    foreach ($modules as $mod) {
        if (in_array($mod, $activeGateways, true)) {
            try {
                $p = getGatewayVariables($mod);
                if (!empty($p['clientId'])) {
                    return [$mod, $p];
                }
            } catch (\Throwable $t) {}
        }
    }

    // 3. Fallback to moneria
    try {
        return ['moneria', getGatewayVariables('moneria')];
    } catch (\Throwable $t) {
        return ['moneria', []];
    }
}

list($gatewayModuleName, $gatewayParams) = moneriaGetActiveParams();

$rawBody = file_get_contents('php://input');
$jsonBody = json_decode($rawBody, true) ?: [];

$action = $_GET['action'] ?? ($_POST['action'] ?? ($jsonBody['action'] ?? ''));

// -------------------------------------------------------------
// 1. AJAX Status Check (Invoked by JavaScript on Invoice Page)
// -------------------------------------------------------------
if ($action === 'check_status') {
    header('Content-Type: application/json; charset=utf-8');
    $invoiceId = (int)($_GET['invoiceid'] ?? ($_POST['invoiceid'] ?? ($jsonBody['invoiceid'] ?? 0)));
    $token = trim((string)($_GET['token'] ?? ($_POST['token'] ?? ($jsonBody['token'] ?? ''))));

    if ($invoiceId <= 0) {
        http_response_code(400);
        echo json_encode(['paid' => false, 'error' => 'Invalid invoice ID']);
        exit;
    }

    try {
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first(['id', 'userid', 'status', 'paymentmethod']);
        if (!$invoice) {
            http_response_code(404);
            echo json_encode(['paid' => false, 'error' => 'Invoice not found']);
            exit;
        }

        // Authorization check: User must be invoice owner, or admin, or provide valid HMAC token
        $userId = (int)($_SESSION['uid'] ?? 0);
        $adminId = (int)($_SESSION['adminid'] ?? 0);
        $secret = (string)($gatewayParams['clientSecret'] ?? '');
        if (!empty($invoice->paymentmethod)) {
            try {
                $p = getGatewayVariables($invoice->paymentmethod);
                if (!empty($p['clientSecret'])) {
                    $secret = (string)$p['clientSecret'];
                }
            } catch (\Throwable $t) {}
        }
        $expectedToken = !empty($secret) ? hash_hmac('sha256', (string)$invoiceId, $secret) : '';

        $isAuthorized = ($userId > 0 && (int)$invoice->userid === $userId)
            || ($adminId > 0)
            || (!empty($expectedToken) && !empty($token) && hash_equals($expectedToken, $token));

        if (!$isAuthorized) {
            http_response_code(403);
            echo json_encode(['paid' => false, 'error' => 'Acesso não autorizado.']);
            exit;
        }

        $isPaid = (strcasecmp($invoice->status, 'Paid') === 0);
        echo json_encode([
            'paid'   => $isPaid,
            'status' => $invoice->status,
        ]);
        exit;
    } catch (\Throwable $e) {
        logTransaction('moneria', ['invoiceid' => $invoiceId, 'error' => $e->getMessage()], 'Erro em check_status');
        http_response_code(500);
        echo json_encode(['paid' => false, 'error' => 'Erro interno ao consultar a fatura.']);
        exit;
    }
}

// -------------------------------------------------------------
// 2. AJAX Credit Card Authorization Handler
// -------------------------------------------------------------
if ($action === 'process_creditcard') {
    header('Content-Type: application/json; charset=utf-8');

    $invoiceId = (int)($jsonBody['invoiceid'] ?? ($_POST['invoiceid'] ?? 0));
    $cardToken = trim((string)($jsonBody['token'] ?? ($_POST['token'] ?? '')));
    $cardNumber = MoneriaHelper::onlyNumbers($jsonBody['number'] ?? ($_POST['number'] ?? ''));
    $holderName = trim($jsonBody['name'] ?? ($_POST['name'] ?? ''));
    $holderDoc = MoneriaHelper::onlyNumbers($jsonBody['document'] ?? ($_POST['document'] ?? ''));
    $expMonth = (int)($jsonBody['month'] ?? ($_POST['month'] ?? 0));
    $expYear = (int)($jsonBody['year'] ?? ($_POST['year'] ?? 0));
    $cvv = trim($jsonBody['cvv'] ?? ($_POST['cvv'] ?? ''));
    $installments = (int)($jsonBody['installments'] ?? ($_POST['installments'] ?? 1));
    $payMethodId = (int)($jsonBody['paymethod_id'] ?? ($_POST['paymethod_id'] ?? 0));
    $saveCard = !empty($jsonBody['save_card'] ?? ($_POST['save_card'] ?? false));

    if ($invoiceId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Fatura inválida ou não informada.']);
        exit;
    }

    try {
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice || strcasecmp($invoice->status, 'Unpaid') !== 0) {
            echo json_encode(['success' => false, 'error' => 'Esta fatura já foi paga ou cancelada.']);
            exit;
        }

        // Security 1: User must be logged in as invoice owner, or admin
        $userId = (int)($_SESSION['uid'] ?? 0);
        $adminId = (int)($_SESSION['adminid'] ?? 0);

        if ($userId <= 0 && $adminId <= 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessão expirada. Faça login para realizar o pagamento.']);
            exit;
        }

        if ($userId > 0 && (int)$invoice->userid !== $userId && $adminId <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Você não tem permissão para realizar o pagamento desta fatura.']);
            exit;
        }

        // Security 2: Strict HMAC CSRF Token verification
        // The form may have been rendered by moneria_creditcard while the invoice belongs to another Moneria gateway.
        $secrets = [(string)($gatewayParams['clientSecret'] ?? '')];
        foreach (array_unique(array_filter([$invoice->paymentmethod, 'moneria_creditcard'])) as $gw) {
            try {
                $p = getGatewayVariables($gw);
                if (!empty($p['clientSecret'])) {
                    $secrets[] = (string)$p['clientSecret'];
                }
            } catch (\Throwable $t) {}
        }
        $validCardToken = false;
        foreach (array_unique(array_filter($secrets)) as $secret) {
            if ($cardToken !== '' && hash_equals(hash_hmac('sha256', 'cc_invoice_' . $invoiceId, $secret), $cardToken)) {
                $validCardToken = true;
                break;
            }
        }
        if (!$validCardToken) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token de segurança inválido ou expirado. Atualize a página e tente novamente.']);
            exit;
        }

        // Anti card-testing: limit attempts per invoice, per client and per IP (15 min window)
        $rlIp = MoneriaHelper::getClientIp();
        if (
            MoneriaHelper::rateLimitExceeded('cc_inv_' . $invoiceId, 5, 900)
            || MoneriaHelper::rateLimitExceeded('cc_usr_' . (int)$invoice->userid, 10, 900)
            || MoneriaHelper::rateLimitExceeded('cc_ip_' . $rlIp, 15, 900)
        ) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.']);
            exit;
        }
        MoneriaHelper::rateLimitHit('cc_inv_' . $invoiceId, 900);
        MoneriaHelper::rateLimitHit('cc_usr_' . (int)$invoice->userid, 900);
        MoneriaHelper::rateLimitHit('cc_ip_' . $rlIp, 900);

        // Basic card field validation (clear messages instead of generic API errors)
        if ($payMethodId <= 0) {
            if ($holderName === '' || $cvv === '' || !ctype_digit($cvv) || strlen($cvv) < 3 || strlen($cvv) > 4) {
                echo json_encode(['success' => false, 'error' => 'Dados do cartão incompletos ou inválidos.']);
                exit;
            }
            $fullYear = $expYear < 100 ? (2000 + $expYear) : $expYear;
            if ($expMonth < 1 || $expMonth > 12 || $fullYear < (int)date('Y') || ($fullYear === (int)date('Y') && $expMonth < (int)date('n'))) {
                echo json_encode(['success' => false, 'error' => 'Validade do cartão inválida.']);
                exit;
            }
        }
        $maxInst = (int)(getGatewayVariables('moneria_creditcard')['maxInstallments'] ?? 12);
        if ($installments < 1 || $installments > max(1, $maxInst)) {
            echo json_encode(['success' => false, 'error' => 'Número de parcelas inválido.']);
            exit;
        }

        $savedCard = null;
        if ($payMethodId > 0) {
            $savedCard = MoneriaHelper::isCardSavingEnabled((int)$invoice->userid) ? MoneriaHelper::findSavedCard((int)$invoice->userid, $payMethodId) : null;
            if (!$savedCard) {
                echo json_encode(['success' => false, 'error' => 'Cartão salvo não encontrado.']);
                exit;
            }
            if (empty($holderDoc)) {
                $holderDoc = MoneriaHelper::extractDocument([
                    'invoiceid'      => $invoiceId,
                    'customFieldDoc' => getGatewayVariables('moneria_creditcard')['customFieldDoc'] ?? '',
                ]);
            }
            $installments = 1;
        } elseif (empty($cardNumber) || strlen($cardNumber) < 13) {
            echo json_encode(['success' => false, 'error' => 'Número do cartão inválido.']);
            exit;
        }

        if (empty($holderDoc) || (strlen($holderDoc) !== 11 && strlen($holderDoc) !== 14)) {
            echo json_encode(['success' => false, 'error' => 'CPF/CNPJ do titular inválido.']);
            exit;
        }

        $clientId = $gatewayParams['clientId'] ?? '';
        $clientSecret = $gatewayParams['clientSecret'] ?? '';
        $baseUrl = !empty($gatewayParams['environment']) ? $gatewayParams['environment'] : 'https://api.moneria.com.br';
        $debug = !empty($gatewayParams['debug']);

        $client = new MoneriaClient($clientId, $clientSecret, $baseUrl, $debug);

        // Fetch client details
        $whmcsClient = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
        if (!$whmcsClient) {
            echo json_encode(['success' => false, 'error' => 'Cadastro de cliente não encontrado.']);
            exit;
        }

        $address1 = trim($whmcsClient->address1 ?? '');
        $streetNumber = MoneriaHelper::extractAddressNumber($address1);
        if ($streetNumber !== 'S/N') {
            $cleanStreet = trim(preg_replace('/,?\s*\b' . preg_quote($streetNumber, '/') . '\b\s*$/', '', $address1));
            if (!empty($cleanStreet)) {
                $address1 = $cleanStreet;
            }
        }

        // Sincroniza cliente na Moneria
        $customerPayload = [
            'name'     => trim($whmcsClient->firstname . ' ' . $whmcsClient->lastname),
            'document' => $holderDoc,
            'email'    => trim($whmcsClient->email),
            'phone'    => $whmcsClient->phonenumber,
            'address'  => [
                'postalCode'   => $whmcsClient->postcode,
                'street'       => $address1 ?: 'Rua Principal',
                'number'       => $streetNumber,
                'complement'   => '',
                'neighborhood' => $whmcsClient->address2 ?: 'Centro',
                'city'         => trim($whmcsClient->city ?: 'São Paulo'),
                'state'        => !empty($whmcsClient->state) ? strtoupper(substr(trim($whmcsClient->state), 0, 2)) : 'SP',
                'country'      => 'Brasil',
            ],
        ];

        $moneriaCustomer = $client->findOrCreateCustomer($customerPayload);
        $customerId = $moneriaCustomer['id'] ?? '';

        if (empty($customerId)) {
            echo json_encode(['success' => false, 'error' => 'Não foi possível cadastrar o cliente na Moneria.']);
            exit;
        }

        // Determine client IP
        $clientIp = MoneriaHelper::getClientIp();

        // Build credit card authorize payload
        $cardPayload = [
            'amount'       => (float)$invoice->total,
            'installments' => $installments > 0 ? $installments : 1,
            'customerId'   => $customerId,
            'card'         => [
                'number'          => $cardNumber,
                'holderName'      => strtoupper($holderName),
                'holderDocument'  => $holderDoc,
                'holderEmail'     => trim($whmcsClient->email),
                'holderPhone'     => MoneriaHelper::formatPhone($whmcsClient->phonenumber) ?? ('+55' . MoneriaHelper::onlyNumbers($whmcsClient->phonenumber)),
                'expirationMonth' => $expMonth,
                'expirationYear'  => $expYear < 100 ? (2000 + $expYear) : $expYear,
                'cvv'             => $cvv,
                'ipAddress'       => $clientIp,
                'metadata'        => ['invoice_id' => $invoiceId],
                'billingAddress'  => [
                    'postalCode'   => MoneriaHelper::formatCep($whmcsClient->postcode),
                    'street'       => substr($address1 ?: 'Rua Principal', 0, 100),
                    'number'       => substr($streetNumber, 0, 10),
                    'complement'   => '',
                    'neighborhood' => substr($whmcsClient->address2 ?: 'Centro', 0, 60),
                    'city'         => substr(trim($whmcsClient->city ?: 'São Paulo'), 0, 60),
                    'state'        => substr(!empty($whmcsClient->state) ? strtoupper(substr(trim($whmcsClient->state), 0, 2)) : 'SP', 0, 2),
                    'country'      => 'Brasil',
                ],
            ],
        ];

        if ($savedCard) {
            $tokenPayload = $cardPayload;
            unset($tokenPayload['card']);
            $tokenPayload['metadata'] = ['invoice_id' => $invoiceId];
            $authResponse = $client->authorizeWithCardToken($savedCard['token'], $tokenPayload);
        } else {
            $authResponse = $client->authorizeCreditCard($cardPayload);
        }
        $txStatus = strtoupper($authResponse['status'] ?? '');
        $txId = $authResponse['id'] ?? ($authResponse['transactionId'] ?? uniqid('tx_'));

        if (in_array($txStatus, ['APPROVED', 'CONFIRMED', 'PAID', 'AUTHORIZED', 'SUCCESS'])) {
            $fee = (float)($authResponse['totalFee'] ?? ($authResponse['fee'] ?? 0));
            $paymentMethodName = $invoice->paymentmethod ?: 'moneria';

            checkCbTransID($txId);
            addInvoicePayment(
                $invoiceId,
                $txId,
                $invoice->total,
                $fee,
                $paymentMethodName
            );

            logTransaction($paymentMethodName, $authResponse, "Cartão Aprovado para Fatura #{$invoiceId}");

            // The payment is already applied; failing to save the card must not fail the request.
            if (!$savedCard && $saveCard && MoneriaHelper::isCardSavingEnabled((int)$invoice->userid)) {
                try {
                    $tokenCard = $cardPayload['card'];
                    unset($tokenCard['metadata'], $tokenCard['billingAddress']['country']);

                    $savedPayMethodId = MoneriaHelper::saveCardForClient((int)$invoice->userid, $client, $tokenCard);
                    MoneriaHelper::moveClientBillingToCard((int)$invoice->userid);
                    logTransaction('moneria_creditcard', ['invoiceid' => $invoiceId, 'paymethod_id' => $savedPayMethodId], "Cartão salvo para cobrança automática (cliente #{$invoice->userid})");
                } catch (\Throwable $t) {
                    logTransaction('moneria_creditcard', ['invoiceid' => $invoiceId, 'error' => $t->getMessage()], 'Falha ao salvar cartão (pagamento já aprovado)');
                }
            }

            echo json_encode(['success' => true, 'status' => $txStatus, 'transactionId' => $txId]);
            exit;
        } else {
            $declinedMsg = $authResponse['message'] ?? ($authResponse['error'] ?? 'Transação não autorizada pela operadora do cartão.');
            if (is_array($declinedMsg)) {
                $declinedMsg = implode('; ', $declinedMsg);
            }
            logTransaction('moneria', $authResponse, "Cartão Recusado para Fatura #{$invoiceId}: {$declinedMsg}");
            // Raw gateway/issuer details stay in tblgatewaylog; the customer only sees a generic message.
            echo json_encode(['success' => false, 'error' => 'Pagamento não autorizado pela operadora do cartão. Verifique os dados ou tente outro cartão.']);
            exit;
        }

    } catch (\Throwable $e) {
        logTransaction('moneria', ['invoiceid' => $invoiceId, 'error' => $e->getMessage()], 'Exceção ao Processar Cartão');
        echo json_encode(['success' => false, 'error' => 'Não foi possível processar o pagamento. Tente novamente ou contate o suporte.']);
        exit;
    }
}

// -------------------------------------------------------------
// 3. Moneria Webhook / IPN Notification Handler
// -------------------------------------------------------------
$payload = !empty($jsonBody) ? $jsonBody : $_POST;

// Handle ping / test requests or empty GET requests
if (empty($payload)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'status'  => 'ok',
        'gateway' => 'moneria',
        'message' => 'Moneria Webhook endpoint is active and listening.',
        'time'    => date('Y-m-d H:i:s'),
    ]);
    exit;
}

// Full raw webhook bodies only when gateway debug is on (avoid PII in tblgatewaylog by default)
if (!empty($gatewayParams['debug'])) {
    logTransaction('moneria', [
        'raw_body' => $rawBody,
        'parsed'   => $payload,
        'headers'  => function_exists('getallheaders') ? getallheaders() : [],
    ], 'Moneria Webhook Notification Received');
}

$transData = $payload['data'] ?? $payload;

$transactionId = $transData['id'] ?? ($transData['transactionId'] ?? ($payload['id'] ?? uniqid('mon_')));
$chargeId = $transData['chargeId'] ?? ($transData['charge']['id'] ?? ($transData['id'] ?? ($payload['chargeId'] ?? ($payload['id'] ?? ''))));
$status = strtoupper($transData['status'] ?? ($payload['status'] ?? ''));
$event = strtolower($payload['event'] ?? ($transData['event'] ?? ''));
$amount = (float)($transData['amount'] ?? ($transData['paidAmount'] ?? ($transData['totalAmount'] ?? ($payload['amount'] ?? 0))));
$fee = (float)($transData['totalFee'] ?? ($transData['fee'] ?? ($payload['fee'] ?? 0)));

// If event indicates payment, map to PAID status
if (in_array($event, ['charge.paid', 'charge.confirmed', 'transaction.paid', 'transaction.approved', 'payment.received', 'pix.received', 'boleto.paid'], true)) {
    $status = 'PAID';
}

$invoiceId = null;

// 1. Find invoice by Charge ID or Transaction ID
if (!empty($chargeId)) {
    $invoiceId = MoneriaHelper::findInvoiceIdByCharge($chargeId);
}

// 2. Find invoice by query string param ?invoiceid=
if (!$invoiceId && !empty($_GET['invoiceid'])) {
    $invoiceId = (int)$_GET['invoiceid'];
}

// 3. Find invoice by description
if (!$invoiceId && !empty($transData['description'])) {
    if (preg_match('/#(\d+)/', $transData['description'], $matches)) {
        $invoiceId = (int)$matches[1];
    }
}

// 4. Find invoice by name
if (!$invoiceId && !empty($transData['name'])) {
    if (preg_match('/#(\d+)/', $transData['name'], $matches)) {
        $invoiceId = (int)$matches[1];
    }
}

// 5. Find invoice by metadata
if (!$invoiceId && !empty($transData['metadata']['invoice_id'])) {
    $invoiceId = (int)$transData['metadata']['invoice_id'];
}

if (!$invoiceId) {
    logTransaction('moneria', $payload, 'Webhook Ignored: Invoice ID could not be identified');
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'message' => 'Invoice not found for this transaction']);
    exit;
}

// Check invoice record in WHMCS
$invRecord = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
if (!$invRecord) {
    logTransaction('moneria', $payload, "Webhook Ignored: Invoice #{$invoiceId} does not exist in WHMCS");
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'message' => "Invoice #{$invoiceId} not found"]);
    exit;
}

// Ensure amount is valid
if ($amount <= 0 && $invRecord->total > 0) {
    $amount = (float)$invRecord->total;
}

// Determine active gateway module to log against
$actualGateway = $invRecord->paymentmethod ?: 'moneria';
$activeGateways = Capsule::table('tblpaymentgateways')->where('setting', 'visible')->where('value', 'on')->pluck('gateway')->toArray();
if (!in_array($actualGateway, $activeGateways, true)) {
    $actualGateway = 'moneria';
}

// Check if already paid
if (strcasecmp($invRecord->status, 'Paid') === 0) {
    logTransaction($actualGateway, $payload, "Webhook Notice: Invoice #{$invoiceId} is already Marked as Paid");
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['status' => 'already_paid', 'invoiceId' => $invoiceId]);
    exit;
}

// Verify status against live Moneria API (Zero-Trust Server-to-Server Verification)
$paidStatuses = ['PAID', 'APPROVED', 'CONFIRMED', 'RECEIVED', 'COMPLETED', 'SETTLED', 'SUCCEEDED', 'SUCCESS', 'CONCLUDED'];

$verifiedLive = false;

if (in_array($status, $paidStatuses, true)) {
    // Payment requires a local charge mapping for this invoice. Never pay using only a
    // webhook-supplied chargeId + attacker-controlled ?invoiceid= / description fallbacks.
    $record = Capsule::table('mod_moneria_charges')->where('invoice_id', $invoiceId)->first();
    $actualChargeId = !empty($record->charge_id) ? (string)$record->charge_id : '';

    if ($actualChargeId === '') {
        logTransaction($actualGateway, $payload, "Webhook Rejected (Security): No local charge mapping for invoice #{$invoiceId}");
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'rejected', 'error' => 'Missing local charge mapping']);
        exit;
    }

    // Webhook chargeId, when present, must match the local mapping (no foreign charge swap).
    if (!empty($chargeId) && !hash_equals($actualChargeId, (string)$chargeId)) {
        logTransaction($actualGateway, [
            'invoice_id' => $invoiceId,
            'local_charge' => $actualChargeId,
            'webhook_charge' => $chargeId,
        ], "Webhook Rejected (Security): chargeId does not match local mapping for invoice #{$invoiceId}");
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'rejected', 'error' => 'Charge mismatch']);
        exit;
    }

    try {
        $client = MoneriaHelper::createClient($gatewayParams);
        $liveCharge = $client->getCharge($actualChargeId);
        $liveStatus = strtoupper($liveCharge['status'] ?? '');

        // Bind live charge metadata to this invoice when Moneria provides it
        $liveMetaInvoice = (int)($liveCharge['metadata']['invoice_id'] ?? ($liveCharge['metadata']['invoiceId'] ?? 0));
        if ($liveMetaInvoice > 0 && $liveMetaInvoice !== (int)$invoiceId) {
            logTransaction($actualGateway, [
                'invoice_id' => $invoiceId,
                'live_metadata_invoice' => $liveMetaInvoice,
                'charge' => $actualChargeId,
            ], "Webhook Rejected (Security): live charge metadata invoice mismatch");
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'rejected', 'error' => 'Invoice mismatch']);
            exit;
        }

        $paidTx = null;
        if (!empty($liveCharge['invoices'][0]['transactions'])) {
            foreach ($liveCharge['invoices'][0]['transactions'] as $txItem) {
                $txSt = strtoupper($txItem['status'] ?? '');
                if (in_array($txSt, $paidStatuses, true)) {
                    $paidTx = $txItem;
                    break;
                }
            }
        }

        if ($paidTx) {
            $transactionId = $paidTx['id'] ?? $transactionId;
            $amount = (float)($paidTx['amount'] ?? $amount);
            $fee = (float)($paidTx['totalFee'] ?? ($paidTx['fee'] ?? $fee));
            $status = 'PAID';
            $verifiedLive = true;
        } elseif (in_array($liveStatus, $paidStatuses, true)) {
            $status = 'PAID';
            $verifiedLive = true;
            $liveAmount = (float)($liveCharge['amount'] ?? ($liveCharge['total'] ?? 0));
            if ($liveAmount > 0) {
                $amount = $liveAmount;
            }
        } else {
            logTransaction($actualGateway, [
                'webhook_payload' => $payload,
                'live_charge' => $liveCharge,
            ], "Webhook Rejected (Security): Charge #{$actualChargeId} status on Moneria API is '{$liveStatus}', not PAID.");

            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'rejected', 'error' => "Live charge status is {$liveStatus}"]);
            exit;
        }
    } catch (\Throwable $t) {
        logTransaction($actualGateway, [
            'webhook_payload' => $payload,
            'exception' => $t->getMessage(),
        ], "Webhook Verification Failed (Security): Could not verify charge #{$actualChargeId} on Moneria API");

        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Moneria API verification failed']);
        exit;
    }
}

if ($verifiedLive && in_array($status, $paidStatuses, true)) {
    checkCbTransID($transactionId);

    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $amount,
        $fee,
        $actualGateway
    );

    if (!empty($chargeId)) {
        Capsule::table('mod_moneria_charges')
            ->where('invoice_id', $invoiceId)
            ->update(['status' => 'PAID', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    logTransaction($actualGateway, $payload, "Payment Successful (Webhook Verified & Applied) for Invoice #{$invoiceId}");

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['status' => 'success', 'invoiceId' => $invoiceId, 'action' => 'paid']);
    exit;
}

if (in_array($status, ['REFUNDED'], true)) {
    logTransaction($actualGateway, $payload, "Transaction Refunded in Moneria for Invoice #{$invoiceId}");
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['status' => 'refunded', 'invoiceId' => $invoiceId]);
    exit;
}

if (in_array($status, ['CANCELLED', 'CANCELED', 'FAILED', 'EXPIRED'], true)) {
    // Zero-trust: never change local state from an unauthenticated webhook. Confirm on the Moneria API first.
    $record = Capsule::table('mod_moneria_charges')->where('invoice_id', $invoiceId)->first();
    $actualChargeId = !empty($record->charge_id) ? $record->charge_id : $chargeId;
    $liveStatus = '';

    if (!empty($actualChargeId)) {
        try {
            $liveCharge = MoneriaHelper::createClient($gatewayParams)->getCharge($actualChargeId);
            $liveStatus = strtoupper($liveCharge['status'] ?? '');
        } catch (\Throwable $t) {
            logTransaction($actualGateway, ['webhook_payload' => $payload, 'exception' => $t->getMessage()], "Webhook Verification Failed (Security): Could not verify charge #{$actualChargeId}");
        }
    }

    if (!in_array($liveStatus, ['CANCELLED', 'CANCELED', 'FAILED', 'EXPIRED'], true)) {
        logTransaction($actualGateway, $payload, "Webhook Rejected (Security): status '{$status}' not confirmed on Moneria API for invoice #{$invoiceId}");
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'rejected', 'error' => 'Status not confirmed']);
        exit;
    }

    Capsule::table('mod_moneria_charges')
        ->where('invoice_id', $invoiceId)
        ->update(['status' => $liveStatus, 'updated_at' => date('Y-m-d H:i:s')]);
    logTransaction($actualGateway, $payload, "Transaction status updated: {$liveStatus} for Invoice #{$invoiceId}");
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['status' => 'updated', 'invoiceId' => $invoiceId, 'newStatus' => $liveStatus]);
    exit;
}

logTransaction($actualGateway, $payload, "Webhook processed with unhandled status: {$status}");
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode(['status' => 'ignored', 'message' => "Unhandled status: {$status}"]);
