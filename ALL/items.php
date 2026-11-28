<?php
	include "dbconn.php";


	if(isset($_POST['delete'])){
		$checkIDs = $_POST['checkIDs']; # one variable with many data

		foreach ($checkIDs as $id) {
			$delete = "DELETE FROM items WHERE ID = '$id' "; #in php this is just pure text/string
			$result = mysqli_query($conn, $delete);
		}

		header("location: items.php");
		exit();
	}
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
	<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
</head>
<body>
	<div class = "container">
		<nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
		  <div class="container-fluid">
		    <a class="navbar-brand" href="index.php">SIT-CAFE</a>
		    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
		      <span class="navbar-toggler-icon"></span>
		    </button>
		    <div class="collapse navbar-collapse" id="navbarNav">
		      <ul class="navbar-nav">
		        <li class="nav-item">
		          <a class="nav-link" aria-current="page" href="index.php">Home</a>
		        </li>
		        <li class="nav-item">
		          <a class="nav-link active" href="items.php">Items</a>
		        </li>
		      </ul>
		    </div>
		  </div>
		</nav>

		<form method = "post">
			<table class="table table-bordered table-striped" style="margin-top: 20px;">
				<?php
					$fetch = "SELECT * FROM items";
					$result = mysqli_query($conn,$fetch);

					if(mysqli_num_rows($result) < 1){
						echo "<br><br><center><h1>No Items Found...</h1></center>";
					}
					else{
						echo '
							<tr>
								<th>
									<button name = "delete">						
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16">
										  <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/>
										</svg>
										
									</button>
								</th>
								<th>Item Name</th>
								<th>Category</th>
								<th>Quantity</th>
								<th>Unit</th>
								<th>Supplier</th>
								<th>Date Added</th>
								<th>Actions</th>
							</tr>
						';


						while ($row = mysqli_fetch_assoc($result)) {
				?>
							<tr>
								<td>
									<input style="height:30px; width:30px;" type="checkbox" name="checkIDs[]" value = "<?php echo $row['ID']; ?>">
								</td>
								<td><?php echo $row['Item_Name']; ?></td>
								<td><?php echo $row['Category']; ?></td>
								<td><?php echo $row['Quantity']; ?></td>
								<td><?php echo $row['Unit']; ?></td>
								<td><?php echo $row['Supplier']; ?></td>
								<td><?php echo $row['Date_Added']; ?></td>
								<td>
									<a href = "update.php?url_id=<?php echo $row['ID']; ?>" class="btn btn-info">Edit</a>

									<a href = "delete.php?url_id=<?php echo $row['ID']; ?>" class="btn btn-danger">Delete</a>
								</td>
							</tr>
				<?php			
						}
					}
				?>
			</table>
		</form>

	</div>

	<script src = "js/bootstrap.bundle.min.js"></script>
</body>
</html>