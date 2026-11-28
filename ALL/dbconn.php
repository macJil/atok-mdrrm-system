<?php
    $con = mysqli_connect("localhost","root","","laundry_shop");

    if(isset($_POST['submit'])){
        $name = $_POST['name'];
        $type = $_POST['type'];
        $weight = $_POST['weight'];
        $price = $_POST['price'];
        $stat = $_POST['stat'];

        $insert = mysqli_query($con,"INSERT INTO customer (customer_name, service_type, weight, total_amount, status)
        VALUES ('$name', '$type', '$weight', '$price', '$stat')");

        if($insert){
            echo "<script>alert('Successfully Added...');</script>";
        }
    }

    $showall = mysqli_query($con, "SELECT * FROM customer");
?>