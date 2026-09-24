<?php

namespace Moneria\Gateway;

use Exception;
use WHMCS\Database\Capsule;

/**
 * Class MoneriaHelper
 * 
 * Helper utilities for formatting, CPF/CNPJ extraction, database persistence and template rendering.
 */
class MoneriaHelper
{
    /**
     * Safely retrieves gateway configuration parameters without breaking WHMCS function definitions.
     *
     * @param string $gateway
     * @return array
     */
    public static function getGatewayParams(string $gateway = 'moneria'): array
    {
        if (!function_exists('getGatewayVariables')) {
            if (defined('ROOTDIR') && file_exists(ROOTDIR . '/includes/gatewayfunctions.php')) {
                require_once ROOTDIR . '/includes/gatewayfunctions.php';
            } elseif (file_exists(__DIR__ . '/../../../../includes/gatewayfunctions.php')) {
                require_once __DIR__ . '/../../../../includes/gatewayfunctions.php';
            }
        }

        if (function_exists('getGatewayVariables')) {
            try {
                return (array)getGatewayVariables($gateway);
            } catch (\Throwable $t) {
                // Fallback to database
            }
        }

        $params = ['paymentmethod' => $gateway];
        if (class_exists('WHMCS\Database\Capsule')) {
            try {
                $rows = Capsule::table('tblpaymentgateways')
                    ->where('gateway', $gateway)
                    ->get();
                foreach ($rows as $row) {
                    $val = $row->value;
                    if (function_exists('decrypt')) {
                        try {
                            $val = decrypt($val);
                        } catch (\Throwable $t) {}
                    }
                    $params[$row->setting] = $val;
                }
            } catch (\Throwable $t) {}
        }

        return $params;
    }

    /**
     * Returns configuration fields for visual theme customization.
     *
     * @return array
     */
    public static function getThemeConfigFields(): array
    {
        return [
            'themeStyle' => [
                'FriendlyName' => 'Estilo Visual da Fatura',
                'Type' => 'dropdown',
                'Options' => [
                    'dark'   => 'Dark Moderno',
                    'light'  => 'Clean Light',
                    'slate'  => 'Dark Slate',
                    'custom' => 'Personalizado',
                ],
                'Default' => 'dark',
                'Description' => 'Escolha o visual do box de pagamento para harmonizar com o seu tema WHMCS.',
            ],
            'themePrimaryColor' => [
                'FriendlyName' => 'Cor Primária / Destaque (Personalizado)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '',
                'Description' => 'Usada no modo Personalizado para botões, badges e ícones (Ex: #0284c7, #e2c83a, #10b981).',
            ],
            'themeCardBg' => [
                'FriendlyName' => 'Cor de Fundo do Card (Personalizado)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '',
                'Description' => 'Usada no modo Personalizado para o fundo da caixa (Ex: #ffffff, #1e293b, #14221e).',
            ],
            'themeTextColor' => [
                'FriendlyName' => 'Cor do Texto (Personalizado)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '',
                'Description' => 'Usada no modo Personalizado para os textos principais (Ex: #0f172a, #f8fafc).',
            ],
            'themeBorderRadius' => [
                'FriendlyName' => 'Arredondamento das Bordas',
                'Type' => 'text',
                'Size' => '6',
                'Default' => '16px',
                'Description' => 'Raio de curvatura das bordas do box de pagamento (Ex: 16px, 12px, 8px, 4px).',
            ],
        ];
    }

    /**
     * Calculates CSS variables and color tokens according to gateway configuration.
     *
     * @param array $params
     * @return array
     */
    public static function getThemeVariables(array $params): array
    {
        $style = $params['themeStyle'] ?? 'dark';
        $radius = !empty($params['themeBorderRadius']) ? trim($params['themeBorderRadius']) : '16px';
        if (is_numeric($radius)) {
            $radius .= 'px';
        }

        switch ($style) {
            case 'light':
                $bg = '#ffffff';
                $bg2 = '#f8fafc';
                $border = '#e2e8f0';
                $text = '#0f172a';
                $muted = '#64748b';
                $accent = '#0284c7';
                $accent2 = '#0369a1';
                $ctaText = '#ffffff';
                $inputBg = '#f8fafc';
                $inputBorder = '#cbd5e1';
                $inputText = '#0f172a';
                $noteBg = '#f8fafc';
                $noteBorder = '#e2e8f0';
                $noteText = '#475569';
                $boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04)';
                break;

            case 'slate':
                $bg = '#0f172a';
                $bg2 = '#1e293b';
                $border = 'rgba(255, 255, 255, 0.12)';
                $text = '#f8fafc';
                $muted = '#94a3b8';
                $accent = '#38bdf8';
                $accent2 = '#0284c7';
                $ctaText = '#ffffff';
                $inputBg = '#1e293b';
                $inputBorder = 'rgba(255, 255, 255, 0.14)';
                $inputText = '#ffffff';
                $noteBg = 'rgba(56, 189, 248, 0.08)';
                $noteBorder = 'rgba(56, 189, 248, 0.2)';
                $noteText = '#bae6fd';
                $boxShadow = '0 16px 40px rgba(0, 0, 0, 0.4)';
                break;

            case 'custom':
                $accent = !empty($params['themePrimaryColor']) ? trim($params['themePrimaryColor']) : '#0284c7';
                $accent2 = $accent;
                $bg = !empty($params['themeCardBg']) ? trim($params['themeCardBg']) : '#ffffff';
                $text = !empty($params['themeTextColor']) ? trim($params['themeTextColor']) : '#0f172a';
                
                $isDarkBg = self::isColorDark($bg);
                if ($isDarkBg) {
                    $bg2 = 'rgba(255, 255, 255, 0.05)';
                    $border = 'rgba(255, 255, 255, 0.12)';
                    $muted = 'rgba(255, 255, 255, 0.7)';
                    $inputBg = 'rgba(255, 255, 255, 0.06)';
                    $inputBorder = 'rgba(255, 255, 255, 0.15)';
                    $inputText = '#ffffff';
                    $noteBg = 'rgba(255, 255, 255, 0.05)';
                    $noteBorder = 'rgba(255, 255, 255, 0.12)';
                    $noteText = 'rgba(255, 255, 255, 0.85)';
                    $boxShadow = '0 16px 40px rgba(0, 0, 0, 0.35)';
                } else {
                    $bg2 = '#f8fafc';
                    $border = '#e2e8f0';
                    $muted = '#64748b';
                    $inputBg = '#f8fafc';
                    $inputBorder = '#cbd5e1';
                    $inputText = '#0f172a';
                    $noteBg = '#f8fafc';
                    $noteBorder = '#e2e8f0';
                    $noteText = '#475569';
                    $boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04)';
                }
                $ctaText = self::isColorDark($accent) ? '#ffffff' : '#0f172a';
                break;

            case 'dark':
            default:
                $style = 'dark';
                $bg = '#14221e';
                $bg2 = '#0d1714';
                $border = 'rgba(255, 255, 255, 0.1)';
                $text = '#eef3f0';
                $muted = 'rgba(238, 243, 240, 0.72)';
                $accent = '#e2c83a';
                $accent2 = '#d1b42b';
                $ctaText = '#204d44';
                $inputBg = '#0d1714';
                $inputBorder = 'rgba(255, 255, 255, 0.1)';
                $inputText = '#eef3f0';
                $noteBg = 'rgba(251, 191, 36, 0.1)';
                $noteBorder = 'rgba(251, 191, 36, 0.28)';
                $noteText = '#fde68a';
                $boxShadow = '0 16px 40px rgba(0, 0, 0, 0.35)';
                break;
        }

        $inlineStyle = sprintf(
            '--gb-bg:%s;--gb-bg-2:%s;--gb-border:%s;--gb-text:%s;--gb-muted:%s;--gb-accent:%s;--gb-accent-2:%s;--gb-cta-text:%s;--gb-radius:%s;--gb-shadow:%s;--gb-input-bg:%s;--gb-input-border:%s;--gb-input-text:%s;--gb-note-bg:%s;--gb-note-border:%s;--gb-note-text:%s;',
            $bg, $bg2, $border, $text, $muted, $accent, $accent2, $ctaText, $radius, $boxShadow, $inputBg, $inputBorder, $inputText, $noteBg, $noteBorder, $noteText
        );

        return [
            'themeStyle'   => $style,
            'inlineStyle'  => $inlineStyle,
            'bg'           => $bg,
            'bg2'          => $bg2,
            'border'       => $border,
            'text'         => $text,
            'muted'        => $muted,
            'accent'       => $accent,
            'accent2'      => $accent2,
            'ctaText'      => $ctaText,
            'radius'       => $radius,
            'boxShadow'    => $boxShadow,
            'inputBg'      => $inputBg,
            'inputBorder'  => $inputBorder,
            'inputText'    => $inputText,
            'noteBg'       => $noteBg,
            'noteBorder'   => $noteBorder,
            'noteText'     => $noteText,
        ];
    }

    /**
     * Determines whether a HEX color is dark or light based on luminance.
     *
     * @param string $hexColor
     * @return bool
     */
    public static function isColorDark(string $hexColor): bool
    {
        $hex = ltrim($hexColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return false;
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return $yiq < 128;
    }

    /**
     * Strips all non-numeric characters.
     *
     * @param string|null $value
     * @return string
     */
    public static function onlyNumbers(?string $value): string
    {
        return preg_replace('/\D/', '', $value ?? '') ?? '';
    }

    /**
     * Normalizes phone number to Brazilian E.164 (+55...) format.
     *
     * @param string|null $phone
     * @return string|null
     */
    public static function formatPhone(?string $phone): ?string
    {
        $digits = self::onlyNumbers($phone);
        if (empty($digits)) {
            return null;
        }

        // If starts with country code 55 and length > 11, strip leading 55
        if (strlen($digits) >= 12 && substr($digits, 0, 2) === '55') {
            $digits = substr($digits, 2);
        }

        // Brazilian DDD + Number: 10 or 11 digits
        if (strlen($digits) === 10) {
            $ddd = substr($digits, 0, 2);
            $num = substr($digits, 2);
            // If mobile starting with 6, 7, 8, 9, add 9th digit
            if (in_array(substr($num, 0, 1), ['6', '7', '8', '9'])) {
                $digits = $ddd . '9' . $num;
            }
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $ddd = (int)substr($digits, 0, 2);
            if ($ddd >= 11 && $ddd <= 99) {
                return '+55' . $digits;
            }
        }

        return null;
    }

    /**
     * Formats Brazilian CEP (00000-000).
     *
     * @param string|null $cep
     * @return string
     */
    public static function formatCep(?string $cep): string
    {
        $digits = self::onlyNumbers($cep);
        if (strlen($digits) === 8) {
            return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
        }
        return $cep ?? '';
    }

    /**
     * Extracts and sanitizes address street number to maximum 10 characters.
     * Guaranteed to return a valid value accepted by Moneria / CIP bank registration.
     *
     * @param string|null $address1
     * @param string|null $rawNumber
     * @return string
     */
    public static function extractAddressNumber(?string $address1, ?string $rawNumber = null): string
    {
        // 1. If rawNumber provided and has digits, sanitize and return
        if (!empty($rawNumber) && !in_array(strtolower(trim($rawNumber)), ['s/n', 'sn', 'preencher', 'sem numero', 'sem número', 'null'], true)) {
            $digits = preg_replace('/[^\d\w\/-]/', '', trim($rawNumber));
            if (!empty($digits)) {
                return substr($digits, 0, 10);
            }
        }

        $addr = trim($address1 ?? '');
        if (!empty($addr) && !in_array(strtolower($addr), ['preencher', 's/n', 'sn', 'sem numero', 'sem número'], true)) {
            // Check number after comma: "Rua X, 123" or "Rua X, 123-A"
            if (preg_match('/,\s*(\d+[a-zA-Z0-9\/-]*)/', $addr, $m)) {
                return substr(trim($m[1]), 0, 10);
            }

            // Check "nº 123", "num 123", "n. 123"
            if (preg_match('/(?:n[º°.]?|numero|num|n)\s*:?\s*(\d+[a-zA-Z0-9\/-]*)/i', $addr, $m)) {
                return substr(trim($m[1]), 0, 10);
            }

            // Check number at the end of the line: "Rua das Flores 123"
            if (preg_match('/\b(\d+[a-zA-Z0-9\/-]*)\s*$/', $addr, $m)) {
                return substr(trim($m[1]), 0, 10);
            }

            // Any standalone number in the address
            if (preg_match('/\b(\d{1,6}[a-zA-Z]?)\b/', $addr, $m)) {
                return substr(trim($m[1]), 0, 10);
            }
        }

        // Safe fallback for banking APIs (CIP requires numeric street number)
        return '100';
    }

    /**
     * Extracts CPF or CNPJ from WHMCS params, client details, and database.
     *
     * @param array $params
     * @return string
     */
    public static function extractDocument(array $params): string
    {
        $customFieldSetting = trim($params['customFieldDoc'] ?? '');
        $keywords = ['cnpj/cpf', 'cpf/cnpj', 'cpf', 'cnpj', 'documento', 'doc', 'taxid', 'tax_id'];

        // 1. Check in $params['clientdetails']['customfields']
        if (!empty($params['clientdetails']['customfields'])) {
            $cfs = $params['clientdetails']['customfields'];

            if (is_array($cfs)) {
                foreach ($cfs as $key => $item) {
                    $name = '';
                    $val = '';

                    if (is_array($item)) {
                        $name = $item['name'] ?? ($item['fieldname'] ?? (is_string($key) ? $key : ''));
                        $val = $item['value'] ?? ($item['rawvalue'] ?? '');
                    } elseif (is_object($item)) {
                        $name = $item->name ?? ($item->fieldname ?? (is_string($key) ? $key : ''));
                        $val = $item->value ?? ($item->rawvalue ?? '');
                    } elseif (is_string($item) || is_numeric($item)) {
                        $name = is_string($key) ? $key : '';
                        $val = (string)$item;
                    }

                    // Remove description after pipe if present (e.g., "CNPJ/CPF|Informe seu documento")
                    $cleanName = explode('|', $name)[0];

                    // Check configured setting
                    if (!empty($customFieldSetting) && strcasecmp(trim($cleanName), $customFieldSetting) === 0) {
                        $doc = self::onlyNumbers($val);
                        if (strlen($doc) >= 11) {
                            return $doc;
                        }
                    }

                    // Check keywords
                    $lowerName = strtolower(trim($cleanName));
                    foreach ($keywords as $kw) {
                        if (strpos($lowerName, $kw) !== false) {
                            $doc = self::onlyNumbers($val);
                            if (strlen($doc) >= 11) {
                                return $doc;
                            }
                        }
                    }
                }
            }
        }

        // 2. Check numbered customfields in clientdetails ($params['clientdetails']['customfield1'] etc.)
        if (!empty($params['clientdetails']) && is_array($params['clientdetails'])) {
            foreach ($params['clientdetails'] as $k => $v) {
                if (is_string($v) && stripos($k, 'customfield') !== false) {
                    $doc = self::onlyNumbers($v);
                    if (strlen($doc) === 11 || strlen($doc) === 14) {
                        return $doc;
                    }
                }
            }
        }

        // 3. Check WHMCS native Tax ID field
        if (!empty($params['clientdetails']['tax_id'])) {
            $doc = self::onlyNumbers($params['clientdetails']['tax_id']);
            if (strlen($doc) >= 11) {
                return $doc;
            }
        }

        // 4. Direct Database Lookup (Guaranteed fallback)
        try {
            if (class_exists('WHMCS\Database\Capsule')) {
                $userId = $params['clientdetails']['userid'] ?? ($params['clientdetails']['id'] ?? 0);

                if (!$userId && !empty($params['invoiceid'])) {
                    $userId = Capsule::table('tblinvoices')->where('id', (int)$params['invoiceid'])->value('userid');
                }

                if ($userId) {
                    // Check client tax_id in database
                    $taxId = Capsule::table('tblclients')->where('id', $userId)->value('tax_id');
                    if (!empty($taxId)) {
                        $doc = self::onlyNumbers($taxId);
                        if (strlen($doc) >= 11) {
                            return $doc;
                        }
                    }

                    // Check custom fields in database
                    $cfValues = Capsule::table('tblcustomfieldsvalues')
                        ->join('tblcustomfields', 'tblcustomfields.id', '=', 'tblcustomfieldsvalues.fieldid')
                        ->where('tblcustomfields.type', 'client')
                        ->where('tblcustomfieldsvalues.relid', $userId)
                        ->get(['tblcustomfields.fieldname', 'tblcustomfieldsvalues.value']);

                    // Priority 1: Match configured custom field setting
                    if (!empty($customFieldSetting)) {
                        foreach ($cfValues as $cf) {
                            $cleanName = explode('|', $cf->fieldname)[0];
                            if (strcasecmp(trim($cleanName), $customFieldSetting) === 0) {
                                $doc = self::onlyNumbers($cf->value);
                                if (strlen($doc) >= 11) {
                                    return $doc;
                                }
                            }
                        }
                    }

                    // Priority 2: Match any document keyword
                    foreach ($cfValues as $cf) {
                        $cleanName = strtolower(explode('|', $cf->fieldname)[0]);
                        foreach ($keywords as $kw) {
                            if (strpos($cleanName, $kw) !== false) {
                                $doc = self::onlyNumbers($cf->value);
                                if (strlen($doc) >= 11) {
                                    return $doc;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fallback
        }

        return '';
    }

    /**
     * Retrieves cached OAuth access token from database.
     *
     * @param string $clientId
     * @return string|null
     */
    public static function getCachedToken(string $clientId): ?string
    {
        try {
            if (!class_exists('WHMCS\Database\Capsule')) {
                return null;
            }

            self::ensureTables();

            $record = Capsule::table('mod_moneria_meta')
                ->where('setting_key', 'oauth_token_' . md5($clientId))
                ->first();

            if ($record && !empty($record->setting_value) && (int)$record->expires_at > time()) {
                return $record->setting_value;
            }
        } catch (Exception $e) {
            // Silently fallback if table not yet available
        }

        return null;
    }

    /**
     * Stores OAuth access token in database with TTL.
     *
     * @param string $clientId
     * @param string $token
     * @param int $ttlSeconds
     * @return void
     */
    public static function setCachedToken(string $clientId, string $token, int $ttlSeconds = 780): void
    {
        try {
            if (!class_exists('WHMCS\Database\Capsule')) {
                return;
            }

            self::ensureTables();

            $key = 'oauth_token_' . md5($clientId);
            $expiresAt = time() + $ttlSeconds;

            Capsule::table('mod_moneria_meta')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $token, 'expires_at' => $expiresAt, 'updated_at' => date('Y-m-d H:i:s')]
            );
        } catch (Exception $e) {
            // Log or ignore cache error
        }
    }

    /**
     * Stores Moneria Charge metadata for a WHMCS invoice.
     *
     * @param int $invoiceId
     * @param array $chargeData
     * @return void
     */
    public static function saveInvoiceCharge(int $invoiceId, array $chargeData): void
    {
        try {
            if (!class_exists('WHMCS\Database\Capsule')) {
                return;
            }

            self::ensureTables();

            $chargeId = $chargeData['id'] ?? '';
            $status = $chargeData['status'] ?? 'PENDING';
            $payloadJson = json_encode($chargeData, JSON_UNESCAPED_UNICODE);

            Capsule::table('mod_moneria_charges')->updateOrInsert(
                ['invoice_id' => $invoiceId],
                [
                    'charge_id' => $chargeId,
                    'status' => $status,
                    'payload' => $payloadJson,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (Exception $e) {
            // Fail safely
        }
    }

    /**
     * Retrieves existing Moneria Charge metadata for a WHMCS invoice.
     *
     * @param int $invoiceId
     * @return array|null
     */
    public static function getInvoiceCharge(int $invoiceId): ?array
    {
        try {
            if (!class_exists('WHMCS\Database\Capsule')) {
                return null;
            }

            self::ensureTables();

            $record = Capsule::table('mod_moneria_charges')
                ->where('invoice_id', $invoiceId)
                ->first();

            if ($record && !empty($record->payload)) {
                $decoded = json_decode($record->payload, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (Exception $e) {
            // Fail safely
        }

        return null;
    }

    /**
     * Finds WHMCS invoice ID associated with a Moneria chargeId or transactionId.
     *
     * @param string $chargeId
     * @return int|null
     */
    public static function findInvoiceIdByCharge(string $chargeId): ?int
    {
        try {
            if (!class_exists('WHMCS\Database\Capsule') || empty($chargeId)) {
                return null;
            }

            self::ensureTables();

            $record = Capsule::table('mod_moneria_charges')
                ->where('charge_id', $chargeId)
                ->first();

            if ($record) {
                return (int)$record->invoice_id;
            }

            // Fallback: search within payload JSON for transaction ID / charge ID
            $record = Capsule::table('mod_moneria_charges')
                ->where('payload', 'like', '%' . $chargeId . '%')
                ->first();

            if ($record) {
                return (int)$record->invoice_id;
            }
        } catch (Exception $e) {
            // Fail safely
        }

        return null;
    }

    /**
     * Ensures helper metadata tables exist in WHMCS database.
     *
     * @return void
     */
    public static function ensureTables(): void
    {
        if (!class_exists('WHMCS\Database\Capsule')) {
            return;
        }

        $schema = Capsule::schema();

        if (!$schema->hasTable('mod_moneria_meta')) {
            $schema->create('mod_moneria_meta', function ($table) {
                $table->string('setting_key', 128)->primary();
                $table->text('setting_value')->nullable();
                $table->bigInteger('expires_at')->default(0);
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (!$schema->hasTable('mod_moneria_charges')) {
            $schema->create('mod_moneria_charges', function ($table) {
                $table->increments('id');
                $table->integer('invoice_id')->unique();
                $table->string('charge_id', 128)->index();
                $table->string('status', 32)->default('PENDING');
                $table->mediumText('payload')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }
    }

    /**
     * Extracts YYYY-MM-DD from string safely without timezone shifting issues.
     *
     * @param string|null $dateStr
     * @return string
     */
    public static function sanitizeDate(?string $dateStr): string
    {
        if (empty($dateStr)) {
            return '';
        }
        $trimmed = trim($dateStr);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $trimmed, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $trimmed, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        $ts = strtotime($trimmed);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    /**
     * Clears any locally cached Boleto PDF file for an invoice.
     *
     * @param int $invoiceId
     * @return void
     */
    public static function clearCachedBoletoPdf(int $invoiceId): void
    {
        $directories = [sys_get_temp_dir()];
        if (defined('ROOTDIR')) {
            $directories[] = ROOTDIR . '/templates_c';
            $directories[] = ROOTDIR . '/downloads';
        }
        foreach ($directories as $dir) {
            $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "boleto_moneria_{$invoiceId}.pdf";
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Downloads or generates Boleto PDF locally for email attachment.
     *
     * @param int $invoiceId
     * @param string $boletoUrl
     * @param array $chargeData
     * @return string|null Path to local PDF file
     */
    public static function getOrDownloadBoletoPdf(int $invoiceId, string $boletoUrl, array $chargeData = []): ?string
    {
        // Determine temporary/storage directory
        $targetDir = sys_get_temp_dir();
        if (defined('ROOTDIR')) {
            if (is_dir(ROOTDIR . '/templates_c') && is_writable(ROOTDIR . '/templates_c')) {
                $targetDir = ROOTDIR . '/templates_c';
            } elseif (is_dir(ROOTDIR . '/downloads') && is_writable(ROOTDIR . '/downloads')) {
                $targetDir = ROOTDIR . '/downloads';
            }
        }

        $filePath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "boleto_moneria_{$invoiceId}.pdf";

        // Return if cached valid PDF
        if (file_exists($filePath) && (time() - filemtime($filePath) < 86400)) {
            $head = @file_get_contents($filePath, false, null, 0, 4);
            if ($head === '%PDF') {
                return $filePath;
            }
        }

        // Try generating PDF via WHMCS built-in TCPDF
        $tcpdfPath = defined('ROOTDIR') ? ROOTDIR . '/vendor/tecnickcom/tcpdf/tcpdf.php' : '';
        if ($tcpdfPath && file_exists($tcpdfPath)) {
            require_once $tcpdfPath;
            try {
                $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value') ?? 'WHMCS';
                $pdf->SetCreator('WHMCS');
                $pdf->SetAuthor($companyName);
                $pdf->SetTitle('Boleto Bancário - Fatura #' . $invoiceId);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetMargins(15, 15, 15);
                $pdf->AddPage();

                $barCode = $chargeData['barCode'] ?? '';
                $amount = number_format((float)($chargeData['amount'] ?? 0), 2, ',', '.');
                $dueDate = !empty($chargeData['dueDate']) ? date('d/m/Y', strtotime($chargeData['dueDate'])) : date('d/m/Y');
                $payerName = htmlspecialchars($chargeData['payerName'] ?? 'Cliente');
                $payerDoc = htmlspecialchars($chargeData['payerDoc'] ?? '');

                $html = '
                <div style="font-family: helvetica; color: #1e293b;">
                    <table width="100%" cellpadding="6" style="border-bottom: 2px solid #0f172a;">
                        <tr>
                            <td width="50%"><span style="font-size: 18px; font-weight: bold; color: #0284c7;">' . htmlspecialchars($companyName) . '</span></td>
                            <td width="50%" align="right"><span style="font-size: 14px; font-weight: bold; color: #0f172a;">BOLETO BANCÁRIO</span></td>
                        </tr>
                    </table>
                    <br><br>
                    <table width="100%" cellpadding="6" style="border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <tr>
                            <td width="50%"><strong>Fatura:</strong> #' . $invoiceId . '</td>
                            <td width="50%" align="right"><strong>Vencimento:</strong> ' . $dueDate . '</td>
                        </tr>
                        <tr>
                            <td width="50%"><strong>Beneficiário:</strong> ' . htmlspecialchars($companyName) . '</td>
                            <td width="50%" align="right"><strong>Valor:</strong> R$ ' . $amount . '</td>
                        </tr>
                        <tr>
                            <td colspan="2"><strong>Pagador:</strong> ' . $payerName . ($payerDoc ? ' (' . $payerDoc . ')' : '') . '</td>
                        </tr>
                    </table>
                    <br><br>
                    <div style="background-color: #0f172a; color: #ffffff; padding: 12px; text-align: center; font-size: 13px; font-weight: bold; border-radius: 4px;">
                        LINHA DIGITÁVEL DO BOLETO (PAGÁVEL EM QUALQUER BANCO OU APLICATIVO):<br>
                        <span style="font-size: 14px; color: #38bdf8; letter-spacing: 1px;">' . htmlspecialchars($barCode) . '</span>
                    </div>
                    <br><br>';

                if (!empty($boletoUrl)) {
                    $html .= '
                    <table width="100%" cellpadding="8">
                        <tr>
                            <td align="center">
                                <a href="' . htmlspecialchars($boletoUrl) . '" style="background-color: #204d44; color: #e2c83a; padding: 12px 24px; text-decoration: none; font-weight: bold; font-size: 14px; border-radius: 6px;">CLIQUE AQUI PARA ABRIR / IMPRIMIR O BOLETO OFICIAL COMPLETO (PDF)</a>
                            </td>
                        </tr>
                    </table>';
                }

                $html .= '
                    <div style="font-size: 11px; color: #64748b; line-height: 1.5;">
                        <p><strong>Instruções de Pagamento:</strong></p>
                        <p>- Pagável em qualquer agência bancária, internet banking ou casas lotéricas até o vencimento.</p>
                        <p>- Não receber após o vencimento sem os devidos acréscimos legais configurados.</p>
                    </div>
                </div>';

                $pdf->writeHTML($html, true, false, true, false, '');

                if (!empty($barCode) && method_exists($pdf, 'write1DBarcode')) {
                    $cleanBarcode = preg_replace('/\D/', '', $barCode);
                    if (strlen($cleanBarcode) >= 44) {
                        $pdf->Ln(10);
                        $pdf->write1DBarcode(substr($cleanBarcode, 0, 44), 'I25', '', '', '', 18, 0.4, [
                            'position' => 'S',
                            'border' => false,
                            'padding' => 4,
                            'fgcolor' => [0, 0, 0],
                            'bgcolor' => false,
                            'text' => false,
                        ], 'N');
                    }
                }

                $pdf->Output($filePath, 'F');
                if (file_exists($filePath) && filesize($filePath) > 100) {
                    return $filePath;
                }
            } catch (\Exception $e) {
                // Fallback to direct download
            }
        }

        // Try downloading official PDF from Moneria URL
        if (!empty($boletoUrl) && filter_var($boletoUrl, FILTER_VALIDATE_URL)) {
            $ch = curl_init($boletoUrl);
            $fp = @fopen($filePath, 'wb');
            if ($fp) {
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);

                if (file_exists($filePath) && filesize($filePath) > 500) {
                    $head = @file_get_contents($filePath, false, null, 0, 4);
                    if ($head === '%PDF') {
                        return $filePath;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Generates or refreshes a Moneria Charge programmatically for a specific WHMCS invoice.
     *
     * @param int $invoiceId
     * @param array $gatewayParams
     * @param bool $force
     * @return array|null
     */
    public static function generateChargeForInvoice(int $invoiceId, array $gatewayParams, bool $force = false): ?array
    {
        if (!class_exists('WHMCS\Database\Capsule') || $invoiceId <= 0) {
            return null;
        }

        self::ensureTables();

        $clientId = $gatewayParams['clientId'] ?? '';
        $clientSecret = $gatewayParams['clientSecret'] ?? '';
        $baseUrl = !empty($gatewayParams['environment']) ? $gatewayParams['environment'] : 'https://api.moneria.com.br';
        $debug = !empty($gatewayParams['debug']);

        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        try {
            $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
            if (!$invoice || strcasecmp($invoice->status, 'Unpaid') !== 0) {
                return null;
            }

            // Calculate current expected due date
            $rawInvDueDate = !empty($invoice->duedate) ? self::sanitizeDate($invoice->duedate) : date('Y-m-d', strtotime('+3 days'));
            $expectedDueDate = $rawInvDueDate;
            if (strtotime($expectedDueDate) < strtotime(date('Y-m-d'))) {
                $expectedDueDate = date('Y-m-d', strtotime('+1 day'));
            }
            $expectedAmount = (float)$invoice->total;

            // Check if charge already exists
            $existing = self::getInvoiceCharge($invoiceId);
            if ($existing && !empty($existing['id']) && !$force) {
                $rawExistingDueDate = $existing['dueDate'] ?? ($existing['invoices'][0]['dueDate'] ?? ($existing['invoices'][0]['due_date'] ?? null));
                $existingDueDate = self::sanitizeDate($rawExistingDueDate);
                $existingAmount = (float)($existing['amount'] ?? ($existing['total'] ?? 0));
                $existingStatus = strtoupper($existing['status'] ?? '');

                $isStatusValid = !in_array($existingStatus, ['CANCELED', 'CANCELLED', 'EXPIRED', 'FAILED'], true);
                $isDueDateMatch = (!empty($existingDueDate) && $existingDueDate === $expectedDueDate);
                $isAmountMatch = ($existingAmount > 0 && abs($existingAmount - $expectedAmount) < 0.01);

                if ($isStatusValid && $isDueDateMatch && $isAmountMatch) {
                    return $existing;
                }

                // If due date or amount changed, cancel previous outdated charge on Moneria
                try {
                    $client = new MoneriaClient($clientId, $clientSecret, $baseUrl, $debug);
                    $client->cancelCharge($existing['id']);
                } catch (\Exception $e) {
                    // Ignore cancellation failure on API
                }

                self::clearCachedBoletoPdf($invoiceId);
            }

            $user = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
            if (!$user) {
                return null;
            }

            // Get custom fields for client
            $customFields = [];
            $customFieldValues = Capsule::table('tblcustomfieldsvalues')
                ->join('tblcustomfields', 'tblcustomfields.id', '=', 'tblcustomfieldsvalues.fieldid')
                ->where('tblcustomfields.type', 'client')
                ->where('tblcustomfieldsvalues.relid', $user->id)
                ->get(['tblcustomfields.fieldname as name', 'tblcustomfieldsvalues.value as value']);

            foreach ($customFieldValues as $cf) {
                $customFields[] = ['name' => $cf->name, 'value' => $cf->value];
            }

            $params = [
                'customFieldDoc' => $gatewayParams['customFieldDoc'] ?? 'CPF',
                'clientdetails'  => [
                    'firstname'    => $user->firstname,
                    'lastname'     => $user->lastname,
                    'email'        => $user->email,
                    'phonenumber'  => $user->phonenumber,
                    'address1'     => $user->address1,
                    'address2'     => $user->address2,
                    'city'         => $user->city,
                    'state'        => $user->state,
                    'postcode'     => $user->postcode,
                    'country'      => $user->country,
                    'tax_id'       => $user->tax_id ?? '',
                    'customfields' => $customFields,
                ],
            ];

            $document = self::extractDocument($params);
            if (empty($document)) {
                return null;
            }

            $client = new MoneriaClient($clientId, $clientSecret, $baseUrl, $debug);

            $customerPayload = [
                'name'     => trim($user->firstname . ' ' . $user->lastname),
                'document' => $document,
                'email'    => $user->email,
                'phone'    => $user->phonenumber,
                'address'  => [
                    'postalCode'   => $user->postcode,
                    'street'       => $user->address1 ?: 'Rua Principal',
                    'number'       => self::extractAddressNumber($user->address1),
                    'complement'   => '',
                    'neighborhood' => $user->address2 ?: 'Centro',
                    'city'         => $user->city ?: 'Curitiba',
                    'state'        => $user->state ?: 'PR',
                    'country'      => 'Brasil',
                ],
            ];

            $moneriaCustomer = $client->findOrCreateCustomer($customerPayload);
            $customerId = $moneriaCustomer['id'] ?? '';
            if (empty($customerId)) {
                return null;
            }

            $methodsConfig = $gatewayParams['paymentMethods'] ?? 'PIX_BOLETO';
            $paymentMethods = ($methodsConfig === 'PIX_ONLY') ? ['PIX'] : (($methodsConfig === 'BOLETO_ONLY') ? ['BOLETO'] : ['PIX', 'BOLETO']);

            $systemUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL') ?? '', '/');
            $postBackUrl = $systemUrl . '/modules/gateways/callback/moneria.php';

            $dueDate = !empty($invoice->duedate) ? date('Y-m-d', strtotime($invoice->duedate)) : date('Y-m-d', strtotime('+3 days'));
            if (strtotime($dueDate) < strtotime(date('Y-m-d'))) {
                $dueDate = date('Y-m-d', strtotime('+1 day'));
            }

            $chargePayload = [
                'customerId'     => $customerId,
                'name'           => 'Fatura #' . $invoiceId,
                'description'    => 'Pagamento da Fatura #' . $invoiceId,
                'amount'         => number_format((float)$invoice->total, 2, '.', ''),
                'paymentMethods' => $paymentMethods,
                'dueDate'        => $dueDate,
                'postBackUrl'    => $postBackUrl,
                'expirationDays' => (int)($gatewayParams['expirationDays'] ?? 30),
            ];

            if (!empty($gatewayParams['applyFine'])) {
                $chargePayload['applyFine'] = true;
                $chargePayload['percentFineValue'] = (float)($gatewayParams['percentFineValue'] ?? 2.0);
                $chargePayload['quantityFineDays'] = (int)($gatewayParams['quantityFineDays'] ?? 1);
            }

            if (!empty($gatewayParams['applyInterest'])) {
                $chargePayload['overdueInterestPercentage'] = (float)($gatewayParams['overdueInterestPercentage'] ?? 1.0);
                $chargePayload['overdueInterestDays'] = 1;
            }

            $charge = $client->createCharge($chargePayload);
            if (!empty($charge['id'])) {
                self::saveInvoiceCharge($invoiceId, $charge);
                return $charge;
            }
        } catch (\Exception $e) {
            // Fail safely
        }

        return null;
    }

    /**
     * Instantiates a MoneriaClient from gateway parameters.
     *
     * @param array $gatewayParams
     * @return MoneriaClient
     */
    public static function createClient(array $gatewayParams): MoneriaClient
    {
        $clientId = trim($gatewayParams['clientId'] ?? '');
        $clientSecret = trim($gatewayParams['clientSecret'] ?? '');
        $env = trim($gatewayParams['environment'] ?? '');
        $baseUrl = 'https://api.moneria.com.br';
        if ($env === 'sandbox') {
            $baseUrl = 'https://sandbox.moneria.com.br';
        } elseif (filter_var($env, FILTER_VALIDATE_URL)) {
            $baseUrl = rtrim($env, '/');
        }
        $debug = !empty($gatewayParams['debug']);

        return new MoneriaClient($clientId, $clientSecret, $baseUrl, $debug);
    }

    /**
     * Cancels an existing charge and low/cancel boleto on Moneria for an invoice.
     *
     * @param int $invoiceId
     * @param array $gatewayParams
     * @return bool
     */
    public static function cancelInvoiceCharge(int $invoiceId, array $gatewayParams = []): bool
    {
        try {
            $charge = self::getInvoiceCharge($invoiceId);
            if (!$charge || empty($charge['id'])) {
                return false;
            }

            if (empty($gatewayParams) || empty($gatewayParams['clientId'])) {
                $gatewayParams = self::getGatewayParams($gatewayParams['paymentmethod'] ?? 'moneria');
            }

            $client = self::createClient($gatewayParams);
            try {
                $client->cancelCharge($charge['id']);
            } catch (\Exception $e) {
                // If already cancelled or expired on API, proceed with local cleanup
            }

            if (class_exists('WHMCS\Database\Capsule')) {
                Capsule::table('mod_moneria_charges')
                    ->where('invoice_id', $invoiceId)
                    ->update([
                        'status' => 'CANCELLED',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            self::clearCachedBoletoPdf($invoiceId);

            if (function_exists('logTransaction')) {
                logTransaction($gatewayParams['paymentmethod'] ?? 'moneria', [
                    'action' => 'cancel_charge',
                    'invoice_id' => $invoiceId,
                    'charge_id' => $charge['id'],
                ], 'Charge / Boleto Cancelled on Moneria');
            }

            return true;
        } catch (\Exception $e) {
            if (function_exists('logTransaction')) {
                logTransaction($gatewayParams['paymentmethod'] ?? 'moneria', [
                    'action' => 'cancel_charge_error',
                    'invoice_id' => $invoiceId,
                    'error' => $e->getMessage(),
                ], 'Error Cancelling Charge on Moneria');
            }
            return false;
        }
    }

    /**
     * Renders a PHP template file.
     *
     * @param string $templatePath
     * @param array $data
     * @return string
     */
    public static function render(string $templatePath, array $data = []): string
    {
        if (!file_exists($templatePath)) {
            return '<div class="alert alert-danger">Template Moneria não encontrado.</div>';
        }

        extract($data);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}
