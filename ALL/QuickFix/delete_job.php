<?php
require_once "dbconn.php";
require_once "sit_functions.php";

$db = new db();
$conn = new JobOrders($db->conn());

if(isset($_POST['delete_job'])){
    echo $conn->delete_job($_GET['id']);
    exit();

}
$job = $conn->get_single_job($_GET['id']);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickFix</title>
</head>
<body>
    <div>
        <form action="" method="post">
            <h3>Are you sure you want to delete job?</h3>
            <table>
                <tr>
                    <th>Customer Name:</th>
                    <td><?php echo $job['customer_name']?></td>
                    
                    
                </tr>
                <tr>
                    <th>Contact Number:</th>
                    <td><?php echo $job['contact_number']?></td>
                </tr>
                
            </table>
            
            <a href="manage_job_orders.php">Cancel</a>
            <button name="delete_job">Yes</button>
        </form>
        
    </div>
</body>
</html>