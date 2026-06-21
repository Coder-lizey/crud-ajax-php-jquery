<?php
// Headers for CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database connection include kiya
include_once 'db_connect.php'; 

$method = $_SERVER['REQUEST_METHOD'];

// 1. GET REQUEST (Fetch All Products OR Single Product for Edit Page)

if ($method === 'GET') {
    try {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $query = "SELECT * FROM products WHERE id = :id LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($product) {
                $product['sizes'] = json_decode($product['sizes'] ?? '[]');
                $product['images'] = json_decode($product['images'] ?? '[]');
                echo json_encode(array("success" => true, "data" => $product));
            } else {
                echo json_encode(array("success" => false, "message" => "Product not found."));
            }
        } 
        else {
            $query = "SELECT * FROM products ORDER BY id DESC";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($products as &$product) {
                $product['sizes'] = json_decode($product['sizes'] ?? '[]');
                $product['images'] = json_decode($product['images'] ?? '[]');
            }
            echo json_encode(array("success" => true, "data" => $products));
        }
    } catch (PDOException $e) {
        echo json_encode(array("success" => false, "message" => "Error: " . $e->getMessage()));
    }
    exit();
}

// =========================================================================
// 2. POST REQUEST (Handle Add New Product AND Update Product)
// =========================================================================
if ($method === 'POST') {
    
    // Inputs collect karein
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $sizes = isset($_POST['sizes']) ? $_POST['sizes'] : '[]'; 
    $colorName = isset($_POST['colorName']) ? trim($_POST['colorName']) : '';
    $colorHex = isset($_POST['colorHex']) ? trim($_POST['colorHex']) : '';
    
    // === NEW FIELDS INCLUDED ===
    $sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;

    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // -----------------------------------------------------------------
    // CASE A: UPDATE PRODUCT
    // -----------------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['id'])) {
        $id = $_POST['id'];

        if (empty($id) || empty($name) || empty($price) || empty($sku)) {
            echo json_encode(array("success" => false, "message" => "Required fields, SKU, or Product ID missing."));
            exit();
        }

        try {
            // Secure check for files array structure
            if (isset($_FILES['images']['name']) && is_array($_FILES['images']['name']) && !empty($_FILES['images']['name'][0])) {
                $uploaded_images = array();
                $file_count = count($_FILES['images']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    $file_name = $_FILES['images']['name'][$i];
                    $file_tmp = $_FILES['images']['tmp_name'][$i];
                    if ($_FILES['images']['error'][$i] === 0) {
                        $unique_file_name = time() . '-' . basename($file_name);
                        $target_file = $upload_dir . $unique_file_name;
                        if (move_uploaded_file($file_tmp, $target_file)) {
                            $uploaded_images[] = "api/" . $target_file;
                        }
                    }
                }
                $images_json = json_encode($uploaded_images);
                
                // SKU aur Stock query me add kiya (With Images)
                $query = "UPDATE products SET name=:name, brand=:brand, description=:description, category=:category, 
                          price=:price, sizes=:sizes, colorName=:colorName, colorHex=:colorHex, sku=:sku, stock=:stock, images=:images WHERE id=:id";
            } else {
                // SKU aur Stock query me add kiya (Without Images)
                $query = "UPDATE products SET name=:name, brand=:brand, description=:description, category=:category, 
                          price=:price, sizes=:sizes, colorName=:colorName, colorHex=:colorHex, sku=:sku, stock=:stock WHERE id=:id";
            }

            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':brand', $brand);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':sizes', $sizes);
            $stmt->bindParam(':colorName', $colorName);
            $stmt->bindParam(':colorHex', $colorHex);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':stock', $stock);
            $stmt->bindParam(':id', $id);
            if (isset($images_json)) {
                $stmt->bindParam(':images', $images_json);
            }

            if ($stmt->execute()) {
                echo json_encode(array("success" => true, "message" => "Product updated successfully!"));
            } else {
                echo json_encode(array("success" => false, "message" => "Failed to update product."));
            }

        } catch (PDOException $e) {
            echo json_encode(array("success" => false, "message" => "Database Error: " . $e->getMessage()));
        }
        exit();
    } 
    
    // -----------------------------------------------------------------
    // CASE B: ADD NEW PRODUCT
    // -----------------------------------------------------------------
    else {
        if (empty($name) || empty($brand) || empty($price) || empty($sku)) {
            echo json_encode(array("success" => false, "message" => "Required text fields (Name, Brand, Price, SKU) are missing."));
            exit();
        }

        $uploaded_images = array();
        if (isset($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
            $file_count = count($_FILES['images']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                $file_name = $_FILES['images']['name'][$i];
                $file_tmp = $_FILES['images']['tmp_name'][$i];
                if ($_FILES['images']['error'][$i] === 0) {
                    $unique_file_name = time() . '-' . basename($file_name);
                    $target_file = $upload_dir . $unique_file_name;
                    if (move_uploaded_file($file_tmp, $target_file)) {
                        $uploaded_images[] = "api/" . $target_file;
                    }
                }
            }
        }
        $images_json = json_encode($uploaded_images);

        try {
            // SQL Columns aur values me sku aur stock ko add kiya
            $query = "INSERT INTO products (name, brand, description, category, price, sizes, colorName, colorHex, sku, stock, images) 
                      VALUES (:name, :brand, :description, :category, :price, :sizes, :colorName, :colorHex, :sku, :stock, :images)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':brand', $brand);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':sizes', $sizes);
            $stmt->bindParam(':colorName', $colorName);
            $stmt->bindParam(':colorHex', $colorHex);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':stock', $stock);
            $stmt->bindParam(':images', $images_json);

            if ($stmt->execute()) {
                echo json_encode(array("success" => true, "message" => "Product published successfully!"));
            } else {
                echo json_encode(array("success" => false, "message" => "Unable to save product."));
            }
        } catch (PDOException $e) {
            echo json_encode(array("success" => false, "message" => "SQL Error: " . $e->getMessage()));
        }
        exit();
    }
}

// =========================================================================
// 3. DELETE REQUEST (Delete Product from Inventory)
// =========================================================================
if ($method === 'DELETE') {
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        try {
            $query = "DELETE FROM products WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                echo json_encode(array("success" => true, "message" => "Product deleted successfully!"));
            } else {
                echo json_encode(array("success" => false, "message" => "Delete failed."));
            }
        } catch (PDOException $e) {
            echo json_encode(array("success" => false, "message" => "Error: " . $e->getMessage()));
        }
    }
    exit();
}
?>