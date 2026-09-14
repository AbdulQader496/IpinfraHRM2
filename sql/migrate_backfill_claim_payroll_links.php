<?php
// One-time backfill, run AFTER migration_claims_payroll_link.sql and BEFORE the next
// "Generate Payroll" click.
//
// migration_claims_payroll_link.sql adds claims.payroll_id but leaves it NULL on every
// existing row — including claims that were already paid out by a payroll run generated
// under the old month-matching logic. Payroll generation now sweeps in every approved
// claim with payroll_id IS NULL, so without this backfill, every claim that was already
// paid before the migration would get paid a SECOND time the next time payroll runs for
// that employee.
//
// For each existing payroll row that already has approved_claims > 0, this reconstructs
// exactly which claims were actually summed into it: a claim counts as a candidate only if
// it was approved (reviewed_at) by the time that payroll was generated, AND its applied
// month or its approved month matches that payroll's month_year (the two rules the app has
// used at different points in time). If the candidates' amounts add up to EXACTLY the
// payroll's stored approved_claims, they're linked (payroll_id set) so they won't be swept
// again. If they don't add up exactly, nothing is linked for that payroll — it's printed
// as an exception for manual review instead, because guessing wrong here either double-pays
// someone or silently makes them wait longer for money they're owed, and neither should
// happen silently.
//
// Run from the command line: php sql/migrate_backfill_claim_payroll_links.php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This migration must be run from the command line, not a browser:\n  php " . basename(__FILE__) . "\n");
}

require_once __DIR__ . '/../includes/db.php';

$payrolls = mysqli_query($conn, "SELECT id, employee_id, month_year, approved_claims, generated_at
    FROM payroll WHERE approved_claims > 0 ORDER BY generated_at ASC");

$linked_payrolls = 0;
$linked_claims = 0;
$exceptions = [];

while ($p = mysqli_fetch_assoc($payrolls)) {
    $emp_id     = (int)$p['employee_id'];
    $month_year = mysqli_real_escape_string($conn, $p['month_year']);
    $gen_at     = mysqli_real_escape_string($conn, $p['generated_at']);
    $expected   = round((float)$p['approved_claims'], 2);

    $cand_q = mysqli_query($conn, "SELECT id, amount FROM claims
        WHERE employee_id = $emp_id
        AND status = 'approved'
        AND payroll_id IS NULL
        AND reviewed_at IS NOT NULL
        AND reviewed_at <= '$gen_at'
        AND (DATE_FORMAT(applied_at, '%Y-%m') = '$month_year' OR DATE_FORMAT(reviewed_at, '%Y-%m') = '$month_year')");

    $ids = [];
    $sum = 0.0;
    while ($c = mysqli_fetch_assoc($cand_q)) {
        $ids[] = (int)$c['id'];
        $sum += (float)$c['amount'];
    }
    $sum = round($sum, 2);

    if ($ids && abs($sum - $expected) < 0.01) {
        $ids_str = implode(',', $ids);
        mysqli_query($conn, "UPDATE claims SET payroll_id = {$p['id']} WHERE id IN ($ids_str)");
        $linked_payrolls++;
        $linked_claims += count($ids);
        echo "Linked " . count($ids) . " claim(s) totalling RM$sum to payroll #{$p['id']} (employee $emp_id, {$p['month_year']})\n";
    } else {
        $exceptions[] = [
            'payroll_id'  => $p['id'],
            'employee_id' => $emp_id,
            'month_year'  => $p['month_year'],
            'expected'    => $expected,
            'candidates'  => $ids,
            'candidate_sum' => $sum,
        ];
    }
}

echo "\nDone. Payroll rows reconciled: $linked_payrolls, claims linked: $linked_claims.\n";

if ($exceptions) {
    echo "\n" . count($exceptions) . " payroll row(s) could NOT be auto-reconciled — check these manually before running payroll again:\n";
    foreach ($exceptions as $e) {
        echo "  Payroll #{$e['payroll_id']} (employee {$e['employee_id']}, {$e['month_year']}): expected RM{$e['expected']}, candidate claims "
            . ($e['candidates'] ? implode(',', $e['candidates']) : 'none') . " sum to RM{$e['candidate_sum']}\n";
    }
    echo "\nThese claims are left UNLINKED (payroll_id still NULL) rather than guessed at, so they'll\n";
    echo "be swept into the next payroll run for that employee unless you manually link or reject them first.\n";
    echo "To manually link a claim once you've confirmed it was already paid: \n";
    echo "  UPDATE claims SET payroll_id = <payroll id> WHERE id = <claim id>;\n";
}
