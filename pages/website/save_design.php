<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Create user-specific upload directory
    $uploadDir = "../assets/uploads/save_design/{$user_id}/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $response = [];
    
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
    
    // Handle single uploaded design file (for single upload type)
    if (isset($_FILES['design_file']) && $_FILES['design_file']['error'] === 0) {
        $uploadedFile = $_FILES['design_file'];
        
        // Get file extension
        $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        $filename = 'design_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($uploadedFile['tmp_name'], $filepath)) {
            $response['uploaded_file'] = "save_design/{$user_id}/{$filename}";
        }
    }
    
    // Handle front mockup image (existing functionality)
    if (isset($_POST['front_image']) && !empty($_POST['front_image'])) {
        $frontImageData = $_POST['front_image'];
        $frontImageData = str_replace('data:image/png;base64,', '', $frontImageData);
        $frontImageData = str_replace(' ', '+', $frontImageData);
        $frontImageData = base64_decode($frontImageData);
        
        $frontFilename = 'front_mockup_' . time() . '_' . uniqid() . '.png';
        $frontFilepath = $uploadDir . $frontFilename;
        
        if (file_put_contents($frontFilepath, $frontImageData)) {
            $response['front_mockup'] = "save_design/{$user_id}/{$frontFilename}";
        }
    }
    
    // Handle back mockup image (existing functionality)
    if (isset($_POST['back_image']) && !empty($_POST['back_image'])) {
        $backImageData = $_POST['back_image'];
        $backImageData = str_replace('data:image/png;base64,', '', $backImageData);
        $backImageData = str_replace(' ', '+', $backImageData);
        $backImageData = base64_decode($backImageData);
        
        $backFilename = 'back_mockup_' . time() . '_' . uniqid() . '.png';
        $backFilepath = $uploadDir . $backFilename;
        
        if (file_put_contents($backFilepath, $backImageData)) {
            $response['back_mockup'] = "save_design/{$user_id}/{$backFilename}";
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>