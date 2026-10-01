# Moneria Payments for WHMCS (PIX, Boleto & Credit Card) / Moneria Pagamentos para WHMCS

<p align="center">
  <img src="https://moneria.com.br/wp-content/uploads/2023/11/logo-moneria.png" alt="Moneria Pagamentos" width="220" />
</p>

<p align="center">
  <strong>Accept Credit Card, Instant PIX, and Bank Slip (Boleto Bancário) in WHMCS with real-time automatic reconciliation.</strong><br>
  <em>Aceite Cartão de Crédito, PIX e Boleto Bancário no seu WHMCS com baixa automática em tempo real.</em>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WHMCS-7.10%20to%209.0+-blue?style=flat-square&logo=whmcs" alt="WHMCS Version" />
  <img src="https://img.shields.io/badge/PHP-7.4%20|%208.0%20|%208.1%20|%208.2%20|%208.3-777bb4?style=flat-square&logo=php" alt="PHP Version" />
  <img src="https://img.shields.io/badge/Security-Zero--Trust%20HMAC-success?style=flat-square" alt="Security" />
  <img src="https://img.shields.io/badge/PCI--DSS-Compliant-brightgreen?style=flat-square" alt="PCI-DSS" />
  <img src="https://img.shields.io/badge/License-MIT-orange?style=flat-square" alt="License" />
</p>

---

## 🇺🇸 English Version

### Overview
**Moneria Payments for WHMCS** is a high-performance, transparent payment gateway module designed for hosting providers, SaaS companies, and digital businesses operating in Brazil. It seamlessly connects WHMCS with the Moneria Payment Gateway (powered by BTG Pactual and CIP banking networks), enabling your customers to pay invoices instantly using **PIX (dynamic QR Code & Copy-Paste)**, **CIP Registered Bank Slips (Boleto Bancário with integrated Pix)**, and **Direct Credit Cards (with up to 12x installments)** without any external redirection.

### Key Features
- ⚡ **Instant PIX Payments:** Generates dynamic PIX QR Codes and Copy-Paste strings with real-time automatic reconciliation (instant invoice status update via AJAX).
- 📄 **CIP Registered Bank Slips (Boleto Bancário):** 1-click copy for the barcode line, integrated Pix on the bank slip, and direct download/print for the official bank-issued PDF.
- 💳 **Transparent Credit Card Processing:** Fully embedded checkout form inside the invoice with live card brand recognition, CVV validation, expiry checks, and configurable installments (1x to 12x). No iframe or redirection.
- 🔔 **Zero-Configuration Automatic Webhooks (Postback):** The module automatically sends the dynamic return URL upon charge creation. No manual webhook configuration needed.
- 🛡️ **Zero-Trust Server-to-Server Security:** Every webhook event is verified in real-time with the Moneria REST API (`GET /charge/{id}`) before marking invoices as Paid, eliminating fraudulent or forged notifications.
- 🎨 **Universal WHMCS Theme Compatibility:** Out-of-the-box support for Twenty-One, Six, Lagom, and custom themes, with 4 built-in aesthetic styles (Modern Dark, Clean Light, Dark Slate, and Custom Brand Colors).
- ✉️ **Boleto PDF Email Attachments:** Automatically replaces default WHMCS invoice attachments with official bank-rendered Boleto PDF files in outgoing billing emails.
- 💬 **Admin WhatsApp Quick-Action Box:** View PIX copy-paste, boleto barcodes, and direct payment links directly in the WHMCS Admin Invoice View for instant sharing with customers via WhatsApp or support tickets.
- 🔄 **Automatic Cancellation & DDA Sync:** Canceling or modifying invoices in WHMCS automatically cancels/reconciles charges on Moneria without generating duplicate records.

### System Requirements
- **WHMCS:** 7.10, 8.x, 9.0+
- **PHP:** 7.4, 8.0, 8.1, 8.2, 8.3
- **PHP Extensions:** `curl`, `openssl`, `json`, `mbstring`, `bcmath`
- **Moneria Account:** Active account with Client ID and Client Secret generated in the Moneria dashboard.

### Installation
1. Download the latest release [`moneria-whmcs.zip`](https://github.com/MonkeyHacker91/moneria-whmcs/releases/latest/download/moneria-whmcs.zip).
2. Extract the archive and upload the `modules/` and `includes/` folders to your WHMCS root directory via FTP, cPanel File Manager, or SSH.
3. In the WHMCS Admin Panel, navigate to **System Settings > Payment Gateways** (or **Setup > Payments > Payment Gateways** in WHMCS 7.x).
4. Under **All Available Gateways**, activate:
   - **Moneria Pagamentos (Pix e Boleto)** (Combined tabbed payment view)
   - **Moneria — PIX Instantâneo** (Standalone PIX module)
   - **Moneria — Boleto Bancário** (Standalone Boleto module)
   - **Moneria — Cartão de Crédito** (Standalone Credit Card module)
5. Enter your Moneria API credentials (**Client ID** and **Client Secret**) and save changes.

---

## 🇧🇷 Versão em Português

### Visão Geral
O **Moneria Pagamentos para WHMCS** é um módulo de gateway completo, transparente e de alta performance desenvolvido para empresas de hospedagem, provedores de serviços e negócios digitais no Brasil. Ele integra o WHMCS diretamente com a API da Moneria (com liquidação bancária via BTG Pactual e CIP), permitindo que seus clientes paguem faturas de forma transparente por **PIX Instantâneo**, **Boleto Bancário Registrado CIP** e **Cartão de Crédito Direto com Parcelamento**, sem redirecionamento.

### Principais Funcionalidades
- ⚡ **PIX Instantâneo:** Geração de QR Code dinâmico e código "Copia e Cola" com baixa em tempo real e atualização instantânea da tela da fatura via AJAX.
- 📄 **Boleto Bancário CIP Registrado:** Linha digitável com botão de 1 clique para cópia, Pix integrado no boleto e botão direto para download/impressão do PDF oficial emitido pelo banco.
- 💳 **Cartão de Crédito Transparente:** Formulário integrado diretamente na fatura, validação de número, bandeira, data de validade, CVV e parcelamento configurável em até 12x. Sem redirecionamento.
- 🔔 **Postback 100% Automático:** O módulo informa a URL de retorno dinamicamente na criação da cobrança. Zero necessidade de cadastrar ou copiar URLs de webhook no painel da Moneria.
- 🛡️ **Segurança Zero-Trust:** Todas as baixas via Postback passam por validação server-to-server na API da Moneria (`GET /charge/{id}`) antes de registrar o pagamento no WHMCS, impedindo notificações forjadas.
- 🎨 **Universalidade Visual:** Funciona com qualquer tema do WHMCS (Twenty-One, Six, Lagom, etc.) com 4 estilos integrados: Modern Dark, Clean Light, Dark Slate e Personalizado.
- ✉️ **Boleto PDF nos E-mails:** Substitui automaticamente o anexo padrão de fatura do WHMCS pelo arquivo PDF oficial do boleto emitido pelo banco nos e-mails de cobrança enviados pelo sistema.
- 💬 **Painel Rápido no Admin (WhatsApp):** Na tela de visualização da fatura no painel de administração do WHMCS, exibe uma caixa prática com Pix Copia e Cola, Linha Digitável e Link Direto para envio rápido ao cliente via WhatsApp ou suporte.
- 🔄 **Sincronização de Cancelamento:** Se uma fatura for cancelada no WHMCS, a cobrança correspondente na Moneria é baixada/cancelada automaticamente sem duplicidades.

### Requisitos do Sistema
- **WHMCS:** 7.10, 8.x, 9.0+
- **PHP:** 7.4, 8.0, 8.1, 8.2, 8.3
- **Extensões PHP:** `curl`, `openssl`, `json`, `mbstring`, `bcmath`
- **Conta Moneria:** Conta ativa com *Client ID* e *Client Secret* gerados no painel.

### Como Instalar
1. Baixe o pacote mais recente [`moneria-whmcs.zip`](https://github.com/MonkeyHacker91/moneria-whmcs/releases/latest/download/moneria-whmcs.zip) na aba [Releases](https://github.com/MonkeyHacker91/moneria-whmcs/releases).
2. Extraia o conteúdo e envie as pastas `modules/` e `includes/` para a raiz da instalação do seu WHMCS.
3. No painel administrativo do WHMCS, acesse **Opções do Sistema > Pagamentos > Gateways de Pagamento**.
4. Ative os módulos desejados da Moneria.
5. Insira seu **Client ID** e **Client Secret** obtidos no painel da Moneria e clique em **Salvar Alterações**.

---

### Licença
Distribuído sob a licença MIT. Consulte `LICENSE` para mais informações.
