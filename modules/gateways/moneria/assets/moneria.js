/**
 * Moneria WHMCS Gateway JavaScript
 */

function moneriaSwitchTab(tabId) {
    // Buttons
    var buttons = document.querySelectorAll('.moneria-tab-btn');
    buttons.forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.getAttribute('data-tab') === tabId) {
            btn.classList.add('active');
        }
    });

    // Content
    var contents = document.querySelectorAll('.moneria-tab-content');
    contents.forEach(function(content) {
        content.classList.remove('active');
    });

    var target = document.getElementById('moneria-tab-' + tabId);
    if (target) {
        target.classList.add('active');
    }
}

function moneriaCopyText(inputId, successMsg) {
    var input = document.getElementById(inputId);
    if (!input) return;

    var text = input.value;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            moneriaShowToast(successMsg || 'Copiado para a área de transferência!');
        }).catch(function() {
            moneriaFallbackCopy(input, successMsg);
        });
    } else {
        moneriaFallbackCopy(input, successMsg);
    }
}

function moneriaFallbackCopy(input, successMsg) {
    input.select();
    input.setSelectionRange(0, 99999);
    try {
        document.execCommand('copy');
        moneriaShowToast(successMsg || 'Copiado para a área de transferência!');
    } catch (err) {
        alert('Por favor selecione e copie o código manualmente.');
    }
}

function moneriaShowToast(message) {
    var toast = document.getElementById('moneria-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'moneria-toast';
        toast.className = 'moneria-toast';
        document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.classList.add('show');

    setTimeout(function() {
        toast.classList.remove('show');
    }, 3500);
}

function moneriaStartPolling(invoiceId, checkUrl, intervalSeconds) {
    if (!invoiceId || !checkUrl) return;

    var interval = (intervalSeconds || 5) * 1000;

    var poller = setInterval(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', checkUrl + (checkUrl.indexOf('?') === -1 ? '?' : '&') + 'invoiceid=' + encodeURIComponent(invoiceId) + '&t=' + new Date().getTime(), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res && (res.paid === true || res.status === 'Paid' || res.status === 'APPROVED' || res.status === 'CONFIRMED')) {
                        clearInterval(poller);
                        moneriaShowToast('Pagamento confirmado! Atualizando fatura...');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1200);
                    }
                } catch (e) {}
            }
        };

        xhr.send();
    }, interval);
}

function moneriaProcessCreditCard(event, invoiceId, processUrl) {
    if (event) event.preventDefault();

    var alertBox = document.getElementById('moneria-cc-alert');
    var successBox = document.getElementById('moneria-cc-success');
    var submitBtn = document.getElementById('moneria_cc_submit_btn');

    if (alertBox) alertBox.style.display = 'none';
    if (successBox) successBox.style.display = 'none';

    var number = (document.getElementById('moneria_cc_number').value || '').replace(/\D/g, '');
    var name = (document.getElementById('moneria_cc_name').value || '').trim();
    var doc = (document.getElementById('moneria_cc_doc').value || '').replace(/\D/g, '');
    var month = document.getElementById('moneria_cc_month').value;
    var year = document.getElementById('moneria_cc_year').value;
    var cvv = (document.getElementById('moneria_cc_cvv').value || '').trim();
    var installments = document.getElementById('moneria_cc_installments') ? document.getElementById('moneria_cc_installments').value : 1;

    if (number.length < 13 || number.length > 19) {
        if (alertBox) {
            alertBox.textContent = 'Por favor, informe um número de cartão de crédito válido.';
            alertBox.style.display = 'block';
        }
        return false;
    }

    if (doc.length < 11) {
        if (alertBox) {
            alertBox.textContent = 'Por favor, informe um CPF/CNPJ válido para o titular.';
            alertBox.style.display = 'block';
        }
        return false;
    }

    if (cvv.length < 3) {
        if (alertBox) {
            alertBox.textContent = 'Por favor, informe o código de segurança (CVV) do cartão.';
            alertBox.style.display = 'block';
        }
        return false;
    }

    // Disable button & show spinner
    var originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="moneria-spinner" style="display:inline-block; vertical-align:middle; margin-right:8px; border-color:#fff; border-top-color:transparent;"></div> Processando pagamento...';

    var payload = {
        action: 'process_creditcard',
        invoiceid: invoiceId,
        number: number,
        name: name,
        document: doc,
        month: parseInt(month, 10),
        year: parseInt(year, 10),
        cvv: cvv,
        installments: parseInt(installments, 10) || 1
    };

    var xhr = new XMLHttpRequest();
    xhr.open('POST', processUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.onload = function() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;

        try {
            var res = JSON.parse(xhr.responseText);
            if (res && res.success === true) {
                if (successBox) {
                    successBox.textContent = 'Pagamento aprovado com sucesso! Atualizando fatura...';
                    successBox.style.display = 'block';
                }
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                var err = res.error || (res.message || 'Não foi possível autorizar a transação no cartão.');
                if (alertBox) {
                    alertBox.textContent = err;
                    alertBox.style.display = 'block';
                }
            }
        } catch (e) {
            if (alertBox) {
                alertBox.textContent = 'Erro ao processar resposta do servidor. Tente novamente.';
                alertBox.style.display = 'block';
            }
        }
    };

    xhr.onerror = function() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        if (alertBox) {
            alertBox.textContent = 'Falha de conexão com o servidor. Verifique sua internet e tente novamente.';
            alertBox.style.display = 'block';
        }
    };

    xhr.send(JSON.stringify(payload));
    return false;
}

// Auto format card number input
document.addEventListener('input', function(e) {
    if (e.target && e.target.id === 'moneria_cc_number') {
        var v = e.target.value.replace(/\D/g, '').substring(0, 16);
        var matches = v.match(/\d{4,16}/g);
        var match = matches && matches[0] || '';
        var parts = [];
        for (var i = 0, len = match.length; i < len; i += 4) {
            parts.push(match.substring(i, i + 4));
        }
        if (parts.length) {
            e.target.value = parts.join(' ');
        } else {
            e.target.value = v;
        }
    }
});

