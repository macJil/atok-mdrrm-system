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

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</head>
