<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/toast_fn.php';

$message = '';

// Handle Bulk Delete
if (isset($_POST['bulk_delete']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $ids = $_POST['delete_ids'] ?? [];
    if (!empty($ids)) {
        $safe_ids = implode(',', array_map('intval', $ids));
        mysqli_query($conn, "DELETE FROM payroll WHERE id IN ($safe_ids)");
        logAction('delete', count($ids) . ' payroll record(s) bulk deleted', null, 'payroll');
        showToast(count($ids) . ' payroll record(s) deleted.', 'info');
    }
    $qs = http_build_query(array_filter(['month' => $_POST['filter_month'] ?? '', 'per_page' => $_POST['filter_per_page'] ?? '']));
    header('Location: payroll.php' . ($qs ? '?'.$qs : '')); exit();
}

// Handle Delete Payroll Record
if (isset($_POST['pay_delete']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $del_id = intval($_POST['pay_delete']);
    mysqli_query($conn, "DELETE FROM payroll WHERE id = $del_id");
    logAction('delete', 'Deleted payroll record', $del_id, 'payroll');
    showToast('Payroll record deleted.', 'info');
    $qs = http_build_query(array_filter(['month' => $_POST['filter_month'] ?? '', 'per_page' => $_POST['filter_per_page'] ?? '', 'page' => $_POST['filter_page'] ?? '']));
    header('Location: payroll.php' . ($qs ? '?'.$qs : '')); exit();
}

// Handle Regenerate — delete then rebuild for one employee+month
if (isset($_POST['pay_regenerate']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $regen_id = intval($_POST['pay_regenerate']);
    $regen_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT p.month_year, e.* FROM payroll p JOIN employees e ON p.employee_id=e.id WHERE p.id=$regen_id"));
    if ($regen_row) {
        mysqli_query($conn, "DELETE FROM payroll WHERE id=$regen_id");
        $month_year  = $regen_row['month_year'];
        $month_start = $month_year . '-01';
        $month_end   = date('Y-m-t', strtotime($month_start));
        $wdays       = (int)date('t', strtotime($month_start));
        $basic       = $regen_row['basic_salary'];
        $is_intern   = (isset($regen_row['employee_type']) && $regen_row['employee_type'] == 'intern');
        $is_malaysian= ($regen_row['nationality'] == 'Malaysian');

        if ($is_intern) {
            $epf_emp=$epf_er=$socso_emp=$socso_er=$eis=$eis_er=$pcb=0;
        } else {
            $epf_emp  = calculateEPF($basic, true,  $is_malaysian);
            $epf_er   = calculateEPF($basic, false, $is_malaysian);
            $socso_emp= calculateSOCSO($basic, true,  $is_malaysian);
            $socso_er = calculateSOCSO($basic, false, $is_malaysian);
            $eis      = calculateEIS($basic, $is_malaysian);
            $eis_er   = $eis;
            $pcb      = calculatePCB($basic, $is_malaysian);
        }

        $per_day = $basic / $wdays;
        $regen_leave_filter = $is_intern ? "IN ('unpaid', 'annual')" : "= 'unpaid'";
        $uq = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(
    DATEDIFF(LEAST(end_date, '$month_end'), GREATEST(start_date, '$month_start')) + 1
), 0) as ud FROM leaves WHERE employee_id={$regen_row['id']} AND status='approved' AND leave_type $regen_leave_filter AND start_date <= '$month_end' AND end_date >= '$month_start'"));
        $unpaid_deduction = round($per_day * (float)$uq['ud'], 2);

        $cq = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as ca FROM claims WHERE employee_id={$regen_row['id']} AND status='approved' AND DATE_FORMAT(reviewed_at,'%Y-%m')='$month_year'"));
        $approved_claims = (float)$cq['ca'];

        $net = $basic - $epf_emp - $socso_emp - $eis - $pcb - $unpaid_deduction + $approved_claims;
        $net = max(0, $net);
        try {
            mysqli_query($conn, "INSERT INTO payroll (employee_id,month_year,basic_salary,epf_employee,epf_employer,socso_employee,socso_employer,eis_employee,eis_employer,pcb,unpaid_deduction,approved_claims,net_salary) VALUES ({$regen_row['id']},'$month_year',$basic,$epf_emp,$epf_er,$socso_emp,$socso_er,$eis,$eis_er,$pcb,$unpaid_deduction,$approved_claims,$net)");
            logAction('generate', 'Regenerated payroll for ' . $regen_row['name'] . ' (' . $month_year . ')', $regen_row['id'], 'payroll');
            showToast('Payroll regenerated for ' . $regen_row['name'] . ' (' . $month_year . ').', 'success');
        } catch (Exception $e) {
            showToast('Payroll for ' . $regen_row['name'] . ' (' . $month_year . ') was already regenerated elsewhere — please refresh.', 'warning');
        }
    }
    header('Location: payroll.php'); exit();
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exp_month = trim($_GET['month'] ?? '');
    if ($exp_month && !preg_match('/^\d{4}-\d{2}$/', $exp_month)) $exp_month = '';
    $exp_where = $exp_month ? "WHERE p.month_year = '$exp_month'" : '';
    $export_query = mysqli_query($conn, "SELECT e.employee_id, e.name, e.department, e.nationality, e.employee_type, p.month_year, p.basic_salary, p.epf_employee, p.socso_employee, p.eis_employee, p.pcb, p.unpaid_deduction, p.approved_claims, p.net_salary FROM payroll p JOIN employees e ON p.employee_id = e.id $exp_where ORDER BY p.month_year DESC, e.name ASC");
    $filename = 'payroll_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee ID', 'Name', 'Department', 'Month', 'Basic Salary', 'EPF Employee', 'SOCSO Employee', 'EIS Employee', 'PCB', 'Unpaid Deduction', 'Approved Claims', 'Net Salary']);
    while ($row = mysqli_fetch_assoc($export_query)) {
        fputcsv($out, [
            $row['employee_id'],
            $row['name'],
            $row['department'],
            $row['month_year'],
            $row['basic_salary'],
            $row['epf_employee'],
            $row['socso_employee'],
            $row['eis_employee'],
            $row['pcb'],
            $row['unpaid_deduction'],
            $row['approved_claims'],
            $row['net_salary'],
        ]);
    }
    fclose($out);
    exit();
}

// Handle Email Payslip
if (isset($_POST['email_payslip'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Security error.', 'error');
        header('Location: payroll.php'); exit();
    }
    $email_id = (int)$_POST['email_payslip'];
    $email_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT p.*, e.name, e.employee_id, e.department, e.nationality, e.employee_type, e.email
        FROM payroll p JOIN employees e ON p.employee_id = e.id WHERE p.id = $email_id"));

    if ($email_row && !empty($email_row['email'])) {
        $emp_email   = $email_row['email'];
        $emp_name    = $email_row['name'];
        $emp_id_str  = $email_row['employee_id'];
        $dept        = $email_row['department'];
        $month_year  = $email_row['month_year'];
        $month_label = date('F Y', strtotime($month_year . '-01'));

        $is_intern_mail    = (isset($email_row['employee_type']) && $email_row['employee_type'] == 'intern');
        $is_malaysian_mail = ($email_row['nationality'] == 'Malaysian');
        $show_stat_mail    = $is_malaysian_mail && !$is_intern_mail;

        $basic       = (float)$email_row['basic_salary'];
        $epf_emp     = (float)$email_row['epf_employee'];
        $epf_er      = (float)$email_row['epf_employer'];
        $socso_emp   = (float)$email_row['socso_employee'];
        $socso_er    = (float)$email_row['socso_employer'];
        $eis_emp     = (float)$email_row['eis_employee'];
        $eis_er      = (float)$email_row['eis_employer'];
        $pcb         = (float)$email_row['pcb'];
        $unpaid_ded  = (float)$email_row['unpaid_deduction'];
        $claims      = (float)$email_row['approved_claims'];
        $net         = (float)$email_row['net_salary'];

        $total_ded_mail = ($show_stat_mail ? $epf_emp + $socso_emp + $eis_emp + $pcb : 0) + $unpaid_ded;

        $deductions_rows = '';
        if ($show_stat_mail) {
            $deductions_rows .= '<tr><td style="padding:8px 16px;color:#4b5563;font-size:14px;">EPF (Employee 11%)</td><td style="padding:8px 16px;text-align:right;color:#ef4444;font-size:14px;">- RM ' . number_format($epf_emp, 2) . '</td></tr>';
            $deductions_rows .= '<tr style="background:#f9fafb;"><td style="padding:8px 16px;color:#4b5563;font-size:14px;">SOCSO (Employee)</td><td style="padding:8px 16px;text-align:right;color:#ef4444;font-size:14px;">- RM ' . number_format($socso_emp, 2) . '</td></tr>';
            $deductions_rows .= '<tr><td style="padding:8px 16px;color:#4b5563;font-size:14px;">EIS (Employee 0.2%)</td><td style="padding:8px 16px;text-align:right;color:#ef4444;font-size:14px;">- RM ' . number_format($eis_emp, 2) . '</td></tr>';
            $deductions_rows .= '<tr style="background:#f9fafb;"><td style="padding:8px 16px;color:#4b5563;font-size:14px;">PCB / Income Tax</td><td style="padding:8px 16px;text-align:right;color:#ef4444;font-size:14px;">- RM ' . number_format($pcb, 2) . '</td></tr>';
        }
        if ($unpaid_ded > 0) {
            $deductions_rows .= '<tr><td style="padding:8px 16px;color:#4b5563;font-size:14px;">Unpaid Leave Deduction</td><td style="padding:8px 16px;text-align:right;color:#ef4444;font-size:14px;">- RM ' . number_format($unpaid_ded, 2) . '</td></tr>';
        }
        if (!$deductions_rows) {
            $deductions_rows = '<tr><td colspan="2" style="padding:8px 16px;color:#9ca3af;font-size:13px;font-style:italic;">No deductions applicable</td></tr>';
        }

        $claims_row = '';
        if ($claims > 0) {
            $claims_row = '<tr style="background:#f9fafb;"><td style="padding:8px 16px;color:#4b5563;font-size:14px;">Approved Claims</td><td style="padding:8px 16px;text-align:right;color:#16a34a;font-size:14px;">+ RM ' . number_format($claims, 2) . '</td></tr>';
        }

        $epf_er_note = $show_stat_mail
            ? '<p style="margin:0 0 4px 0;font-size:12px;color:#6b7280;">EPF Employer (13%): RM ' . number_format($epf_er, 2) . ' &nbsp;|&nbsp; SOCSO Employer: RM ' . number_format($socso_er, 2) . ' &nbsp;|&nbsp; EIS Employer: RM ' . number_format($eis_er, 2) . '</p>'
            : '';

        $html_body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Inter,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#1e1b4b 0%,#3730a3 50%,#1e1b4b 100%);padding:32px 32px 24px 32px;">
          <p style="margin:0 0 4px 0;font-size:12px;color:#a5b4fc;font-weight:600;letter-spacing:2px;text-transform:uppercase;">IPINFRA Networks Sdn Bhd</p>
          <h1 style="margin:0 0 8px 0;font-size:26px;font-weight:800;color:#ffffff;">Payslip</h1>
          <p style="margin:0;font-size:15px;color:#c7d2fe;">' . $month_label . '</p>
          <table width="100%" style="margin-top:20px;background:rgba(255,255,255,0.12);border-radius:10px;padding:0;" cellpadding="0" cellspacing="0">
            <tr>
              <td style="padding:16px 20px;border-right:1px solid rgba(255,255,255,0.15);">
                <p style="margin:0 0 2px 0;font-size:11px;color:#a5b4fc;text-transform:uppercase;letter-spacing:1px;">Employee</p>
                <p style="margin:0;font-size:16px;font-weight:700;color:#ffffff;">' . htmlspecialchars($emp_name) . '</p>
              </td>
              <td style="padding:16px 20px;border-right:1px solid rgba(255,255,255,0.15);">
                <p style="margin:0 0 2px 0;font-size:11px;color:#a5b4fc;text-transform:uppercase;letter-spacing:1px;">Employee ID</p>
                <p style="margin:0;font-size:16px;font-weight:700;color:#ffffff;">' . htmlspecialchars($emp_id_str) . '</p>
              </td>
              <td style="padding:16px 20px;">
                <p style="margin:0 0 2px 0;font-size:11px;color:#a5b4fc;text-transform:uppercase;letter-spacing:1px;">Department</p>
                <p style="margin:0;font-size:16px;font-weight:700;color:#ffffff;">' . htmlspecialchars($dept) . '</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Net Salary Banner -->
      <tr>
        <td style="background:#f0fdf4;padding:20px 32px;border-bottom:1px solid #dcfce7;text-align:center;">
          <p style="margin:0 0 4px 0;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Net Salary</p>
          <p style="margin:0;font-size:36px;font-weight:800;color:#16a34a;">RM ' . number_format($net, 2) . '</p>
        </td>
      </tr>

      <!-- Earnings -->
      <tr>
        <td style="padding:24px 32px 0 32px;">
          <p style="margin:0 0 12px 0;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:1.5px;">Earnings</p>
          <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
            <tr style="background:#f9fafb;">
              <td style="padding:8px 16px;color:#4b5563;font-size:14px;">Basic Salary</td>
              <td style="padding:8px 16px;text-align:right;color:#16a34a;font-size:14px;font-weight:600;">RM ' . number_format($basic, 2) . '</td>
            </tr>
            ' . $claims_row . '
          </table>
        </td>
      </tr>

      <!-- Deductions -->
      <tr>
        <td style="padding:20px 32px 0 32px;">
          <p style="margin:0 0 12px 0;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:1.5px;">Deductions</p>
          <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
            ' . $deductions_rows . '
            <tr style="background:#fef2f2;border-top:2px solid #fecaca;">
              <td style="padding:10px 16px;color:#dc2626;font-size:14px;font-weight:700;">Total Deductions</td>
              <td style="padding:10px 16px;text-align:right;color:#dc2626;font-size:14px;font-weight:700;">- RM ' . number_format($total_ded_mail, 2) . '</td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Employer Contributions Note -->
      ' . ($show_stat_mail ? '<tr><td style="padding:16px 32px 0 32px;">' . $epf_er_note . '</td></tr>' : '') . '

      <!-- Footer -->
      <tr>
        <td style="padding:28px 32px;border-top:1px solid #e5e7eb;margin-top:24px;text-align:center;background:#f9fafb;">
          <p style="margin:0 0 6px 0;font-size:13px;font-weight:600;color:#374151;">IPINFRA Networks Sdn Bhd</p>
          <p style="margin:0 0 4px 0;font-size:12px;color:#9ca3af;">This is a system-generated payslip. Please do not reply to this email.</p>
          <p style="margin:0;font-size:11px;color:#d1d5db;">Generated on ' . date('d M Y, h:i A') . ' &nbsp;&bull;&nbsp; Payroll Period: ' . $month_label . '</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';

        $subject  = 'Payslip - ' . $month_label . ' - IPINFRA Networks Sdn Bhd';
        $headers  = "From: IPINFRA HRM <sales@ipinfra.com.my>\r\n";
        $headers .= "Reply-To: sales@ipinfra.com.my\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        if (mail($emp_email, $subject, $html_body, $headers)) {
            showToast('Payslip emailed to ' . $emp_email . ' successfully.', 'success');
        } else {
            showToast('Failed to send email to ' . $emp_email . '. Please check server mail configuration.', 'error');
        }
    } else {
        showToast('Could not send payslip: payroll record not found or employee email is missing.', 'error');
    }
    $qs = http_build_query(array_filter(['month' => $_POST['filter_month'] ?? '', 'per_page' => $_POST['filter_per_page'] ?? '', 'page' => $_POST['filter_page'] ?? '']));
    header('Location: payroll.php' . ($qs ? '?'.$qs : '')); exit();
}

if (isset($_POST['generate_payroll'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { showToast('Security error.', 'error'); header('Location: payroll.php'); exit; }
    $month_year = mysqli_real_escape_string($conn, $_POST['month_year']);
    $employees = mysqli_query($conn, "SELECT * FROM employees WHERE role='employee' AND status='active'");
    $generated_count = 0;

    $month_start = $month_year . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    $working_days_in_month = (int)date('t', strtotime($month_start));

    while ($emp = mysqli_fetch_assoc($employees)) {
        $check = mysqli_query($conn, "SELECT id FROM payroll WHERE employee_id = {$emp['id']} AND month_year = '$month_year'");
        if (mysqli_num_rows($check) == 0) {
            $basic = $emp['basic_salary'];
            $is_intern = (isset($emp['employee_type']) && $emp['employee_type'] == 'intern');
            $is_malaysian = ($emp['nationality'] == 'Malaysian');

            // Interns are exempt from all statutory deductions
            if ($is_intern) {
                $epf_emp = 0; $epf_er = 0;
                $socso_emp = 0; $socso_er = 0;
                $eis = 0; $pcb = 0;
            } else {
                $epf_emp = calculateEPF($basic, true, $is_malaysian);
                $epf_er  = calculateEPF($basic, false, $is_malaysian);
                $socso_emp = calculateSOCSO($basic, true, $is_malaysian);
                $socso_er  = calculateSOCSO($basic, false, $is_malaysian);
                $eis = calculateEIS($basic, $is_malaysian);
                $pcb = calculatePCB($basic, $is_malaysian);
            }

            // Unpaid leave deduction for the month
            // Interns have no annual leave entitlement — annual leave also deducted for them
            $per_day = $basic / $working_days_in_month;
            $leave_type_filter = $is_intern ? "IN ('unpaid', 'annual')" : "= 'unpaid'";
            $unpaid_q = mysqli_query($conn, "SELECT COALESCE(SUM(
                DATEDIFF(LEAST(end_date, '$month_end'), GREATEST(start_date, '$month_start')) + 1
            ), 0) as ud FROM leaves
                WHERE employee_id = {$emp['id']} AND status = 'approved'
                AND leave_type $leave_type_filter
                AND start_date <= '$month_end' AND end_date >= '$month_start'");
            $unpaid_days = (float)mysqli_fetch_assoc($unpaid_q)['ud'];
            $unpaid_deduction = round($per_day * $unpaid_days, 2);

            // Approved claims counted in the month they were approved, not the month they were submitted
            $claim_q = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as ca FROM claims
                WHERE employee_id = {$emp['id']} AND status = 'approved'
                AND DATE_FORMAT(reviewed_at, '%Y-%m') = '$month_year'");
            $approved_claims = (float)mysqli_fetch_assoc($claim_q)['ca'];

            // EIS: both employee and employer contribute same rate (0.2%)
            $eis_er = $eis;

            $net = $basic - $epf_emp - $socso_emp - $eis - $pcb - $unpaid_deduction + $approved_claims;
            $net = max(0, $net);

            $insert = "INSERT INTO payroll (employee_id, month_year, basic_salary, epf_employee, epf_employer, socso_employee, socso_employer, eis_employee, eis_employer, pcb, unpaid_deduction, approved_claims, net_salary)
                       VALUES ({$emp['id']}, '$month_year', $basic, $epf_emp, $epf_er, $socso_emp, $socso_er, $eis, $eis_er, $pcb, $unpaid_deduction, $approved_claims, $net)";
            try {
                mysqli_query($conn, $insert);
                $generated_count++;
            } catch (Exception $e) {
                // Already generated by a concurrent request for this employee+month — skip, not fatal.
            }
        }
    }
    if ($generated_count > 0) {
        logAction('generate', "Generated payroll for $generated_count employee(s) ($month_year)", null, 'payroll');
    }
    $message = '<div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm animate-fadeIn">
                    <i class="fas fa-check-circle mr-2"></i> ✓ Payroll generated for ' . htmlspecialchars($month_year) . ' (' . $generated_count . ' employees)
                </div>';
}

// Get statistics (runs after any generate_payroll insert so stats are up to date)
$stats_query = mysqli_query($conn, "SELECT
    COUNT(DISTINCT month_year) as total_months,
    SUM(net_salary) as total_paid,
    COUNT(*) as total_records,
    SUM(epf_employee) as total_epf,
    SUM(socso_employee) as total_socso,
    SUM(pcb) as total_pcb
    FROM payroll");
$stats = mysqli_fetch_assoc($stats_query);

// Filters & pagination
$filter_month = trim($_GET['month'] ?? '');
if ($filter_month && !preg_match('/^\d{4}-\d{2}$/', $filter_month)) $filter_month = '';
$per_page = (int)($_GET['per_page'] ?? 25);
if (!in_array($per_page, [10, 25, 50, 100])) $per_page = 25;
$page = max(1, (int)($_GET['page'] ?? 1));

$where_clause = $filter_month ? "WHERE p.month_year = '" . mysqli_real_escape_string($conn, $filter_month) . "'" : '';

$total_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM payroll p JOIN employees e ON p.employee_id = e.id $where_clause"))['c'];
$total_pages = max(1, (int)ceil($total_count / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Distinct months for the filter dropdown
$months_res = mysqli_query($conn, "SELECT DISTINCT month_year FROM payroll ORDER BY month_year DESC");
$available_months = [];
while ($mr = mysqli_fetch_assoc($months_res)) $available_months[] = $mr['month_year'];

$payrolls = mysqli_query($conn, "SELECT p.*, e.name, e.employee_id, e.nationality, e.department, e.employee_type, e.email
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    $where_clause
    ORDER BY p.month_year DESC, e.name
    LIMIT $per_page OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Payroll - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        
        /* Premium Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn 0.3s ease-out; }
        .animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }
        
        /* Card Hover */
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        
        /* Payroll Row Hover */
        .payroll-row { transition: all 0.2s ease; }
        .payroll-row:hover { background-color: #f8fafc; transform: translateX(2px); }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>
    
<!-- Premium Mobile Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <!-- MENU BUTTON - Left side -->
            <button onclick="toggleSidebar()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                <span class="text-white font-bold text-sm">IN</span>
            </div>
            <div>
                <p class="text-xs text-blue-200 font-medium">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-wide">Admin Portal</p>
            </div>
        </div>
        <!-- No back button - just empty space or nothing -->
    </div>
</div>
<?php require_once '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="mb-6 animate-fadeInUp">
            <h1 class="text-2xl font-bold text-gray-800">Payroll Management</h1>
            <p class="text-sm text-gray-500 mt-1">Process employee salaries and manage payroll records</p>
        </div>

        <?php echo $message; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-md card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total Processed</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_months'] ?? 0; ?></p>
                        <p class="text-xs text-gray-400">Months</p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total Paid</p>
                        <p class="text-2xl font-bold text-green-600">RM <?php echo number_format($stats['total_paid'] ?? 0, 0); ?></p>
                        <p class="text-xs text-gray-400">All time</p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total Records</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_records'] ?? 0; ?></p>
                        <p class="text-xs text-gray-400">Salary slips</p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-purple-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-red-600 to-pink-600 rounded-xl p-4 shadow-md text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-red-100">Statutory Deductions</p>
                        <p class="text-xl font-bold">RM <?php echo number_format(($stats['total_epf'] ?? 0) + ($stats['total_socso'] ?? 0) + ($stats['total_pcb'] ?? 0), 0); ?></p>
                        <p class="text-xs text-red-100">EPF + SOCSO + PCB</p>
                    </div>
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generate Payroll Section -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6 card-hover">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-calculator text-blue-600 text-xs"></i>
                </div>
                <span class="font-semibold text-gray-800 text-sm">Generate Payroll</span>
            </div>
            <form method="POST" class="flex gap-2">
                <?php echo csrfField(); ?>
                <input type="month" name="month_year" required
                       class="w-44 min-w-0 px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none text-sm">
                <button type="submit" name="generate_payroll"
                        class="flex-shrink-0 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-1.5 whitespace-nowrap">
                    <i class="fas fa-sync-alt text-xs"></i> Generate
                </button>
            </form>
            <p class="text-xs text-gray-400 mt-2">Malaysian employees: EPF, SOCSO, EIS auto-deducted. Interns &amp; non-Malaysians: no statutory deductions.</p>
        </div>

        <!-- Hidden bulk-delete form (outside row forms to avoid nesting) -->
        <form id="bulkDeleteForm" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="bulk_delete" value="1">
            <input type="hidden" name="filter_month" value="<?php echo htmlspecialchars($filter_month); ?>">
            <input type="hidden" name="filter_per_page" value="<?php echo $per_page; ?>">
        </form>

        <!-- Payroll Records -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">

            <!-- Filter bar -->
            <form method="GET" class="bg-gray-50 px-4 py-3 border-b flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 mr-auto">
                    <i class="fas fa-file-invoice-dollar text-indigo-500 text-sm"></i>
                    <p class="font-semibold text-gray-800 text-sm whitespace-nowrap">Payroll Records</p>
                    <span class="text-xs text-gray-400">(<?php echo $total_count; ?> total)</span>
                </div>
                <!-- Month picker -->
                <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>"
                       class="text-xs border border-gray-200 rounded-lg px-3 py-2 focus:border-indigo-400 focus:outline-none bg-white">
                <!-- Per-page -->
                <select name="per_page" onchange="this.form.submit()"
                    class="text-xs border border-gray-200 rounded-lg px-3 py-2 focus:border-indigo-400 focus:outline-none bg-white">
                    <?php foreach ([10, 25, 50, 100] as $pp): ?>
                        <option value="<?php echo $pp; ?>" <?php echo ($per_page === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?>/page</option>
                    <?php endforeach; ?>
                </select>
                <!-- Filter + Clear -->
                <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg font-semibold transition whitespace-nowrap">
                    <i class="fas fa-search text-[10px] mr-1"></i> Filter
                </button>
                <?php if ($filter_month): ?>
                <a href="?per_page=<?php echo $per_page; ?>" class="text-xs text-gray-500 hover:text-gray-700 bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg transition whitespace-nowrap">
                    Clear
                </a>
                <?php endif; ?>
                <!-- Export -->
                <a href="?export=csv<?php echo $filter_month ? '&month='.urlencode($filter_month) : ''; ?>"
                   class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 px-3 py-2 rounded-lg hover:bg-green-200 transition font-semibold whitespace-nowrap">
                    <i class="fas fa-download"></i> CSV
                </a>
            </form>

            <!-- Bulk action bar (shown when rows are checked) -->
            <div id="bulkBar" class="hidden bg-indigo-50 border-b border-indigo-100 px-5 py-2.5 flex items-center gap-3">
                <input type="checkbox" id="selectAll" onchange="toggleAllPayroll(this.checked)"
                    class="w-4 h-4 accent-indigo-600 cursor-pointer">
                <label for="selectAll" class="text-xs font-semibold text-indigo-700 cursor-pointer select-none">Select All</label>
                <span class="text-xs text-indigo-500"><span id="bulkCount">0</span> selected</span>
                <button type="button" onclick="submitBulkDelete()"
                    class="ml-auto flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </div>

            <!-- Column header row -->
            <div class="px-5 py-2 bg-gray-50 border-b flex items-center gap-3">
                <input type="checkbox" id="selectAllTop" onchange="toggleAllPayroll(this.checked)"
                    class="w-4 h-4 accent-indigo-600 cursor-pointer">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Select</span>
            </div>

            <?php if(mysqli_num_rows($payrolls) > 0): ?>
                <div class="divide-y divide-gray-100">
                    <?php while ($row = mysqli_fetch_assoc($payrolls)):
                        $is_intern_row   = isset($row['employee_type']) && $row['employee_type'] == 'intern';
                        $is_malaysian_row = $row['nationality'] == 'Malaysian';
                        $show_deductions = $is_malaysian_row && !$is_intern_row;
                        $approved_claims = (float)($row['approved_claims'] ?? 0);
                        $unpaid_ded      = (float)($row['unpaid_deduction'] ?? 0);
                        $total_statutory = $show_deductions
                            ? $row['epf_employee'] + $row['socso_employee'] + $row['eis_employee'] + $row['pcb']
                            : 0;
                        $total_deductions = $total_statutory + $unpaid_ded;

                        // Badge
                        if ($is_intern_row) {
                            $badge = '<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"><i class="fas fa-graduation-cap mr-1"></i>Intern</span>';
                        } elseif (!$is_malaysian_row) {
                            $badge = '<span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full"><i class="fas fa-globe mr-1"></i>Expat</span>';
                        } else {
                            $badge = '<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"><i class="fas fa-check-circle mr-1"></i>Local</span>';
                        }

                        // Array for modal — encoded once at call site with HEX flags
                        $modal_data = [
                            'name'        => $row['name'],
                            'employee_id' => $row['employee_id'],
                            'department'  => $row['department'],
                            'month'       => $row['month_year'],
                            'basic'       => (float)$row['basic_salary'],
                            'claims'      => $approved_claims,
                            'unpaid'      => $unpaid_ded,
                            'epf'         => (float)$row['epf_employee'],
                            'epf_er'      => (float)$row['epf_employer'],
                            'socso'       => (float)$row['socso_employee'],
                            'socso_er'    => (float)$row['socso_employer'],
                            'eis'         => (float)$row['eis_employee'],
                            'eis_er'      => (float)$row['eis_employer'],
                            'pcb'         => (float)$row['pcb'],
                            'net'         => (float)$row['net_salary'],
                            'show_stat'   => $show_deductions,
                            'is_intern'   => $is_intern_row,
                            'is_malaysian'=> $is_malaysian_row,
                            'calc_url'    => '../employee/payroll_calculation.php?month=' . $row['month_year'] . '&emp_id=' . $row['employee_id'],
                        ];
                    ?>
                    <div class="payroll-row px-5 py-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">

                            <!-- Checkbox -->
                            <div class="flex-shrink-0 self-start sm:self-center">
                                <input type="checkbox" class="payroll-cb w-4 h-4 accent-indigo-600 cursor-pointer"
                                    value="<?php echo $row['id']; ?>" onchange="updateBulkBar()">
                            </div>

                            <!-- Left: Employee Info -->
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                    <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['name']); ?></p>
                                        <?php echo $badge; ?>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-0.5"><?php echo $row['employee_id']; ?> &bull; <?php echo $row['department']; ?> &bull; <i class="fas fa-calendar-alt mr-1"></i><?php echo $row['month_year']; ?></p>
                                </div>
                            </div>

                            <!-- Middle: Quick figures -->
                            <div class="flex items-center gap-3 flex-wrap text-center">
                                <div class="px-3 py-2 bg-gray-50 rounded-xl">
                                    <p class="text-xs text-gray-400">Basic</p>
                                    <p class="text-sm font-bold text-gray-700">RM <?php echo number_format($row['basic_salary'], 2); ?></p>
                                </div>
                                <?php if($approved_claims > 0): ?>
                                <div class="px-3 py-2 bg-green-50 rounded-xl">
                                    <p class="text-xs text-green-500">+ Claims</p>
                                    <p class="text-sm font-bold text-green-600">RM <?php echo number_format($approved_claims, 2); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if($unpaid_ded > 0): ?>
                                <div class="px-3 py-2 bg-red-50 rounded-xl">
                                    <p class="text-xs text-red-400">- Unpaid</p>
                                    <p class="text-sm font-bold text-red-500">RM <?php echo number_format($unpaid_ded, 2); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if($show_deductions): ?>
                                <div class="px-3 py-2 bg-orange-50 rounded-xl">
                                    <p class="text-xs text-orange-400">- Statutory</p>
                                    <p class="text-sm font-bold text-orange-500">RM <?php echo number_format($total_statutory, 2); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Right: Net + Button -->
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <div class="text-right">
                                    <p class="text-xs text-gray-400">Net Salary</p>
                                    <p class="text-xl font-bold text-green-600">RM <?php echo number_format($row['net_salary'], 2); ?></p>
                                </div>
                                <button onclick='openBreakdown(<?php echo json_encode($modal_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                    class="flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition shadow-md hover:shadow-lg">
                                    <i class="fas fa-chart-bar"></i>
                                    <span class="hidden sm:inline">View Breakdown</span>
                                    <span class="sm:hidden">View</span>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Send payslip to <?php echo htmlspecialchars($row['email'] ?? 'employee', ENT_QUOTES); ?> for <?php echo htmlspecialchars(date('F Y', strtotime($row['month_year'] . '-01')), ENT_QUOTES); ?>?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="email_payslip" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="filter_month" value="<?php echo htmlspecialchars($_GET['month'] ?? ''); ?>">
                                    <input type="hidden" name="filter_per_page" value="<?php echo htmlspecialchars($_GET['per_page'] ?? ''); ?>">
                                    <input type="hidden" name="filter_page" value="<?php echo htmlspecialchars($_GET['page'] ?? ''); ?>">
                                    <button type="submit" class="flex items-center gap-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-600 px-3 py-2.5 rounded-xl text-sm font-semibold transition" title="Email Payslip">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('Regenerate payroll for <?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?> (<?php echo $row['month_year']; ?>)? The current record will be recalculated.')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="pay_regenerate" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="filter_month" value="<?php echo htmlspecialchars($filter_month); ?>">
                                    <input type="hidden" name="filter_per_page" value="<?php echo $per_page; ?>">
                                    <input type="hidden" name="filter_page" value="<?php echo $page; ?>">
                                    <button type="submit" class="flex items-center gap-1 bg-blue-100 hover:bg-blue-200 text-blue-600 px-3 py-2.5 rounded-xl text-sm font-semibold transition" title="Regenerate">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete payroll record for <?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?> (<?php echo $row['month_year']; ?>)? This cannot be undone.')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="pay_delete" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="filter_month" value="<?php echo htmlspecialchars($filter_month); ?>">
                                    <input type="hidden" name="filter_per_page" value="<?php echo $per_page; ?>">
                                    <input type="hidden" name="filter_page" value="<?php echo $page; ?>">
                                    <button type="submit" class="flex items-center gap-1 bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2.5 rounded-xl text-sm font-semibold transition" title="Delete Record">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="p-16 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-invoice-dollar text-2xl text-gray-300"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No payroll records yet</p>
                    <p class="text-xs text-gray-400 mt-1">Generate payroll for a month above</p>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
            <!-- Pagination -->
            <div class="px-5 py-4 border-t bg-gray-50 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-gray-500">
                    Showing <?php echo ($offset + 1); ?>–<?php echo min($offset + $per_page, $total_count); ?> of <?php echo $total_count; ?> records
                </p>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <?php
                    $pg_base = '?page=%d&per_page=' . $per_page . ($filter_month ? '&month=' . urlencode($filter_month) : '');

                    // Prev
                    if ($page > 1):
                    ?><a href="<?php echo sprintf($pg_base, $page - 1); ?>"
                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition text-xs font-medium">
                        <i class="fas fa-chevron-left text-[10px]"></i>
                    </a>
                    <?php endif;

                    // Page numbers — show up to 7 with ellipsis
                    $range = 2;
                    $start = max(1, $page - $range);
                    $end   = min($total_pages, $page + $range);
                    if ($start > 1): ?>
                        <a href="<?php echo sprintf($pg_base, 1); ?>"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition">1</a>
                        <?php if ($start > 2): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                    <?php endif;

                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="<?php echo sprintf($pg_base, $i); ?>"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg border text-xs font-semibold transition
                                  <?php echo ($i === $page)
                                    ? 'bg-indigo-600 border-indigo-600 text-white shadow'
                                    : 'border-gray-200 bg-white text-gray-600 hover:bg-indigo-50 hover:text-indigo-600'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;

                    if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                        <a href="<?php echo sprintf($pg_base, $total_pages); ?>"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition"><?php echo $total_pages; ?></a>
                    <?php endif;

                    // Next
                    if ($page < $total_pages): ?>
                    <a href="<?php echo sprintf($pg_base, $page + 1); ?>"
                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition text-xs font-medium">
                        <i class="fas fa-chevron-right text-[10px]"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ===== SALARY BREAKDOWN MODAL ===== -->
    <div id="breakdownModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto animate-fadeInUp">

            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-t-2xl p-5 text-white">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-indigo-200 font-medium uppercase tracking-wider">Salary Breakdown</p>
                        <h2 class="text-xl font-bold mt-1" id="modal_name">—</h2>
                        <p class="text-sm text-indigo-200 mt-0.5" id="modal_meta">—</p>
                    </div>
                    <button onclick="closeBreakdown()" class="text-white/70 hover:text-white transition p-1">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="mt-4 bg-white/20 rounded-xl px-4 py-3 text-center">
                    <p class="text-xs text-indigo-100">Net Salary</p>
                    <p class="text-3xl font-bold" id="modal_net">RM 0.00</p>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-5 space-y-4">

                <!-- Earnings -->
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-plus-circle text-green-500"></i> Earnings
                    </p>
                    <div class="bg-gray-50 rounded-xl divide-y divide-gray-100">
                        <div class="flex justify-between items-center px-4 py-3">
                            <span class="text-sm text-gray-600">Basic Salary</span>
                            <span class="text-sm font-semibold text-gray-800" id="modal_basic">RM 0.00</span>
                        </div>
                        <div id="row_claims" class="flex justify-between items-center px-4 py-3">
                            <span class="text-sm text-gray-600">Approved Claims</span>
                            <span class="text-sm font-semibold text-green-600" id="modal_claims">RM 0.00</span>
                        </div>
                        <div class="flex justify-between items-center px-4 py-3 font-semibold border-t border-gray-200">
                            <span class="text-sm text-gray-700">Total Earnings</span>
                            <span class="text-sm text-green-600" id="modal_total_earn">RM 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Deductions -->
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-minus-circle text-red-500"></i> Deductions
                    </p>
                    <div class="bg-gray-50 rounded-xl divide-y divide-gray-100">
                        <div id="row_unpaid" class="flex justify-between items-center px-4 py-3">
                            <span class="text-sm text-gray-600">Unpaid Leave</span>
                            <span class="text-sm font-semibold text-red-500" id="modal_unpaid">RM 0.00</span>
                        </div>
                        <div id="statutory_rows">
                            <div class="flex justify-between items-center px-4 py-3">
                                <span class="text-sm text-gray-600">EPF (Employee 11%)</span>
                                <span class="text-sm font-semibold text-blue-600" id="modal_epf">RM 0.00</span>
                            </div>
                            <div class="flex justify-between items-center px-4 py-3">
                                <span class="text-sm text-gray-600">SOCSO (0.5%)</span>
                                <span class="text-sm font-semibold text-orange-500" id="modal_socso">RM 0.00</span>
                            </div>
                            <div class="flex justify-between items-center px-4 py-3">
                                <span class="text-sm text-gray-600">EIS (0.2%)</span>
                                <span class="text-sm font-semibold text-purple-500" id="modal_eis">RM 0.00</span>
                            </div>
                            <div class="flex justify-between items-center px-4 py-3">
                                <span class="text-sm text-gray-600">PCB / Income Tax</span>
                                <span class="text-sm font-semibold text-red-600" id="modal_pcb">RM 0.00</span>
                            </div>
                        </div>
                        <div id="no_statutory" class="hidden px-4 py-3 text-center">
                            <p class="text-xs text-gray-400 italic" id="no_statutory_msg"></p>
                        </div>
                        <div class="flex justify-between items-center px-4 py-3 font-semibold border-t border-gray-200">
                            <span class="text-sm text-gray-700">Total Deductions</span>
                            <span class="text-sm text-red-500" id="modal_total_ded">RM 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Employer Contributions -->
                <div id="employer_section">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-building text-gray-400"></i> Employer Contributions
                    </p>
                    <div class="bg-blue-50 rounded-xl divide-y divide-blue-100">
                        <div class="flex justify-between items-center px-4 py-3">
                            <span class="text-sm text-gray-600">EPF (Employer 13%)</span>
                            <span class="text-sm font-semibold text-blue-600" id="modal_epf_er">RM 0.00</span>
                        </div>
                        <div class="flex justify-between items-center px-4 py-3">
                            <span class="text-sm text-gray-600">SOCSO (Employer)</span>
                            <span class="text-sm font-semibold text-orange-500" id="modal_socso_er">RM 0.00</span>
                        </div>
                        <div class="flex justify-between items-center px-4 py-3">
                            <span class="text-sm text-gray-600">EIS (Employer)</span>
                            <span class="text-sm font-semibold text-purple-500" id="modal_eis_er">RM 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Full Detail Link -->
                <a id="modal_detail_link" href="#" target="_blank"
                   class="flex items-center justify-center gap-2 w-full bg-gradient-to-r from-gray-700 to-gray-800 hover:from-gray-800 hover:to-gray-900 text-white py-3 rounded-xl text-sm font-semibold transition">
                    <i class="fas fa-external-link-alt"></i> View Full Day-by-Day Report
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
        <div class="flex justify-around py-2">
            <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-home text-xl"></i>
                <span class="text-xs mt-1">Home</span>
            </a>
            <a href="employees.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-users text-xl"></i>
                <span class="text-xs mt-1">Staff</span>
            </a>
            <a href="manage_assets.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-boxes text-xl"></i>
                <span class="text-xs mt-1">Assets</span>
            </a>
            <a href="payroll.php" class="flex flex-col items-center py-1 px-3 text-red-600">
                <i class="fas fa-file-invoice-dollar text-xl"></i>
                <span class="text-xs mt-1">Payroll</span>
            </a>
        </div>
    </div>

    <script>
        function fmt(n) {
            return 'RM ' + parseFloat(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function openBreakdown(d) {
            // Header
            document.getElementById('modal_name').textContent = d.name;
            document.getElementById('modal_meta').textContent = d.employee_id + ' • ' + d.department + ' • ' + d.month;
            document.getElementById('modal_net').textContent = fmt(d.net);

            // Earnings
            document.getElementById('modal_basic').textContent = fmt(d.basic);
            const rowClaims = document.getElementById('row_claims');
            if (d.claims > 0) {
                rowClaims.classList.remove('hidden');
                document.getElementById('modal_claims').textContent = '+ ' + fmt(d.claims);
            } else {
                rowClaims.classList.add('hidden');
            }
            document.getElementById('modal_total_earn').textContent = fmt(d.basic + d.claims);

            // Deductions
            const rowUnpaid = document.getElementById('row_unpaid');
            if (d.unpaid > 0) {
                rowUnpaid.classList.remove('hidden');
                document.getElementById('modal_unpaid').textContent = '- ' + fmt(d.unpaid);
            } else {
                rowUnpaid.classList.add('hidden');
            }

            const statRows = document.getElementById('statutory_rows');
            const noStat   = document.getElementById('no_statutory');
            const empSec   = document.getElementById('employer_section');

            if (d.show_stat) {
                statRows.classList.remove('hidden');
                noStat.classList.add('hidden');
                empSec.classList.remove('hidden');
                document.getElementById('modal_epf').textContent   = '- ' + fmt(d.epf);
                document.getElementById('modal_socso').textContent = '- ' + fmt(d.socso);
                document.getElementById('modal_eis').textContent   = '- ' + fmt(d.eis);
                document.getElementById('modal_pcb').textContent   = '- ' + fmt(d.pcb);
                document.getElementById('modal_epf_er').textContent   = fmt(d.epf_er);
                document.getElementById('modal_socso_er').textContent = fmt(d.socso_er);
                document.getElementById('modal_eis_er').textContent   = fmt(d.eis_er);
            } else {
                statRows.classList.add('hidden');
                noStat.classList.remove('hidden');
                empSec.classList.add('hidden');
                document.getElementById('no_statutory_msg').textContent =
                    d.is_intern ? 'Intern — No statutory deductions (EPF/SOCSO/EIS/PCB)' :
                                  'Non-Malaysian — No statutory deductions';
            }

            const totalDed = (d.show_stat ? d.epf + d.socso + d.eis + d.pcb : 0) + d.unpaid;
            document.getElementById('modal_total_ded').textContent = '- ' + fmt(totalDed);

            // Full detail link
            document.getElementById('modal_detail_link').href = d.calc_url;

            document.getElementById('breakdownModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeBreakdown() {
            document.getElementById('breakdownModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Close on backdrop click
        document.getElementById('breakdownModal').addEventListener('click', function(e) {
            if (e.target === this) closeBreakdown();
        });

        // ── Bulk select ────────────────────────────────────
        function toggleAllPayroll(checked) {
            document.querySelectorAll('.payroll-cb').forEach(function(cb) { cb.checked = checked; });
            // keep both checkboxes in sync
            document.querySelectorAll('#selectAll, #selectAllTop').forEach(function(el) { el.checked = checked; });
            updateBulkBar();
        }

        function updateBulkBar() {
            var cbs  = document.querySelectorAll('.payroll-cb:checked');
            var bar  = document.getElementById('bulkBar');
            var cnt  = document.getElementById('bulkCount');
            var all  = document.getElementById('selectAll');
            var allT = document.getElementById('selectAllTop');
            var total = document.querySelectorAll('.payroll-cb').length;

            cnt.textContent = cbs.length;

            // show bar if any checked
            if (cbs.length > 0) {
                bar.classList.remove('hidden');
                bar.classList.add('flex');
            } else {
                bar.classList.add('hidden');
                bar.classList.remove('flex');
            }

            // reflect indeterminate state on "select all" checkboxes
            var allChecked = (cbs.length === total && total > 0);
            [all, allT].forEach(function(el) {
                el.checked = allChecked;
                el.indeterminate = (cbs.length > 0 && !allChecked);
            });
        }

        function submitBulkDelete() {
            var cbs = document.querySelectorAll('.payroll-cb:checked');
            if (cbs.length === 0) return;
            if (!confirm('Delete ' + cbs.length + ' selected payroll record(s)? This cannot be undone.')) return;

            var form = document.getElementById('bulkDeleteForm');
            // remove any previously injected id inputs
            form.querySelectorAll('input[name="delete_ids[]"]').forEach(function(el) { el.remove(); });

            cbs.forEach(function(cb) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'delete_ids[]';
                inp.value = cb.value;
                form.appendChild(inp);
            });
            form.submit();
        }
    </script>
</body>
</html>