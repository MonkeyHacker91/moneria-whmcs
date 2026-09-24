# Moneria Pagamentos para WHMCS

<p align="center">
  <img src="https://moneria.com.br/wp-content/uploads/2023/11/logo-moneria.png" alt="Moneria Pagamentos" width="220" />
</p>

<p align="center">
  <strong>Aceite Cartão de Crédito, PIX e Boleto Bancário no seu WHMCS com baixa automática em tempo real.</strong><br>
  Pagamentos transparentes, modernos, seguros e sem redirecionamento para seus clientes.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WHMCS-7.10%20a%209.0+-blue?style=flat-square&logo=whmcs" alt="WHMCS Version" />
  <img src="https://img.shields.io/badge/PHP-7.4%20|%208.0%20|%208.1%20|%208.2%20|%208.3-777bb4?style=flat-square&logo=php" alt="PHP Version" />
  <img src="https://img.shields.io/badge/Seguran%C3%A7a-Zero--Trust%20HMAC-success?style=flat-square" alt="Security" />
  <img src="https://img.shields.io/badge/PCI--DSS-Compliant-brightgreen?style=flat-square" alt="PCI-DSS" />
  <img src="https://img.shields.io/badge/Licen%C3%A7a-MIT-orange?style=flat-square" alt="License" />
</p>

---

## ⚡ Principais Funcionalidades

- ⚡ **PIX Instantâneo:** Geração de QR Code dinâmico e código "Copia e Cola" com baixa em tempo real e atualização instantânea da tela da fatura via AJAX.
- 📄 **Boleto Bancário CIP Registrado:** Linha digitável com botão de 1 clique para cópia, Pix integrado no boleto e botão direto para download/impressão do PDF oficial emitido pelo banco.
- 💳 **Cartão de Crédito Transparente:** Formulário integrado diretamente na fatura, validação de número, bandeira, data de validade, CVV e parcelamento configurável em até 12x. Sem redirecionamento.
- 🔔 **Postback 100% Automático:** O módulo informa a URL de retorno dinamicamente na criação da cobrança. **Zero necessidade de cadastrar ou copiar URLs de webhook no painel da Moneria**.
- 🛡️ **Segurança Zero-Trust:** Todas as baixas via Postback passam por validação server-to-server na API da Moneria (`GET /charges/{id}`) antes de registrar o pagamento no WHMCS, impedindo notificações forjadas.
- 🎨 **Universalidade Visual:** Funciona com qualquer tema do WHMCS (Twenty-One, Six, Lagom, etc.) com 4 estilos integrados: **Dark Moderno**, **Clean Light**, **Dark Slate** e **Personalizado** (com seleção de cores Hex da sua marca).
- ✉️ **Boleto PDF nos E-mails:** Substitui automaticamente o anexo padrão de fatura do WHMCS pelo arquivo PDF oficial do boleto emitido pelo banco nos e-mails de cobrança enviados pelo sistema.
- 💬 **Painel Rápido no Admin (WhatsApp):** Na tela de visualização da fatura no painel de administração do WHMCS, exibe uma caixa prática com Pix Copia e Cola, Linha Digitável e Link Direto para envio rápido ao cliente via WhatsApp ou suporte.
- 🔄 **Sincronização de Cancelamento:** Se uma fatura for cancelada no WHMCS, a cobrança correspondente na Moneria é baixada/cancelada automaticamente.

---

## 📋 Requisitos do Sistema

| Requisito | Versão Suportada |
| :--- | :--- |
| **WHMCS** | 7.10, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.10, 8.11, 8.12, 8.13, 9.0+ |
| **PHP** | 7.4, 8.0, 8.1, 8.2, 8.3 |
| **Extensões PHP** | `curl`, `openssl`, `json`, `mbstring`, `bcmath` |
| **Conta Moneria** | Conta ativa com *Client ID* e *Client Secret* gerados no painel |

---

## 📥 Como Instalar

### Opção 1: Instalação Rápida via Arquivo ZIP (Recomendado)

1. Baixe o pacote mais recente [`moneria-whmcs.zip`](https://github.com/MonkeyHacker91/moneria-whmcs/releases/latest/download/moneria-whmcs.zip) na aba [Releases](https://github.com/MonkeyHacker91/moneria-whmcs/releases).
2. Extraia o conteúdo do arquivo `.zip` no seu computador.
3. Envie as pastas `modules/` e `includes/` para a raiz da instalação do seu WHMCS via FTP, cPanel File Manager ou SCP:
   ```bash
   # Exemplo via SCP / SSH:
   scp -r modules includes usuario@seuservidor.com:/caminho/para/public_html/
   ```
4. Ajuste as permissões dos arquivos no servidor:
   ```bash
   chmod -R 755 modules/gateways/moneria/
   chmod 644 modules/gateways/moneria.php modules/gateways/moneria_*.php
   chmod 644 modules/gateways/callback/moneria.php
   chmod 644 includes/hooks/moneria.php
   ```

---

## ⚙️ Configuração no Painel do WHMCS

1. Acesse o **Painel Administrativo do WHMCS**.
2. Vá em:
   * **WHMCS 8.x / 9.x:** Ícone de Engrenagem (topo direito) > **Opções do Sistema** (*System Settings*) > **Pagamentos** (*Payments*) > **Gateways de Pagamento** (*Payment Gateways*).
   * **WHMCS 7.x:** **Opções** (*Setup*) > **Pagamentos** (*Payments*) > **Gateways de Pagamento** (*Payment Gateways*).
3. Na aba **Todos os Gateways Disponíveis** (*All Available Gateways*), localize e ative os módulos desejados:
   * **Moneria Pagamentos (Pix e Boleto)**: Exibe abas interativas para o cliente escolher entre Pix ou Boleto em uma única opção.
   * **Moneria — PIX Instantâneo**: Módulo dedicado exclusivo para Pix.
   * **Moneria — Boleto Bancário**: Módulo dedicado exclusivo para Boleto.
   * **Moneria — Cartão de Crédito**: Módulo dedicado exclusivo para Cartão de Crédito.
4. Clique na aba **Gateways Gerenciados** (*Manage Existing Gateways*) e preencha os parâmetros:

### Parâmetros de Configuração:

| Campo | Descrição | Exemplo / Padrão |
| :--- | :--- | :--- |
| **Nome de Exibição** | Nome do método que o cliente vê na finalização e na fatura | `PIX / Boleto` |
| **Client ID** | Client ID da Chave de API gerada no painel da Moneria | `mon_live_client_xxxxxxxx` |
| **Client Secret** | Client Secret da Chave de API da Moneria | `mon_secret_xxxxxxxx` |
| **URL da API** | Endpoint da API da Moneria | `https://api.moneria.com.br` |
| **Estilo Visual da Fatura** | Tema do box de pagamento na tela da fatura | `Dark Moderno` ou `Clean Light` |
| **Campo de CPF/CNPJ** | Nome do Campo Personalizado do cliente (Custom Field) usado para CPF/CNPJ | `CNPJ/CPF` *(auto-detectado se omitido)* |
| **Aplicar Multa por Atraso** | Cobra multa percentual após o vencimento no boleto | Marque a caixa *(opcional)* |
| **Percentual de Multa (%)** | Percentual cobrado após o vencimento | `2.00` (2%) |
| **Aplicar Juros de Mora** | Cobra juros de mora diários no boleto após o vencimento | Marque a caixa *(opcional)* |
| **Juros Mensal (%)** | Percentual de juros ao mês calculado por dia de atraso | `1.00` (1% ao mês) |
| **Validade da Cobrança** | Dias corridos após o vencimento em que o boleto pode ser pago | `30` dias |
| **Substituir PDF do WHMCS** | Substitui o PDF padrão de fatura do WHMCS pelo PDF oficial emitido pelo banco | Marcado *(recomendado)* |
| **Painel Rápido na Fatura (Admin)** | Mostra dados de Pix/Boleto no admin para envio rápido por WhatsApp | Marcado *(recomendado)* |
| **Modo Debug / Logs** | Registra o tráfego detalhado em *Utilitários > Logs > Gateway Log* | Desmarcado *(ative se precisar depurar)* |

5. Clique em **Salvar Alterações** (*Save Changes*).

---

## 🎨 Personalização Visual (Temas)

O módulo oferece total liberdade estética para combinar perfeitamente com a identidade visual da sua marca:

* **Dark Moderno:** Fundo escuro contemporâneo (`#14221e`), badges douradas e alto contraste. Padrão nativo para portais escuros.
* **Clean Light:** Fundo branco puro (`#ffffff`), tipografia chumbo e detalhes sutis em azul. Perfeito para temas claros como Twenty-One, Six e Lagom claro.
* **Dark Slate:** Fundo grafite elegante (`#0f172a`) com acentos em azul céu (`#38bdf8`).
* **Personalizado:** Permite informar qualquer código Hexadecimal para:
  * *Cor Primária / Destaque* (Ex: `#0284c7`, `#10b981`, `#e2c83a`)
  * *Cor de Fundo do Card* (Ex: `#ffffff`, `#1e293b`)
  * *Cor do Texto Principal* (Ex: `#0f172a`, `#ffffff`)
  * *Arredondamento das Bordas* (Ex: `16px`, `12px`, `8px`, `4px`)

---

## ✉️ Tags para Modelos de E-mail (Merge Fields)

Você pode inserir as seguintes variáveis diretamente nos seus modelos de e-mail de cobrança em **Configuração** > **Modelos de E-mail**:

| Variável | O que renderiza no e-mail |
| :--- | :--- |
| `{$moneria_pix_code}` | Código Pix Copia e Cola puro para o cliente copiar no app do banco. |
| `{$moneria_pix_qrcode}` | Imagem do QR Code Pix em HTML pronta para leitura pela câmera. |
| `{$moneria_boleto_barcode}` | Linha digitável formatada do Boleto bancário. |
| `{$moneria_boleto_url}` | Link direto para visualizar ou imprimir o PDF oficial do boleto. |

---

## 🔔 Notificações Automáticas (Postback em Tempo Real)

O módulo utiliza o recurso de **Postback Dinâmico**:
* Ao registrar qualquer transação (Pix, Boleto ou Cartão), o módulo envia automaticamente a URL de retorno do seu WHMCS para a Moneria (`postBackUrl`).
* Assim que o pagamento é liquidado pelo cliente, a Moneria envia a notificação e a fatura recebe baixa automática em poucos segundos.
* **Você não precisa configurar nenhuma URL de webhook manual no painel da Moneria.**

---

## ⏰ Cron Job de Conciliação Automática (Opcional)

Para lojas com alto volume de vendas que desejam uma garantia extra de sincronização periódica caso ocorra alguma oscilação de rede no servidor, o módulo inclui um script de conciliação:

```bash
# Executar a cada 30 minutos via crontab:
*/30 * * * * php -q /caminho/para/seu/whmcs/modules/gateways/moneria/cron.php >/dev/null 2>&1
```

O script verifica cobranças pendentes na API da Moneria e atualiza automaticamente as faturas correspondentes no WHMCS.

---

## ❓ Perguntas Frequentes (FAQ)

<details>
<summary><strong>1. Onde encontro o Client ID e Client Secret?</strong></summary>
Acesse o seu painel na Moneria, navegue até a seção <strong>Configurações / Integrações > Chaves de API</strong> e gere uma nova credencial com permissões de cobrança e transações.
</details>

<details>
<summary><strong>2. Como o cliente informa o CPF ou CNPJ?</strong></summary>
O módulo possui detecção inteligente. Se você já tiver um Campo Personalizado de Cliente no WHMCS chamado <code>CPF</code>, <code>CNPJ</code>, <code>CPF/CNPJ</code> ou <code>Documento</code>, o módulo identifica automaticamente. Você também pode digitar o nome exato do campo na opção <em>Campo de CPF/CNPJ</em> nas configurações do gateway.
</details>

<details>
<summary><strong>3. É seguro processar Cartão de Crédito?</strong></summary>
Sim, 100%. O módulo atende aos requisitos do padrão PCI-DSS: os dados do cartão de crédito trafegam exclusivamente via conexão criptografada (HTTPS/TLS) diretamente para a API da Moneria e <strong>nunca são gravados no banco de dados do WHMCS</strong>. Além disso, requisições de cartão são protegidas contra CSRF com tokens criptográficos HMAC-SHA256.
</details>

<details>
<summary><strong>4. Como funciona a baixa do Boleto Bancário?</strong></summary>
A baixa do boleto ocorre automaticamente via notificação de compensação bancária (D+1 útil) ou instantaneamente caso o cliente realize o pagamento utilizando o QR Code Pix impresso no próprio boleto.
</details>

---

## 🛡️ Segurança e Privacidade

- **Proteção Zero-Trust:** Notificações de pagamento recebidas por webhook são checadas diretamente nos servidores da Moneria antes de computar qualquer crédito.
- **Proteção contra Enumeração (IDOR):** As consultas de status via polling na tela da fatura exigem tokens HMAC assinados com chave privada, impedindo a raspagem ou enumeração de faturas por terceiros.
- **Conformidade de Logs:** Nenhuma informação sensível de pagamento (PAN completo ou código CVV) é gravada nos logs do sistema.

---

## 📞 Suporte e Contato

- **Documentação da API:** [https://api.moneria.com.br/docs/reference](https://api.moneria.com.br/docs/reference)
- **Portal Oficial:** [https://moneria.com.br](https://moneria.com.br)
- **Repositório GitHub:** [https://github.com/MonkeyHacker91/moneria-whmcs](https://github.com/MonkeyHacker91/moneria-whmcs)

---

<p align="center">
  Desenvolvido com excelência para a comunidade WHMCS.
</p>
