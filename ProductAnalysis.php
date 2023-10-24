<?php
// 1. เชื่อมต่อกับฐานข้อมูล MySQL
$db_host = 'localhost';
$db_user = 'root';
$db_password = 'root';
$db_db = 'test';

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_db", $db_user, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// 2. คำสั่ง SQL เพื่อดึงวันที่เริ่มต้นและวันที่สิ้นสุด
if (isset($_POST['startDate']) && isset($_POST['endDate'])) {
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
} else {
    $sql_dates = "SELECT MIN(Create_at) AS min_date, MAX(Create_at) AS max_date FROM FormSurvey";
    $result_dates = $conn->query($sql_dates);
    $row_dates = $result_dates->fetch();

    $startDate = $row_dates['min_date'];
    $endDate = date('Y-m-d'); // ใช้วันปัจจุบัน
}

$selectedTopProduct = isset($_POST['selectedTopProduct']) ? $_POST['selectedTopProduct'] : '5';

$sql_product = "SELECT ProductName AS 'Product Name', COUNT(cp.ProductName) AS 'Count'
FROM CustomerPreferencesProduct AS cp
JOIN Customer as c ON cp.CustomerID = c.CustomerID
JOIN FormSurvey as fs ON cp.CustomerID = fs.CustomerID
WHERE fs.Create_at BETWEEN :startDate AND :endDate
GROUP BY ProductName
ORDER BY COUNT(ProductName) DESC
        LIMIT :selectedTopProduct"; // เปลี่ยน LIMIT เป็น :selectedTopProduct

$stmt = $conn->prepare($sql_product);
$stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$stmt->bindParam(':selectedTopProduct', $selectedTopProduct, PDO::PARAM_INT); // รับค่าจาก $_POST
$stmt->execute();

// สร้างอาเรย์เพื่อเก็บข้อมูลกราฟ
$productData = [];
while ($row = $stmt->fetch()) {
    $productData[] = [
        'Product Name' => $row["Product Name"],
        'Count' => $row["Count"]
    ];
}

// 3. คำสั่ง SQL เพื่อดึงข้อมูลสำหรับกราฟอีกแบบ
$sql_review = "SELECT ProductName, COUNT(ProductName) as 'CountProduct'
FROM `CustomerFeedbackProduct` as cfp
JOIN Customer as c ON cfp.CustomerID = c.CustomerID
JOIN FormSurvey as fs ON cfp.CustomerID = fs.CustomerID
WHERE fs.Create_at BETWEEN :startDate AND :endDate
GROUP BY ProductName
ORDER BY COUNT(ProductName) DESC
LIMIT :selectedTopProduct"; // เปลี่ยน LIMIT เป็น :selectedTopProduct

$stmt_review = $conn->prepare($sql_review);
$stmt_review->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$stmt_review->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$stmt_review->bindParam(':selectedTopProduct', $selectedTopProduct, PDO::PARAM_INT); // รับค่าจาก $_POST
$stmt_review->execute();

// สร้างอาเรย์เพื่อเก็บข้อมูลกราฟสำหรับรีวิวสินค้า
$reviewData = [];
while ($row_review = $stmt_review->fetch()) {
    $reviewData[] = [
        'ProductName' => $row_review["ProductName"],
        'CountProduct' => $row_review["CountProduct"]
    ];
}

//คำสั่ง SQL เพื่อดึงข้อมูลเกี่ยวกับอีเวนต์
$sql_event = "SELECT e.EventName AS 'Event Name', COUNT(cpe.EventName) AS 'EventCount'
FROM CustomerPreferencesProduct AS cpp
INNER JOIN CustomerPreferencesEvent AS cpe ON cpp.CustomerID = cpe.CustomerID
INNER JOIN Events AS e ON cpe.EventName = e.EventName
INNER JOIN Products AS p ON cpp.ProductName = p.ProductName
INNER JOIN FormSurvey AS fs ON cpp.CustomerID = fs.CustomerID
WHERE p.ProductName IN (
    SELECT TopProducts.ProductName
    FROM (
        SELECT pr.ProductName, COUNT(*) AS Count
        FROM CustomerPreferencesProduct AS cp
        JOIN Products AS pr ON cp.ProductName = pr.ProductName
        JOIN Customer AS c ON cp.CustomerID = c.CustomerID
        JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
        WHERE fs.Create_at BETWEEN :startDate AND :endDate
        GROUP BY pr.ProductName
        ORDER BY Count DESC
        LIMIT :selectedTopProduct
    ) AS TopProducts
)
GROUP BY e.EventName
ORDER BY e.EventName, EventCount DESC;";

$stmt_event = $conn->prepare($sql_event);
$stmt_event->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$stmt_event->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$stmt_event->bindParam(':selectedTopProduct', $selectedTopProduct, PDO::PARAM_INT);
$stmt_event->execute();

// สร้างอาเรย์เพื่อเก็บข้อมูลของอีเวนต์
$eventData = [];
while ($row_event = $stmt_event->fetch()) {
    $eventData[] = [
        'Event Name' => $row_event["Event Name"],
        'Event Count' => $row_event["EventCount"]
    ];
}

//คำสั่ง SQL เพื่อดึงข้อมูลเกี่ยวกับช่องสื่อ
$sql_channel = "SELECT c.ChannelName AS 'Channel Name', COUNT(c.ChannelName) AS 'ChannelCount'
FROM CustomerFeedbackProduct AS cfp
INNER JOIN Channel AS c ON cfp.ChannelName = c.ChannelName
WHERE cfp.ProductName IN (
    SELECT TopProducts.ProductName
    FROM (
        SELECT pr.ProductName, COUNT(*) AS Count
        FROM CustomerPreferencesProduct AS cp
        JOIN Products AS pr ON cp.ProductName = pr.ProductName
        JOIN Customer AS c ON cp.CustomerID = c.CustomerID
        JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
        WHERE fs.Create_at BETWEEN :startDate AND :endDate
        GROUP BY pr.ProductName
        ORDER BY Count DESC
        LIMIT :selectedTopProduct
    ) AS TopProducts
)
GROUP BY c.ChannelName
ORDER BY c.ChannelName, ChannelCount DESC;";

$stmt_channel = $conn->prepare($sql_channel);
$stmt_channel->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$stmt_channel->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$stmt_channel->bindParam(':selectedTopProduct', $selectedTopProduct, PDO::PARAM_INT);
$stmt_channel->execute();

// สร้างอาเรย์เพื่อเก็บข้อมูลของช่องสื่อ
$channelData = [];
while ($row_channel = $stmt_channel->fetch()) {
    $channelData[] = [
        'Channel Name' => $row_channel["Channel Name"],
        'Channel Count' => $row_channel["ChannelCount"]
    ];
}

// 5. คำสั่ง SQL เพื่อดึงข้อมูลจำนวนผู้ตอบแบบสำรวจตามเพศ
$sql_gender = "SELECT c.Gender, COUNT(*) AS GenderCount
FROM CustomerPreferencesProduct AS cp
JOIN Customer AS c ON cp.CustomerID = c.CustomerID
JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
WHERE ProductName IN (
    SELECT TopProducts.ProductName
    FROM (
        SELECT pr.ProductName, COUNT(*) AS Count
        FROM CustomerPreferencesProduct AS cp
        JOIN Products AS pr ON cp.ProductName = pr.ProductName
        JOIN Customer AS c ON cp.CustomerID = c.CustomerID
        JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
        WHERE fs.Create_at BETWEEN :startDate AND :endDate
        GROUP BY pr.ProductName
        ORDER BY Count DESC
        LIMIT :selectedTopProduct
    ) AS TopProducts
)
GROUP BY c.Gender;";

$stmt_gender = $conn->prepare($sql_gender);
$stmt_gender->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$stmt_gender->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$stmt_gender->bindParam(':selectedTopProduct', $selectedTopProduct, PDO::PARAM_INT);
$stmt_gender->execute();

// สร้างอาเรย์เพื่อเก็บข้อมูลเพศ
$genderData = [];
while ($row_gender = $stmt_gender->fetch()) {
    $genderData[] = [
        'Gender' => $row_gender["Gender"],
        'Gender Count' => $row_gender["GenderCount"]
    ];
}

// 6. คำสั่ง SQL เพื่อดึงข้อมูลจำนวนผู้ตอบแบบสำรวจตามกลุ่มอายุ
$sql_age = "SELECT c.Age, COUNT(*) AS AgeCount
FROM CustomerPreferencesProduct AS cp
JOIN Customer AS c ON cp.CustomerID = c.CustomerID
JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
WHERE ProductName IN (
    SELECT TopProducts.ProductName
    FROM (
        SELECT pr.ProductName, COUNT(*) AS Count
        FROM CustomerPreferencesProduct AS cp
        JOIN Products AS pr ON cp.ProductName = pr.ProductName
        JOIN Customer AS c ON cp.CustomerID = c.CustomerID
        JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
        WHERE fs.Create_at BETWEEN :startDate AND :endDate
        GROUP BY pr.ProductName
        ORDER BY Count DESC
        LIMIT :selectedTopProduct
    ) AS TopProducts
)
GROUP BY c.Age;";

$stmt_age = $conn->prepare($sql_age);
$stmt_age->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$stmt_age->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$stmt_age->bindParam(':selectedTopProduct', $selectedTopProduct, PDO::PARAM_INT);
$stmt_age->execute();

// สร้างอาเรย์เพื่อเก็บข้อมูลกลุ่มอายุ
$ageData = [];
while ($row_age = $stmt_age->fetch()) {
    $ageData[] = [
        'Age' => $row_age["Age"],
        'Age Count' => $row_age["AgeCount"]
    ];
}

// 7. คำสั่ง SQL เพื่อดึงข้อมูลจำนวนผู้ตอบแบบสำรวจตามสัญชาติ
$sql_nationality = "SELECT c.Nationality, COUNT(*) AS NationalityCount
FROM CustomerPreferencesProduct AS cp
JOIN Customer AS c ON cp.CustomerID = c.CustomerID
JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
WHERE ProductName IN (
    SELECT TopProducts.ProductName
    FROM (
        SELECT pr.ProductName, COUNT(*) AS Count
        FROM CustomerPreferencesProduct AS cp
        JOIN Products AS pr ON cp.ProductName = pr.ProductName
        JOIN Customer AS c ON cp.CustomerID = c.CustomerID
        JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
        WHERE fs.Create_at BETWEEN :startDate AND :endDate
        GROUP BY pr.ProductName
        ORDER BY Count DESC
        LIMIT :selectedTopProduct
    ) AS TopProducts
)
GROUP BY c.Nationality;";

$stmt_nationality = $conn->prepare($sql_nationality);
$stmt_nationality->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$stmt_nationality->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$stmt_nationality->bindParam(':selectedTopProduct', $selectedTopProduct, PDO::PARAM_INT);
$stmt_nationality->execute();

// สร้างอาเรย์เพื่อเก็บข้อมูลสัญชาติ
$nationalityData = [];
while ($row_nationality = $stmt_nationality->fetch()) {
    $nationalityData[] = [
        'Nationality' => $row_nationality["Nationality"],
        'Nationality Count' => $row_nationality["NationalityCount"]
    ];
}

// 8. คำสั่ง SQL เพื่อดึงความคิดเห็นสินค้า
$sql_comment_product = "SELECT p.ProductName, cfp.Comment
FROM Products AS p
INNER JOIN (
    SELECT cp.ProductName
    FROM CustomerPreferencesProduct AS cp
    JOIN Products AS pr ON cp.ProductName = pr.ProductName
    JOIN FormSurvey AS fs ON cp.CustomerID = fs.CustomerID
    WHERE fs.Create_at BETWEEN :startDate AND :endDate
    LIMIT :selectedTopProduct
) AS TopProducts ON p.ProductName = TopProducts.ProductName
LEFT JOIN CustomerFeedbackProduct AS cfp ON p.ProductName = cfp.ProductName
WHERE cfp.Comment IS NOT NULL;
";

$stmt_comment_product = $conn->prepare($sql_comment_product);
$stmt_comment_product->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$stmt_comment_product->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$stmt_comment_product->bindParam(':selectedTopProduct', $selectedTopProduct, PDO::PARAM_INT);
$stmt_comment_product->execute();

// สร้างอาเรย์เพื่อเก็บข้อมูลความคิดเห็นสินค้า
$commentProductData = [];
while ($row_comment_product = $stmt_comment_product->fetch()) {
    $commentProductData[] = [
        'Product Name' => htmlspecialchars($row_comment_product['ProductName']),
        'Comment' => htmlspecialchars($row_comment_product['Comment'])
    ];
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Product Analysis Dashboard</title>
    <!-- เรียกใช้งาน Chart.js จาก CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Custom styles for this page -->
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.5.0-beta4/html2canvas.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.3.4/jspdf.debug.js"></script>
    <style>
        #barChartProduct {
            height: 400px !important;
            /* หรือค่าความสูงที่คุณต้องการ */
            display: block;
        }
    </style>


</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="ProductAnalysis.php">
                <div class="sidebar-brand-text mx-3">MahasawatPQR</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">
            <!-- Heading -->
            <div class="sidebar-heading">
                Dashboard
            </div>
            <!-- Nav Item - Tourism -->
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Tourism Analysis</span></a>
            </li>

            <!-- Nav Item - Product -->
            <li class="nav-item">
                <a class="nav-link" href="productAnalysis.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Product Analysis</span></a>
            </li>

            <!-- Nav Item - Attention -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages" aria-expanded="true" aria-controls="collapsePages">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Attention</span>
                </a>
                <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Attention:</h6>
                        <a class="collapse-item" href="product.php">Product</a>
                        <a class="collapse-item" href="event.php">Event</a>
                    </div>
                </div>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>

        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <div class="container-fluid">

                        <!-- Page Heading -->
                        <h1 class="h3 mb-4 text-gray-800">Product Analysis Dashboard</h1>
                        <div class="text-right">
                            <button id="generateReport">Generate Report</button>
                            <button onclick="captureAndExportToPDF()">Export to PDF</button>
                        </div>
                    </div>

                </nav>
                <!-- End of Topbar -->

                <!-- ตัวเลือก -->
                <form id="myForm" method="POST" action="">
                    <label for="startDate">Start Date:</label>
                    <input type="date" id="startDate" name="startDate" value="<?php echo $startDate; ?>">
                    <label for="endDate">End Date:</label>
                    <input type="date" id="endDate" name="endDate" value="<?php echo $endDate; ?>">
                    <label for="selectedTopProduct">Select Top Products:</label>
                    <select id="selectedTopProduct" name="selectedTopProduct">
                        <option value="3" <?php if ($selectedTopProduct === '3') echo 'selected'; ?>>Top 3</option>
                        <option value="5" <?php if ($selectedTopProduct === '5') echo 'selected'; ?>>Top 5</option>
                        <!-- เพิ่มตัวเลือกอื่น ๆ ตามต้องการ -->
                    </select>
                </form>

                <script>
                    // เพิ่มการตรวจสอบเมื่อมีการเปลี่ยนแปลงค่าในฟิลด์
                    document.getElementById('startDate').addEventListener('change', function() {
                        document.getElementById('myForm').submit();
                    });

                    document.getElementById('endDate').addEventListener('change', function() {
                        document.getElementById('myForm').submit();
                    });

                    document.getElementById('selectedTopProduct').addEventListener('change', function() {
                        document.getElementById('myForm').submit();
                    });
                </script>

                <!-- แสดงกราฟ -->
                <div class="row justify-content-center mt-6">
                    <div class="col-md-12"> <!-- เปลี่ยนขนาดของคอลัมน์เป็น 12 (เต็มจอ) -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Top Product</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                        <div class="dropdown-header">Dropdown Product:</div>
                                        <a class="dropdown-item" href="#">Export CSV File</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="barChartProduct"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center mt-5 my-2">
                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Top Product Review</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                        <div class="dropdown-header">Dropdown Product:</div>
                                        <a class="dropdown-item" href="#">Export CSV File</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="chart-container" width="100%" cellspacing="0">
                                    <canvas id="barChartReview"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Event</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                        <div class="dropdown-header">Dropdown Event:</div>
                                        <a class="dropdown-item" href="#">Export CSV File</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="barChartEvent"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Channel</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                        <div class="dropdown-header">Dropdown Channel:</div>
                                        <a class="dropdown-item" href="#">Export CSV File</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="barChartChannel"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center mt-5 my-2">

                    <!-- Gender Card -->
                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Gender</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                        <div class="dropdown-header">Dropdown Gender:</div>
                                        <a class="dropdown-item" href="#">Export CSV File</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="pieChartGender"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Age -->
                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Age</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                        <div class="dropdown-header">Dropdown Age:</div>
                                        <a class="dropdown-item" href="#">Export CSV File</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="pieChartAge"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Nationality -->
                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Nationality</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                        <div class="dropdown-header">Dropdown Nationality:</div>
                                        <a class="dropdown-item" href="#">Export CSV File</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="barChartNationality"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Comment Product</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTableProduct" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Comment</th>
                                        </tr>
                                    </thead>
                                    <tfoot>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Comment</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php
                                        foreach ($commentProductData as $comment) {
                                            echo "<tr>";
                                            echo "<td>" . $comment['Product Name'] . "</td>";
                                            echo "<td>" . $comment['Comment'] . "</td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>

                                </table>
                            </div>
                        </div>
                    </div>
                </div>


                <script>
                    // สร้างแผนภูมิสำหรับสินค้า
                    var ctxProduct = document.getElementById('barChartProduct').getContext('2d');
                    var productDataCounts = <?php echo json_encode(array_column($productData, 'Count')); ?>;
                    var productLabels = <?php echo json_encode(array_column($productData, 'Product Name')); ?>;
                    var barChartProduct = new Chart(ctxProduct, {
                        type: 'bar',
                        data: {
                            labels: productLabels,
                            datasets: [{
                                label: 'Product Distribution',
                                data: productDataCounts,
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.6)',
                                    'rgba(54, 162, 235, 0.6)',
                                    'rgba(255, 206, 86, 0.6)',
                                    'rgba(75, 192, 192, 0.6)',
                                    'rgba(153, 102, 255, 0.6)',
                                    'rgba(255, 159, 64, 0.6)',
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false, // ใส่ค่านี้เพื่อให้กราฟไม่รักษาอัตราส่วนแต่ปรับตามความสูงที่เราตั้ง
                            scales: {
                                x: {
                                    beginAtZero: true
                                },
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });

                    // สร้างแผนภูมิสำหรับรีวิวสินค้า
                    var ctxReview = document.getElementById('barChartReview').getContext('2d');
                    var reviewDataCounts = <?php echo json_encode(array_column($reviewData, 'CountProduct')); ?>;
                    var reviewLabels = <?php echo json_encode(array_column($reviewData, 'ProductName')); ?>;
                    var barChartReview = new Chart(ctxReview, {
                        type: 'bar',
                        data: {
                            labels: reviewLabels,
                            datasets: [{
                                label: 'Review Distribution',
                                data: reviewDataCounts,
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.6)',
                                    'rgba(54, 162, 235, 0.6)',
                                    'rgba(255, 206, 86, 0.6)',
                                    'rgba(75, 192, 192, 0.6)',
                                    'rgba(153, 102, 255, 0.6)',
                                    'rgba(255, 159, 64, 0.6)',
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                x: {
                                    beginAtZero: true
                                },
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });

                    // สร้างแผนภูมิสำหรับอีเวนต์
                    var ctxEvent = document.getElementById('barChartEvent').getContext('2d');
                    var eventDataCounts = <?php echo json_encode(array_column($eventData, 'Event Count')); ?>;
                    var eventLabels = <?php echo json_encode(array_column($eventData, 'Event Name')); ?>;
                    var barChartEvent = new Chart(ctxEvent, {
                        type: 'bar',
                        data: {
                            labels: eventLabels,
                            datasets: [{
                                label: 'Event Distribution',
                                data: eventDataCounts,
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.6)',
                                    'rgba(54, 162, 235, 0.6)',
                                    'rgba(255, 206, 86, 0.6)',
                                    'rgba(75, 192, 192, 0.6)',
                                    'rgba(153, 102, 255, 0.6)',
                                    'rgba(255, 159, 64, 0.6)',
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                x: {
                                    beginAtZero: true
                                },
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });

                    // สร้างแผนภูมิสำหรับช่องสื่อ
                    var ctxChannel = document.getElementById('barChartChannel').getContext('2d');
                    var channelDataCounts = <?php echo json_encode(array_column($channelData, 'Channel Count')); ?>;
                    var channelLabels = <?php echo json_encode(array_column($channelData, 'Channel Name')); ?>;
                    var barChartChannel = new Chart(ctxChannel, {
                        type: 'bar',
                        data: {
                            labels: channelLabels,
                            datasets: [{
                                label: 'Channel Distribution',
                                data: channelDataCounts,
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.6)',
                                    'rgba(54, 162, 235, 0.6)',
                                    'rgba(255, 206, 86, 0.6)',
                                    'rgba(75, 192, 192, 0.6)',
                                    'rgba(153, 102, 255, 0.6)',
                                    'rgba(255, 159, 64, 0.6)',
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                x: {
                                    beginAtZero: true
                                },
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });

                    // สร้างแผนภูมิ Pie Chart สำหรับเพศ
                    var ctxGender = document.getElementById('pieChartGender').getContext('2d');
                    var genderDataCounts = <?php echo json_encode(array_column($genderData, 'Gender Count')); ?>;
                    var genderLabels = <?php echo json_encode(array_column($genderData, 'Gender')); ?>;
                    var pieChartGender = new Chart(ctxGender, {
                        type: 'pie',
                        data: {
                            labels: genderLabels,
                            datasets: [{
                                data: genderDataCounts,
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.6)',
                                    'rgba(54, 162, 235, 0.6)',
                                    'rgba(255, 206, 86, 0.6)',
                                    'rgba(75, 192, 192, 0.6)',
                                    'rgba(153, 102, 255, 0.6)',
                                    'rgba(255, 159, 64, 0.6)',
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                        }
                    });

                    // สร้างแผนภูมิ Pie Chart สำหรับกลุ่มอายุ
                    var ctxAge = document.getElementById('pieChartAge').getContext('2d');
                    var ageDataCounts = <?php echo json_encode(array_column($ageData, 'Age Count')); ?>;
                    var ageLabels = <?php echo json_encode(array_column($ageData, 'Age')); ?>;
                    var pieChartAge = new Chart(ctxAge, {
                        type: 'pie',
                        data: {
                            labels: ageLabels,
                            datasets: [{
                                data: ageDataCounts,
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.6)',
                                    'rgba(54, 162, 235, 0.6)',
                                    'rgba(255, 206, 86, 0.6)',
                                    'rgba(75, 192, 192, 0.6)',
                                    'rgba(153, 102, 255, 0.6)',
                                    'rgba(255, 159, 64, 0.6)',
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                        }
                    });

                    // สร้างแผนภูมิ Bar Chart สำหรับสัญชาติ
                    var ctxNationality = document.getElementById('barChartNationality').getContext('2d');
                    var nationalityDataCounts = <?php echo json_encode(array_column($nationalityData, 'Nationality Count')); ?>;
                    var nationalityLabels = <?php echo json_encode(array_column($nationalityData, 'Nationality')); ?>;
                    var barChartNationality = new Chart(ctxNationality, {
                        type: 'bar',
                        data: {
                            labels: nationalityLabels,
                            datasets: [{
                                label: 'Nationality Count',
                                data: nationalityDataCounts,
                                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                x: {
                                    beginAtZero: true
                                },
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                </script>
            </div>
            <!-- End of Main Content -->


            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>MahasawatPQR &copy; Website 2023</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="login.html">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>
    <!-- Page level plugins -->
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <!-- Initialize DataTables for specific tables -->
    <script>
        $(document).ready(function() {
            $('#dataTableProduct').DataTable();
            $('#dataTableEvent').DataTable();
        });
    </script>
    <!-- Page level custom scripts -->
    <script src="js/demo/datatables-demo.js"></script>
    <script>
        function captureAndExportToPDF() {
            html2canvas(document.body, {
                onrendered: function(canvas) {
                    var imgData = canvas.toDataURL('image/png');

                    var pdf = new jsPDF({
                        orientation: 'portrait',
                    });

                    var imgWidth = 210;
                    var pageHeight = 295;
                    var imgHeight = canvas.height * imgWidth / canvas.width;
                    var heightLeft = imgHeight;
                    var position = 0;

                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;

                    while (heightLeft >= 0) {
                        position = heightLeft - imgHeight;
                        pdf.addPage();
                        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;
                    }

                    pdf.save('Product_Dashboard.pdf');
                }
            });
        }

        $(document).ready(function() {
            $("#myForm").submit(function(e) {
                e.preventDefault(); // ป้องกันการ submit แบบปกติของฟอร์ม

                // บันทึกค่าจากฟอร์ม
                let formData = $(this).serialize();

                // AJAX request กลับไปที่ ProductAnalysis.php หรือ endpoint ที่สามารถส่งผลลัพธ์ dashboard กลับมา
                $.post("ProductAnalysis.php", formData, function(data) {
                    // ตรงนี้คือส่วนที่คุณควรแสดงผล dashboard ของคุณ, ตัวอย่างเช่น:
                    $("#dashboard").html(data); // ปAssuming `data` is the updated dashboard content
                });
            });

            // สำหรับปุ่ม "Report"
            $("#generateReport").click(function(e) {
                e.preventDefault();

                // บันทึกค่าจากฟอร์ม
                let formData = $("#myForm").serialize();

                // ส่งข้อมูลไปยัง reportCustomer.php ในรูปแบบ POST
                $.post("reportProduct.php", formData, function(data) {
                    // แสดงหน้า reportCustomer.php ในหน้าต่างใหม่
                    let reportWindow = window.open("", "_blank");
                    reportWindow.document.write(data);
                });
            });
        });
    </script>
</body>

</body>

</html>