<?php
require_once "dbconn.php";
require_once "sit_functions.php";

$db = new db();
$conn = new JobOrders($db->conn());
if(isset($_POST['save_job'])){
    try{
        echo $conn->add_job(
            trim(htmlspecialchars($_POST['customer_name'])),
            trim(htmlspecialchars($_POST['contact_number'])),
            trim(htmlspecialchars($_POST['device_type'])),
            trim(htmlspecialchars($_POST['device_model'])),
            trim(htmlspecialchars($_POST['issue_description'])),
            trim(htmlspecialchars($_POST['estimated_cost'])),
        );
        exit();
    }catch(PDOException $e){
        die("Error saving job!".$e->getMessage());
    }
}

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
<h3>Add New Job Order</h3>
    <div>
        <form action="" method="post">
            <table>
                <tr>
                    <label >Customer Name</label><br>
                    <input type="text" name="customer_name" required><br>
                </tr>
                <tr>
                    <label >Contact Number</label><br>
                    <input type="text" name="contact_number" required><br>
                </tr>
                <tr>
                    <label >Device type</label><br>
                    <select name="device_type" id="" required>
                        <option value="" default selected></option>
                        <option value="Laptop">Laptop</option>
                        <option value="Mobile">Mobile</option>
                        <option value="TV">TV</option>
                    </select><br>
                </tr>
                <tr>
                    <label >Device Model</label><br>
                    <input type="text" name="device_model" required><br>
                </tr>
                <tr>
                    <label >Issue description</label><br>
                    <input type="text" name="issue_description" required><br>
                </tr>
                
                <tr>
                    <label >Estimated Cost</label><br>
                    <input type="number" name="estimated_cost" required><br>
                </tr>
                <br>
                <tr>
                    <button name="save_job">Save Job Order</button>
                </tr>
               
            </table>
        </form>
    </div>
</body>
</html>