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

// 3. คำสั่ง SQL เพื่อดึงข้อมูล
function fetchData($conn, $startDate, $endDate, $selectedEventName, $chartType)
{
    $sql = "";
    switch ($chartType) {
        case 'event':
            $sql = "SELECT e.EventName AS 'Event Name', COUNT(*) AS 'Count'
            FROM CustomerPreferencesEvent AS ce
            JOIN `Events` as e ON ce.EventName = e.EventName
            JOIN Customer as c ON ce.CustomerID = c.CustomerID
            JOIN FormSurvey as fs ON ce.CustomerID = fs.CustomerID
            WHERE fs.`Create_at` BETWEEN :startDate AND :endDate";
            if ($selectedEventName !== 'All') {
                $sql .= " AND e.EventName = :selectedEventName"; // แก้ไขเป็น e.EventName แทน p.EventName
            }
            $sql .= " GROUP BY e.EventName ORDER BY COUNT(*) DESC";
            break;
        case 'rating':
            $sql = "SELECT `Rating`, COUNT(*) as 'RatingCount' FROM `CustomerFeedbackEvent` as cfe
            JOIN `Events` as e ON cfe.EventName = e.EventName
            JOIN Customer as c ON cfe.CustomerID = c.CustomerID
            JOIN FormSurvey as fs ON cfe.CustomerID = fs.CustomerID
            WHERE fs.`Create_at` BETWEEN :startDate AND :endDate";
            if ($selectedEventName !== 'All') {
                $sql .= " AND e.EventName = :selectedEventName"; // แก้ไขเป็น e.EventName แทน p.EventName
            }
            $sql .= " GROUP BY Rating";
            break;
        case 'product':
            $sql = "SELECT p.ProductName, COUNT(p.ProductName) as 'Count'
            FROM CustomerPreferencesEvent as cpf
            JOIN Events as e ON cpf.EventName = e.EventName
            JOIN Customer as c ON cpf.CustomerID = c.CustomerID
            JOIN CustomerPreferencesProduct as cp ON c.CustomerID = cp.CustomerID
            JOIN Products as p ON cp.ProductName = p.ProductName
            JOIN FormSurvey as fs ON cpf.CustomerID = fs.CustomerID
            WHERE fs.`Create_at` BETWEEN :startDate AND :endDate";
            if ($selectedEventName !== 'All') {
                $sql .= " AND e.EventName = :selectedEventName"; // Update to the correct column name for event name
            }
            $sql .= " GROUP BY p.ProductName ORDER BY COUNT(*) DESC;"; // Update to the correct alias or column name for event name
            break;
        case 'comment':
            $sql = "SELECT e.EventName, Email, Comment FROM `CustomerFeedbackEvent` as cfe
            JOIN FormSurvey as fs ON cfe.CustomerID = fs.CustomerID
            JOIN Events as e ON cfe.EventName = e.EventName
            JOIN Customer as cus ON cfe.CustomerID = cus.CustomerID
            WHERE fs.`Create_at` BETWEEN :startDate AND :endDate";
            if ($selectedEventName !== 'All') {
                $sql .= " AND e.EventName = :selectedEventName"; // Update to the correct column name for event name
            }
            break;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
    if ($selectedEventName !== 'All') {
        $stmt->bindParam(':selectedEventName', $selectedEventName, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$selectedEventName = isset($_POST['selectedEventName']) ? $_POST['selectedEventName'] : 'All'; // ตรวจสอบค่า selectedEventName และกำหนดให้เป็น 'All' ถ้าไม่มีการเลือก

$eventData = fetchData($conn, $startDate, $endDate, $selectedEventName, 'event');
$ratingsData = fetchData($conn, $startDate, $endDate, $selectedEventName, 'rating');
$productData = fetchData($conn, $startDate, $endDate, $selectedEventName, 'product');
$commentData = fetchData($conn, $startDate, $endDate, $selectedEventName, 'comment');

// คำสั่ง SQL เพื่อดึงข้อมูลเพื่อสร้าง Dropdown Event
$sql_Event_dropdown = "SELECT DISTINCT e.EventName FROM Events AS e
JOIN CustomerFeedbackEvent as cfp ON e.EventName = cfp.EventName
JOIN FormSurvey as fs ON cfp.CustomerID = fs.CustomerID
WHERE fs.Create_at BETWEEN :startDate AND :endDate
ORDER BY e.EventName ASC";

$result_dropdown = $conn->prepare($sql_Event_dropdown);
$result_dropdown->bindParam(':startDate', $startDate, PDO::PARAM_STR);
$result_dropdown->bindParam(':endDate', $endDate, PDO::PARAM_STR);
$result_dropdown->execute();

// สร้างอาร์เรย์เพื่อเก็บชื่ออายุ
$EventTypesDropdown = array();
while ($row = $result_dropdown->fetch()) {
    $EventTypesDropdown[] = $row["EventName"];
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

    <title>Event Analysis Dashboard</title>
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
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
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
                        <h1 class="h3 mb-4 text-gray-800">Event Analysis Dashboard</h1>
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
                    <label for="selectedEventName">Select Event:</label>
                    <select id="selectedEventName" name="selectedEventName">
                        <option value="All" <?php if ($selectedEventName === 'All') echo 'selected'; ?>>All Event</option>
                        <?php
                        foreach ($EventTypesDropdown as $EventName) {
                            $selected = ($EventName === $selectedEventName) ? 'selected' : '';
                            echo "<option value='$EventName' $selected>$EventName</option>";
                        }
                        ?>
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

                    document.getElementById('selectedEventName').addEventListener('change', function() {
                        document.getElementById('myForm').submit();
                    });
                </script>

                <!-- แสดงกราฟ -->
                <div class="row justify-content-center mt-6">
                    <div class="col-md-12"> <!-- เปลี่ยนขนาดของคอลัมน์เป็น 12 (เต็มจอ) -->
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
                </div>

                <div class="row justify-content-center mt-5 my-2">
                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Event Rating</h6>
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
                                <div class="chart-container" width="100%" cellspacing="0">
                                    <canvas id="barChartRating"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Product</h6>
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
                <div class="col-lg-12"> <!-- ครึ่งหนึ่งของความกว้างของหน้าจอ -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Comment Event</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTableEvent" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Event Name</th>
                                            <th>Email</th>
                                            <th>Comment</th>
                                        </tr>
                                    </thead>
                                    <tfoot>
                                        <tr>
                                            <th>Event Name</th>
                                            <th>Email</th>
                                            <th>Comment</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php
                                        foreach ($commentData as $comment) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($comment['EventName']) . "</td>";
                                            echo "<td>" . htmlspecialchars($comment['Email']) . "</td>";
                                            echo "<td>" . htmlspecialchars($comment['Comment']) . "</td>";
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
                    var ctxEvent = document.getElementById('barChartEvent').getContext('2d');
                    var eventDataCounts = <?php echo json_encode(array_column($eventData, 'Count')); ?>;
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

                    // สร้างแผนภูมิสำหรับ Rating
                    var ctxRating = document.getElementById('barChartRating').getContext('2d');
                    var ratings = <?php echo json_encode(array_column($ratingsData, 'Rating')); ?>;
                    var ratingCounts = <?php echo json_encode(array_column($ratingsData, 'RatingCount')); ?>;
                    var barchartRating = new Chart(ctxRating, {
                        type: 'bar', // แก้ให้เป็น horizontalBar
                        data: {
                            labels: ratings,
                            datasets: [{
                                label: 'RatingCount',
                                data: ratingCounts,
                                backgroundColor: [
                                    'rgba(75, 192, 192, 0.6)',
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

                    var ctxProduct = document.getElementById('barChartProduct').getContext('2d');
                    var productDataCounts = <?php echo json_encode(array_column($productData, 'Count')); ?>;
                    var productLabels = <?php echo json_encode(array_column($productData, 'ProductName')); ?>;
                    var barChartProduct = new Chart(ctxProduct, {
                        type: 'bar',
                        data: {
                            labels: productLabels,
                            datasets: [{
                                label: 'Product Count', // Update to the desired label
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

                    pdf.save('event_Dashboard.pdf');
                }
            });
        }

        $(document).ready(function() {
            $("#myForm").submit(function(e) {
                e.preventDefault(); // ป้องกันการ submit แบบปกติของฟอร์ม

                // บันทึกค่าจากฟอร์ม
                let formData = $(this).serialize();

                // AJAX request กลับไปที่ product.php หรือ endpoint ที่สามารถส่งผลลัพธ์ dashboard กลับมา
                $.post("event.php", formData, function(data) {
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
                $.post("reportEvent.php", formData, function(data) {
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