
<!DOCTYPE html>
<html lang="en">
<?php 
include("dbconn.php");
    $id = $_GET['id'];
    

    if(isset($_POST['delete'])){
        $del = mysqli_query($con, "DELETE FROM customer WHERE id = '$id'");
        if($del){
            echo "<script>alert('Successfully Deleted...');
            window.location.href = 'index.php';</script>";
        }
    }
?>
<body>
    <div class="container">
        <form action="" method="post">
            <h1 style="margin-top: 10px;">Are you Sure?</h1>
            <button name="delete" class="btn btn-outline-danger">Delete</button>
            <a href="index.php" class="btn btn-outline-warning">Cancel</a>
        </form>
    </div>
    
    
</body>
</html>