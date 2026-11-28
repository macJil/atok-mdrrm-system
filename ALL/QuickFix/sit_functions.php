<?php
class JobOrders{
    private const SUCCESS = "<script>alert('Successful!'); location.href='index.php';</script>";
    private const UNSUCCESS = "<script>alert('Unsuccessful!'); location.href='index.php';</script>";
    function __construct(private PDO $pdo){}

    private function checker($result){
        return $result ? self::SUCCESS : self::UNSUCCESS;
    }
    public function add_job(string $customer_name,string $contact_number,string $device_type, string $device_model,string $issue_description, float $estimated_cost){
        try{
            return $this->checker($this->pdo->prepare("INSERT INTO job_orders(customer_name,contact_number,device_type,device_model,issue_description,estimated_cost)VALUES(?,?,?,?,?,?)")->execute([$customer_name,$contact_number,$device_type,$device_model,$issue_description,$estimated_cost]));
        }catch (PDOException $e){
            die("ERROR!".$e->getMessage());
        }
    }
    public function show_jobs(){
        try{
            return $this->pdo->query("SELECT * FROM job_orders")->fetchAll();
        }catch (PDOException $e){
            die("ERROR!".$e->getMessage());
        }
    }
    public function delete_job($id){
        try{
            return $this->checker($this->pdo->prepare("DELETE FROM job_orders WHERE id = :id")->execute(["id"=>$id]));
        }catch(PDOException $e){
            die("ERROR!".$e->getMessage());
        }
    }
    public function edit_job(int $id, string $customer_name,string $contact_number,string $device_type, string $device_model,string $issue_description, float $estimated_cost,string $service_status){
        try{
            return $this->checker($this->pdo->prepare("UPDATE job_orders SET customer_name = :customer_name, contact_number = :contact_number, service_status = :service_status,device_type = :device_type,  device_model = :device_model, issue_description = :issue_description,  estimated_cost = :estimated_cost WHERE id = :id")->execute([
            "customer_name"=> $customer_name,
            "contact_number"=> $contact_number,
            "device_type"=> $device_type,
            "device_model"=> $device_model,
            "issue_description"=> $issue_description,
            "estimated_cost"=> $estimated_cost,
            "id"=>$id,
            "service_status"=>$service_status
            ]));
        }catch(PDOException $e){
            die("ERROR!".$e->getMessage());
        }

    }
    public function get_single_job(int $id){
        try{
            $stmt = $this->pdo->prepare("SELECT * FROM job_orders WHERE id = :id");
            $stmt->execute(["id"=>$id]);
            return $stmt->fetch();
        } catch(PDOException $e){
            die("ERROR!".$e->getMessage());
        }
    }
    public function total_job_received(){
        return $this->pdo->query("SELECT * FROM job_orders WHERE service_status = 'Received' ")->fetchAll();
    }
    public function total_job_in_repair(){
        return $this->pdo->query("SELECT * FROM job_orders WHERE service_status = 'In Repair' ")->fetchAll();
    }
    public function total_job_ready(){
        return $this->pdo->query("SELECT * FROM job_orders WHERE service_status = 'Ready For Pick Up' ")->fetchAll();
    }

}


?>