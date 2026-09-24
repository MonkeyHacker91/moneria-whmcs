<?php
/**
 * Moneria WHMCS Hooks
 * 
 * - Intercepts invoice emails to attach/replace Boleto PDF and inject rich merge fields (Pix & Boleto & Direct Payment Link).
 * - Displays Fast Payment Actions Box in Admin Invoice View for instant WhatsApp/Email copy-paste.
 * - Automatically cancels charge on Moneria when an invoice is cancelled in WHMCS.
 *
 * @version 1.2.0
 * @author Moneria
 * @link https://moneria.com.br
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/../../modules/gateways/moneria/src/MoneriaClient.php';
require_once __DIR__ . '/../../modules/gateways/moneria/src/MoneriaHelper.php';

use Moneria\Gateway\MoneriaHelper;
use WHMCS\Database\Capsule;

/**
 * Pre-generate Moneria Charge when invoice is created
 */
add_hook('InvoiceCreated', 1, function(array $vars) {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }

    try {
        $moneriaGateways = ['moneria', 'moneria_pix', 'moneria_boleto'];
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first(['paymentmethod', 'status']);
        if ($invoice && in_array($invoice->paymentmethod, $moneriaGateways, true) && strcasecmp($invoice->status, 'Unpaid') === 0) {
            $gatewayParams = MoneriaHelper::getGatewayParams($invoice->paymentmethod);
            if (!empty($gatewayParams['type']) || !empty($gatewayParams['clientId'])) {
                MoneriaHelper::generateChargeForInvoice($invoiceId, $gatewayParams);
            }
        }
    } catch (\Exception $e) {
        // Fail safely
    }
});

/**
 * Automatically update Moneria Charge and DDA when Invoice is modified or Due Date is changed
 */
add_hook('InvoiceModified', 1, function(array $vars) {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }

    try {
        $moneriaGateways = ['moneria', 'moneria_pix', 'moneria_boleto'];
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first(['paymentmethod', 'status']);
        if ($invoice && in_array($invoice->paymentmethod, $moneriaGateways, true) && strcasecmp($invoice->status, 'Unpaid') === 0) {
            $gatewayParams = MoneriaHelper::getGatewayParams($invoice->paymentmethod);
            if (!empty($gatewayParams['type']) || !empty($gatewayParams['clientId'])) {
                MoneriaHelper::generateChargeForInvoice($invoiceId, $gatewayParams, false);
            }
        }
    } catch (\Exception $e) {
        // Fail safely
    }
});

add_hook('InvoiceDueDateChanged', 1, function(array $vars) {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }

    try {
        $moneriaGateways = ['moneria', 'moneria_pix', 'moneria_boleto'];
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first(['paymentmethod', 'status']);
        if ($invoice && in_array($invoice->paymentmethod, $moneriaGateways, true) && strcasecmp($invoice->status, 'Unpaid') === 0) {
            $gatewayParams = MoneriaHelper::getGatewayParams($invoice->paymentmethod);
            if (!empty($gatewayParams['type']) || !empty($gatewayParams['clientId'])) {
                MoneriaHelper::generateChargeForInvoice($invoiceId, $gatewayParams, false);
            }
        }
    } catch (\Exception $e) {
        // Fail safely
    }
});

/**
 * Automatically cancel charge/boleto on Moneria when Invoice is marked as Cancelled
 */
add_hook('InvoiceCancelled', 1, function(array $vars) {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }

    try {
        $moneriaGateways = ['moneria', 'moneria_pix', 'moneria_boleto', 'moneria_creditcard'];
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first(['paymentmethod']);
        if ($invoice && in_array($invoice->paymentmethod, $moneriaGateways, true)) {
            $gatewayParams = MoneriaHelper::getGatewayParams($invoice->paymentmethod);
            if (!empty($gatewayParams['cancelOnInvoiceCancelled'])) {
                MoneriaHelper::cancelInvoiceCharge($invoiceId, $gatewayParams);
            }
        }
    } catch (\Exception $e) {
        // Fail safely
    }
});

/**
 * Automatically cancel charge/boleto on Moneria when Invoice is Deleted
 */
add_hook('InvoiceDeleted', 1, function(array $vars) {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }

    try {
        $charge = MoneriaHelper::getInvoiceCharge($invoiceId);
        if ($charge && !empty($charge['id'])) {
            $gatewayParams = MoneriaHelper::getGatewayParams('moneria');
            MoneriaHelper::cancelInvoiceCharge($invoiceId, $gatewayParams);
        }
    } catch (\Exception $e) {
        // Fail safely
    }
});

/**
 * Intercept invoice emails to attach Boleto PDF and inject merge fields
 */
add_hook('EmailPreSend', 1, function(array $vars) {
    $messageName = $vars['messagename'] ?? '';
    $relId = (int)($vars['relid'] ?? 0);

    // List of invoice-related email templates
    $invoiceTemplates = [
        'Invoice Created',
        'Invoice Payment Reminder',
        'First Invoice Overdue Notice',
        'Second Invoice Overdue Notice',
        'Third Invoice Overdue Notice',
        'Invoice Payment Confirmation',
    ];

    $isInvoiceEmail = in_array($messageName, $invoiceTemplates, true) || (stripos($messageName, 'Invoice') !== false && $relId > 0);

    if (!$isInvoiceEmail || $relId <= 0) {
        return [];
    }

    $invoiceId = $relId;

    try {
        $moneriaGateways = ['moneria', 'moneria_pix', 'moneria_boleto', 'moneria_creditcard'];
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first(['id', 'paymentmethod', 'status', 'total', 'duedate']);
        if (!$invoice || !in_array($invoice->paymentmethod, $moneriaGateways, true)) {
            return [];
        }

        $gatewayParams = getGatewayVariables($invoice->paymentmethod);
        if (empty($gatewayParams['type'])) {
            return [];
        }

        // Get or generate charge
        $charge = MoneriaHelper::generateChargeForInvoice($invoiceId, $gatewayParams);
        if (!$charge) {
            $charge = MoneriaHelper::getInvoiceCharge($invoiceId);
        }

        if (!$charge) {
            return [];
        }

        // Extract Pix and Boleto info
        $pixQrCode = '';
        $pixQrCodeBase64 = '';
        $boletoBarCode = '';
        $boletoUrl = '';

        if (!empty($charge['invoices']) && is_array($charge['invoices'])) {
            foreach ($charge['invoices'] as $inv) {
                if (!empty($inv['transactions']) && is_array($inv['transactions'])) {
                    foreach ($inv['transactions'] as $tx) {
                        $type = $tx['type'] ?? ($tx['paymentMethod'] ?? '');
                        if ($type === 'PIX' || $type === 'PIX_QRCODE' || !empty($tx['pixQrCode'])) {
                            $pixQrCode = $tx['pixQrCode'] ?? ($tx['emv'] ?? ($tx['pix']['qrCode'] ?? $pixQrCode));
                            $pixQrCodeBase64 = $tx['pixQrCodeBase64'] ?? ($tx['pix']['qrCodeBase64'] ?? $pixQrCodeBase64);
                        }
                        if ($type === 'BOLETO' || $type === 'BANK_SLIP' || !empty($tx['boletoUrl']) || !empty($tx['boletoBarCode'])) {
                            $boletoBarCode = $tx['boletoBarCode'] ?? ($tx['bankSlip']['barCode'] ?? $boletoBarCode);
                            $boletoUrl = $tx['boletoUrl'] ?? ($tx['bankSlip']['pdfUrl'] ?? ($tx['bankSlip']['url'] ?? $boletoUrl));
                            if (empty($pixQrCode) && !empty($tx['pixQrCode'])) {
                                $pixQrCode = $tx['pixQrCode'];
                            }
                        }
                    }
                }
            }
        }

        $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') ?? '';
        $directInvoiceUrl = rtrim($systemUrl, '/') . '/viewinvoice.php?id=' . $invoiceId;

        // WhatsApp message preformatted
        $formattedTotal = 'R$ ' . number_format((float)$invoice->total, 2, ',', '.');
        $formattedDueDate = date('d/m/Y', strtotime($invoice->duedate));
        $whatsappMsg = "Olá! Seguem os dados para pagamento da sua Fatura #{$invoiceId} no valor de {$formattedTotal} (Vencimento: {$formattedDueDate}):\n\n";
        if (!empty($pixQrCode)) {
            $whatsappMsg .= "🔹 Chave Pix Copia e Cola:\n{$pixQrCode}\n\n";
        }
        if (!empty($boletoBarCode)) {
            $whatsappMsg .= "📄 Linha Digitável do Boleto:\n{$boletoBarCode}\n\n";
        }
        if (!empty($boletoUrl)) {
            $whatsappMsg .= "🔗 Boleto PDF: {$boletoUrl}\n\n";
        }
        $whatsappMsg .= "💳 Link Direto da Fatura: {$directInvoiceUrl}";

        // Generate rich Payment Box HTML
        $paymentBoxHtml = '<div style="margin: 20px 0; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-family: sans-serif;">';
        $paymentBoxHtml .= '<p style="margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #1e293b;">Opções de Pagamento da Fatura #' . $invoiceId . ':</p>';
        $paymentBoxHtml .= '<p style="margin: 0 0 12px 0;"><a href="' . htmlspecialchars($directInvoiceUrl) . '" style="display: inline-block; padding: 12px 24px; background: #204d44; color: #e2c83a; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px;">💳 Pagar / Ver Fatura Online</a></p>';

        if (!empty($boletoUrl)) {
            $paymentBoxHtml .= '<div style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed #cbd5e1;">';
            $paymentBoxHtml .= '<p style="margin: 0 0 8px 0; font-weight: 700; color: #334155; font-size: 13px;">📄 Boleto Bancário Oficial:</p>';
            $paymentBoxHtml .= '<p style="margin: 0 0 10px 0;"><a href="' . htmlspecialchars($boletoUrl) . '" target="_blank" rel="noopener" style="display: inline-block; padding: 10px 20px; background: #d1b42b; color: #204d44; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 13px;">Abrir Boleto Bancário Moneria (PDF/Impressão)</a></p>';
            if (!empty($boletoBarCode)) {
                $paymentBoxHtml .= '<p style="margin: 0 0 6px 0; font-size: 12px; color: #475569;"><strong>Linha Digitável:</strong><br><span style="font-family: monospace; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; display: inline-block; font-size: 12px; word-break: break-all; margin-top: 3px;">' . htmlspecialchars($boletoBarCode) . '</span></p>';
            }
            $paymentBoxHtml .= '</div>';
        }

        if (!empty($pixQrCode)) {
            $paymentBoxHtml .= '<div style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed #cbd5e1;">';
            $paymentBoxHtml .= '<p style="margin: 0 0 8px 0; font-weight: 700; color: #334155; font-size: 13px;">⚡ Pix Instantâneo:</p>';
            $paymentBoxHtml .= '<p style="margin: 0 0 6px 0; font-size: 12px; color: #475569;"><strong>Chave Pix Copia e Cola:</strong><br><span style="font-family: monospace; background: #e2e8f0; padding: 6px 8px; border-radius: 4px; display: inline-block; font-size: 11px; word-break: break-all; margin-top: 3px;">' . htmlspecialchars($pixQrCode) . '</span></p>';
            $paymentBoxHtml .= '</div>';
        }

        $paymentBoxHtml .= '</div>';

        // Merge fields to inject into WHMCS email
        $mergeFields = [
            'invoice_url'              => $directInvoiceUrl,
            'invoice_payment_link'     => $directInvoiceUrl,
            'moneria_payment_box'      => $paymentBoxHtml,
            'moneria_pix_code'         => $pixQrCode,
            'moneria_pix_qrcode'       => $pixQrCodeBase64,
            'moneria_pix_img_tag'      => !empty($pixQrCodeBase64) ? '<img src="' . (strpos($pixQrCodeBase64, 'data:') === 0 ? $pixQrCodeBase64 : 'data:image/png;base64,' . $pixQrCodeBase64) . '" width="180" height="180" style="display:block;margin:10px auto;" />' : '',
            'moneria_boleto_url'       => $boletoUrl,
            'moneria_boleto_barcode'   => $boletoBarCode,
            'moneria_payment_link'     => $directInvoiceUrl,
            'moneria_whatsapp_text'    => $whatsappMsg,
            'invoice_moneria_pix_code' => $pixQrCode,
            'invoice_moneria_boleto_url' => $boletoUrl,
            'invoice_moneria_boleto_barcode' => $boletoBarCode,
        ];

        $return = [
            'mergefields' => $mergeFields,
        ];

        // Attach Boleto PDF if configured
        $attachBoleto = !empty($gatewayParams['attachBoletoPdf']);
        $replaceInvoicePdf = !empty($gatewayParams['replaceInvoicePdf']);

        if (($attachBoleto || $replaceInvoicePdf) && (!empty($boletoUrl) || !empty($boletoBarCode))) {
            $chargePdfData = [
                'barCode'   => $boletoBarCode,
                'amount'    => $invoice->total,
                'dueDate'   => $invoice->duedate,
                'payerName' => $charge['customer']['name'] ?? 'Cliente',
                'payerDoc'  => $charge['customer']['document'] ?? '',
            ];

            $localPdf = MoneriaHelper::getOrDownloadBoletoPdf($invoiceId, $boletoUrl, $chargePdfData);
            if ($localPdf && file_exists($localPdf)) {
                $attachments = $vars['attachments'] ?? [];
                if (!is_array($attachments)) {
                    $attachments = [];
                }

                if ($replaceInvoicePdf) {
                    $attachments = [];
                }

                $pdfData = @file_get_contents($localPdf);

                $attachments[] = [
                    'filepath'    => $localPdf,
                    'data'        => $pdfData,
                    'filename'    => "Boleto_Fatura_{$invoiceId}.pdf",
                    'displayname' => "Boleto_Fatura_{$invoiceId}.pdf",
                ];

                $return['attachments'] = $attachments;
            }
        }

        return $return;

    } catch (\Exception $e) {
        return [];
    }
});

/**
 * Render Quick Actions Box in WHMCS Admin Invoice View (WhatsApp link, Boleto PDF, Pix Code)
 */
add_hook('AdminInvoicesControlsOutput', 1, function(array $vars) {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return '';
    }

    try {
        $moneriaGateways = ['moneria', 'moneria_pix', 'moneria_boleto', 'moneria_creditcard'];
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first(['id', 'paymentmethod', 'status', 'total', 'duedate']);
        if (!$invoice || !in_array($invoice->paymentmethod, $moneriaGateways, true)) {
            return '';
        }

        $gatewayParams = getGatewayVariables($invoice->paymentmethod);
        if (isset($gatewayParams['showAdminPaymentBox']) && empty($gatewayParams['showAdminPaymentBox'])) {
            return '';
        }

        $forceRefresh = !empty($_GET['moneria_refresh']);
        $charge = MoneriaHelper::generateChargeForInvoice($invoiceId, $gatewayParams, $forceRefresh);
        if (!$charge) {
            $charge = MoneriaHelper::getInvoiceCharge($invoiceId);
        }

        $pixQrCode = '';
        $boletoBarCode = '';
        $boletoUrl = '';

        if (!empty($charge['invoices']) && is_array($charge['invoices'])) {
            foreach ($charge['invoices'] as $inv) {
                if (!empty($inv['transactions']) && is_array($inv['transactions'])) {
                    foreach ($inv['transactions'] as $tx) {
                        $type = $tx['type'] ?? ($tx['paymentMethod'] ?? '');
                        if ($type === 'PIX' || $type === 'PIX_QRCODE' || !empty($tx['pixQrCode'])) {
                            $pixQrCode = $tx['pixQrCode'] ?? ($tx['emv'] ?? ($tx['pix']['qrCode'] ?? $pixQrCode));
                        }
                        if ($type === 'BOLETO' || $type === 'BANK_SLIP' || !empty($tx['boletoUrl']) || !empty($tx['boletoBarCode'])) {
                            $boletoBarCode = $tx['boletoBarCode'] ?? ($tx['bankSlip']['barCode'] ?? $boletoBarCode);
                            $boletoUrl = $tx['boletoUrl'] ?? ($tx['bankSlip']['pdfUrl'] ?? ($tx['bankSlip']['url'] ?? $boletoUrl));
                        }
                    }
                }
            }
        }

        $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') ?? '';
        $directInvoiceUrl = rtrim($systemUrl, '/') . '/viewinvoice.php?id=' . $invoiceId;

        // WhatsApp message preformatted
        $formattedTotal = 'R$ ' . number_format((float)$invoice->total, 2, ',', '.');
        $formattedDueDate = date('d/m/Y', strtotime($invoice->duedate));
        $whatsappMsg = "Olá! Seguem os dados para pagamento da sua fatura no valor de {$formattedTotal} (Vencimento: {$formattedDueDate}):\n\n";
        if (!empty($pixQrCode)) {
            $whatsappMsg .= "🔹 *Pix Copia e Cola:*\n{$pixQrCode}\n\n";
        }
        if (!empty($boletoBarCode)) {
            $whatsappMsg .= "📄 *Linha Digitável:*\n{$boletoBarCode}\n\n";
        }
        if (!empty($boletoUrl)) {
            $whatsappMsg .= "🔗 *Boleto PDF:* {$boletoUrl}\n\n";
        }
        $whatsappMsg .= "💳 *Link da Fatura:* {$directInvoiceUrl}";
        $whatsappUrl = 'https://api.whatsapp.com/send?text=' . urlencode($whatsappMsg);

        ob_start();
        ?>
        <div class="panel panel-default" style="margin-top: 15px; border-color: #0284c7;">
            <div class="panel-heading" style="background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
                <h3 class="panel-title" style="font-weight: 700; color: #fff;">
                    <i class="fas fa-bolt" style="color: #38bdf8; margin-right: 6px;"></i> Cobrança Moneria (Ações Rápidas)
                </h3>
                <span class="label label-info" style="font-size: 11px; background: #0284c7;">Fatura #<?php echo $invoiceId; ?></span>
            </div>
            <div class="panel-body" style="background: #f8fafc; font-size: 13px;">
                <div class="row">
                    <?php if (!empty($pixQrCode)): ?>
                        <div class="col-md-6" style="margin-bottom: 10px;">
                            <label style="font-weight: 600; color: #334155;"><i class="fas fa-qrcode"></i> Chave Pix Copia e Cola:</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="admin_moneria_pix_code" class="form-control input-sm" value="<?php echo htmlspecialchars($pixQrCode); ?>" readonly />
                                <span class="input-group-btn">
                                    <button class="btn btn-default btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('admin_moneria_pix_code').value); alert('Código Pix copiado com sucesso!');">
                                        <i class="fas fa-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($boletoBarCode)): ?>
                        <div class="col-md-6" style="margin-bottom: 10px;">
                            <label style="font-weight: 600; color: #334155;"><i class="fas fa-barcode"></i> Linha Digitável do Boleto:</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="admin_moneria_bol_code" class="form-control input-sm" value="<?php echo htmlspecialchars($boletoBarCode); ?>" readonly />
                                <span class="input-group-btn">
                                    <button class="btn btn-default btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('admin_moneria_bol_code').value); alert('Linha digitável copiada!');">
                                        <i class="fas fa-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="<?php echo htmlspecialchars($whatsappUrl); ?>" target="_blank" class="btn btn-success btn-sm" style="background: #25D366; border-color: #25D366; color: #fff; font-weight: 600;">
                        <i class="fab fa-whatsapp"></i> Enviar Cobrança via WhatsApp
                    </a>

                    <?php if (!empty($boletoUrl)): ?>
                        <a href="<?php echo htmlspecialchars($boletoUrl); ?>" target="_blank" class="btn btn-primary btn-sm" style="font-weight: 600;">
                            <i class="fas fa-file-pdf"></i> Visualizar Boleto PDF
                        </a>
                    <?php endif; ?>

                    <button type="button" class="btn btn-default btn-sm" onclick="navigator.clipboard.writeText(<?php echo json_encode($directInvoiceUrl); ?>); alert('Link de pagamento da fatura copiado!');">
                        <i class="fas fa-link"></i> Copiar Link da Fatura
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    } catch (\Exception $e) {
        return '';
    }
});

/**
 * Automatically Reconcile / Synchronize all Unpaid Moneria Invoices during Cron execution
 */
add_hook('DailyCronJob', 1, function(array $vars) {
    try {
        require_once __DIR__ . '/../../modules/gateways/moneria/src/MoneriaClient.php';
        require_once __DIR__ . '/../../modules/gateways/moneria/src/MoneriaHelper.php';

        $gwParams = getGatewayVariables('moneria');
        if (empty($gwParams['clientId']) || empty($gwParams['clientSecret'])) {
            return;
        }

        $client = new Moneria\Gateway\MoneriaClient($gwParams['clientId'], $gwParams['clientSecret'], 'https://api.moneria.com.br');

        $pendingCharges = Capsule::table('mod_moneria_charges')
            ->join('tblinvoices', 'tblinvoices.id', '=', 'mod_moneria_charges.invoice_id')
            ->where('tblinvoices.status', 'Unpaid')
            ->get([
                'mod_moneria_charges.invoice_id',
                'mod_moneria_charges.charge_id',
                'tblinvoices.total',
                'tblinvoices.paymentmethod',
            ]);

        $paidStatuses = ['PAID', 'APPROVED', 'CONFIRMED', 'RECEIVED', 'COMPLETED', 'SETTLED', 'SUCCEEDED', 'SUCCESS'];

        foreach ($pendingCharges as $item) {
            $invoiceId = (int)$item->invoice_id;
            $chargeId = $item->charge_id;

            if (empty($chargeId)) {
                continue;
            }

            try {
                $charge = $client->getCharge($chargeId);
                $status = strtoupper($charge['status'] ?? '');
                $isPaid = in_array($status, $paidStatuses, true);

                $txId = null;
                $fee = 0.0;
                $amount = (float)$item->total;

                if (!empty($charge['invoices'])) {
                    foreach ($charge['invoices'] as $inv) {
                        if (in_array(strtoupper($inv['status'] ?? ''), $paidStatuses, true)) {
                            $isPaid = true;
                        }
                        if (!empty($inv['transactions'])) {
                            foreach ($inv['transactions'] as $tx) {
                                if (in_array(strtoupper($tx['status'] ?? ''), $paidStatuses, true)) {
                                    $isPaid = true;
                                    $txId = $tx['id'] ?? ($tx['providerTxId'] ?? null);
                                    $fee = (float)($tx['totalFee'] ?? ($tx['fixedFee'] ?? 0));
                                    if (!empty($tx['amount'])) {
                                        $amount = (float)$tx['amount'];
                                    }
                                }
                            }
                        }
                    }
                }

                if ($isPaid) {
                    $txId = $txId ?: ($charge['id'] ?? uniqid('mon_sync_'));
                    $actualGateway = $item->paymentmethod ?: 'moneria';

                    checkCbTransID($txId);
                    addInvoicePayment(
                        $invoiceId,
                        $txId,
                        $amount,
                        $fee,
                        $actualGateway
                    );

                    Capsule::table('mod_moneria_charges')
                        ->where('invoice_id', $invoiceId)
                        ->update(['status' => 'PAID', 'updated_at' => date('Y-m-d H:i:s')]);

                    logTransaction($actualGateway, $charge, "Auto-Sync Cron: Payment Confirmed and Applied for Invoice #{$invoiceId}");
                }
            } catch (\Throwable $t) {
                // Ignore per-item failure
            }
        }
    } catch (\Throwable $e) {
        // Fail safely
    }
});
