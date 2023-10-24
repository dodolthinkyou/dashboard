<?php
// 1. เชื่อมต่อกับฐานข้อมูล MySQL
$db_host = 'localhost';
$db_user = 'root';
$db_password = 'root';
$db_db = 'adminDashboardData';

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

$selectedTopProduct = isset($_POST['selectedTopProduct']) ? $_POST['selectedTopProduct'] : 'All';

$sql_product = "SELECT p.ProductName AS 'Product Name', COUNT(cp.ProductID) AS 'Count'
        FROM CustomerPreferencesProduct AS cp
        JOIN Products AS p ON cp.ProductID = p.ProductID
        JOIN Customer as c ON cp.CustomerID = c.CustomerID
        JOIN FormSurvey as fs ON cp.SurveyID = fs.SurveyID
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
JOIN Products as p ON cfp.ProductID = p.ProductID
JOIN Customer as c ON cfp.CustomerID = c.CustomerID
JOIN FormSurvey as fs ON cfp.SurveyID = fs.SurveyID
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
$sql_event = "SELECT e.EventName AS 'Event Name', COUNT(cpe.EventID) AS 'EventCount'
    FROM Products AS p
    INNER JOIN CustomerPreferencesProduct AS cpp ON p.ProductID = cpp.ProductID
    INNER JOIN CustomerPreferencesEvent AS cpe ON cpp.CustomerID = cpe.CustomerID
    INNER JOIN Events AS e ON cpe.EventID = e.EventID
    WHERE p.ProductName IN (
        SELECT ProductName
        FROM (
            SELECT ProductName, COUNT(*) AS Count
            FROM CustomerPreferencesProduct AS cp
            JOIN Products AS pr ON cp.ProductID = pr.ProductID
            JOIN Customer AS c ON cp.CustomerID = c.CustomerID
            JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
            WHERE fs.Create_at BETWEEN :startDate AND :endDate
            GROUP BY ProductName
            ORDER BY Count DESC
            LIMIT :selectedTopProduct
        ) AS TopProducts
    )
    GROUP BY e.EventName
    ORDER BY EventName, EventCount DESC";

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
$sql_channel = "SELECT c.ChannelName AS 'Channel Name', COUNT(cfp.ChannelID) AS 'ChannelCount'
    FROM CustomerFeedbackProduct AS cfp
    INNER JOIN Channel AS c ON cfp.ChannelID = c.ChannelID
    WHERE cfp.ProductID IN (
        SELECT ProductID
        FROM (
            SELECT cp.ProductID, COUNT(*) AS Count
            FROM CustomerPreferencesProduct AS cp
            JOIN Products AS pr ON cp.ProductID = pr.ProductID
            JOIN Customer AS c ON cp.CustomerID = c.CustomerID
            JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
            WHERE fs.Create_at BETWEEN :startDate AND :endDate
            GROUP BY cp.ProductID
            ORDER BY Count DESC
            LIMIT :selectedTopProduct
        ) AS TopProducts
    )
    GROUP BY c.ChannelName
    ORDER BY ChannelName, ChannelCount DESC";

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
$sql_gender = "SELECT Gender, COUNT(*) AS GenderCount
    FROM CustomerPreferencesProduct AS cp
    JOIN Products AS p ON cp.ProductID = p.ProductID
    JOIN Customer AS c ON cp.CustomerID = c.CustomerID
    JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
    WHERE p.ProductName IN (
        SELECT ProductName
        FROM (
            SELECT ProductName, COUNT(*) AS Count
            FROM CustomerPreferencesProduct AS cp
            JOIN Products AS p ON cp.ProductID = p.ProductID
            JOIN Customer AS c ON cp.CustomerID = c.CustomerID
            JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
            WHERE fs.Create_at BETWEEN :startDate AND :endDate
            GROUP BY ProductName
            ORDER BY Count DESC
            LIMIT :selectedTopProduct
        ) AS TopProducts
    )
    GROUP BY Gender";

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
$sql_age = "SELECT Age, COUNT(*) AS AgeCount
    FROM CustomerPreferencesProduct AS cp
    JOIN Products AS p ON cp.ProductID = p.ProductID
    JOIN Customer AS c ON cp.CustomerID = c.CustomerID
    JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
    WHERE p.ProductName IN (
        SELECT ProductName
        FROM (
            SELECT ProductName, COUNT(*) AS Count
            FROM CustomerPreferencesProduct AS cp
            JOIN Products AS p ON cp.ProductID = p.ProductID
            JOIN Customer AS c ON cp.CustomerID = c.CustomerID
            JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
            WHERE fs.Create_at BETWEEN :startDate AND :endDate
            GROUP BY ProductName
            ORDER BY Count DESC
            LIMIT :selectedTopProduct
        ) AS TopProducts
    )
    GROUP BY Age";

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
$sql_nationality = "SELECT Nationality, COUNT(*) AS NationalityCount
    FROM CustomerPreferencesProduct AS cp
    JOIN Products AS p ON cp.ProductID = p.ProductID
    JOIN Customer AS c ON cp.CustomerID = c.CustomerID
    JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
    WHERE p.ProductName IN (
        SELECT ProductName
        FROM (
            SELECT ProductName, COUNT(*) AS Count
            FROM CustomerPreferencesProduct AS cp
            JOIN Products AS p ON cp.ProductID = p.ProductID
            JOIN Customer AS c ON cp.CustomerID = c.CustomerID
            JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
            WHERE fs.Create_at BETWEEN :startDate AND :endDate
            GROUP BY ProductName
            ORDER BY Count DESC
            LIMIT :selectedTopProduct
        ) AS TopProducts
    )
    GROUP BY Nationality";

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
$sql_comment_product = "SELECT p.ProductName, cfp.`Comment`
    FROM Products AS p
    INNER JOIN (
        SELECT cp.ProductID
        FROM CustomerPreferencesProduct AS cp
        JOIN Products AS pr ON cp.ProductID = pr.ProductID
        JOIN FormSurvey AS fs ON cp.SurveyID = fs.SurveyID
        WHERE fs.Create_at BETWEEN :startDate AND :endDate
        GROUP BY cp.ProductID
        ORDER BY COUNT(*) DESC
        LIMIT :selectedTopProduct
    ) AS TopProducts ON p.ProductID = TopProducts.ProductID
    LEFT JOIN CustomerFeedbackProduct AS cfp ON p.ProductID = cfp.ProductID";

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
</head>

<body id="page-top">
    <!-- เพิ่มแบบฟอร์มสำหรับเลือกวันที่ -->
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
</body>

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

</html>