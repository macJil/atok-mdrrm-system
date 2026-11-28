<?php 
require_once "dbconn.php";
require_once "sit_functions.php";

$db = new db();
$conn = new JobOrders($db->conn());

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickFix</title>
</head>
<body>
<header>
    <h1>QuickFix</h1>
    <span>Computer Repair Job Order Management System</span><br>
    <a href="index.php">Dashboard</a>
    <a href="add_job.php">Add Job Orders</a>
    <a href="manage_job_orders.php">Manage Job Orders</a>
</header>

    <h3>Job Order Dashboard</h3>
    <div class="container">
      <table border="1" class="table table-borderless">
        <tr>
            <th>Total Job Orders</th>
            <th>Received</th>
            <th>In Repair</th>
            <th>Ready for Pickup</th>
        </tr>
        <tr>
            <td><?php echo count($conn->show_jobs())?></td>
            <td><?php echo count($conn->total_job_received())?></td>
            <td><?php echo count($conn->total_job_in_repair())?></td>
            <td><?php echo count($conn->total_job_ready())?></td>
        </tr>
      </table>
    </div>
    <h3>Recent Job Orders</h3>
    <div>
    <?php if(count($conn->show_jobs())<1):?>
        <h1>No Data Found...</h1>
    <?php else: ?>
        <table border="1" class="table table-striped" >
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Device</th>
                <th>Status</th>
                <th>Estimated Cost</th>
            </tr>
        
            <?php foreach($conn->show_jobs() as $job):?>
                <tr>
                    <td><?php echo $job['id']?></td>
                    <td><?php echo $job['customer_name']?></td>
                    <td><?php echo $job['device_model']?></td>
                    <td><?php echo $job['service_status']?></td>
                    <td><?php echo $job['estimated_cost']?></td>
                </tr>
            <?php endforeach?>
        </table  >
    <?php endif ?>
    </div>
</body>
</html>