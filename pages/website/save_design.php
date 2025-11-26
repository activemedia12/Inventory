<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Create user-specific upload directory
    $uploadDir = "../../assets/uploads/save_design/{$user_id}/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $response = [];
    
    // Get design flags
    $hasFrontDesign = isset($_POST['has_front_design']) && $_POST['has_front_design'] === '1';
    $hasBackDesign = isset($_POST['has_back_design']) && $_POST['has_back_design'] === '1';
    
    // Handle front mockup image - ALWAYS CREATE
    if ($hasFrontDesign && isset($_POST['front_image']) && !empty($_POST['front_image'])) {
        // Save the designed front mockup
        $frontImageData = $_POST['front_image'];
        $frontImageData = str_replace('data:image/png;base64,', '', $frontImageData);
        $frontImageData = str_replace(' ', '+', $frontImageData);
        $frontImageData = base64_decode($frontImageData);
        
        $frontFilename = 'front_mockup_' . time() . '_' . uniqid() . '.png';
        $frontFilepath = $uploadDir . $frontFilename;
        
        if (file_put_contents($frontFilepath, $frontImageData)) {
            $response['front_mockup'] = "save_design/{$user_id}/{$frontFilename}";
        }
    } else {
        // Create a plain front mockup (just the template if available)
        $frontFilename = 'front_mockup_' . time() . '_' . uniqid() . '.png';
        $frontFilepath = $uploadDir . $frontFilename;
        
        // Copy the base template as plain mockup
        $baseImagePath = "../../assets/images/base/base-" . $_POST['product_id'] . ".jpg";
        if (file_exists($baseImagePath)) {
            if (copy($baseImagePath, $frontFilepath)) {
                $response['front_mockup'] = "save_design/{$user_id}/{$frontFilename}";
            }
        }
    }
    
    // Handle back mockup image - ALWAYS CREATE
    if ($hasBackDesign && isset($_POST['back_image']) && !empty($_POST['back_image'])) {
        // Save the designed back mockup
        $backImageData = $_POST['back_image'];
        $backImageData = str_replace('data:image/png;base64,', '', $backImageData);
        $backImageData = str_replace(' ', '+', $backImageData);
        $backImageData = base64_decode($backImageData);
        
        $backFilename = 'back_mockup_' . time() . '_' . uniqid() . '.png';
        $backFilepath = $uploadDir . $backFilename;
        
        if (file_put_contents($backFilepath, $backImageData)) {
            $response['back_mockup'] = "save_design/{$user_id}/{$backFilename}";
        }
    } else {
        // Create a plain back mockup ONLY IF BACK TEMPLATE EXISTS
    $backImagePath = "../../assets/images/base/base-" . $_POST['product_id'] . "-1.jpg";
    if (file_exists($backImagePath)) {
        $backFilename = 'back_mockup_' . time() . '_' . uniqid() . '.png';
        $backFilepath = $uploadDir . $backFilename;
        
        if (copy($backImagePath, $backFilepath)) {
            $response['back_mockup'] = "save_design/{$user_id}/{$backFilename}";
        }
    }
    }
    
    // Handle uploaded front design file
    if (isset($_FILES['front_design_file']) && $_FILES['front_design_file']['error'] === 0) {
        $uploadedFile = $_FILES['front_design_file'];
        
        // Get file extension
        $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        $filename = 'front_design_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($uploadedFile['tmp_name'], $filepath)) {
            $response['front_uploaded_file'] = "save_design/{$user_id}/{$filename}";
        }
    }
    
    // Handle uploaded back design file
    if (isset($_FILES['back_design_file']) && $_FILES['back_design_file']['error'] === 0) {
        $uploadedFile = $_FILES['back_design_file'];
        
        // Get file extension
        $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        $filename = 'back_design_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($uploadedFile['tmp_name'], $filepath)) {
            $response['back_uploaded_file'] = "save_design/{$user_id}/{$filename}";
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>