<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// **تغيير هذه البيانات ببيانات الاتصال بقاعدة البيانات الخاصة بك**
$servername = "sql100.infinityfree.com";
$username = "if0_39688451";
$password = "KYjQjJTmVBe";
$dbname = "if0_39688451_almahmoud";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllItems':
        if ($method === 'GET') {
            $sql = "SELECT id, name, price FROM items";
            $result = $conn->query($sql);
            $items = [];
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $items[] = $row;
                }
            }
            echo json_encode(["success" => true, "items" => $items]);
        }
        break;

    case 'addItem':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            $name = $data['name'] ?? null;
            $price = $data['price'] ?? null;

            if (!$name || !is_numeric($price)) {
                echo json_encode(["success" => false, "message" => "Invalid input."]);
                break;
            }

            $sql = "INSERT INTO items (name, price) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sd", $name, $price);

            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Item added successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
            }
            $stmt->close();
        }
        break;

    case 'updateItem':
        if ($method === 'PUT') {
            $data = json_decode(file_get_contents("php://input"), true);
            $id = $_GET['id'] ?? null;
            $price = $data['price'] ?? null;

            if (!$id || !is_numeric($price)) {
                echo json_encode(["success" => false, "message" => "Invalid input."]);
                break;
            }

            $sql = "UPDATE items SET price = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $price, $id);

            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Item updated successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
            }
            $stmt->close();
        }
        break;
        
    case 'deleteItem':
        if ($method === 'DELETE') {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode(["success" => false, "message" => "Item ID is required."]);
                break;
            }
            
            $sql = "DELETE FROM items WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Item deleted successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
            }
            $stmt->close();
        }
        break;

    case 'clearAllItems':
        if ($method === 'DELETE') {
            $sql = "DELETE FROM items";
            if ($conn->query($sql) === TRUE) {
                echo json_encode(["success" => true, "message" => "All items cleared successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Error: " . $conn->error]);
            }
        }
        break;

    default:
        echo json_encode(["success" => false, "message" => "Invalid action or method."]);
        break;
}

$conn->close();
?>
