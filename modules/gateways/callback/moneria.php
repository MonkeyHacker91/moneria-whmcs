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

    // 2. Try individual modules if active
    $modules = ['moneria_pix', 'moneria_boleto', 'moneria_creditcard'];
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
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first(['id', 'userid', 'status']);
        if (!$invoice) {
            http_response_code(404);
            echo json_encode(['paid' => false, 'error' => 'Invoice not found']);
            exit;
        }

        // Authorization check: User must be invoice owner, or admin, or provide valid HMAC token
        $userId = (int)($_SESSION['uid'] ?? 0);
        $adminId = (int)($_SESSION['adminid'] ?? 0);
        $secret = (string)($gatewayParams['clientSecret'] ?? '');
        $expectedToken = !empty($secret) ? hash_hmac('sha256', (string)$invoiceId, $secret) : '';

        $isAuthorized = ($userId > 0 && (int)$invoice->userid === $userId)
            || ($adminId > 0)
            || (!empty($token) && hash_equals($expectedToken, $token));

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
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['paid' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 2. AJAX Credit Card Authorization Handler
// -------------------------------------------------------------
if ($action === 'process_creditcard') {
    header('Content-Type: application/json; charset=utf-8');

    $invoiceId = (int)($jsonBody['invoiceid'] ?? ($_POST['invoiceid'] ?? 0));
    $cardNumber = MoneriaHelper::onlyNumbers($jsonBody['number'] ?? ($_POST['number'] ?? ''));
    $holderName = trim($jsonBody['name'] ?? ($_POST['name'] ?? ''));
    $holderDoc = MoneriaHelper::onlyNumbers($jsonBody['document'] ?? ($_POST['document'] ?? ''));
    $expMonth = (int)($jsonBody['month'] ?? ($_POST['month'] ?? 0));
    $expYear = (int)($jsonBody['year'] ?? ($_POST['year'] ?? 0));
    $cvv = trim($jsonBody['cvv'] ?? ($_POST['cvv'] ?? ''));
    $installments = (int)($jsonBody['installments'] ?? ($_POST['installments'] ?? 1));

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

        // Security: User must be logged in as invoice owner, or admin
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

        if (empty($cardNumber) || strlen($cardNumber) < 13) {
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
        $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        }

        // Build credit card authorize payload
        $cardPayload = [
            'amount'       => (float)$invoice->total,
            'installments' => $installments > 0 ? $installments : 1,
            'customerId'   => $customerId,
            'card'         => [
                'number'          => $cardNumber,
                'holderName'      => strtoupper($holderName),
                'holderDocument'  => $holderDoc,
                'expirationMonth' => $expMonth,
                'expirationYear'  => $expYear < 100 ? (2000 + $expYear) : $expYear,
                'cvv'             => $cvv,
                'ipAddress'       => $clientIp,
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

        $authResponse = $client->authorizeCreditCard($cardPayload);
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

            echo json_encode(['success' => true, 'status' => $txStatus, 'transactionId' => $txId]);
            exit;
        } else {
            $declinedMsg = $authResponse['message'] ?? ($authResponse['error'] ?? 'Transação não autorizada pela operadora do cartão.');
            if (is_array($declinedMsg)) {
                $declinedMsg = implode('; ', $declinedMsg);
            }
            logTransaction('moneria', $authResponse, "Cartão Recusado para Fatura #{$invoiceId}: {$declinedMsg}");
            echo json_encode(['success' => false, 'error' => $declinedMsg]);
            exit;
        }

    } catch (\Exception $e) {
        logTransaction('moneria', ['invoiceid' => $invoiceId, 'error' => $e->getMessage()], 'Exceção ao Processar Cartão');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

// Always log incoming webhook payload for full debugging
logTransaction('moneria', [
    'raw_body' => $rawBody,
    'parsed'   => $payload,
    'headers'  => function_exists('getallheaders') ? getallheaders() : [],
], 'Moneria Webhook Notification Received');

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
    if (!empty($chargeId)) {
        try {
            $client = MoneriaHelper::createClient($gatewayParams);
            $liveCharge = $client->getCharge($chargeId);
            $liveStatus = strtoupper($liveCharge['status'] ?? '');

            if (in_array($liveStatus, $paidStatuses, true)) {
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
                ], "Webhook Rejected (Security): Charge #{$chargeId} status on Moneria API is '{$liveStatus}', not PAID.");

                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['status' => 'rejected', 'error' => "Live charge status is {$liveStatus}"]);
                exit;
            }
        } catch (\Throwable $t) {
            logTransaction($actualGateway, [
                'webhook_payload' => $payload,
                'exception' => $t->getMessage(),
            ], "Webhook Verification Failed (Security): Could not verify charge #{$chargeId} on Moneria API");

            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Moneria API verification failed']);
            exit;
        }
    } else {
        logTransaction($actualGateway, $payload, "Webhook Rejected (Security): No charge ID provided in payload");
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'rejected', 'error' => 'Missing charge ID']);
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

if (in_array($status, ['CANCELLED', 'FAILED', 'EXPIRED'], true)) {
    if (!empty($chargeId)) {
        Capsule::table('mod_moneria_charges')
            ->where('invoice_id', $invoiceId)
            ->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }
    logTransaction($actualGateway, $payload, "Transaction status updated: {$status} for Invoice #{$invoiceId}");
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['status' => 'updated', 'invoiceId' => $invoiceId, 'newStatus' => $status]);
    exit;
}

logTransaction($actualGateway, $payload, "Webhook processed with unhandled status: {$status}");
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode(['status' => 'ignored', 'message' => "Unhandled status: {$status}"]);
