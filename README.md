# Moneria Pagamentos para WHMCS

> **Aceite cartão de crédito, PIX e boleto bancário na sua loja com a Moneria. Pagamentos simples, seguros e transparentes para você e seus clientes.**

---

## 🚀 Módulos Disponíveis

Você pode ativar os módulos individualmente de acordo com a sua necessidade no checkout do WHMCS:

1. **Moneria — PIX Instantâneo** (`moneria_pix`):
   - Exibição de QR Code e código "Copia e Cola" na tela da fatura.
   - Botão de 1 clique para copiar o código Pix.
   - Verificação automática em tempo real (AJAX Polling) com baixa instantânea na fatura.

2. **Moneria — Boleto Bancário** (`moneria_boleto`):
   - Linha Digitável com botão de cópia.
   - Botão direto para Visualizar/Imprimir o PDF oficial emitido pelo banco.
   - Pix integrado no boleto.
   - Opção para **anexar ou substituir o PDF do WHMCS pelo Boleto PDF oficial da Moneria** nos e-mails de cobrança.
   - Configuração de Multa por atraso e Juros de Mora diários.

3. **Moneria — Cartão de Crédito** (`moneria_creditcard`):
   - Checkout direto na fatura com validação e formatação automática.
   - Opções de parcelamento em até 12x.
   - Processamento direto e seguro via API da Moneria (`/transactions/authorize`).

4. **Moneria Unificado (Pix + Boleto)** (`moneria`):
   - Interface com abas para clientes alternarem entre Pix e Boleto em uma única opção.

---

## 📁 Estrutura de Arquivos

```
modules/
└── gateways/
    ├── moneria_pix.php                     # Módulo Dedicado: Moneria PIX Instantâneo
    ├── moneria_boleto.php                  # Módulo Dedicado: Moneria Boleto Bancário
    ├── moneria_creditcard.php              # Módulo Dedicado: Moneria Cartão de Crédito
    ├── moneria.php                         # Módulo Unificado (Abas PIX + Boleto)
    ├── callback/
    │   └── moneria.php                     # Endpoint de Webhook, Polling e Cartão
    └── moneria/
        ├── src/
        │   ├── MoneriaClient.php           # Cliente HTTP da API Moneria (OAuth, Clientes, Cobranças e Cartão)
        │   └── MoneriaHelper.php           # Utilitários de CPF/CNPJ, CEP, parcelamento e banco
        ├── templates/
        │   ├── pix_view.php                # Tela exclusiva para PIX
        │   ├── boleto_view.php             # Tela exclusiva para Boleto
        │   ├── creditcard_view.php         # Tela/Formulário de Cartão de Crédito
        │   └── payment_view.php            # Tela com abas (PIX + Boleto)
        └── assets/
            ├── moneria.css                 # Folha de estilo moderna e responsiva
            └── moneria.js                  # Lógica de cópia, polling e processamento de cartão
includes/
└── hooks/
    └── moneria.php                         # Hook para envio de Boletos PDF e Merge Fields em e-mails
```

---

## 📦 Instalação

1. Copie as pastas `modules/` e `includes/` deste repositório para a raiz da sua instalação do WHMCS:
   ```bash
   cp -r modules/ includes/ /caminho/para/seu/whmcs/
   ```
2. Certifique-se de que as permissões dos arquivos estejam corretas (ex: 644 para arquivos e 755 para diretórios).

---

## ⚙️ Configuração no WHMCS

1. Acesse o Painel Administrativo do WHMCS.
2. Navegue até **Configuração (Setup)** > **Pagamentos (Payments)** > **Gateways de Pagamento (Payment Gateways)**.
3. Na aba **Todos os Gateways**, localize **Moneria (Pix e Boleto)** e clique para **Ativar**.
4. Preencha as seguintes opções:
   - **Client ID**: O ID da sua Chave de API gerada no Painel da Moneria.
   - **Client Secret**: O Segredo da sua Chave de API da Moneria.
   - **URL da API**: `https://api.moneria.com.br` (padrão).
   - **Estilo Visual da Fatura**: Escolha entre `Dark Moderno`, `Clean Light` (fundo claro ideal para Twenty-One/Six), `Dark Slate` (grafite/azul) ou `Personalizado`.
   - **Personalização de Cores**: No modo Personalizado, defina Cor de Destaque, Fundo do Card, Cor do Texto e Arredondamento das Bordas da sua marca.
   - **Métodos de Pagamento**: Escolha entre `PIX e Boleto Bancário`, `Apenas PIX` ou `Apenas Boleto Bancário`.
   - **Campo de CPF/CNPJ**: Nome do campo personalizado do cliente (Custom Field) usado para armazenar o CPF/CNPJ (Ex: `CPF`, `CNPJ` ou `CPF/CNPJ`). *O módulo também possui detecção inteligente automática*.
   - **Anexar Boleto PDF nos E-mails**: Marque para anexar o arquivo PDF do boleto da Moneria nos e-mails de cobrança enviados pelo WHMCS.
   - **Substituir PDF do WHMCS**: Marque para que o anexo do e-mail seja **exclusivamente o Boleto PDF oficial da Moneria**, removendo o PDF padrão gerado pelo WHMCS.
   - **Multa e Juros**: Configure se deseja aplicar multa (%) e juros por atraso.
   - **Modo Debug / Logs**: Marque para registrar logs de requisições em **Utilitários (Utilities) > Logs > Gateway Log**.
5. Clique em **Salvar Alterações**.

---

## 📧 Variáveis Disponíveis para Modelos de E-mail

Você pode utilizar as seguintes variáveis diretamente nos seus modelos de e-mail de fatura (**Configuração > Modelos de E-mail**):

- `{$moneria_boleto_url}` : Link direto para visualização e impressão do Boleto.
- `{$moneria_boleto_barcode}` : Linha digitável do Boleto bancário.
- `{$moneria_pix_code}` : Código Pix Copia e Cola.
- `{$moneria_pix_qrcode}` : Imagem do QR Code Pix renderizada em HTML.

---

## 🔔 Notificações Automáticas (Postback em Tempo Real)

O módulo envia dinamicamente a URL de retorno durante a geração de cada cobrança (`postBackUrl`). Isso significa que a comunicação entre a Moneria e o WHMCS é **100% automática**: assim que o cliente paga (Pix, Boleto ou Cartão), a Moneria notifica o WHMCS e a fatura recebe baixa instantânea, sem necessidade de cadastros manuais de webhook.

---

## 🧪 Suporte e Licença

Desenvolvido para integração perfeita com a plataforma **Moneria**.
Para dúvidas sobre a API da Moneria, consulte a [Documentação Oficial](https://api.moneria.com.br/docs/reference).
