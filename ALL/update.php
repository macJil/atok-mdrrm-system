
<!DOCTYPE html>
<html lang="en">
<?php 
include("dbconn.php");
    $url_id = $_GET['id'];
    $selec = mysqli_query($con, "SELECT * FROM customer WHERE id = '$url_id'");
    //update
    if(isset($_POST['update'])){
        $name = $_POST['name'];
        $type = $_POST['type'];
        $weight = $_POST['weight'];
        $price = $_POST['price'];
        $stat = $_POST['stat'];
        $upt = mysqli_query($con, "UPDATE customer SET customer_name='$name',service_type='$type',weight='$weight',total_amount='$price',status='$stat' WHERE id = $url_id");
        if($upt){
            echo "<script>alert('Successfully Updated...');
            window.location.href = 'index.php';</script>";
        }
    }

?>
<body>
    <div class="container">
        <form action="" method="post">
            <?php while($row = mysqli_fetch_assoc($selec)):?>
                <table class="table table-borderless">
                    <tr>
                        <th>Laundry Shop</th>
                    </tr>
                    <tr>
                        <td>Name:</td>
                        <td>
                            <input type="text" name="name" value="<?php echo $row['customer_name']?>" class="form-control">
                        </td>
                    </tr>
                    <tr>
                        <td>Service:</td>
                        <td>
                            <select name="type" id="" class="form-control">
                                <option value="" selected disabled>Select Category:</option>
                                <option value="Self‑Service">Self‑Service</option>
                                <option value="Full‑Service">Full‑Service</option>
                                <option value="Dry Cleaning">Dry Cleaning</option>
                                <option value="Pick‑Up & Delivery">Pick‑Up & Delivery</option>
                                <option value="Specialty Garment Cleaning">Specialty Garment Cleaning</option>
                                <option value="Eco‑Friendly Laundry">Eco‑Friendly Laundry</option>
                            </select>
                            
                        </td>
                    </tr>
                    <tr>
                        <td>Weight(kg):</td>
                        <td>
                            <input type="number" name="weight" required value="<?php echo $row['weight']?>" class="form-control">
                        </td>
                    </tr>
                    <tr>
                        <td>Unit Price:</td>
                        <td>
                            <input type="number" name="price" required value="<?php echo $row['total_amount']?>" class="form-control">
                        </td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td>
                            <input type="text" name="stat" required value="<?php echo $row['status']?>" class="form-control">
                        </td>
                    </tr>
                    <tr>
                        <td >
                            
                        </td>
                        <td>
                        </td>
                        <td>
                            <button name="update" class="btn btn-outline-primary">Update</button>
                            <a href="index.php" class="btn btn-outline-danger">Cancel</a>
                        </td>
                    </tr>
                </table>
            <?php endwhile?>
        </form>
    </div>
</body>
</html>
