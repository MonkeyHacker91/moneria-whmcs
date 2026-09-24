<?php
/**
 * Moneria Dedicated PIX View Template
 * Universal Theme Support (Light, Slate, Dark, Custom)
 * 
 * @var int $invoiceId
 * @var string $amountFormatted
 * @var string|null $pixQrCodeBase64
 * @var string|null $pixQrCode
 * @var string $checkUrl
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
                <span class="moneria-badge gen3-boleto__badge"><?php echo htmlspecialchars($displayTitle ?? 'PIX'); ?></span>
                <span class="moneria-amount" style="font-size: 1rem; font-weight: 700; color: var(--gb-text); margin-left: 4px;"><?php echo htmlspecialchars($amountFormatted); ?></span>
            </div>
            <div class="moneria-due gen3-boleto__due">
                <span>Status</span>
                <strong style="color: #4ade80;">Instantâneo</strong>
            </div>
        </div>

        <?php if (!empty($pixQrCodeBase64)): ?>
            <div class="moneria-qr-box gen3-qr-box">
                <?php 
                    $qrSrc = $pixQrCodeBase64;
                    if (strpos($qrSrc, 'http://') !== 0 && strpos($qrSrc, 'https://') !== 0 && strpos($qrSrc, 'data:') !== 0) {
                        $qrSrc = 'data:image/png;base64,' . $qrSrc;
                    }
                ?>
                <img src="<?php echo htmlspecialchars($qrSrc); ?>" 
                     alt="QR Code Pix" 
                     class="moneria-qr-img gen3-qr-img" />
            </div>
        <?php endif; ?>

        <p class="moneria-lead gen3-boleto__lead">Escaneie o QR Code acima ou use a chave Copia e Cola:</p>

        <?php if (!empty($pixQrCode)): ?>
            <div class="moneria-code-group gen3-code-group">
                <input
                    class="moneria-code-input gen3-code-input gen3-boleto__code"
                    id="gen3-dedicated-pix-val"
                    type="text"
                    readonly
                    value="<?php echo htmlspecialchars($pixQrCode); ?>"
                    aria-label="Código Pix Copia e Cola"
                    onclick="this.select();"
                >
                <button type="button" class="moneria-copy-btn gen3-copy-btn gen3-boleto__copy" onclick="moneriaPerformCopy('gen3-dedicated-pix-val', this)">
                    <i class="fas fa-copy" aria-hidden="true"></i>
                    <span>Copiar</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="moneria-status-line gen3-status-line">
            <div class="moneria-status-spinner gen3-status-spinner"></div>
            <span>Aguardando pagamento... A baixa é instantânea.</span>
        </div>
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
.moneria-payment-box.moneria-theme-light .gen3-copy-btn {
  background: #0284c7 !important;
  border-color: #0284c7 !important;
  color: #ffffff !important;
}
.moneria-payment-box.moneria-theme-light .gen3-copy-btn:hover {
  background: #0369a1 !important;
}
.moneria-payment-box.moneria-theme-light .gen3-status-line {
  background: #f0fdf4 !important;
  border: 1px solid #bbf7d0 !important;
  color: #166534 !important;
}
.moneria-payment-box.moneria-theme-light .gen3-status-spinner {
  border-color: rgba(22, 101, 52, 0.2) !important;
  border-top-color: #166534 !important;
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
.moneria-payment-box.moneria-theme-slate .gen3-copy-btn:hover {
  background: #38bdf8 !important;
  color: #0f172a !important;
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

/* QR Code Box */
.gen3-qr-box {
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 10px auto 14px auto;
  padding: 10px;
  width: 172px;
  height: 172px;
  background: #ffffff !important;
  border-radius: 12px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.28);
}
.gen3-qr-img {
  width: 152px !important;
  height: 152px !important;
  max-width: 152px !important;
  max-height: 152px !important;
  aspect-ratio: 1 / 1 !important;
  object-fit: contain !important;
  image-rendering: pixelated;
  display: block;
}

.gen3-boleto__lead {
  margin: 0 0 10px;
  font-size: 0.84rem;
  line-height: 1.45;
  color: var(--gb-muted);
}

/* Code Wrap Input Group */
.gen3-code-group {
  display: flex;
  align-items: stretch;
  gap: 8px;
  margin-bottom: 14px;
}
.gen3-code-input {
  flex: 1;
  min-width: 0;
  height: 40px;
  margin: 0 !important;
  padding: 0 12px !important;
  border-radius: 8px !important;
  border: 1px solid var(--gb-input-border) !important;
  background: var(--gb-input-bg) !important;
  color: var(--gb-input-text) !important;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace !important;
  font-size: 0.8125rem !important;
  letter-spacing: 0.02em;
  line-height: 38px;
  cursor: text;
  opacity: 1 !important;
  -webkit-text-fill-color: var(--gb-input-text) !important;
  outline: none;
}
.gen3-copy-btn {
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 0 14px;
  border: 1px solid rgba(255, 255, 255, 0.14) !important;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.04) !important;
  color: var(--gb-text) !important;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}
.gen3-copy-btn:hover {
  background: rgba(74, 222, 128, 0.12) !important;
  border-color: rgba(74, 222, 128, 0.4) !important;
  color: #fff !important;
}
.gen3-copy-btn.is-copied {
  background: rgba(74, 222, 128, 0.2) !important;
  border-color: rgba(74, 222, 128, 0.5) !important;
  color: var(--gb-accent) !important;
}

/* Status Line */
.gen3-status-line {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 0.78125rem;
  color: var(--gb-muted);
  margin-top: 10px;
  padding: 5px 10px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.25);
}
.gen3-status-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid rgba(255, 255, 255, 0.15);
  border-top-color: var(--gb-accent);
  border-radius: 50%;
  animation: gen3-spin 0.8s linear infinite;
}
@keyframes gen3-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 575.98px) {
  .gen3-boleto { padding: 16px; }
  .gen3-boleto__head { flex-direction: column; align-items: flex-start; }
  .gen3-boleto__due { align-items: flex-start; text-align: left; }
  .gen3-code-group { flex-direction: column; }
  .gen3-copy-btn { height: 38px; }
}
</style>

<script>
function moneriaPerformCopy(inputId, btn) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var value = input.value || '';
    
    function done() {
        if (!btn) return;
        btn.classList.add('is-copied');
        var span = btn.querySelector('span');
        if (span) span.textContent = 'Copiado!';
        setTimeout(function () {
            btn.classList.remove('is-copied');
            if (span) span.textContent = 'Copiar';
        }, 1600);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(function () {
            input.select();
            try { document.execCommand('copy'); done(); } catch (err) {}
        });
    } else {
        input.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
    }
}
window.moneriaPerformCopy = moneriaPerformCopy;
window.gen3PerformCopy = moneriaPerformCopy;
window.gen3DedicatedPerformCopy = moneriaPerformCopy;

document.addEventListener('DOMContentLoaded', function() {
    var checkUrl = <?php echo json_encode($checkUrl); ?>;
    var invoiceId = <?php echo (int)$invoiceId; ?>;
    if (!invoiceId || !checkUrl) return;

    setInterval(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', checkUrl + (checkUrl.indexOf('?') === -1 ? '?' : '&') + 'invoiceid=' + encodeURIComponent(invoiceId) + '&t=' + new Date().getTime(), true);
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res && (res.paid === true || res.status === 'Paid')) {
                        setTimeout(function() { window.location.reload(); }, 800);
                    }
                } catch (e) {}
            }
        };
        xhr.send();
    }, 4000);
});
</script>
