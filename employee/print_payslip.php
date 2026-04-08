<?php
require_once '../includes/db.php';
$id = $_GET['id'];
$query = "SELECT p.*, e.name, e.employee_id, e.ic_number, e.department, e.position 
          FROM payroll p 
          JOIN employees e ON p.employee_id = e.id 
          WHERE p.id = $id";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payslip</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .payslip { max-width: 800px; margin: auto; border: 1px solid #ddd; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .company { font-size: 24px; font-weight: bold; }
        .title { font-size: 20px; margin-top: 10px; }
        .info { margin-bottom: 20px; }
        .info table { width: 100%; }
        .details { margin: 20px 0; }
        .details table { width: 100%; border-collapse: collapse; }
        .details th, .details td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .total { font-weight: bold; background: #f0f0f0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="payslip">
        <div class="header">
            <div class="company">Ipinfra Networks Sdn Bhd</div>
            <div class="title">PAYSLIP</div>
            <div>Month: <?php echo $row['month_year']; ?></div>
        </div>
        
        <div class="info">
            <table>
                <tr><td><strong>Employee ID:</strong></td><td><?php echo $row['employee_id']; ?></td>
                    <td><strong>Name:</strong></td><td><?php echo $row['name']; ?></td></tr>
                <tr><td><strong>IC Number:</strong></td><td><?php echo $row['ic_number']; ?></td>
                    <td><strong>Department:</strong></td><td><?php echo $row['department']; ?></td></tr>
                <tr><td><strong>Position:</strong></td><td><?php echo $row['position']; ?></td>
                    <td></td><td></td></tr>
            </table>
        </div>
        
        <div class="details">
            <table>
                <tr><th>Earnings</th><th>Amount (RM)</th><th>Deductions</th><th>Amount (RM)</th></tr>
                <tr>
                    <td>Basic Salary</td><td><?php echo number_format($row['basic_salary'], 2); ?></td>
                    <td>EPF (Employee)</td><td><?php echo number_format($row['epf_employee'], 2); ?></td>
                </tr>
                <tr>
                    <td>Allowances</td><td><?php echo number_format($row['allowances'], 2); ?></td>
                    <td>SOCSO</td><td><?php echo number_format($row['socso_employee'], 2); ?></td>
                </tr>
                <tr>
                    <td>Overtime Pay</td><td><?php echo number_format($row['overtime_pay'], 2); ?></td>
                    <td>EIS</td><td><?php echo number_format($row['eis_employee'], 2); ?></td>
                </tr>
                <tr>
                    <td></td><td></td>
                    <td>PCB</td><td><?php echo number_format($row['pcb'], 2); ?></td>
                </tr>
                <tr class="total">
                    <td>Total Earnings</td><td><?php echo number_format($row['basic_salary'] + $row['allowances'] + $row['overtime_pay'], 2); ?></td>
                    <td>Total Deductions</td><td><?php echo number_format($row['epf_employee'] + $row['socso_employee'] + $row['eis_employee'] + $row['pcb'], 2); ?></td>
                </tr>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: right;">
            <h3>Net Salary: RM <?php echo number_format($row['net_salary'], 2); ?></h3>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated document. No signature required.</p>
            <p>Ipinfra Networks Sdn Bhd - HR Department</p>
        </div>
    </div>
    <script>window.print();</script>
</body>
</html>