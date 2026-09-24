<?php
/**
 * Moneria Dedicated Credit Card View Template
 * Universal Theme Support (Light, Slate, Dark, Custom)
 * 
 * @var int $invoiceId
 * @var string $amountFormatted
 * @var float $amount
 * @var string $holderName
 * @var string $holderDocument
 * @var int $maxInstallments
 * @var string $processUrl
 * @var string|null $errorMessage
 * @var string $assetsUrl
 * @var array|null $themeVars
 * @var string|null $themeStyle
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$activeTheme = !empty($themeVars['themeStyle']) ? $themeVars['themeStyle'] : (!empty($themeStyle) ? $themeStyle : 'dark');
$themeClass = 'moneria-theme-' . htmlspecialchars($activeTheme);
$inlineStyle = !empty($themeVars['inlineStyle']) ? $themeVars['inlineStyle'] : '';
$currentYear = (int)date('Y');
?>

<div class="moneria-payment-box gen3-boleto <?php echo $themeClass; ?>" data-gen3-boleto data-moneria-box style="<?php echo htmlspecialchars($inlineStyle); ?>">
    <?php if (!empty($errorMessage)): ?>
        <div class="moneria-note gen3-boleto__note" style="border-color: rgba(239, 68, 68, 0.35); background: rgba(239, 68, 68, 0.12); color: #fca5a5;">
            <i class="fas fa-exclamation-triangle" aria-hidden="true" style="color: #ef4444;"></i>
            <span><?php echo htmlspecialchars($errorMessage); ?></span>
        </div>
    <?php else: ?>
        <div class="moneria-head gen3-boleto__head">
            <div class="moneria-brand gen3-boleto__brand">
                <span class="moneria-badge gen3-boleto__badge"><?php echo htmlspecialchars($displayTitle ?? 'Cartão'); ?></span>
                <span class="moneria-amount" style="font-size: 1rem; font-weight: 700; color: var(--gb-text); margin-left: 4px;"><?php echo htmlspecialchars($amountFormatted); ?></span>
            </div>
            <div class="moneria-due gen3-boleto__due">
                <span>Status</span>
                <strong style="color: #38bdf8;">Online</strong>
            </div>
        </div>

        <div id="gen3-cc-alert-box" class="moneria-note gen3-boleto__note" style="display: none; border-color: rgba(239, 68, 68, 0.35); background: rgba(239, 68, 68, 0.12); color: #fca5a5;"></div>
        <div id="gen3-cc-success-box" class="moneria-note gen3-boleto__note" style="display: none; border-color: rgba(74, 222, 128, 0.4); background: rgba(74, 222, 128, 0.12); color: #86efac;"></div>

        <form id="gen3-moneria-cc-form" onsubmit="return gen3SubmitCreditCard(event)">
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:11px; color:var(--gb-muted); text-transform:uppercase; margin-bottom:4px; font-weight:600; letter-spacing:0.04em;">Número do Cartão</label>
                <input type="text" id="gen3_field_cc_num" class="moneria-code-input gen3-code-input gen3-boleto__code" style="width: 100%;" placeholder="0000 0000 0000 0000" maxlength="19" required />
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:11px; color:var(--gb-muted); text-transform:uppercase; margin-bottom:4px; font-weight:600; letter-spacing:0.04em;">Nome Impresso no Cartão</label>
                <input type="text" id="gen3_field_cc_name" class="moneria-code-input gen3-code-input gen3-boleto__code" style="width: 100%; text-transform: uppercase;" value="<?php echo htmlspecialchars($holderName); ?>" placeholder="NOME DO TITULAR" required />
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:11px; color:var(--gb-muted); text-transform:uppercase; margin-bottom:4px; font-weight:600; letter-spacing:0.04em;">CPF/CNPJ do Titular</label>
                <input type="text" id="gen3_field_cc_doc" class="moneria-code-input gen3-code-input gen3-boleto__code" style="width: 100%;" value="<?php echo htmlspecialchars($holderDocument); ?>" placeholder="000.000.000-00" required />
            </div>

            <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                <div style="flex: 1;">
                    <label style="display:block; font-size:11px; color:var(--gb-muted); text-transform:uppercase; margin-bottom:4px; font-weight:600; letter-spacing:0.04em;">Validade</label>
                    <div style="display: flex; gap: 4px;">
                        <select id="gen3_field_cc_month" class="moneria-code-input gen3-code-input gen3-boleto__code" style="flex: 1; padding: 0 4px !important;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="gen3_field_cc_year" class="moneria-code-input gen3-code-input gen3-boleto__code" style="flex: 1; padding: 0 4px !important;">
                            <?php for ($y = $currentYear; $y <= $currentYear + 15; $y++): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div style="flex: 1;">
                    <label style="display:block; font-size:11px; color:var(--gb-muted); text-transform:uppercase; margin-bottom:4px; font-weight:600; letter-spacing:0.04em;">CVV</label>
                    <input type="password" id="gen3_field_cc_cvv" class="moneria-code-input gen3-code-input gen3-boleto__code" style="width: 100%;" placeholder="123" maxlength="4" required />
                </div>
            </div>

            <?php if ($maxInstallments > 1): ?>
                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-size:11px; color:var(--gb-muted); text-transform:uppercase; margin-bottom:4px; font-weight:600; letter-spacing:0.04em;">Parcelamento</label>
                    <select id="gen3_field_cc_installments" class="moneria-code-input gen3-code-input gen3-boleto__code" style="width: 100%;">
                        <?php for ($i = 1; $i <= $maxInstallments; $i++): 
                            $instVal = $amount / $i;
                        ?>
                            <option value="<?php echo $i; ?>">
                                <?php echo $i . 'x de R$ ' . number_format($instVal, 2, ',', '.') . ($i === 1 ? ' (à vista)' : ''); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" id="gen3_field_cc_installments" value="1" />
            <?php endif; ?>

            <button type="submit" id="gen3_cc_submit_btn" class="moneria-print-btn gen3-print-btn gen3-boleto__print">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Pagar Agora (<?php echo htmlspecialchars($amountFormatted); ?>)</span>
            </button>
        </form>
    <?php endif; ?>
</div>

<style>
.moneria-payment-box,
.gen3-boleto {
  --gb-bg: #14221e;
  --gb-bg-2: #0d1714;
  --gb-border: rgba(255, 255, 255, 0.1);
  --gb-text: #eef3f0;
  --gb-muted: rgba(238, 243, 240, 0.72);
  --gb-accent: #e2c83a;
  --gb-accent-2: #d1b42b;
  --gb-cta-text: #204d44;
  --gb-radius: 16px;
  --gb-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
  --gb-input-bg: rgba(0, 0, 0, 0.35);
  --gb-input-border: rgba(255, 255, 255, 0.12);
  --gb-input-text: #ffffff;
  --gb-note-bg: rgba(251, 191, 36, 0.08);
  --gb-note-border: rgba(251, 191, 36, 0.25);
  --gb-note-text: #fde68a;
  width: 100%;
  max-width: 540px;
  margin: 15px auto;
  padding: 22px;
  border-radius: var(--gb-radius);
  background: var(--gb-bg) !important;
  border: 1px solid var(--gb-border) !important;
  box-shadow: var(--gb-shadow);
  color: var(--gb-text) !important;
  font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  text-align: left;
  box-sizing: border-box;
}

/* Light Theme Variables & Overrides */
.moneria-payment-box.moneria-theme-light {
  --gb-bg: #ffffff;
  --gb-bg-2: #f8fafc;
  --gb-border: #e2e8f0;
  --gb-text: #0f172a;
  --gb-muted: #64748b;
  --gb-accent: #0284c7;
  --gb-accent-2: #0369a1;
  --gb-cta-text: #ffffff;
  --gb-input-bg: #f8fafc;
  --gb-input-border: #cbd5e1;
  --gb-input-text: #0f172a;
  --gb-note-bg: #f8fafc;
  --gb-note-border: #e2e8f0;
  --gb-note-text: #475569;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
}
.moneria-payment-box.moneria-theme-light .gen3-boleto__badge {
  border-color: rgba(2, 132, 199, 0.35) !important;
  background: rgba(2, 132, 199, 0.08) !important;
  color: #0284c7 !important;
}
.moneria-payment-box.moneria-theme-light .moneria-amount {
  color: #0f172a !important;
}
.moneria-payment-box.moneria-theme-light .gen3-code-input {
  background: #f8fafc !important;
  border-color: #cbd5e1 !important;
  color: #0f172a !important;
  -webkit-text-fill-color: #0f172a !important;
}
.moneria-payment-box.moneria-theme-light .gen3-print-btn {
  background: #0284c7 !important;
  color: #ffffff !important;
}
.moneria-payment-box.moneria-theme-light .gen3-print-btn:hover {
  background: #0369a1 !important;
}
.moneria-payment-box.moneria-theme-light .gen3-boleto__note {
  background: #f8fafc !important;
  border-color: #e2e8f0 !important;
  color: #475569 !important;
}

/* Slate Theme Variables & Overrides */
.moneria-payment-box.moneria-theme-slate {
  --gb-bg: #0f172a;
  --gb-bg-2: #1e293b;
  --gb-border: rgba(255, 255, 255, 0.12);
  --gb-text: #f8fafc;
  --gb-muted: #94a3b8;
  --gb-accent: #38bdf8;
  --gb-accent-2: #0284c7;
  --gb-cta-text: #ffffff;
  --gb-input-bg: #1e293b;
  --gb-input-border: rgba(255, 255, 255, 0.14);
  --gb-input-text: #ffffff;
  --gb-note-bg: rgba(56, 189, 248, 0.08);
  --gb-note-border: rgba(56, 189, 248, 0.2);
  --gb-note-text: #bae6fd;
}
.moneria-payment-box.moneria-theme-slate .gen3-boleto__badge {
  border-color: rgba(56, 189, 248, 0.35) !important;
  background: rgba(56, 189, 248, 0.1) !important;
  color: #38bdf8 !important;
}
.moneria-payment-box.moneria-theme-slate .gen3-print-btn {
  background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%) !important;
  color: #ffffff !important;
}

.gen3-boleto *,
.gen3-boleto *::before,
.gen3-boleto *::after { box-sizing: border-box !important; }

.gen3-boleto__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--gb-border);
}
.gen3-boleto__brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.gen3-boleto__badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  border: 1px solid rgba(209, 180, 43, 0.4);
  background: rgba(209, 180, 43, 0.12);
  color: var(--gb-accent);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.gen3-boleto__due {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  text-align: right;
}
.gen3-boleto__due span {
  font-size: 11px;
  color: var(--gb-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.gen3-boleto__due strong {
  font-size: 0.95rem;
  font-weight: 700;
}

.gen3-code-input {
  height: 42px;
  margin: 0 !important;
  padding: 0 14px !important;
  border-radius: 8px !important;
  border: 1px solid var(--gb-input-border) !important;
  background: var(--gb-input-bg) !important;
  color: var(--gb-input-text) !important;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace !important;
  font-size: 0.8125rem !important;
  letter-spacing: 0.02em;
  line-height: 40px;
  outline: none;
}
.gen3-code-input option {
  background: var(--gb-bg);
  color: var(--gb-text);
}

.gen3-boleto__note {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 0 0 16px;
  padding: 12px 14px;
  border-radius: 8px;
  border: 1px solid var(--gb-note-border);
  background: var(--gb-note-bg);
  color: var(--gb-note-text);
  font-size: 0.8125rem;
  line-height: 1.45;
}

.gen3-print-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 44px;
  padding: 0 18px;
  border-radius: 999px;
  border: none !important;
  background: linear-gradient(135deg, var(--gb-accent) 0%, var(--gb-accent-2) 100%) !important;
  color: var(--gb-cta-text, #204d44) !important;
  font-size: 0.875rem;
  font-weight: 700;
  text-decoration: none !important;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
  transition: all 0.2s ease;
  cursor: pointer;
}
.gen3-print-btn:hover {
  transform: translateY(-1px);
  filter: brightness(1.05);
  color: var(--gb-cta-text, #204d44) !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
  text-decoration: none !important;
}

@media (max-width: 575.98px) {
  .gen3-boleto { padding: 16px; }
  .gen3-boleto__head { flex-direction: column; align-items: flex-start; }
  .gen3-boleto__due { align-items: flex-start; text-align: left; }
}
</style>

<script>
var ccNumEl = document.getElementById('gen3_field_cc_num');
if (ccNumEl) {
    ccNumEl.addEventListener('input', function(e) {
        var v = e.target.value.replace(/\D/g, '').substring(0, 16);
        var matches = v.match(/\d{4,16}/g);
        var match = matches && matches[0] || '';
        var parts = [];
        for (var i = 0, len = match.length; i < len; i += 4) {
            parts.push(match.substring(i, i + 4));
        }
        e.target.value = parts.length ? parts.join(' ') : v;
    });
}

function gen3SubmitCreditCard(e) {
    if (e && e.preventDefault) e.preventDefault();
    var alertBox = document.getElementById('gen3-cc-alert-box');
    var successBox = document.getElementById('gen3-cc-success-box');
    var btn = document.getElementById('gen3_cc_submit_btn');

    if (alertBox) alertBox.style.display = 'none';
    if (successBox) successBox.style.display = 'none';

    var number = (document.getElementById('gen3_field_cc_num').value || '').replace(/\D/g, '');
    var name = (document.getElementById('gen3_field_cc_name').value || '').trim();
    var doc = (document.getElementById('gen3_field_cc_doc').value || '').replace(/\D/g, '');
    var month = document.getElementById('gen3_field_cc_month').value;
    var year = document.getElementById('gen3_field_cc_year').value;
    var cvv = (document.getElementById('gen3_field_cc_cvv').value || '').trim();
    var installmentsEl = document.getElementById('gen3_field_cc_installments');
    var installments = installmentsEl ? installmentsEl.value : 1;

    if (number.length < 13) {
        if (alertBox) {
            alertBox.textContent = 'Informe um número de cartão válido.';
            alertBox.style.display = 'flex';
        }
        return false;
    }

    if (doc.length < 11) {
        if (alertBox) {
            alertBox.textContent = 'Informe um CPF/CNPJ válido.';
            alertBox.style.display = 'flex';
        }
        return false;
    }

    var originalBtnHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>Processando...</span>';
    }

    var payload = {
        action: 'process_creditcard',
        invoiceid: <?php echo (int)$invoiceId; ?>,
        number: number,
        name: name,
        document: doc,
        month: parseInt(month, 10),
        year: parseInt(year, 10),
        cvv: cvv,
        installments: parseInt(installments, 10) || 1
    };

    var xhr = new XMLHttpRequest();
    xhr.open('POST', <?php echo json_encode($processUrl); ?>, true);
    xhr.setRequestHeader('Content-Type', 'application/json');

    xhr.onload = function() {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
        }

        try {
            var res = JSON.parse(xhr.responseText);
            if (res && res.success === true) {
                if (successBox) {
                    successBox.textContent = 'Pagamento aprovado com sucesso! Atualizando fatura...';
                    successBox.style.display = 'flex';
                }
                setTimeout(function() { window.location.reload(); }, 1200);
            } else {
                var err = res.error || (res.message || 'Transação não autorizada.');
                if (alertBox) {
                    alertBox.textContent = err;
                    alertBox.style.display = 'flex';
                }
            }
        } catch (err) {
            if (alertBox) {
                alertBox.textContent = 'Erro ao processar resposta do servidor.';
                alertBox.style.display = 'flex';
            }
        }
    };

    xhr.onerror = function() {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
        }
        if (alertBox) {
            alertBox.textContent = 'Erro de conexão com o servidor.';
            alertBox.style.display = 'flex';
        }
    };

    xhr.send(JSON.stringify(payload));
    return false;
}
window.gen3SubmitCreditCard = gen3SubmitCreditCard;
window.moneriaSubmitCreditCard = gen3SubmitCreditCard;
</script>
