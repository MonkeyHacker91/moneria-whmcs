<?php
/**
 * Moneria Auto-Sync & Reconcile Cron Job
 * 
 * Periodically polls Moneria API for all pending charges and automatically
 * marks confirmed/approved invoices as Paid in WHMCS.
 */

// Allow running from CLI or HTTP with secret token
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if (empty($token)) {
        die('Access denied');
    }
}

// Find WHMCS root dynamically
$whmcsRoot = dirname(__DIR__, 3);
if (file_exists($whmcsRoot . '/init.php')) {
    require_once $whmcsRoot . '/init.php';
} elseif (defined('ROOTDIR') && file_exists(ROOTDIR . '/init.php')) {
    require_once ROOTDIR . '/init.php';
} else {
    die('WHMCS init.php not found');
}

require_once __DIR__ . '/src/MoneriaClient.php';
require_once __DIR__ . '/src/MoneriaHelper.php';
require_once $whmcsRoot . '/includes/gatewayfunctions.php';
require_once $whmcsRoot . '/includes/invoicefunctions.php';

use WHMCS\Database\Capsule;
use Moneria\Gateway\MoneriaHelper;

$gatewayParams = getGatewayVariables('moneria');
if (empty($gatewayParams['clientId']) || empty($gatewayParams['clientSecret'])) {
    die("Moneria gateway not configured.\n");
}

if (php_sapi_name() !== 'cli') {
    $expectedToken = hash_hmac('sha256', 'moneria_cron', $gatewayParams['clientSecret']);
    if (!hash_equals($expectedToken, (string)$token) && $token !== $gatewayParams['clientSecret']) {
        die("Access denied: invalid token\n");
    }
}

$client = MoneriaHelper::createClient($gatewayParams);

// Find all pending charges for unpaid invoices
$pendingCharges = Capsule::table('mod_moneria_charges')
    ->join('tblinvoices', 'tblinvoices.id', '=', 'mod_moneria_charges.invoice_id')
    ->where('tblinvoices.status', 'Unpaid')
    ->whereNotNull('mod_moneria_charges.charge_id')
    ->where('mod_moneria_charges.charge_id', '!=', '')
    ->select('mod_moneria_charges.*', 'tblinvoices.total', 'tblinvoices.userid')
    ->get();

$count = 0;
$synced = 0;

foreach ($pendingCharges as $c) {
    $count++;
    $invoiceId = (int)$c->invoice_id;
    $chargeId = $c->charge_id;
    
    try {
        $live = $client->getCharge($chargeId);
        $status = strtoupper($live['status'] ?? '');
        $invData = $live['invoices'][0] ?? [];
        $tx = $invData['transactions'][0] ?? [];
        $txStatus = strtoupper($tx['status'] ?? '');
        
        if ($status === 'CONFIRMED' || $status === 'PAID' || $txStatus === 'APPROVED' || $txStatus === 'CONFIRMED') {
            $paidAmount = (float)($invData['paidAmount'] ?? ($tx['amount'] ?? $c->total));
            $fee = (float)($tx['totalFee'] ?? 1.99);
            $transId = $tx['id'] ?? $chargeId;
            
            // Mark paid in WHMCS
            addInvoicePayment($invoiceId, $transId, $paidAmount, $fee, 'moneria');
            Capsule::table('mod_moneria_charges')->where('invoice_id', $invoiceId)->update(['status' => 'CONFIRMED']);
            
            logTransaction('moneria', [
                'invoiceid' => $invoiceId,
                'transid' => $transId,
                'chargeid' => $chargeId,
                'amount' => $paidAmount,
                'fee' => $fee,
                'source' => 'moneria_cron_sync'
            ], 'Auto-Sync: Payment Confirmed');
            
            echo "Invoice #$invoiceId ($chargeId) synced and marked as PAID!\n";
            $synced++;
        }
    } catch (\Exception $e) {
        // Ignore single failures
    }
}

echo "Sync completed: checked $count pending charges, synced $synced paid invoices.\n";
