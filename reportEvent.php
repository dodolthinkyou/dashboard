<?php
ob_start();  // เริ่มต้น output buffering
include 'event.php';
ob_end_clean();  // ล้าง output และหยุด output buffering

$startDate = $_POST['startDate'] ?? '';
$endDate = $_POST['endDate'] ?? '';
$selectedEventName = $_POST['selectedEventName'] ?? '';

$selectedEventName = isset($_POST['selectedEventName']) ? $_POST['selectedEventName'] : null;

//$reportText = "ข้อมูลจากวันที่ $startDate ถึง $endDate จากที่คุณเลือกข้อมูลของเพศ $selectedEventName ";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Report</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.5.0-beta4/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>

    <style>
        .custom-table th,
        .custom-table td {
            width: 50%;
        }
    </style>

</head>

<body>
    <div class="text-right">
        <button id="download">Export to PDF</button>
    </div>


    <!-- <p><?php echo $reportText; ?></p> -->
    <div class="container mt-5">
        <h2>Event Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Event Name</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventData as $data) : ?>
                    <tr>
                        <td><?= $data['Event Name'] ?></td>
                        <td><?= $data['Count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Rating Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Rating</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ratingsData as $data) : ?>
                    <tr>
                        <td><?= $data['Rating'] ?></td>
                        <td><?= $data['RatingCount'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Product Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productData as $data) : ?>
                    <tr>
                        <td><?= $data['ProductName'] ?></td>
                        <td><?= $data['Count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Product Comment Data</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Event Name</th>
                    <th>Email</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commentData as $data) : ?>
                    <tr>
                        <td><?= $data['EventName'] ?></td>
                        <td><?= $data['Email'] ?></td>
                        <td><?= $data['Comment'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
        document.getElementById('download').addEventListener('click', function() {
            var element = document.querySelector('.container');  // หรือ document.body หากต้องการทั้งหน้า
            var opt = {
                margin: 10,
                filename: 'report_event.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            html2pdf().from(element).set(opt).save();
        });

        $(document).ready(function() {
            $("#generateReport").click(function(e) {
                e.preventDefault(); // ป้องกันการ submit แบบปกติของปุ่ม

                let formData = $("#myForm").serialize();

                $.post("reportEvent.php", formData, function(data) {
                    let reportWindow = window.open("", "_blank");
                    reportWindow.document.write(data);
                });
            });
        });
    </script>
</body>

</html>