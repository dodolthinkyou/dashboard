<?php
ob_start();  // เริ่มต้น output buffering
include 'ProductAnalysis.php';
ob_end_clean();  // ล้าง output และหยุด output buffering

$startDate = $_POST['startDate'] ?? '';
$endDate = $_POST['endDate'] ?? '';
$selectedTopProduct = $_POST['selectedTopProduct'] ?? '';

$selectedTopProduct = isset($_POST['selectedTopProduct']) ? $_POST['selectedTopProduct'] : null;

//$reportText = "ข้อมูลจากวันที่ $startDate ถึง $endDate จากที่คุณเลือกข้อมูล $selectedTopProduct ";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Report</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        <h2>Product</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Product Namer</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productData as $data) : ?>
                    <tr>
                        <td><?= $data['Product Name'] ?></td>
                        <td><?= $data['Count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Product Review Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>ProducName</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviewData as $data) : ?>
                    <tr>
                        <td><?= $data['ProductName'] ?></td>
                        <td><?= $data['CountProduct'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

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
                        <td><?= $data['Event Count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Channel Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Channel Name</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($channelData as $data) : ?>
                    <tr>
                        <td><?= $data['Channel Name'] ?></td>
                        <td><?= $data['Channel Count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Gender Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Gender</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($genderData as $data) : ?>
                    <tr>
                        <td><?= $data['Gender'] ?></td>
                        <td><?= $data['Gender Count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Age Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Age</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ageData as $data) : ?>
                    <tr>
                        <td><?= $data['Age'] ?></td>
                        <td><?= $data['Age Count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Nationality Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Nationality</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nationalityData as $data) : ?>
                    <tr>
                        <td><?= $data['Nationality'] ?></td>
                        <td><?= $data['Nationality Count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Comment Data</h2>
        <table class="table table-bordered custom-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commentProductData as $data) : ?>
                    <tr>
                        <td><?= $data['Product Name'] ?></td>
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
                filename: 'report_top_product.pdf',
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

                $.post("reportProduct.php", formData, function(data) {
                    let reportWindow = window.open("", "_blank");
                    reportWindow.document.write(data);
                });
            });
        });
    </script>
</body>

</html>