<?php
// =============================================================================
// generate_doc.php — DOCX Template Generator
// BaaS Partner Monitoring System
//
// Usage: generate_doc.php?partner_id=1&type=contract|tariff|nda
//
// Generates a .docx file on-the-fly using PHP ZipArchive (no Composer).
// The three templates are:
//   contract — Əsas Müqavilə (Main Agreement)
//   tariff   — Tarif Cədvəli Əlavəsi (Tariff Appendix)
//   nda      — Məxfilik Sazişi (Non-Disclosure Agreement)
// =============================================================================
require_once '_common.php';
require_auth();

$partner_id  = (int)($_GET['partner_id'] ?? 0);
$type        = trim($_GET['type'] ?? 'contract');

if (!in_array($type, ['contract','tariff','nda'], true)) {
    http_response_code(400); exit('Invalid document type.');
}

// ── Resolve partner data ──────────────────────────────────────────────────────
$partner = null;
$packages = [];

if (!USE_MOCK_DATA) {
    $stmt = get_monitor_pdo()->prepare('
        SELECT * FROM ' . tbl('partners') . ' WHERE partner_id = :pid');
    $stmt->execute([':pid' => $partner_id]);
    $row = $stmt->fetch();
    if ($row) $partner = array_change_key_case($row, CASE_LOWER);

    $stmt2 = get_monitor_pdo()->prepare('
        SELECT cp.*, s.issued_cards, s.remaining_cards, s.usage_percent
        FROM ' . tbl('card_packages') . ' cp
        LEFT JOIN ' . tbl('package_usage_snapshot') . ' s
               ON s.package_id = cp.package_id
              AND s.snapshot_date = (SELECT MAX(s2.snapshot_date)
                                     FROM ' . tbl('package_usage_snapshot') . ' s2
                                     WHERE s2.package_id = cp.package_id)
        WHERE cp.partner_id = :pid AND cp.status = \'active\'
        ORDER BY cp.start_date DESC');
    $stmt2->execute([':pid' => $partner_id]);
    $packages = array_map(fn($r) => array_change_key_case($r, CASE_LOWER), $stmt2->fetchAll());
} else {
    foreach ($MOCK_PARTNERS as $p) {
        if ($p['partner_id'] === $partner_id) { $partner = $p; break; }
    }
    $packages = array_values(array_filter($MOCK_PACKAGES,
        fn($pk) => $pk['partner_id'] === $partner_id && $pk['status'] === 'active'));
}

if (!$partner) { http_response_code(404); exit('Partner not found.'); }

// ── Convenience getters with HTML-entity escaping ────────────────────────────
function p(array $data, string $key, string $fallback = '_______________'): string {
    $v = $data[$key] ?? '';
    return $v !== '' ? htmlspecialchars((string)$v, ENT_XML1) : $fallback;
}

$today       = date('d.m.Y');
$today_long  = date('d') . ' ' . az_month(date('n')) . ' ' . date('Y');
$contract_no = 'BaaS-' . str_pad($partner_id, 4, '0', STR_PAD_LEFT) . '/' . date('Y');
$type_labels = ['contract' => 'Əsas Müqavilə', 'tariff' => 'Tarif Cədvəli Əlavəsi', 'nda' => 'Məxfilik Sazişi'];
$doc_title   = $type_labels[$type];
$file_name   = p($partner,'partner_name') . '_' . $type . '_' . date('Ymd') . '.docx';
$file_name   = preg_replace('/[^\w\-.]/', '_', html_entity_decode($file_name));

function az_month(int $n): string {
    return ['','yanvar','fevral','mart','aprel','may','iyun','iyul','avqust','sentyabr','oktyabr','noyabr','dekabr'][$n];
}

// ── Build document XML for each template ─────────────────────────────────────

function docx_para(string $text, string $style = 'Normal', bool $bold = false, int $size = 0, string $align = ''): string {
    $rPr  = $bold ? '<w:b/>' : '';
    if ($size) $rPr .= "<w:sz w:val=\"{$size}\"/><w:szCs w:val=\"{$size}\"/>";
    $pPr  = "<w:pStyle w:val=\"{$style}\"/>";
    if ($align) $pPr .= "<w:jc w:val=\"{$align}\"/>";
    return "<w:p><w:pPr>{$pPr}</w:pPr><w:r><w:rPr>{$rPr}</w:rPr><w:t xml:space=\"preserve\">{$text}</w:t></w:r></w:p>";
}

function docx_heading(string $text, int $level = 1): string {
    return docx_para($text, "Heading{$level}", true, $level === 1 ? 28 : 24);
}

function docx_blank(): string {
    return '<w:p><w:pPr><w:pStyle w:val="Normal"/></w:pPr></w:p>';
}

function docx_line(string $label, string $value): string {
    return "<w:p><w:pPr><w:pStyle w:val=\"Normal\"/></w:pPr>"
        . "<w:r><w:rPr><w:b/></w:rPr><w:t xml:space=\"preserve\">{$label}: </w:t></w:r>"
        . "<w:r><w:t xml:space=\"preserve\">{$value}</w:t></w:r></w:p>";
}

function docx_table_row(array $cells, bool $header = false): string {
    $xml = '<w:tr>';
    foreach ($cells as $cell) {
        $rPr = $header ? '<w:b/>' : '';
        $bg  = $header ? '<w:shd w:val="clear" w:color="auto" w:fill="E3F2FD"/>' : '';
        $xml .= "<w:tc><w:tcPr>{$bg}<w:tcW w:w=\"0\" w:type=\"auto\"/></w:tcPr>"
            . "<w:p><w:pPr><w:pStyle w:val=\"Normal\"/></w:pPr>"
            . "<w:r><w:rPr>{$rPr}</w:rPr><w:t xml:space=\"preserve\">{$cell}</w:t></w:r></w:p></w:tc>";
    }
    return $xml . '</w:tr>';
}

// ──────────────────────────────────────────────────────────────────────────────
// TEMPLATE: CONTRACT — Əsas Müqavilə
// ──────────────────────────────────────────────────────────────────────────────
function build_contract(array $p, string $today_long, string $contract_no): string {
    $paras  = '';
    $paras .= docx_heading('BANK-AS-A-SERVICE XİDMƏTLƏRİNİN GÖSTƏRİLMƏSİ HAQQINDA MÜQAVİLƏ');
    $paras .= docx_para("Müqavilə №: {$contract_no}", 'Normal', true);
    $paras .= docx_para("Bakı şəhəri, {$today_long}", 'Normal', false, 0, 'right');
    $paras .= docx_blank();
    $paras .= docx_para('BU MÜQAVİLƏ aşağıdakı tərəflər arasında bağlanmışdır:', 'Normal', true);
    $paras .= docx_blank();
    $paras .= docx_line('Tərəf 1 — Bank', 'Azərbaycan Respublikası, BaaS Monitoring Bank, lisenziya №1234');
    $paras .= docx_line('Tərəf 2 — Tərəfdaş', htmlspecialchars($p['partner_name'], ENT_XML1) . ' (' . htmlspecialchars($p['legal_form'], ENT_XML1) . ')');
    $paras .= docx_line('VÖEN', p($p, 'voen'));
    $paras .= docx_line('Hüquqi ünvan', p($p, 'legal_address'));
    $paras .= docx_line('Bank', p($p, 'bank_name'));
    $paras .= docx_line('Hesab №', p($p, 'bank_account'));
    $paras .= docx_line('Bank kodu', p($p, 'bank_code'));
    $paras .= docx_line('İmzalayan şəxs', p($p,'signatory_name') . ', ' . p($p,'signatory_position'));
    $paras .= docx_blank();
    $paras .= docx_heading('1. MÜQAVİLƏNİN MÖVZUSİ', 2);
    $paras .= docx_para('1.1. Bank bu Müqavilə çərçivəsində Tərəfdaşa Bank-as-a-Service platforması əsasında ödəniş kartlarının buraxılması xidmətlərini göstərir.');
    $paras .= docx_para('1.2. Xidmətlərin həcmi, şərtləri və tariflər Tarif Cədvəli Əlavəsində müəyyən edilir.');
    $paras .= docx_blank();
    $paras .= docx_heading('2. TƏRƏFLƏRİN HÜQUQLARİ VƏ VƏZİFƏLƏRİ', 2);
    $paras .= docx_para('2.1. Bank öhdəlik götürür: kart paketlərini müvafiq müddətdə aktivləşdirməyi; texniki dəstəyi 7/24 rejimində təmin etməyi; hesabatları müəyyən edilmiş müddətdə təqdim etməyi.');
    $paras .= docx_para('2.2. Tərəfdaş öhdəlik götürür: ödənişləri müəyyən edilmiş müddətdə həyata keçirməyi; konfidensial məlumatların qorunmasını təmin etməyi; AML/KYC tələblərinə əməl etməyi.');
    $paras .= docx_blank();
    $paras .= docx_heading('3. MALİYYƏ ŞƏRTLƏR', 2);
    $paras .= docx_para('3.1. Xidmətlərin dəyəri Tarif Cədvəli Əlavəsinə uyğun hesablanır.');
    $paras .= docx_para('3.2. Ödənişlər hər ayın 5-dək həyata keçirilir.');
    $paras .= docx_blank();
    $paras .= docx_heading('4. MÜQAVİLƏNİN MÜDDƏTİ', 2);
    $paras .= docx_para('4.1. Müqavilə imzalandığı gündən qüvvəyə minir və imzalanma tarixindən 1 (bir) il müddətinə bağlanır.');
    $paras .= docx_para('4.2. Heç bir tərəf müqavilənin sona çatmasına 30 gün qalmış onu ləğv etmək istəyini bildirməzsə, müqavilə eyni şərtlərlə avtomatik olaraq uzadılır.');
    $paras .= docx_blank();
    $paras .= docx_heading('5. TƏRƏFLƏRİN İMZALARI', 2);
    $paras .= docx_blank();
    $paras .= '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        . '<w:top w:val="none"/><w:left w:val="none"/><w:bottom w:val="none"/><w:right w:val="none"/><w:insideH w:val="none"/><w:insideV w:val="none"/>'
        . '</w:tblBorders></w:tblPr>'
        . docx_table_row(['BANK', 'TƏRƏFDAŞ'], true)
        . docx_table_row(['BaaS Monitoring Bank', htmlspecialchars($p['partner_name'], ENT_XML1)])
        . docx_table_row(['İmza: _______________', 'İmza: _______________'])
        . docx_table_row(['M.Y.', 'M.Y.'])
        . '</w:tbl>';
    return $paras;
}

// ──────────────────────────────────────────────────────────────────────────────
// TEMPLATE: TARIFF APPENDIX — Tarif Cədvəli Əlavəsi
// ──────────────────────────────────────────────────────────────────────────────
function build_tariff(array $p, array $packages, string $today_long, string $contract_no): string {
    $paras  = '';
    $paras .= docx_heading('TARİF CƏDVƏLİ ƏLAVƏSİ');
    $paras .= docx_para("Əlavə № 1 / Müqavilə №: {$contract_no}", 'Normal', true);
    $paras .= docx_para("Bakı şəhəri, {$today_long}", 'Normal', false, 0, 'right');
    $paras .= docx_blank();
    $paras .= docx_line('Tərəfdaş', htmlspecialchars($p['partner_name'], ENT_XML1) . ' (' . htmlspecialchars($p['legal_form'], ENT_XML1) . ')');
    $paras .= docx_line('VÖEN', p($p, 'voen'));
    $paras .= docx_blank();
    $paras .= docx_heading('1. AKTİV KART PAKETLƏRİ', 2);
    $paras .= docx_blank();

    if (empty($packages)) {
        $paras .= docx_para('Hazırda aktiv kart paketi yoxdur.');
    } else {
        $paras .= '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/>'
            . '<w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/>'
            . '<w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/>'
            . '</w:tblBorders></w:tblPr>';
        $paras .= docx_table_row(['Paket №','Ölçü','Başlanğıc','Bitmə','Buraxılmış','Qalıq','İstifadə %'], true);
        foreach ($packages as $pkg) {
            $paras .= docx_table_row([
                'PKG-' . str_pad($pkg['package_id'], 3, '0', STR_PAD_LEFT),
                number_format($pkg['package_size']),
                $pkg['start_date'],
                $pkg['end_date'],
                number_format($pkg['issued_cards']),
                number_format($pkg['remaining_cards']),
                number_format($pkg['usage_percent'], 2) . '%',
            ]);
        }
        $paras .= '</w:tbl>';
    }

    $paras .= docx_blank();
    $paras .= docx_heading('2. TARİF MATRİSİ', 2);
    $paras .= docx_blank();
    $paras .= '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        . '<w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/>'
        . '<w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/>'
        . '<w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/>'
        . '</w:tblBorders></w:tblPr>';
    $paras .= docx_table_row(['Xidmət', 'Tarif', 'Qeyd'], true);
    $paras .= docx_table_row(['Kart buraxılması (1 ədəd)', '2.50 ₼', 'Bir dəfəlik']);
    $paras .= docx_table_row(['Aylıq kart xidməti', '0.80 ₼/ay', 'Aktiv kartlar üzrə']);
    $paras .= docx_table_row(['Əməliyyat komissiyası', '0.30% (min 0.10 ₼)', 'Alış əməliyyatları']);
    $paras .= docx_table_row(['Pul çıxarışı — ATM', '0.50% (min 0.50 ₼)', 'Yerli ATM']);
    $paras .= docx_table_row(['Pul çıxarışı — Xarici ATM', '1.50% (min 2.00 ₼)', 'Xarici ölkə']);
    $paras .= docx_table_row(['3D Secure', '0.10 ₼/əməliyyat', 'Online ödənişlər']);
    $paras .= docx_table_row(['API inteqrasiya dəstəyi', '250.00 ₼/ay', 'Sabit ödəniş']);
    $paras .= '</w:tbl>';
    $paras .= docx_blank();
    $paras .= docx_heading('3. İMZALAR', 2);
    $paras .= docx_blank();
    $paras .= '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        . '<w:top w:val="none"/><w:left w:val="none"/><w:bottom w:val="none"/><w:right w:val="none"/><w:insideH w:val="none"/><w:insideV w:val="none"/>'
        . '</w:tblBorders></w:tblPr>'
        . docx_table_row(['BANK', 'TƏRƏFDAŞ — ' . htmlspecialchars($p['partner_name'], ENT_XML1)], true)
        . docx_table_row(['İmza: _______________', 'İmza: _______________'])
        . '</w:tbl>';
    return $paras;
}

// ──────────────────────────────────────────────────────────────────────────────
// TEMPLATE: NDA — Məxfilik Sazişi
// ──────────────────────────────────────────────────────────────────────────────
function build_nda(array $p, string $today_long, string $contract_no): string {
    $paras  = '';
    $paras .= docx_heading('MƏXFİLİK SAZİŞİ (NDA)');
    $paras .= docx_para("Saziş №: NDA-{$contract_no}", 'Normal', true);
    $paras .= docx_para("Bakı şəhəri, {$today_long}", 'Normal', false, 0, 'right');
    $paras .= docx_blank();
    $paras .= docx_para('Bu Məxfilik Sazişi aşağıdakı tərəflər arasında bağlanmışdır:');
    $paras .= docx_blank();
    $paras .= docx_line('Tərəf 1 (Açıqlayan)', 'BaaS Monitoring Bank, Azərbaycan Respublikası');
    $paras .= docx_line('Tərəf 2 (Alan)', htmlspecialchars($p['partner_name'], ENT_XML1) . ' (' . htmlspecialchars($p['legal_form'], ENT_XML1) . ')');
    $paras .= docx_line('VÖEN', p($p, 'voen'));
    $paras .= docx_line('Hüquqi ünvan', p($p, 'legal_address'));
    $paras .= docx_line('İmzalayan şəxs', p($p,'signatory_name') . ', ' . p($p,'signatory_position'));
    $paras .= docx_blank();
    $paras .= docx_heading('1. MƏXFİ MƏLUMATLAR', 2);
    $paras .= docx_para('1.1. "Məxfi məlumat" anlayışı: texniki sənədlər, API açarları, kart məlumatları, müştəri bazaları, maliyyə göstəriciləri, iş prosedurları və hər iki tərəfin məxfi olaraq təyin etdiyi digər məlumatları əhatə edir.');
    $paras .= docx_para('1.2. Tərəflər bir-birinin məxfi məlumatlarını üçüncü şəxslərə ötürməmək, açıqlamamaq öhdəliyini qəbul edir.');
    $paras .= docx_blank();
    $paras .= docx_heading('2. İSTİSNALAR', 2);
    $paras .= docx_para('2.1. Məxfilik öhdəliyi aşağıdakılara şamil edilmir: ictimaiyyətə açıq olan məlumatlara; dövlət orqanlarının tələbi ilə açıqlanan məlumatlara; digər mənbələrdən müstəqil olaraq əldə edilmiş məlumatlara.');
    $paras .= docx_blank();
    $paras .= docx_heading('3. SAZİŞİN MÜDDƏTİ', 2);
    $paras .= docx_para('3.1. Bu Saziş imzalandığı tarixdən qüvvəyə minir və əsas müqavilənin fəaliyyəti başa çatdıqdan sonra 3 (üç) il müddətində qüvvədə qalır.');
    $paras .= docx_blank();
    $paras .= docx_heading('4. MƏSULİYYƏT', 2);
    $paras .= docx_para('4.1. Bu Sazişin pozulması halında günahkar tərəf dəymiş zərəri tam ödəməyə məsuliyyət daşıyır.');
    $paras .= docx_blank();
    $paras .= docx_heading('5. TƏRƏFLƏRİN İMZALARI', 2);
    $paras .= docx_blank();
    $paras .= '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        . '<w:top w:val="none"/><w:left w:val="none"/><w:bottom w:val="none"/><w:right w:val="none"/><w:insideH w:val="none"/><w:insideV w:val="none"/>'
        . '</w:tblBorders></w:tblPr>'
        . docx_table_row(['BANK', 'TƏRƏFDAŞ'], true)
        . docx_table_row(['BaaS Monitoring Bank', htmlspecialchars($p['partner_name'], ENT_XML1)])
        . docx_table_row(['İmza: _______________', 'İmza: _______________'])
        . docx_table_row(['M.Y.', 'M.Y.'])
        . '</w:tbl>';
    return $paras;
}

// ── Build the body XML ────────────────────────────────────────────────────────
switch ($type) {
    case 'contract': $body = build_contract($partner, $today_long, $contract_no); break;
    case 'tariff':   $body = build_tariff($partner, $packages, $today_long, $contract_no); break;
    case 'nda':      $body = build_nda($partner, $today_long, $contract_no); break;
    default:         $body = '';
}

// ── Assemble DOCX (ZipArchive — built-in PHP, no Composer) ───────────────────
$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/word/document.xml"
    ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/docProps/core.xml"
    ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties"
    Target="docProps/core.xml"/>
</Relationships>';

$doc_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
          xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>
    <w:pPr><w:spacing w:after="120"/></w:pPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:rPr><w:b/><w:sz w:val="32"/><w:szCs w:val="32"/><w:color w:val="1E3A5F"/></w:rPr>
    <w:pPr><w:jc w:val="center"/><w:spacing w:before="200" w:after="200"/></w:pPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:basedOn w:val="Normal"/>
    <w:rPr><w:b/><w:sz w:val="26"/><w:szCs w:val="26"/><w:color w:val="1E3A5F"/></w:rPr>
    <w:pPr><w:spacing w:before="160" w:after="80"/></w:pPr>
  </w:style>
</w:styles>';

$doc_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml">
<w:body>
<w:sectPr>
  <w:pgMar w:top="1134" w:right="850" w:bottom="1134" w:left="1701"/>
</w:sectPr>
' . $body . '
</w:body>
</w:document>';

$core_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
                   xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>' . htmlspecialchars($doc_title, ENT_XML1) . '</dc:title>
  <dc:creator>BaaS Monitor v' . APP_VERSION . '</dc:creator>
  <cp:created>' . date('c') . '</cp:created>
</cp:coreProperties>';

// Write to a temp file then stream to browser
$tmp = sys_get_temp_dir() . '/baas_doc_' . uniqid() . '.docx';
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); exit('Could not create document.');
}
$zip->addFromString('[Content_Types].xml',         $content_types);
$zip->addFromString('_rels/.rels',                 $rels);
$zip->addFromString('word/document.xml',           $doc_xml);
$zip->addFromString('word/_rels/document.xml.rels',$doc_rels);
$zip->addFromString('word/styles.xml',             $styles);
$zip->addFromString('docProps/core.xml',           $core_xml);
$zip->close();

// Stream to client
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . rawurlencode($file_name) . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-cache, no-store');
readfile($tmp);
@unlink($tmp);
