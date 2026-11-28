
<!DOCTYPE html>
<html lang="en">
<?php
include("dbconn.php");
    if(isset($_POST['delmul'])){
        $checks = $_POST['checks'];

        foreach ($checks as $check){
            $result = mysqli_query($con, "DELETE FROM customer WHERE id = $check");
            
        }
        if($result){
            echo "<script>alert('Successfully Deleted Multiple...');
            window.location.href = 'index.php';</script>";
        }
    }


   

?>


<body >
    <center>
        
        
        <div class="container">
            <form action="" method="post">
                <table class="table table-borderless">
                    <tr>
                        <th>Laundry Shop</th>
                    </tr>
                    <tr>
                        <td>Name:</td>
                        <td>
                            <input type="text" name="name" class="form-control">
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
                            <input type="number" name="weight" required class="form-control">
                        </td>
                    </tr>
                    <tr>
                        <td>Unit Price:</td>
                        <td>
                            <input type="number" name="price" required class="form-control">
                        </td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td>
                            <input type="text" name="stat" required class="form-control">
                        </td>
                    </tr>
                    <tr>
                        <td >
                            <button name="submit" class="btn btn-outline-primary">Submit</button>
                        </td>
                        
                    </tr>
                </table>
            </form>
        </div>
        <div class="container">
            <form action="" method="post">
                <table class="table table-bordered">
                    <?php if($result = mysqli_num_rows($showall)<1):?>
                        <h1>No info found...</h1>


                    <?php else: ?>
                        <tr>
                            <th>
                                <button name="delmul">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                        <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                                    </svg>
                                </button>
                            </th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Weight</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        <?php while($row = mysqli_fetch_assoc($showall)): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="checks[]" value="<?php echo $row['id']?>">
                            </td>
                            <td><?php echo $row['customer_name']?></th>
                            <td><?php echo $row['service_type']?></td>
                            <td><?php echo $row['weight']?>kg</td>
                            <td>₱<?php echo $row['total_amount']?></td>
                            <td><?php echo $row['status']?></td>

                            <td>

                                <a href="update.php?id=<?php echo $row['id'] ?>" class="btn btn-outline-success">Update</a>
                                <a href="delete.php?id=<?php echo $row['id'] ?>" class="btn btn-outline-warning">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile ?>
                    <?php endif ?>
                </table>
            </form>
        </div>
    </center>
</body>
</html>