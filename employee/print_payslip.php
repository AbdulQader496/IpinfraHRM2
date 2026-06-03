<?php
require_once '../includes/db.php';
$id = (int)$_GET['id'];
$query = "SELECT p.*, e.name, e.employee_id, e.ic_number, e.department, e.position, e.nationality, e.employee_type
          FROM payroll p
          JOIN employees e ON p.employee_id = e.id
          WHERE p.id = $id";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$show_statutory = ($row['nationality'] == 'Malaysian') &&
                  (!isset($row['employee_type']) || $row['employee_type'] != 'intern');

/* ── Amount-in-words helper (RM, up to 99,999) ─────────────────────────── */
function numberToWords($number) {
    $ones  = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven',
              'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
              'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens  = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty',
              'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $number  = round($number, 2);
    $ringgit = (int)$number;
    $sen     = (int)round(($number - $ringgit) * 100);

    function twoDigits($n, $ones, $tens) {
        if ($n < 20)  return $ones[$n];
        return $tens[(int)($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
    }

    $words = '';
    if ($ringgit >= 1000) {
        $thousands = (int)($ringgit / 1000);
        $remainder = $ringgit % 1000;
        $words    .= twoDigits($thousands, $ones, $tens) . ' Thousand';
        if ($remainder >= 100) {
            $words .= ' ' . $ones[(int)($remainder / 100)] . ' Hundred';
            $remainder %= 100;
            if ($remainder) $words .= ' ' . twoDigits($remainder, $ones, $tens);
        } elseif ($remainder) {
            $words .= ' ' . twoDigits($remainder, $ones, $tens);
        }
    } elseif ($ringgit >= 100) {
        $words .= $ones[(int)($ringgit / 100)] . ' Hundred';
        $r      = $ringgit % 100;
        if ($r) $words .= ' ' . twoDigits($r, $ones, $tens);
    } else {
        $words .= twoDigits($ringgit, $ones, $tens);
    }

    $result = 'Ringgit Malaysia ' . ($words ?: 'Zero');
    if ($sen > 0) {
        $result .= ' and ' . twoDigits($sen, $ones, $tens) . ' Sen';
    }
    return $result . ' Only';
}

/* ── Computed totals ────────────────────────────────────────────────────── */
$basic          = (float)($row['basic_salary']     ?? 0);
$overtime       = (float)($row['overtime_pay']     ?? 0);
$claims         = (float)($row['approved_claims']  ?? 0);
$allowances     = (float)($row['allowances']       ?? 0);
$unpaid         = (float)($row['unpaid_deduction'] ?? 0);
$epf_ee         = $show_statutory ? (float)($row['epf_employee']   ?? 0) : 0;
$socso_ee       = $show_statutory ? (float)($row['socso_employee'] ?? 0) : 0;
$eis_ee         = $show_statutory ? (float)($row['eis_employee']   ?? 0) : 0;
$pcb            = $show_statutory ? (float)($row['pcb']            ?? 0) : 0;
$epf_er         = $show_statutory ? (float)($row['epf_employer']   ?? 0) : 0;
$socso_er       = $show_statutory ? (float)($row['socso_employer'] ?? 0) : 0;
$eis_er         = $show_statutory ? (float)($row['eis_employer']   ?? 0) : 0;

$total_earnings    = $basic + $overtime + $claims + $allowances - $unpaid;
$total_deductions  = $epf_ee + $socso_ee + $eis_ee + $pcb;
$net_salary        = (float)($row['net_salary'] ?? 0);

/* ── Format pay period ──────────────────────────────────────────────────── */
$period_display = $row['month_year'] ?? '';
// Try to pretty-print if stored as "YYYY-MM" or "Month YYYY"
if (preg_match('/^(\d{4})-(\d{2})$/', $period_display, $m)) {
    $period_display = date('F Y', mktime(0, 0, 0, (int)$m[2], 1, (int)$m[1]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip – <?php echo htmlspecialchars($row['name']); ?> – <?php echo htmlspecialchars($period_display); ?></title>
    <style>
        /* ── Reset & base ───────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            background: #e8e8e8;
            padding: 30px;
        }

        /* ── Outer wrapper ──────────────────────────────────────────────── */
        .payslip-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #c0c0c0;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            position: relative;
            overflow: hidden;
        }

        /* ── CONFIDENTIAL watermark ─────────────────────────────────────── */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 80pt;
            font-weight: 900;
            color: rgba(180, 0, 0, 0.055);
            letter-spacing: 6px;
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
            user-select: none;
        }

        /* ── All real content sits above the watermark ──────────────────── */
        .payslip-content {
            position: relative;
            z-index: 1;
            padding: 0;
        }

        /* ── Header band ────────────────────────────────────────────────── */
        .header-band {
            background: #1a2e4a;
            color: #fff;
            padding: 22px 30px 18px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-box {
            width: 56px;
            height: 56px;
            border: 2.5px solid #fff;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22pt;
            font-weight: 900;
            letter-spacing: -2px;
            flex-shrink: 0;
            background: rgba(255,255,255,0.10);
        }

        .header-company {
            flex: 1;
        }

        .company-name {
            font-size: 17pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .company-sub {
            font-size: 8.5pt;
            opacity: 0.78;
            margin-top: 3px;
            line-height: 1.5;
        }

        .header-right {
            text-align: right;
            flex-shrink: 0;
        }

        .payslip-label {
            font-size: 20pt;
            font-weight: 900;
            letter-spacing: 4px;
            text-transform: uppercase;
            line-height: 1;
        }

        .payslip-period {
            font-size: 9.5pt;
            opacity: 0.80;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ── Accent stripe ──────────────────────────────────────────────── */
        .accent-stripe {
            height: 4px;
            background: linear-gradient(to right, #e8a020, #c0392b, #1a2e4a);
        }

        /* ── Body padding ───────────────────────────────────────────────── */
        .body-pad {
            padding: 24px 30px;
        }

        /* ── Section title ──────────────────────────────────────────────── */
        .section-title {
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #1a2e4a;
            border-bottom: 1.5px solid #1a2e4a;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }

        /* ── Employee info 2-column table ───────────────────────────────── */
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }

        .info-grid td {
            padding: 4px 6px;
            vertical-align: top;
            font-size: 10pt;
            width: 25%;
        }

        .info-grid td.lbl {
            color: #555;
            font-size: 8.5pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
            padding-right: 2px;
        }

        .info-grid td.val {
            font-weight: 600;
            color: #1a1a1a;
            border-bottom: 1px dotted #ccc;
            padding-right: 20px;
        }

        /* ── Earnings / Deductions table ────────────────────────────────── */
        .pay-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            font-size: 10pt;
        }

        .pay-table thead tr {
            background: #1a2e4a;
            color: #fff;
        }

        .pay-table thead th {
            padding: 8px 10px;
            text-align: left;
            font-size: 8.5pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .pay-table thead th.amt {
            text-align: right;
            width: 110px;
        }

        .pay-table thead th.divider-col {
            width: 12px;
            padding: 0;
            background: #fff;
        }

        .pay-table tbody tr {
            border-bottom: 1px solid #e8e8e8;
        }

        .pay-table tbody tr:nth-child(even) {
            background: #f7f9fc;
        }

        .pay-table tbody td {
            padding: 6px 10px;
            vertical-align: middle;
        }

        .pay-table tbody td.amt {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .pay-table tbody td.divider-col {
            width: 12px;
            background: #e0e0e0;
            padding: 0;
        }

        .pay-table tbody td.no-stat {
            color: #999;
            font-style: italic;
            font-size: 9pt;
        }

        .pay-table tfoot tr {
            background: #1a2e4a;
            color: #fff;
        }

        .pay-table tfoot td {
            padding: 8px 10px;
            font-weight: 700;
            font-size: 10pt;
        }

        .pay-table tfoot td.amt {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .pay-table tfoot td.divider-col {
            background: #fff;
            width: 12px;
            padding: 0;
        }

        /* ── Employer contribution note ─────────────────────────────────── */
        .er-note {
            font-size: 8.5pt;
            color: #555;
            margin-top: 8px;
            padding: 6px 10px;
            background: #f0f4f8;
            border-left: 3px solid #1a2e4a;
        }

        /* ── Net salary box ─────────────────────────────────────────────── */
        .net-box {
            margin-top: 20px;
            border: 2px solid #1a2e4a;
            border-radius: 4px;
            overflow: hidden;
        }

        .net-box-header {
            background: #1a2e4a;
            color: #fff;
            padding: 6px 16px;
            font-size: 8.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .net-box-body {
            padding: 12px 16px;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
        }

        .net-amount {
            font-size: 26pt;
            font-weight: 900;
            color: #1a2e4a;
            letter-spacing: -1px;
        }

        .net-amount span.currency {
            font-size: 14pt;
            font-weight: 700;
            margin-right: 4px;
            color: #c0392b;
        }

        .net-calc {
            font-size: 8.5pt;
            color: #666;
            text-align: right;
            line-height: 1.6;
        }

        /* ── Amount in words ────────────────────────────────────────────── */
        .words-line {
            margin-top: 10px;
            padding: 7px 14px;
            background: #fffdf0;
            border: 1px dashed #c0a030;
            font-size: 9pt;
            color: #3a3a1a;
            border-radius: 3px;
        }

        .words-line strong {
            color: #7a6010;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 2px;
        }

        /* ── Signature section ──────────────────────────────────────────── */
        .sig-row {
            display: flex;
            gap: 40px;
            margin-top: 32px;
            margin-bottom: 4px;
        }

        .sig-block {
            flex: 1;
        }

        .sig-line {
            border-top: 1.5px solid #333;
            margin-bottom: 5px;
        }

        .sig-label {
            font-size: 8.5pt;
            color: #444;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sig-sub {
            font-size: 8pt;
            color: #888;
            margin-top: 2px;
        }

        /* ── Footer band ────────────────────────────────────────────────── */
        .footer-band {
            background: #f0f0f0;
            border-top: 1px solid #ccc;
            padding: 8px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
        }

        .footer-band .footer-left {
            font-size: 8pt;
            color: #666;
        }

        .footer-band .footer-confidential {
            font-size: 8pt;
            font-weight: 900;
            color: #b00000;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* ── Print CSS ──────────────────────────────────────────────────── */
        @media print {
            .no-print { display: none !important; }

            @page {
                size: A4;
                margin: 12mm 14mm;
            }

            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }

            .payslip-wrapper {
                box-shadow: none;
                border: none;
                max-width: 100%;
            }

            /* Keep dark backgrounds when printing */
            .header-band,
            .pay-table thead tr,
            .pay-table tfoot tr,
            .net-box-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .accent-stripe {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .watermark {
                color: rgba(180, 0, 0, 0.06);
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .footer-band {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Avoid page breaks inside key blocks */
            .net-box, .sig-row, .pay-table { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<!-- Action Buttons (hidden on print) -->
<div style="text-align:center; padding: 16px 0; display:flex; gap:12px; justify-content:center; background:#f8fafc; border-bottom:1px solid #e2e8f0;" class="no-print">
    <button onclick="window.print()"
        style="background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;border:none;padding:10px 24px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-family:Arial,sans-serif;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
        Print
    </button>
    <button id="downloadBtn" onclick="downloadPDF()"
        style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;padding:10px 24px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-family:Arial,sans-serif;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download PDF
    </button>
    <button onclick="window.close()"
        style="background:#e5e7eb;color:#374151;border:none;padding:10px 24px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;font-family:Arial,sans-serif;">
        Close
    </button>
</div>

<div class="payslip-wrapper">
    <!-- Watermark -->
    <div class="watermark">CONFIDENTIAL</div>

    <div class="payslip-content">

        <!-- ══ HEADER ══ -->
        <div class="header-band">
            <div class="logo-box">IN</div>
            <div class="header-company">
                <div class="company-name">Ipinfra Networks Sdn Bhd</div>
                <div class="company-sub">
                    D3-06, Tamarind Square, 05, Persiaran Multimedia,<br>
                    Cyberjaya, 63000 Cyberjaya, Selangor&nbsp;&nbsp;|&nbsp;&nbsp;
                    sales@ipinfra.com.my
                </div>
            </div>
            <div class="header-right">
                <div class="payslip-label">Payslip</div>
                <div class="payslip-period"><?php echo htmlspecialchars($period_display); ?></div>
            </div>
        </div>

        <!-- Accent stripe -->
        <div class="accent-stripe"></div>

        <!-- ══ BODY ══ -->
        <div class="body-pad">

            <!-- ── Employee details ── -->
            <div class="section-title">Employee Details</div>
            <table class="info-grid">
                <tr>
                    <td class="lbl">Employee ID</td>
                    <td class="val"><?php echo htmlspecialchars($row['employee_id']); ?></td>
                    <td class="lbl">Department</td>
                    <td class="val"><?php echo htmlspecialchars($row['department']); ?></td>
                </tr>
                <tr>
                    <td class="lbl">Full Name</td>
                    <td class="val"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td class="lbl">Designation</td>
                    <td class="val"><?php echo htmlspecialchars($row['position']); ?></td>
                </tr>
                <tr>
                    <td class="lbl">IC Number</td>
                    <td class="val"><?php echo htmlspecialchars($row['ic_number']); ?></td>
                    <td class="lbl">Nationality</td>
                    <td class="val"><?php echo htmlspecialchars($row['nationality'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="lbl">Pay Period</td>
                    <td class="val"><?php echo htmlspecialchars($period_display); ?></td>
                    <td class="lbl">Employee Type</td>
                    <td class="val"><?php echo htmlspecialchars(ucfirst($row['employee_type'] ?? '—')); ?></td>
                </tr>
            </table>

            <!-- ── Earnings & Deductions ── -->
            <div class="section-title">Earnings &amp; Deductions</div>

            <table class="pay-table">
                <thead>
                    <tr>
                        <th>Earnings</th>
                        <th class="amt">Amount (RM)</th>
                        <th class="divider-col"></th>
                        <th>Deductions</th>
                        <th class="amt">Amount (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1: Basic salary / EPF -->
                    <tr>
                        <td>Basic Salary</td>
                        <td class="amt"><?php echo number_format($basic, 2); ?></td>
                        <td class="divider-col"></td>
                        <?php if ($show_statutory): ?>
                        <td>EPF – Employee (11%)</td>
                        <td class="amt"><?php echo number_format($epf_ee, 2); ?></td>
                        <?php else: ?>
                        <td colspan="2" class="no-stat">No statutory deductions applicable</td>
                        <?php endif; ?>
                    </tr>

                    <!-- Row 2: Claims or Allowances / SOCSO -->
                    <?php if ($claims > 0): ?>
                    <tr>
                        <td>Approved Claims</td>
                        <td class="amt"><?php echo number_format($claims, 2); ?></td>
                        <td class="divider-col"></td>
                        <?php if ($show_statutory): ?>
                        <td>SOCSO – Employee (0.5%)</td>
                        <td class="amt"><?php echo number_format($socso_ee, 2); ?></td>
                        <?php else: ?>
                        <td></td><td></td>
                        <?php endif; ?>
                    </tr>
                    <?php elseif ($allowances > 0): ?>
                    <tr>
                        <td>Allowances</td>
                        <td class="amt"><?php echo number_format($allowances, 2); ?></td>
                        <td class="divider-col"></td>
                        <?php if ($show_statutory): ?>
                        <td>SOCSO – Employee (0.5%)</td>
                        <td class="amt"><?php echo number_format($socso_ee, 2); ?></td>
                        <?php else: ?>
                        <td></td><td></td>
                        <?php endif; ?>
                    </tr>
                    <?php elseif ($show_statutory): ?>
                    <tr>
                        <td style="color:#999; font-style:italic; font-size:9pt;">—</td>
                        <td></td>
                        <td class="divider-col"></td>
                        <td>SOCSO – Employee (0.5%)</td>
                        <td class="amt"><?php echo number_format($socso_ee, 2); ?></td>
                    </tr>
                    <?php endif; ?>

                    <!-- Row 3: Overtime / EIS -->
                    <tr>
                        <td>Overtime Pay</td>
                        <td class="amt"><?php echo number_format($overtime, 2); ?></td>
                        <td class="divider-col"></td>
                        <?php if ($show_statutory): ?>
                        <td>EIS – Employee (0.2%)</td>
                        <td class="amt"><?php echo number_format($eis_ee, 2); ?></td>
                        <?php else: ?>
                        <td></td><td></td>
                        <?php endif; ?>
                    </tr>

                    <!-- Row 4: Unpaid Leave / PCB -->
                    <?php if ($unpaid > 0 || $show_statutory): ?>
                    <tr>
                        <?php if ($unpaid > 0): ?>
                        <td style="color:#c0392b;">Less: Unpaid Leave</td>
                        <td class="amt" style="color:#c0392b;">–<?php echo number_format($unpaid, 2); ?></td>
                        <?php else: ?>
                        <td></td><td></td>
                        <?php endif; ?>
                        <td class="divider-col"></td>
                        <?php if ($show_statutory): ?>
                        <td>PCB / Income Tax</td>
                        <td class="amt"><?php echo number_format($pcb, 2); ?></td>
                        <?php else: ?>
                        <td></td><td></td>
                        <?php endif; ?>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total Earnings</td>
                        <td class="amt">RM <?php echo number_format($total_earnings, 2); ?></td>
                        <td class="divider-col"></td>
                        <td>Total Deductions</td>
                        <td class="amt">RM <?php echo number_format($total_deductions, 2); ?></td>
                    </tr>
                </tfoot>
            </table>

            <?php if ($show_statutory): ?>
            <div class="er-note">
                <strong>Employer Contributions (for reference, not deducted from salary):</strong>
                &nbsp; EPF: RM <?php echo number_format($epf_er, 2); ?>
                &nbsp;&nbsp;|&nbsp;&nbsp; SOCSO: RM <?php echo number_format($socso_er, 2); ?>
                &nbsp;&nbsp;|&nbsp;&nbsp; EIS: RM <?php echo number_format($eis_er, 2); ?>
            </div>
            <?php endif; ?>

            <!-- ── Net Salary box ── -->
            <div class="net-box">
                <div class="net-box-header">Net Salary Payable</div>
                <div class="net-box-body">
                    <div class="net-amount">
                        <span class="currency">RM</span><?php echo number_format($net_salary, 2); ?>
                    </div>
                    <div class="net-calc">
                        Total Earnings &nbsp;RM <?php echo number_format($total_earnings, 2); ?><br>
                        Less Deductions &nbsp;RM <?php echo number_format($total_deductions, 2); ?><br>
                        <strong>Net Payable &nbsp;RM <?php echo number_format($net_salary, 2); ?></strong>
                    </div>
                </div>
            </div>

            <!-- ── Amount in words ── -->
            <div class="words-line">
                <strong>Amount in Words</strong>
                <?php echo htmlspecialchars(numberToWords($net_salary)); ?>
            </div>

            <!-- ── Signature lines ── -->
            <div class="sig-row">
                <div class="sig-block">
                    <div style="height: 44px;"></div>
                    <div class="sig-line"></div>
                    <div class="sig-label">Prepared by</div>
                    <div class="sig-sub">HR / Payroll Department<br>Ipinfra Networks Sdn Bhd</div>
                </div>
                <div class="sig-block">
                    <div style="height: 44px;"></div>
                    <div class="sig-line"></div>
                    <div class="sig-label">Received by</div>
                    <div class="sig-sub"><?php echo htmlspecialchars($row['name']); ?><br>
                        <?php echo htmlspecialchars($row['employee_id']); ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($period_display); ?>
                    </div>
                </div>
            </div>

        </div><!-- /body-pad -->

        <!-- ══ FOOTER BAND ══ -->
        <div class="footer-band">
            <div class="footer-left">
                This is a computer-generated payslip. &copy; <?php echo date('Y'); ?> Ipinfra Networks Sdn Bhd. All rights reserved.
            </div>
            <div class="footer-confidential">&#128274; Confidential</div>
        </div>

    </div><!-- /payslip-content -->
</div><!-- /payslip-wrapper -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    // Auto-trigger based on URL param
    window.onload = function() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('action') === 'print') {
            window.print();
        } else if (params.get('action') === 'download') {
            downloadPDF();
        }
    };

    function downloadPDF() {
        const btn = document.getElementById('downloadBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generating...';
        btn.disabled = true;

        const element = document.querySelector('.payslip-wrapper');
        const filename = 'Payslip_<?php echo $row['employee_id'] . '_' . $row['month_year']; ?>.pdf';

        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     filename,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(function() {
            btn.innerHTML = '<i class="fas fa-download mr-2"></i> Download PDF';
            btn.disabled = false;
        });
    }
</script>
</body>
</html>
